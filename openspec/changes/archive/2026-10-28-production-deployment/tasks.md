# Tasks

Avant de commencer : `ROADMAP.md` entrée 3 faite ; relire `proposal.md` (décisions du mainteneur du 2026-10-28 : publication `main` + tags, `amd64` seul, service `pg_dump`, `worker-health` ici), `design.md` et les quatre deltas de specs. Les fichiers de référence Next sont listés dans `design.md`, « Context ». Chaque test Pest nomme dans un commentaire la mutation qui le rend rouge ; elle est appliquée, vue rouge, puis retirée. Valeurs attendues en littéraux de la spec (`max-age=31536000`, `/tmp/worker-heartbeat`, `300`, `ghcr.io/emmpaul/golf-handicaps-laravel`). Les commandes Docker de l'hôte passent par les cibles `make image*`, jamais en direct.

## 1. Sécurité des réponses en production

- [x] 1.1 Écrire dans `tests/Feature/SecurityHeadersTest.php` les quatre scénarios de « HTTPS imposé au navigateur » : `TRUSTED_PROXIES` à `10.0.0.0/8`, requête `/login` depuis `10.0.1.5` avec `X-Forwarded-Proto: https`, en-tête `Strict-Transport-Security` égal à `max-age=31536000` ; `/up` en HTTP sans l'en-tête ; même requête depuis `198.51.100.9` sans l'en-tête ; adresse inconnue en HTTPS (404) avec l'en-tête ; le voir rouge ; mutations nommées : `isSecure()` lu avant `$next()` (le scénario du proxy de confiance rougit), `includeSubDomains` ajouté
- [x] 1.2 Ajouter HSTS à `AddSecurityHeaders` (D7) ; vérifier `make test-backend FILES=tests/Feature/SecurityHeadersTest.php` vert
- [x] 1.3 Écrire `tests/Feature/SessionCookieTest.php` : avec `APP_ENV=production` et sans `SESSION_SECURE_COOKIE`, la valeur `secure` relue depuis `config/session.php` vaut `true`, et le témoin posé par `POST /login` porte `secure`, `httponly` et `samesite=lax` ; avec `APP_ENV=local`, `secure` vaut `false` et `/` répond 200 à la requête suivante ; le voir rouge ; mutation nommée : défaut `env('SESSION_SECURE_COOKIE')` remis ; modifier `config/session.php` (D8) ; vérifier vert

## 2. Battement du worker

