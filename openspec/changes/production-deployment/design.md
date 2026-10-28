# Design

## Context

- Aucun artefact de déploiement dans le dépôt : ni `Dockerfile`, ni `.dockerignore`, ni stack de production. `compose.yaml` est la stack Sail de développement (`laravel.test` + `pgsql`) et le reste. Dès qu'un `docker-compose.yml` est posé à côté, Docker Compose trouve deux fichiers à la racine et Sail affiche un avertissement à chaque commande (D10).
- `config/app.php` lit la version dans `version.txt` et le commit dans `APP_COMMIT` ; `App\Support\AppBuild` valide le sha. L'image doit donc contenir `version.txt` et recevoir `APP_COMMIT`.
- `/up` est déclaré par `withRouting(health: '/up')`. Sans `APP_PASSCODE`, `EnsurePasscodeConfigured` le fait répondre 500, ce qui fait tomber un healthcheck (ADR 0007). Les commandes artisan, `config:cache` compris, tournent sans le code (`MissingPasscodeTest`).
- `AddSecurityHeaders` est en tête de la pile globale. `TrustProxies` lit `config('trustedproxy.proxies')`. `config/session.php` a `'secure' => env('SESSION_SECURE_COOKIE')`, donc `null` par défaut.
- Le plugin Vite `@laravel/vite-plugin-wayfinder` lance `php artisan wayfinder:generate` pendant `vp build`. Le build des assets a donc besoin de PHP et de `vendor`. `laravel/wayfinder` est dans `require`, pas dans `require-dev`.
- `AppServiceProvider` pose déjà `DB::prohibitDestructiveCommands(app()->isProduction())` (couche 2.6) : `migrate --force` passe, `migrate:fresh` est refusé en production.
- CI : `.github/workflows/ci.yml` a les jobs `changes`, `static`, `test-frontend`, `test-backend` et `report`, et se déclenche sur les PR et les pushs sur `main`. `make changed-code` classe `Dockerfile`, `docker-compose.yml` et `.dockerignore` comme du code (ils ne correspondent pas à `DOCS_PATHS`).
- Référence Next (`/Users/epaul/www/z_perso/golf-handicaps`) : `Dockerfile`, `docker-compose.yml` (anchor `x-app`, healthchecks), `docs/deployment.md` (installation Dokploy, santé du worker, sauvegarde, retour arrière), `src/jobs/heartbeat.ts`.

## Goals / Non-Goals

**Goals**

- Une image, une stack, une doc : le staging se monte depuis `docs/deployment.md` sans rien deviner.
- L'image publiée est celle que la CI a lancée et vue saine, pas une reconstruction.
- Aucun paquet Composer ni PNPM ajouté.

**Non-Goals**

- Automatiser Dokploy (API, webhook) ou décrire sa configuration en code : Dokploy reste piloté à la main.
- Tester la stack depuis Pest : le test de fumée de la CI s'en charge. Pest couvre le code PHP ajouté (battement, HSTS, témoin, tags d'image).

## Decisions

### D1. FrankenPHP en mode classique

Le web tourne sous `frankenphp run` avec un `Caddyfile` versionné (`docker/Caddyfile`) :

- `auto_https off` et `admin off`, écoute `:8080` en HTTP. Dokploy (Traefik) termine le TLS.
- `root /app/public`, `encode zstd gzip`, `php_server`. Caddy sert les fichiers de `public/` (assets compilés, manifeste, icônes) sans passer par PHP. Toute autre adresse va à `index.php`.
- `Cache-Control: public, max-age=31536000, immutable` sur `/build/*`, dont les noms portent une empreinte.

**Alternatives écartées** :

- Octane et le mode worker de FrankenPHP : un paquet de plus, et un état partagé entre requêtes à surveiller (singletons, configuration), pour un trafic de quelques personnes.
- nginx + php-fpm : deux processus à superviser dans un conteneur, ou deux conteneurs.

### D2. Dockerfile en quatre étapes

L'image de base est `dunglas/frankenphp:<version>-php8.5-trixie`, épinglée par tag exact et digest. Le tag exact se choisit à l'apply, parmi les images publiées depuis au moins deux semaines.

1. `base` : extensions par `install-php-extensions` (`pdo_pgsql`, `pcntl` pour les signaux de `queue:work`, `intl`, `opcache`), `php.ini-production`, OPcache sans `validate_timestamps`, utilisateur `app` (uid 1000).
2. `vendor` : `composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --classmap-authoritative`, depuis `composer.json` et `composer.lock` seuls, pour garder la couche en cache.
3. `assets` : part de `base` et ajoute Node 22 et PNPM (Corepack, version de `packageManager`). Elle reçoit `vendor` puis le code, lance `pnpm install --frozen-lockfile` et `pnpm run build`. Wayfinder y trouve `php artisan`. Le build tourne avec `VITE_APP_NAME="Handicaps golf"`, la valeur d'`APP_NAME` dans `.env.example` : `.dockerignore` écarte `.env`, et Vite inscrit la variable dans le JavaScript compilé. Sans elle, `resources/js/app.tsx` retombe sur `Laravel` et l'onglet de `/login` affiche « Connexion - Laravel ». Rien de cette étape ne passe dans l'image finale, sauf `public/build`.
4. `runtime` : part de `base`. Elle reçoit le code, `vendor` et `public/build`. `storage/` et `bootstrap/cache/` appartiennent à `app`. `ARG GIT_COMMIT` devient `ENV APP_COMMIT`. `APP_ENV=production`, `APP_DEBUG=false` et `LOG_CHANNEL=stderr` sont posés par défaut. La commande par défaut est le web, et `USER app`.

`.dockerignore` écarte `.env*`, `node_modules`, `vendor`, `public/build`, `public/hot`, `storage/logs/*`, `reports`, `tests`, `.git`, `openspec`, `docs` et les dossiers d'agents. Il garde `version.txt`.

`composer install` tourne avec `--no-scripts`, puisque l'étape `vendor` n'a pas encore le code. La découverte des paquets se fait une fois dans `runtime` (`php artisan package:discover`), sans secret.

### D3. Caches Laravel construits au démarrage

`docker/entrypoint.sh` lance `php artisan optimize` (config, événements, routes, vues), puis `exec "$@"`. Ce script est l'`ENTRYPOINT` de l'image, et chaque service (web, worker, migrate) y passe.