- [x] 2.1 Écrire `tests/Feature/WorkerHeartbeatTest.php` sur la file `database` et un chemin de battement dans un dossier temporaire du test : sans fichier, `isFresh(300)` est faux ; `queue:work database --stop-when-empty --sleep=0` sur une file vide (boucle du démon, qui émet `Looping` ; `--once` ne l'émet jamais) écrit le fichier avec l'heure courante (`travel` figé, valeur littérale) ; un job de test qui réussit puis un job qui échoue, chacun lancé par `queue:work --once`, laissent un fichier daté de la fin du job ; une requête `GET /up` n'écrit aucun fichier ; seuil : `isFresh(300)` vrai à 299 s, faux à 300 s et à 301 s ; le voir rouge ; mutations nommées : abonnement à `Queue::looping` retiré, abonnement à `JobProcessed` retiré, abonnement à `JobFailed` retiré, seuil `<=` au lieu de `<`
- [x] 2.2 Écrire `App\Support\WorkerHeartbeat`, `queue.heartbeat_path` dans `config/queue.php` et l'abonnement dans `AppServiceProvider` (D5) ; vérifier `make test-backend FILES=tests/Feature/WorkerHeartbeatTest.php` vert, puis `make analyse` vert

## 3. Image de production

- [x] 3.1 Choisir le tag exact et le digest de `dunglas/frankenphp` pour PHP 8.5 (image de plus de deux semaines) et de `postgres:18-alpine`, et vérifier la présence de `curl` dans l'image FrankenPHP ; sans image 8.5, s'arrêter et le dire au mainteneur (design, Risks)
- [x] 3.2 Écrire `.dockerignore`, `docker/Caddyfile` (D1), `docker/entrypoint.sh` (D3) et le `Dockerfile` en quatre étapes (D2) ; ajouter la cible `make image` (D10) avec son aide en français et l'ajouter à la liste de `MakefileHelpTest` ; vérifier `make image` réussi, puis, dans l'image, `id -u` différent de `0`, `public/build/manifest.json` présent, ni `.env` ni `node_modules` ni `vendor/pestphp`
- [x] 3.3 Vérifier que `make image GIT_COMMIT=4794e1b0c0ffee00000000000000000000000000` donne une image dont `APP_COMMIT` vaut ce sha, et qu'une image sans `GIT_COMMIT` a un `APP_COMMIT` vide ; noter les commandes et sorties dans le compte rendu
- [x] 3.4 Construire les assets de l'étape `assets` avec `VITE_APP_NAME="Handicaps golf"` (D2) ; vérifier sur la stack de fumée (`http://127.0.0.1:8080/login`, skill `browser-use`) que le titre de l'onglet vaut `Connexion - Handicaps golf` et non `Connexion - Laravel` ; mutation nommée : variable retirée du `Dockerfile`, le titre redevient `Connexion - Laravel`

## 4. Stack de production et test de fumée

- [x] 4.1 Écrire `docker-compose.yml` (D4 : `pgsql`, `migrate`, `web`, `worker`, `backup`, volumes, environnement, healthchecks, commentaire qui lie le seuil de 300 s à la doc), le service `backup` (D6), `docker-compose.smoke.yml` et `docker/smoke.env` (valeurs de test seulement)
- [x] 4.2 Ajouter `make image-smoke` (D10) et son aide ; vérifier `make image image-smoke` vert : `docker compose up --wait` rend la main avec `pgsql`, `web`, `worker` et `backup` sains et `migrate` sorti en 0, `/up` et `/login` répondent 200, un fichier `golf-*.dump` existe dans `backups`, et la stack est retirée à la fin
- [x] 4.3 Prouver que le test de fumée rougit, une mutation à la fois, puis retirer chaque mutation : commande du worker remplacée par `sleep infinity` (le worker n'est jamais sain) ; `APP_PASSCODE` retiré de `docker/smoke.env` (`web` jamais sain, `/up` en 500) ; une migration de test qui lève une exception (`migrate` sort non nul, `web` ne démarre pas) ; noter le résultat de chaque essai
- [x] 4.4 Vérifier sur la stack de fumée lancée à la main (`up --wait` sans `down`) : un fichier écrit dans `storage/app` par `worker` se lit depuis `web` ; après `docker compose up --force-recreate`, la session ouverte avant reste valide ; `docker compose logs web` montre l'exception d'une route qui échoue, sans fichier sous `storage/logs` ; aucun journal ne contient `birdie-2025` après un code faux ; puis `down -v`
- [x] 4.5 Vérifier la rotation et la santé de `backup` : dans le conteneur, créer `golf-old.dump` daté de 15 jours (`touch -d`) et `golf-recent.dump` daté de 14 jours, relancer une passe ; le premier disparaît, le second reste ; un dump daté de 27 heures comme seul fichier fait échouer le healthcheck ; un `pg_dump` qui échoue (base inexistante) ne laisse aucun `.dump` ni `.partial`
- [x] 4.6 Faire taire l'avertissement de Sail sur les deux fichiers compose : exporter `COMPOSE_FILE=compose.yaml` dans le `Makefile` et ajouter la ligne à `.env.example` (D10) ; vérifier qu'une cible Sail (`make up`, puis une commande `vendor/bin/sail`) ne l'affiche plus, et que `make image-smoke` lance toujours la stack de production
- [x] 4.7 Écrire dans `tests/Feature/` un test Pest : une `QueryException` levée par une requête sur `sessions` avec un identifiant littéral, rapportée par le gestionnaire d'exceptions, laisse dans le journal capturé un message qui contient `select * from "sessions" where "id" = ?` et ne contient pas l'identifiant ; le voir rouge ; mutation nommée : option `mask_bindings_in_exception_messages` retirée de la connexion `pgsql` de `config/database.php` ; poser cette option (D11) ; vérifier `make test-backend` vert ; puis, sur la stack de fumée lancée à la main, ouvrir une session et lire son identifiant dans la table `sessions`, arrêter `pgsql`, rejouer une requête avec le témoin, et vérifier que `docker compose logs` nomme la requête avec `?` et ne contient pas l'identifiant de session ; `down -v`

## 5. Publication par la CI

- [x] 5.1 Écrire `tests/Feature/ImageTagsTest.php`, qui lance `make image-tags` avec un `version.txt` temporaire : `REF=refs/heads/main SHA=<sha>` affiche exactement `main <sha>` ; `REF=refs/tags/v0.1.0` avec `0.1.0` affiche `v0.1.0 latest <sha>` ; `REF=refs/tags/v0.2.0` avec `0.1.0` sort en erreur et le message contient `0.1.0` et `0.2.0` ; `REF=refs/heads/feature` sort en erreur ; le voir rouge ; mutation nommée : comparaison à `version.txt` retirée
- [x] 5.2 Écrire la cible `make image-tags` (D9) et son aide ; vérifier `make test-backend FILES=tests/Feature/ImageTagsTest.php` vert
- [x] 5.3 Ajouter à `ci.yml` le déclencheur `tags: ['v*']`, le job `image` (15 min, si code, `make image` puis `make image-smoke`, artefact `image.tar` d'un jour) et le job `publish` (15 min, `packages: write` seul, conditions de D9, `make image-tags`, `docker login --password-stdin`, push, vérification que l'image chargée porte le label `org.opencontainers.image.source` posé par le `Dockerfile`, avant tout push) ; actions épinglées par sha avec leur version en commentaire, checkout sans jeton ; vérifier `zizmor` en local sur `.github/` sans alerte nouvelle, et `make lint` vert
- [ ] 5.4 Vérifier la CI sur la PR de code : `image` tourne et réussit, `publish` est sauté ; sur une PR de documentation seule, `image` est sauté (une PR de test ou la PR d'archivage le montre)
- [ ] 5.5 (mainteneur) Ajouter `image` aux contrôles requis de la protection de `main` (aujourd'hui `changes`, `static`, `test-frontend`, `test-backend`, `report`), pour qu'une PR dont l'image échoue ne puisse pas fusionner (spec `ci-pipeline`, « Dockerfile cassé ») ; vérifier dans les réglages de la branche que `image` figure dans la liste

## 6. Documentation et décisions

- [x] 6.1 Écrire l'ADR 0008 « Déploiement par image unique et stack Dokploy » (D13) et l'ajouter à `docs/adr/README.md` ; dans l'ADR 0005, renvoyer la couche 12 à l'ADR 0008 et mettre au présent la conséquence sur la publication du tag
- [x] 6.2 Écrire `docs/deployment.md` en français (D13) : chaque commande de la doc se lance telle quelle sur la stack de fumée (restauration comprise, essayée en local sur la stack de 4.4 avant le staging)
- [x] 6.3 Mettre à jour `ARCHITECTURE.md` (section « Déploiement », ligne « Hors périmètre jusqu'à `production-deployment` » retirée), `ROADMAP.md` (entrée 4 en cours), `.env.example` (commentaire de `SESSION_SECURE_COOKIE`) et `CHANGELOG.md` sous `## [Unreleased]`

## 7. Vérification locale

- [x] 7.1 Lancer `make check` et le voir vert ; en cas d'échec, lire `reports/check/<étape>.log` et corriger dans le code
- [x] 7.2 Passe navigateur : `make up`, `make dev` en arrière-plan, `make browser`, puis le skill `browser-use` sur `http://localhost`, à 390 et 1440 px : `/login` puis l'accueil fonctionnent comme avant, la session tient d'une page à l'autre en HTTP local (témoin sans `Secure`), aucun `Strict-Transport-Security` en HTTP, console sans erreur ; puis sur la stack de fumée (`http://127.0.0.1:8080`) : `/login`, connexion, accueil, assets compilés chargés sans serveur Vite ; rapporter ce qui a été vu ; `make dev-stop`
- [x] 7.3 Relire chaque scénario des quatre deltas de specs et noter, pour chacun, le test, l'étape ou la commande qui le vérifie ; les scénarios propres au serveur (proxy HTTPS de Dokploy, restauration sur staging, retour à l'image précédente) sont renvoyés au groupe 8 ; finir par `make openspec ARGS="validate --all --strict"` vert

## 8. Staging Dokploy (après la fusion de la PR de code)

Étapes manuelles du mainteneur, données une à une par l'agent selon `docs/deployment.md` ; l'agent attend le retour de chaque étape.

- [ ] 8.1 Vérifier que la CI de `main` a publié `ghcr.io/emmpaul/golf-handicaps-laravel:main` et `:<sha>` sur le même digest, rendre le paquet public, puis `docker pull` sans identifiants
- [ ] 8.2 Monter le projet compose de staging dans Dokploy (variables, `IMAGE_TAG=main`, domaine HTTPS sur `web:8080`, `TRUSTED_PROXIES` lu sur `dokploy-network`) ; vérifier que tous les services sont sains, que `GET /api/version` répond le sha de `main`, que `/login` porte `Strict-Transport-Security: max-age=31536000` et pose un témoin `Secure`, et que le journal du limiteur voit l'IP du client et non celle de Traefik (10 codes faux, puis « Trop d'essais » depuis ce client seulement)
- [ ] 8.3 Vérifier qu'un dump existe dans `backups`, puis suivre la restauration de `docs/deployment.md` dans une base vide ; la session ouverte avant reste valide ; inscrire la date de l'essai dans la doc
- [ ] 8.4 Reporter dans `docs/deployment.md` ce qui a différé de la doc (réseau Dokploy, healthchecks, ordre des services), et remplir la couche 12 dans l'ADR 0008 avec ce qui a marché
- [ ] 8.5 (mainteneur) Lancer `/release` pour `v0.1.0` (groupe 9 fait et fusionné) : PR de version fusionnée, tag `v0.1.0` poussé, `publish` vert, `:v0.1.0`, `:latest` et `:<sha>` sur le même digest, `GET /api/version` d'un conteneur `:v0.1.0` qui répond `"version": "0.1.0"` (spec `ci-pipeline`, « Version publiée ») ; l'essai de retour à l'image précédente du Workflow follow-up part de cette version

## 9. Skill `/release`

- [x] 9.1 Écrire `.ai/skills/release/SKILL.md` selon D14, en suivant le skill `writing-for-agents` ; frontmatter `disable-model-invocation: true` ; le copier dans `.claude/skills/` par `vendor/bin/sail artisan boost:update` ; vérifier que `.claude/skills/release/SKILL.md` est identique à la source (`diff` vide) et que `boost:update` n'a régénéré aucun autre fichier sans rapport (`git status`)
- [x] 9.2 Ajouter `release` à la liste des scopes de `commitlint.config.js` ; réécrire la puce de publication de `.ai/guidelines/golf-handicaps.md` (une version ne se publie que par `/release`, lancé par le mainteneur) et régénérer `AGENTS.md` par `boost:update` ; faire renvoyer la puce « Versionnement manuel » de l'ADR 0005 au skill ; ajouter la ligne de `CHANGELOG.md` sous `## [Unreleased]` ; vérifier, par le commitlint épinglé du dépôt (le message envoyé sur son entrée standard), que `chore(release): prepare the version` est refusé avant l'ajout du scope et accepté après, et que `chore(release): v0.1.0` est accepté (commitlint ignore par défaut tout message `chore(<scope>): <semver>`, scope compris), puis `make lint` vert

## Workflow follow-up

- Ouvrir la PR de code, avec ses lignes de `CHANGELOG.md`.
- Après la fusion et le groupe 8, archiver le changement dans une PR de documentation séparée, avec les retouches de doc du groupe 8, et passer l'entrée 4 de `ROADMAP.md` à « fait ».
- Le retour à l'image précédente (`deployment`, « Retour à l'image précédente ») s'essaie à la première version publiée `v0.1.0` puis `v0.1.1` ; le noter alors dans `docs/deployment.md`.