**Pourquoi au démarrage** : `config:cache` fige les variables d'environnement. Lancé au build, il figerait des valeurs vides. Au démarrage, l'environnement de Dokploy est connu, et changer une variable demande seulement un redéploiement (requirement « Configuration par l'environnement »).

**Alternative écartée** : pas de cache du tout. Chaque requête relirait tous les fichiers de `config/` et de routes. Le gain est faible pour ce trafic, mais `optimize` est le défaut de la couche 12 du squelette et ne coûte qu'une seconde au démarrage.

### D4. Stack `docker-compose.yml` de production

Une ancre `x-app` partage l'image `ghcr.io/emmpaul/golf-handicaps-laravel:${IMAGE_TAG:-main}`, l'environnement et `restart: unless-stopped`.

| Service | Commande | Dépend de | Healthcheck |
| --- | --- | --- | --- |
| `pgsql` | image `postgres:18-alpine` (digest) | — | `pg_isready`, toutes les 5 s |
| `migrate` | `php artisan migrate --force`, `restart: "no"` | `pgsql` sain | — |
| `web` | `frankenphp run --config /etc/caddy/Caddyfile` | `migrate` terminé avec succès | `curl -fsS http://127.0.0.1:8080/up` toutes les 10 s, 30 s de démarrage |
| `worker` | `php artisan queue:work --tries=2 --sleep=3 --timeout=420 --max-time=3600` | `migrate` terminé avec succès | battement (D5), toutes les 30 s, 3 essais, 30 s de démarrage |
| `backup` | boucle `pg_dump` (D6) | `pgsql` sain | âge du dernier dump (D6) |

- Volumes nommés : `pgdata` (monté sur `/var/lib/postgresql`, comme Sail, pour la disposition de PostgreSQL 18), `storage` (monté sur `/app/storage/app` pour `web` et `worker`) et `backups`.
- Environnement : chaque variable est nommée dans `environment:` et interpolée depuis le `.env` que Dokploy écrit à côté du fichier. `APP_KEY` et `DB_PASSWORD` sont exigés par `${…:?}`, et compose refuse de démarrer sans eux. `APP_PASSCODE` ne l'est pas : son absence doit faire répondre 500 à `/up`, comme le prévoit l'ADR 0007 (scénario « Code d'accès absent »). Les autres variables ont leur défaut de production (`SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `DB_HOST=pgsql`).
- Aucun port publié. Dokploy relie son domaine au port 8080 du service `web`. Pour le test de fumée, `docker-compose.smoke.yml` construit l'image locale et publie `127.0.0.1:${SMOKE_PORT:-8080}:8080`.
- Le `--max-time=3600` du worker le fait sortir chaque heure, et `restart` le relance. La mémoire de PHP repart à zéro, et le battement continue dans le même `/tmp`.

**Alternative écartée** : migrations au démarrage du web (comme Next). Deux réplicas les lanceraient en même temps, et un web en échec de migration redémarrerait en boucle. Le service `migrate` rend l'échec visible et bloque web et worker.

### D5. Battement du worker

`App\Support\WorkerHeartbeat` a deux méthodes :

- `beat()` écrit l'heure Unix en secondes dans `config('queue.heartbeat_path')` ;
- `isFresh(int $maxAgeSeconds)` lit ce fichier pour le test.

`queue.heartbeat_path` vaut `'/tmp/worker-heartbeat'` en dur, sans variable d'environnement : le healthcheck ci-dessous code ce chemin en dur, et une variable qui le déplacerait rendrait le worker malade sans erreur. Les tests le changent par `config()`.

`AppServiceProvider` l'abonne à `Queue::looping` (chaque tour de boucle, file vide ou non) et aux événements `JobProcessed` et `JobFailed`. Seul `queue:work` émet ces événements, donc le web n'écrit jamais de battement. `/tmp` n'est pas un volume : un nouveau conteneur part sans fichier.

Le healthcheck reste en PHP pur, sans démarrer Laravel :

```sh
php -r 'exit(time() - (int) @file_get_contents("/tmp/worker-heartbeat") < 300 ? 0 : 1);'
```

Un fichier absent donne `(int) false = 0`, donc une différence énorme et un échec.

Le seuil de 5 minutes vit à deux endroits : ce healthcheck et la doc. Un commentaire dans `docker-compose.yml` les lie. Un job figé fait passer le worker `unhealthy` vers 360 à 390 s : le battement atteint 300 s, puis le healthcheck échoue trois fois de suite, à 30 s d'écart. Le worker tourne ensuite avec `--timeout=420` : le job est tué à 420 s, le processus du worker sort, et `restart` relance le conteneur. Le `retry_after` de la file `database` vaut 450 s par défaut (`DB_QUEUE_RETRY_AFTER`), au-dessus de ce délai : un job n'est jamais repris pendant qu'il tourne encore. Le job repasse une fois (`--tries=2`), puis part dans `failed_jobs`. Un job de 2 minutes se termine sans que le battement atteigne le seuil (décision du mainteneur du 2026-10-28).

**Alternative écartée** : une commande artisan `worker:health` pour le healthcheck. Elle démarre tout Laravel toutes les 30 s pour lire un fichier.

### D6. Service de sauvegarde

Le service `backup` part de l'image `postgres:18-alpine`, la même que `pgsql`, donc avec un `pg_dump` de la même version majeure. Sa commande est un script shell court écrit dans `docker-compose.yml`, pour que la stack ne dépende d'aucun fichier du dépôt quand Dokploy la lit en mode « Raw ». La boucle :

1. `pg_dump -Fc` vers `/backups/golf-<horodatage UTC>.dump.partial`, puis `mv` vers `.dump` seulement si `pg_dump` sort en 0. En cas d'échec, le `.partial` est supprimé.
2. `find /backups -name 'golf-*.dump' -mtime +14 -delete` : un dump de 15 jours part, ceux de 14 jours et moins restent.
3. `sleep 86400`.

Healthcheck : `find /backups -name 'golf-*.dump' -mmin -1560 | grep -q .`, soit un dump réussi de moins de 26 heures. Le délai de démarrage est de 5 minutes.

Restauration (dans `docs/deployment.md`) : arrêter `web` et `worker`, `dropdb` puis `createdb`, `pg_restore --no-owner -d <base>` depuis le conteneur `backup`, puis redémarrer. Elle est essayée sur staging (tâche 9).

**Alternatives écartées** :

- Sauvegardes de volume de Dokploy vers S3 : elles demandent un stockage externe à configurer, et copient les fichiers de PostgreSQL à chaud au lieu d'un dump cohérent.
- Commande documentée seulement : elle n'a aucune régularité, et rien ne vire au rouge quand on oublie.

### D7. HSTS dans `AddSecurityHeaders`

Après `$next($request)`, quand `$request->isSecure()` est vrai, le middleware pose `Strict-Transport-Security: max-age=31536000`. `AddSecurityHeaders` est le plus externe, mais il lit `isSecure()` au retour. À ce moment, `TrustProxies` a déjà appliqué les mandataires de confiance à la requête, et `X-Forwarded-Proto` ne compte que s'il vient d'un mandataire listé. Le test prouve cet ordre avec le scénario « Mandataire inconnu ».

Sans `includeSubDomains` ni `preload` : le domaine de l'application peut partager son domaine parent avec d'autres services du mainteneur.

### D8. Témoin `Secure` par défaut en production

`config/session.php` : `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`. La valeur est lue dans un fichier de configuration, pas dans le code (couche 2.6). `SESSION_SECURE_COOKIE` reste un moyen de forcer l'une ou l'autre valeur. `.env.example` (local) ne la définit pas.

### D9. CI : construire une fois, publier ce qui a été testé

- `ci.yml` se déclenche aussi sur `push: tags: ['v*']`. Le job `changes` y trouve un commit précédent nul et passe en repli, donc tous les contrôles tournent sur le commit tagué.
- Job `image` (si `code == 'true'`, 15 min) : `make image GIT_COMMIT=$sha`, puis `make image-smoke`, puis `docker save` de l'image en artefact (`image.tar`, 1 jour de rétention).
- Job `publish` (15 min), seulement sur `push` vers `main` ou un tag `v*`. Il exige que `static`, `test-backend`, `test-frontend` et `image` aient réussi, et `code == 'true'`. Il charge `image.tar`, calcule ses tags par `make image-tags REF=… SHA=…`, se connecte à `ghcr.io` avec `GITHUB_TOKEN` (`docker login --password-stdin`), puis tague et pousse. C'est le seul job avec `packages: write`.
- `make image-tags` affiche `main <sha>` pour `refs/heads/main`. Pour `refs/tags/vX.Y.Z`, il affiche `vX.Y.Z latest <sha>`, et seulement si `version.txt` vaut `X.Y.Z`. Sinon il échoue en nommant les deux valeurs. Toute autre ref échoue. La règle vit dans le Makefile, et un test Pest la lance.
- Le job `publish` ne passe ni par `actions/cache` ni par le cache GHA de buildx : zizmor signale la restauration d'un cache dans un job qui publie (empoisonnement de cache). L'image arrive par l'artefact du même run.
- Le label `org.opencontainers.image.source=https://github.com/emmpaul/golf-handicaps-laravel` relie le paquet GHCR au dépôt. Le `Dockerfile` le pose par `LABEL` dans l'étape `runtime`. L'image du test de fumée le porte donc déjà, et le digest publié reste celui de l'image testée. Le job `publish` n'ajoute pas le label : il lit celui de l'image chargée (`docker image inspect`) et échoue avant tout push s'il manque ou diffère.

**Alternatives écartées** :

- Reconstruire l'image dans `publish` avec `docker/build-push-action`. L'image publiée ne serait plus celle du test de fumée, et le job ajouterait deux actions tierces.
- Ajouter le label dans `publish`. Une couche de plus change le digest : l'image poussée ne serait plus celle du test de fumée.

### D10. Makefile

Nouvelles cibles, avec leur aide en français (`MakefileHelpTest`) et lancées sur l'hôte, sans Sail :

- `image` : `docker build --build-arg GIT_COMMIT=$(GIT_COMMIT) -t $(IMAGE) .`, avec `IMAGE ?= golf-handicaps-laravel:local` et `GIT_COMMIT ?=` vide.
- `image-smoke` : `docker compose -f docker-compose.yml -f docker-compose.smoke.yml --env-file docker/smoke.env up --wait --wait-timeout 300`, puis `curl -fsS` sur `/up` et `/login`. À la fin, réussite ou non, `down -v`, et les journaux s'affichent en cas d'échec. `docker/smoke.env` contient des valeurs de test (`APP_KEY` de test, `APP_PASSCODE=smoke-passcode`), sans aucun secret réel.
- `image-tags` : D9.

Le Makefile exporte `COMPOSE_FILE=compose.yaml`, et `.env.example` porte la même ligne. Sail et les cibles qui passent par lui ne lisent alors que la stack de développement, et l'avertissement des deux fichiers disparaît. Les cibles `image*` nomment leurs fichiers par `-f`, qui l'emporte sur `COMPOSE_FILE` : la stack de production n'est pas touchée.

### D11. Journaux

`LOG_CHANNEL=stderr` est posé dans l'image. Docker garde et fait tourner les journaux, et Dokploy les affiche. Le canal `daily` du squelette écrirait dans le conteneur, perdu à chaque redéploiement. Aucun journal ajouté par ce changement ne contient de valeur de requête.

Une `QueryException` porte dans son message la requête SQL avec ses valeurs liées. Base arrêtée, la lecture de session journalise donc `select * from "sessions" where "id" = <identifiant>`, et qui lit les journaux peut reprendre la session (décision du mainteneur du 2026-10-28). La connexion `pgsql` de `config/database.php` pose `mask_bindings_in_exception_messages => true`, option de Laravel lue par `Illuminate\Database\Connection` (présente en 13.39, absente de la documentation) : le message de la `QueryException` garde la requête avec ses `?`, sans les valeurs. La règle vaut dans tous les environnements, sans condition, et la réponse rendue ne change pas. L'option tient en une ligne de configuration, sans code dans `bootstrap/app.php` (décision du mainteneur du 2026-10-28, qui remplace une copie de l'exception construite au rapport). Les traces ne montrent pas les arguments des fonctions : `zend.exception_ignore_args` est actif dans `php.ini-production`.

### D12. Mandataires et domaine

Dokploy route le trafic par Traefik sur le réseau `dokploy-network`. `docs/deployment.md` dit comment lire son sous-réseau (`docker network inspect dokploy-network`) et le mettre dans `TRUSTED_PROXIES`, sans jamais `*`. La façon dont la stack rejoint ce réseau (option « Isolated Deployment » ou réseau externe déclaré à la main) se vérifie sur le staging, et la doc garde celle qui marche.

### D13. ADR et documentation

- ADR 0008 « Déploiement par image unique et stack Dokploy » : D1 à D4, D6 et D9, avec leurs alternatives.
- ADR 0005 : la puce « Couche 12 » renvoie à l'ADR 0008. La conséquence « Le tag déclenchera la publication » passe au présent.
- `docs/deployment.md` (en français) : première installation (paquet GHCR public, projet Dokploy en compose, variables, domaine sur `web:8080`, `TRUSTED_PROXIES`), déploiement d'une version, vérification par `/api/version`, santé du worker et réglage du healthcheck dans Dokploy, sauvegarde, restauration avec la date de l'essai sur staging, retour arrière, fermeture de toutes les sessions (changer `APP_PASSCODE`).
- `ARCHITECTURE.md` : section « Déploiement » ; la ligne « Hors périmètre jusqu'à `production-deployment` » de la section Accès est retirée.
- `ROADMAP.md` : entrée 4 en cours, puis faite à l'archivage.
- `CHANGELOG.md` : lignes sous `## [Unreleased]`.
- ADR 0005 : la puce « Versionnement manuel » renvoie au skill `/release` (D14).

### D14. Publication d'une version par le skill `/release`

Décisions du mainteneur du 2026-10-28. Publier une version se fait par le skill `/release`, que seul le mainteneur lance. Aucun requirement de spec ne change : le skill enchaîne des étapes que l'ADR 0005 et D9 décrivent déjà, dans un ordre fixe, avec des arrêts où le mainteneur décide.

- **Source et invocation.** Le skill vit dans `.ai/skills/release/SKILL.md`. `vendor/bin/sail artisan boost:update` le copie dans `.claude/skills/release/`. Son frontmatter porte `disable-model-invocation: true` : un agent ne le lance jamais de lui-même.
- **Version.** `/release X.Y.Z` prend la version en argument facultatif. Sans argument, le skill propose un saut semver d'après les sous-sections de `## [Unreleased]` : `Removed` ou rupture donne un majeur, `Added` ou `Changed` un mineur, `Fixed` seul un correctif. Sous `1.0.0`, une rupture donne un mineur. Le mainteneur confirme la version par `AskUserQuestion`.
- **Préconditions.** Être sur `main`, à jour avec `origin`, sans modification locale. `## [Unreleased]` n'est pas vide. La dernière exécution de la CI sur `main` est verte. Le tag `vX.Y.Z` n'existe ni en local ni sur `origin`. Une précondition fausse arrête le skill, qui la nomme.
- **PR de version.** Branche `chore/release-vX.Y.Z` (`git switch -c … --no-track`), commit `chore(release): vX.Y.Z`. Le scope `release` entre dans la liste fermée de `commitlint.config.js`. `## [Unreleased]` devient `## [X.Y.Z] - YYYY-MM-DD`, date prise dans le fuseau `America/Montreal`, et une section `## [Unreleased]` vide s'ouvre au-dessus. `version.txt` vaut `X.Y.Z`. Le skill pousse la branche et ouvre la PR par `gh`.
- **Fusion.** Le skill attend que les contrôles requis soient verts, puis demande au mainteneur (`AskUserQuestion`) avant de fusionner. La fusion se fait par rebase, la seule que le dépôt accepte, et GitHub supprime la branche.
- **Tag.** Après la fusion, le skill récupère `main` et redemande. Il pose alors le tag annoté `git tag -a vX.Y.Z -m "vX.Y.Z"` sur la tête de `main`, c'est-à-dire le commit rebasé, et le pousse.
- **Après le tag.** Le skill suit l'exécution de la CI du tag jusqu'à `publish` vert. Il vérifie que `:vX.Y.Z`, `:latest` et `:<sha>` de `ghcr.io/emmpaul/golf-handicaps-laravel` ont le même digest. Il lance l'image par `docker run`, et `GET /api/version` doit répondre `X.Y.Z` et le sha. Il crée la GitHub Release `vX.Y.Z` avec la section du `CHANGELOG.md` en notes, en anglais. Il demande enfin la cible : la production si sa stack existe, le staging épinglé sur `vX.Y.Z`, ou aucune. Les étapes Dokploy de `docs/deployment.md` suivent, une à la fois, et le skill attend le retour du mainteneur entre deux.
- **Échec après le tag.** Un tag poussé ne se déplace ni ne se supprime jamais. Un échec passager se relance, après accord du mainteneur. Un vrai échec arrête le skill, qui propose une version corrective `vX.Y.(Z+1)` une fois le correctif fusionné.
- **Règles.** Dans `.ai/guidelines/golf-handicaps.md`, la puce de publication devient : une version ne se publie que par `/release`, lancé par le mainteneur. `AGENTS.md` se régénère par `boost:update`, jamais à la main. La puce « Versionnement manuel » de l'ADR 0005 renvoie au skill. Pas de nouvel ADR.

**Alternatives écartées** :

- Garder la publication à la main, comme l'ADR 0005 la décrit. Huit étapes dans un ordre précis, dont un tag qu'on ne peut plus reprendre une fois poussé : un oubli (date, section vide, `version.txt`) se voit trop tard.
- release-please, déjà écarté par l'ADR 0005. Il tire le `CHANGELOG.md` des messages de commit, alors que chaque PR de ce dépôt y écrit ses lignes à la main.
- Un skill qu'un agent peut lancer de lui-même. Publier une version pousse un tag définitif et peut toucher la production : la décision revient au mainteneur.

## Risks / Trade-offs

- [L'image FrankenPHP pour PHP 8.5 n'a pas le tag attendu] → Choisir à l'apply le tag publié le plus récent de plus de deux semaines, et noter la version dans le `Dockerfile`. S'il n'existe pas d'image 8.5, s'arrêter et le dire au mainteneur au lieu de descendre en 8.4 (`composer.json` exige `^8.5`).
- [`curl` absent de l'image FrankenPHP] → À vérifier à l'apply. À défaut, le healthcheck du web passe par `php -r` avec `file_get_contents('http://127.0.0.1:8080/up')`.
- [Dokploy n'honore pas `depends_on: condition: service_completed_successfully`] → Dokploy lance `docker compose up` en mode compose (pas Swarm) et la condition est standard. Le premier déploiement sur staging le vérifie. À défaut, le retour à la doc dit comment le contourner avant de fermer la tâche.
- [Un `pg_dump` quotidien reste sur le même disque que la base] → Une panne de disque perd les deux. La copie hors serveur est hors périmètre, et la doc en donne la commande. C'est accepté pour un groupe d'amis, à revoir si la perte d'une saison devient grave.
- [HSTS d'un an sur le domaine de staging] → Le navigateur refusera le HTTP sur ce domaine pendant un an. C'est sans effet tant que le staging reste en HTTPS, et c'est voulu.
- [`optimize` au démarrage échoue (variable mal formée)] → Le conteneur sort en erreur avant de servir. `migrate` échoue le premier et bloque `web` et `worker`, et le journal nomme l'erreur.
- [Artefact `image.tar` de quelques centaines de Mo par run de code] → Rétention d'un jour.
- [Le tag `main` bouge à chaque fusion] → Le staging suit `main` par choix. La production n'utilise que des tags `vX.Y.Z`.

## Migration Plan

1. Fusion de la PR de code : la CI publie `:main`.
2. Le mainteneur rend le paquet GHCR public une fois et monte le projet Dokploy de staging selon `docs/deployment.md`, une étape à la fois avec l'agent.
3. Vérifications sur staging : `docker compose ps` tout sain, `/api/version` répond le sha de `main`, `/login` en HTTPS avec HSTS, témoin `Secure`, IP du client vue par le limiteur, un dump présent dans `backups`, puis l'essai de restauration.
4. La doc garde ce qui a marché (réseau, healthchecks dans Dokploy), puis la PR d'archivage remplit la couche 12.

Retour arrière : la stack de staging est neuve et ne touche pas l'application Next.js en production. La supprimer dans Dokploy suffit. Dans le dépôt, annuler la PR.

## Open Questions

- Nom du domaine de staging : le mainteneur le choisit au montage. Il n'apparaît que dans les variables Dokploy (`APP_URL`), pas dans le dépôt.
