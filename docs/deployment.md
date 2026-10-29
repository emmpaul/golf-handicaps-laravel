# Déploiement

L'application tourne sur un hôte Docker piloté par [Dokploy](https://dokploy.com), en projet compose. La stack est `docker-compose.yml`, à la racine du dépôt. Elle lance l'image que la CI publie sur GitHub Container Registry, `ghcr.io/emmpaul/golf-handicaps-laravel`. Les décisions sont dans l'ADR 0008.

## La stack

| Service   | Rôle                                                                                                                                     | Sain quand                              |
| --------- | ---------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------- |
| `pgsql`   | PostgreSQL 18, données dans le volume `pgdata`                                                                                           | `pg_isready` répond                     |
| `migrate` | `php artisan migrate --force`, puis `golf:initial-players`, puis `golf:recompute-stats`, puis `golf:queue-rating-lookups`, puis s'arrête | sort en 0 (il reste alors `Exited (0)`) |
| `web`     | FrankenPHP sur le port 8080, sans port publié                                                                                            | `/up` répond 200                        |
| `worker`  | `php artisan queue:work`, relancé chaque heure par `--max-time=3600`, seul service qui joint Simulator Golf Tour                         | son battement a moins de 300 s          |
| `backup`  | `pg_dump` au démarrage puis toutes les 24 h dans le volume `backups`                                                                     | un dump réussi a moins de 26 h          |

`web` et `worker` démarrent seulement après la sortie en 0 de `migrate`. Si une migration, la création des joueurs initiaux, le recalcul de l'historique d'index ou la mise en file des recherches de rating échoue, ils ne démarrent pas sur la nouvelle image et restent sur `Created`. Lire alors `docker logs "$P-migrate-1"`, corriger, redéployer. Ce que deviennent les conteneurs de l'ancienne version pendant ce temps reste à observer sur le staging. `migrate`, `web` et `worker` montent le volume `storage` sur `storage/app` : `migrate`, créé avant les deux autres, remplit seul un volume neuf, sinon `web` et `worker`, créés en même temps, copient chacun le contenu de l'image et le second échoue (`file exists`, issue #14). Le contrôle de « Volume `storage` neuf » le vérifie. Les journaux partent sur la sortie d'erreur des conteneurs (`docker logs`, onglet Logs de Dokploy). Aucun fichier de journal ne grossit dans un conteneur.

## Lancer les commandes de cette page

Les commandes visent les conteneurs par leur nom, `<projet>-<service>-1`, et l'application par son adresse. Deux variables, `P` et `URL`, portent ces deux valeurs.

Sur le serveur Dokploy, le projet porte le nom d'application que Dokploy affiche sur le service compose (« App Name »). Pour le retrouver :

```sh
docker ps --format '{{.Names}}' | grep -- '-web-1$'
```

```sh
P=<nom-du-projet>
URL=https://<domaine>
```

En local, la même stack tourne sur l'image construite par `make image`, depuis la racine du dépôt :

```sh
docker compose -f docker-compose.yml -f docker-compose.smoke.yml --env-file docker/smoke.env up --wait
P=golf-handicaps-smoke
URL=http://127.0.0.1:8080
```

Le code d'accès de cette stack locale est `smoke-passcode`. Pour la retirer avec ses volumes : `docker compose -f docker-compose.yml -f docker-compose.smoke.yml --env-file docker/smoke.env down -v`.

Toutes les commandes de cette page ont été lancées telles quelles sur la stack locale le 2026-10-28, restauration comprise. Celles qui ne marchent que sur le serveur le disent. Celles de la recherche des ratings (« Déployer une version », étape 5, et « Retour arrière de la recherche des ratings ») ont seulement été relues : elles restent à lancer sur le staging.

## Image

La CI publie l'image après la réussite de tous ses contrôles, y compris le test de fumée de la stack. Elle pousse l'image même qu'elle a testée, sans la reconstruire.

| Événement                      | Tags publiés                | Utilisé par |
| ------------------------------ | --------------------------- | ----------- |
| push sur `main` (code modifié) | `main`, `<sha>`             | staging     |
| tag `vX.Y.Z` sur `main`        | `vX.Y.Z`, `latest`, `<sha>` | production  |

Un tag `vX.Y.Z` ne publie rien si `version.txt` ne vaut pas `X.Y.Z` sur ce commit. Le job `publish` échoue alors en nommant les deux valeurs. Une PR de documentation seule ne publie rien. L'image est `linux/amd64` seulement.

L'image ne contient aucun secret. Toute la configuration arrive par l'environnement, au démarrage : `docker/entrypoint.sh` construit les caches Laravel (`php artisan optimize`) avant de lancer la commande du service.

## Première installation

### 1. Rendre le paquet public

Une fois, après la première publication : GitHub, profil, Packages, `golf-handicaps-laravel`, Package settings, Change visibility, Public. Dokploy tire alors l'image sans identifiants. Vérifier depuis le serveur :

```sh
docker logout ghcr.io
docker pull ghcr.io/emmpaul/golf-handicaps-laravel:main
```

Serveur seulement : en local, rien n'est publié avant la fusion.

### 2. Vérifier Docker sur l'hôte

Les healthchecks de la stack utilisent `start_interval`, qui demande Docker Engine 25 ou plus récent :

```sh
docker version --format '{{.Server.Version}}'
```

### 3. Créer le projet compose

Dans Dokploy, créer un projet, puis un service de type Compose :

- source : le dépôt GitHub `emmpaul/golf-handicaps-laravel`, branche `main`, fichier `./docker-compose.yml`. Le mode Raw marche aussi, en collant le fichier : la stack ne lit aucun autre fichier du dépôt ;
- type : Docker Compose, pas Stack (Swarm). L'ordre `migrate` puis `web` et `worker` repose sur `depends_on: condition: service_completed_successfully`, que seul `docker compose` honore.

### 4. Renseigner les variables

Onglet Environment. Dokploy écrit ces lignes dans un `.env` à côté de `docker-compose.yml`, et compose les passe aux conteneurs.

| Variable                | Exemple                        | Rôle                                                                                                                                                                                                                                                                                             |
| ----------------------- | ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `IMAGE_TAG`             | `main`, `v0.1.0`               | Tag de l'image. `main` par défaut.                                                                                                                                                                                                                                                               |
| `APP_KEY`               | `base64:…`                     | Obligatoire, compose refuse de démarrer sans. Chiffre les sessions.                                                                                                                                                                                                                              |
| `APP_PASSCODE`          | `birdie-2026`                  | Code d'accès partagé. Sans lui, `/up` répond 500 et `web` n'est jamais sain (ADR 0007).                                                                                                                                                                                                          |
| `APP_URL`               | `https://golf.example.org`     | Adresse publique, en `https`.                                                                                                                                                                                                                                                                    |
| `DB_PASSWORD`           | une chaîne aléatoire           | Obligatoire. Lu une seule fois, à la création du volume `pgdata` (voir plus bas).                                                                                                                                                                                                                |
| `TRUSTED_PROXIES`       | `10.0.1.0/24`                  | Sous-réseau du proxy de Dokploy (étape 6). Jamais `*`.                                                                                                                                                                                                                                           |
| `INITIAL_PLAYERS`       | `Gab,Manu,Sean`                | Facultatif. Joueurs créés par `migrate` sur une base sans joueur, chacun avec son nom en majuscules pour alias. Sans effet dès qu'un joueur existe. Un nom en double, sans tenir compte de la casse, fait échouer `migrate` : `web` et `worker` ne démarrent pas.                                |
| `APP_NAME`              | `Handicaps golf`               | Facultatif, c'est le défaut.                                                                                                                                                                                                                                                                     |
| `DB_DATABASE`           | `golf`                         | Facultatif, c'est le défaut. Les commandes de cette page supposent `golf`.                                                                                                                                                                                                                       |
| `DB_USERNAME`           | `golf`                         | Facultatif, c'est le défaut.                                                                                                                                                                                                                                                                     |
| `LOG_LEVEL`             | `info`                         | Facultatif, c'est le défaut.                                                                                                                                                                                                                                                                     |
| `APP_DISPLAY_TIMEZONE`  | `America/Montreal`             | Facultatif, c'est le défaut.                                                                                                                                                                                                                                                                     |
| `ANTHROPIC_API_KEY`     | la clé de la console Anthropic | Facultatif. Clé de la lecture des photos de carte par l'IA. Sans elle, l'écran d'import affiche la lecture désactivée et la saisie manuelle reste possible. Ne jamais l'inscrire ailleurs que dans Dokploy.                                                                                      |
| `AI_MODEL`              | `claude-sonnet-5-5`            | Facultatif, c'est le défaut. Modèle de la lecture. Il doit avoir un tarif dans `config/golf.php` (voir plus bas).                                                                                                                                                                                |
| `AI_MONTHLY_BUDGET_USD` | `5`                            | Facultatif, c'est le défaut. Plafond de dépense de l'IA par mois, en dollars.                                                                                                                                                                                                                    |
| `AI_BUDGET_TIME_ZONE`   | `America/Montreal`             | Facultatif, c'est le défaut. Fuseau dans lequel le mois du budget commence et finit.                                                                                                                                                                                                             |
| `SGT_LOOKUP`            | `on`, `off`                    | Facultatif, `on` par défaut. Recherche des ratings sur Simulator Golf Tour par le worker. `off` coupe toute requête vers SGT : chaque recherche finit en « Saisie manuelle » avec « Recherche SGT désactivée ». Toute autre valeur fait échouer `migrate`, et `web` et `worker` restent arrêtés. |

Les quatre variables de l'IA vont à `migrate`, `web` et `worker`, et l'image n'en contient aucune. Un `AI_MODEL` sans tarif dans `config/golf.php`, comme un `AI_BUDGET_TIME_ZONE` inconnu, fait échouer le démarrage de l'application avec un message qui nomme la variable : `migrate` sort en erreur, `web` et `worker` restent arrêtés sur `Created` (`docker logs "$P-migrate-1"` donne la raison). Passer à un modèle nouveau demande donc d'ajouter son tarif à `config/golf.php` dans une version de l'image, avant de changer `AI_MODEL`. La clé ajoutée ou changée prend effet au redéploiement suivant (voir « Déployer une version »). L'ADR 0011 décrit la lecture et le budget.

`SGT_LOOKUP` va aussi à `migrate`, `web` et `worker`. Une valeur autre que `on` ou `off` fait échouer le démarrage de l'application avec un message qui commence par `SGT_LOOKUP` : `migrate` sort en erreur, `web` et `worker` restent sur `Created`, comme pour `AI_MODEL`. Seul le worker fait des requêtes sortantes vers `simulatorgolftour.com`, en HTTPS : la liste des parcours (`/courses`, gardée 24 h dans le cache en base) et l'image de la carte d'un parcours trouvé (`/public/assets/courseImages/scorecards/scorecard_<id>.jpg`), avec un `User-Agent` qui nomme l'application, 20 s au plus par requête. `migrate` et `web` ne font que mettre les recherches en file. La carte est ensuite lue par l'IA, sous le même budget mensuel que les photos. L'ADR 0012 décrit la recherche.

L'image accepte des photos de carte jusqu'à 20 Mo (`docker/php/uploads.ini`, copié dans la configuration de PHP) et les convertit en WebP par l'extension `imagick`. Il n'y a rien à régler dans Dokploy.

`APP_ENV=production`, `APP_DEBUG=false` et `LOG_CHANNEL=stderr` viennent de l'image. Sessions, cache et file sont en base. Le témoin de session est `Secure` dès que `APP_ENV=production`, sans variable de plus.

Pour tirer une `APP_KEY`, avec l'image locale (ou l'image publiée, sur le serveur) :

```sh
docker run --rm --entrypoint php golf-handicaps-laravel:local artisan key:generate --show
```

`DB_PASSWORD` ne sert qu'au premier démarrage de `pgsql`, qui crée l'utilisateur avec ce mot de passe dans le volume `pgdata`. Le changer ensuite dans Dokploy ne change pas celui de la base : `migrate` échoue alors à la connexion, et `web` et `worker` ne démarrent pas. Pour le changer vraiment, le changer d'abord dans la base (`docker exec "$P-pgsql-1" psql -U golf -d golf -c "ALTER USER golf PASSWORD '<nouveau>'"`), puis dans Dokploy, puis redéployer.

### 5. Relier le domaine

Onglet Domains : l'hôte de `APP_URL`, service `web`, port `8080`, HTTPS avec un certificat Let's Encrypt. Traefik termine le TLS et parle HTTP au port 8080 de `web`.

Le service `web` doit partager un réseau avec Traefik. Dokploy le fait de deux façons : l'option « Isolated Deployment » du service compose, ou le réseau externe `dokploy-network` ajouté à la main à `web`. Le staging dira laquelle marche avec cette stack, et cette page la gardera (tâche 8.4 de `production-deployment`).

### 6. Régler `TRUSTED_PROXIES`

Laravel ne croit `X-Forwarded-For` et `X-Forwarded-Proto` que s'ils viennent d'un mandataire listé dans `TRUSTED_PROXIES`. Sans la bonne valeur, le limiteur de `/login` voit l'IP de Traefik pour tout le monde et bloque tout le groupe après 10 essais, et HSTS n'est jamais envoyé.

Lire le sous-réseau du réseau de Traefik sur le serveur :

```sh
docker network inspect dokploy-network --format '{{range .IPAM.Config}}{{.Subnet}} {{end}}'
```

Serveur seulement. En local, la même commande sur le réseau de la stack (`docker network inspect "${P}_default" --format '{{range .IPAM.Config}}{{.Subnet}} {{end}}'`) donne par exemple `192.168.239.0/24`.

Mettre ce sous-réseau dans `TRUSTED_PROXIES` (plusieurs valeurs séparées par des virgules si la commande en affiche plusieurs). Ne jamais mettre `*` : n'importe quel client pourrait alors se faire passer pour une autre IP. La configuration retire d'ailleurs `*` et `**`. Si la stack rejoint Traefik par un autre réseau que `dokploy-network` (« Isolated Deployment »), c'est le sous-réseau de ce réseau-là qu'il faut.

Vérifier la valeur que voit l'application après le déploiement :

```sh
docker exec "$P-web-1" php artisan config:show trustedproxy.proxies
```

### 7. Déployer et vérifier

Cliquer sur Deploy. Puis :

```sh
docker ps -a --filter "label=com.docker.compose.project=$P" --format '{{.Names}}  {{.Status}}'
```

Attendu : `pgsql`, `web`, `worker` et `backup` en `(healthy)`, `migrate` en `Exited (0)`. `backup` peut rester en `(health: starting)` quelques secondes, le temps du premier dump.

Puis la version (section suivante), HSTS et le témoin `Secure` :

```sh
curl -sI "$URL/login" | grep -iE 'strict-transport-security|set-cookie: handicaps'
```

Sur le serveur, en HTTPS, la sortie contient `Strict-Transport-Security: max-age=31536000` et un témoin `handicaps-golf-session` marqué `secure; httponly; samesite=lax`. En HTTP local, HSTS manque, c'est normal. Pour le voir en local, il faut que le proxy de confiance dise HTTPS : relancer la stack avec `TRUSTED_PROXIES` réglé au sous-réseau local, puis ajouter `-H 'X-Forwarded-Proto: https'` à `curl`.

Enfin, depuis un téléphone, `/login`, le code, l'accueil.

## Vérifier ce qui tourne

`GET /api/version` répond sans session :

```sh
curl -si "$URL/api/version"
```

```http
HTTP/1.1 200 OK
Cache-Control: no-store, private
Content-Type: application/json

{"version":"0.0.0","commit":"616d7ba8d76f35059096a563d8458f42c3bb4089"}
```

- `version` est le contenu de `version.txt` du commit construit. Sur la production, il vaut le tag déployé sans le `v`.
- `commit` est le sha complet du commit. Une image de la CI le porte toujours. Une image construite à la main sans `GIT_COMMIT` (`make image` seul) répond `"commit": null`.
- `Cache-Control: no-store` empêche un proxy de servir l'ancienne version juste après un déploiement.

## Déployer une version

À la main, dans Dokploy :

1. Production : mettre `IMAGE_TAG=vX.Y.Z`. Staging : garder `IMAGE_TAG=main`.
2. Avant une version qui apporte une migration, forcer un dump (voir « Sauvegarde ») et noter son nom. C'est lui qu'on restaure si le retour arrière en a besoin.
3. Deploy. `migrate` applique les migrations en attente, puis `web` et `worker` repartent sur la nouvelle image.
4. Vérifier l'état des services et `/api/version`, comme à la première installation.
5. Lire la fin du journal de `migrate` :

    ```sh
    docker logs --tail 5 "$P-migrate-1"
    ```

    La dernière ligne dit « N recherches de rating mises en file. » (« 0 recherche de rating mise en file. » quand chaque parcours a déjà sa ligne). Le worker traite ces recherches dans les minutes qui suivent.

6. Ouvrir `/courses` et confirmer chaque proposition, après l'avoir comparée à sa « Carte SGT ». Aucun index ne change avant une confirmation.

Avec `IMAGE_TAG=main`, si `/api/version` montre encore l'ancien `commit`, l'hôte a gardé l'ancienne image `:main` en cache. Tirer la nouvelle sur le serveur, puis redéployer :

```sh
docker pull ghcr.io/emmpaul/golf-handicaps-laravel:main
```

Serveur seulement. Le comportement de Dokploy face à un tag qui bouge reste à confirmer sur le staging.

Changer une variable demande un redéploiement, pas un simple redémarrage. `docker restart` relance le conteneur avec l'environnement qu'il avait à sa création ; seul le redéploiement recrée les conteneurs avec les nouvelles valeurs.

## Volume `storage` neuf

Docker copie le contenu de l'image dans un volume nommé vide à la création du premier conteneur qui le monte. Compose crée `migrate` avant `web` et `worker`, qui en dépendent : c'est donc `migrate` qui remplit un volume `storage` neuf, une seule fois. Après une mise à jour majeure de Docker ou de Compose sur le serveur, refaire ce contrôle sur la stack locale, avec les mêmes versions de Docker et de Compose que le serveur. Il crée `migrate` seul, puis liste le volume depuis une autre image :

```sh
docker compose -f docker-compose.yml -f docker-compose.smoke.yml --env-file docker/smoke.env create migrate
docker run --rm --entrypoint sh -v golf-handicaps-smoke_storage:/mnt postgres:18.4-alpine@sha256:9a8afca54e7861fd90fab5fdf4c42477a6b1cb7d293595148e674e0a3181de15 -c 'ls -lan /mnt'
docker compose -f docker-compose.yml -f docker-compose.smoke.yml --env-file docker/smoke.env down -v
```

Le listing doit montrer `.gitignore`, `private` et `public` à l'uid 1000. Un volume vide, à `root` (`0 0`), veut dire que Compose ne crée plus `migrate` avant `web` et `worker`, ou que Docker ne copie plus : la course de l'issue #14 redevient possible sur un volume neuf.

## Santé du worker

Le worker écrit un battement, l'heure Unix en secondes, dans `/tmp/worker-heartbeat` à chaque tour de sa boucle (toutes les 3 s environ quand la file est vide) et après chaque job, réussi ou non (`App\Support\WorkerHeartbeat`). Le fichier vit dans le `/tmp` du conteneur, hors volume : un nouveau conteneur ne voit jamais le battement de l'ancien. Le web n'en écrit jamais.

Le healthcheck de `docker-compose.yml` déclare le worker malade quand le battement a 300 s ou plus, ou quand le fichier manque. Il tourne toutes les 30 s (délai 5 s, 3 essais, 30 s de démarrage). Il faut donc trois échecs de suite, soit 6 minutes environ sans battement, pour que le worker passe `unhealthy`. Un job figé fait donc passer le worker `unhealthy` vers 6 minutes. Il dure au plus 420 s (`--timeout=420`) : Laravel tue alors le processus du worker (`Worker STOPPED Job timed out` dans son journal), Docker relance le conteneur (`restart: unless-stopped`), qui repart sain, et le job repasse une fois dans la file après le `retry_after` de 450 s avant de partir dans `failed_jobs` (`--tries=2`). Un job de 2 minutes, comme une lecture de photo par l'IA, se termine sans alerte. Si ce seuil change, changer les deux endroits ensemble : la commande du healthcheck dans `docker-compose.yml` et ce paragraphe. Le `--timeout` du worker reste au-dessous du `retry_after` de la file (`config/queue.php`).

En mode compose, Dokploy prend le healthcheck dans `docker-compose.yml`. Il n'y a rien à régler dans son interface (les réglages « Swarm Settings, Health Check » concernent les applications Docker et les stacks Swarm). À confirmer sur le staging.

Lire l'état vu par Docker, et l'âge du battement :

```sh
docker inspect "$P-worker-1" --format '{{.State.Health.Status}} streak={{.State.Health.FailingStreak}}'
docker exec "$P-worker-1" php -r 'echo time() - (int) @file_get_contents("/tmp/worker-heartbeat"), " s\n";'
```

Attendu : `healthy streak=0` et quelques secondes.

Docker ne redémarre pas un conteneur `unhealthy`. Un worker figé reste figé jusqu'à ce que quelqu'un le relance. Lire d'abord son journal, puis le relancer :

```sh
docker logs --tail 50 "$P-worker-1"
docker restart "$P-worker-1"
```

## Sauvegarde

Le service `backup` fait un `pg_dump -Fc` de toute la base au démarrage, puis toutes les 24 heures, dans le volume `backups`, sous le nom `golf-<AAAAMMJJ>T<HHMMSS>Z.dump` (heure UTC). Le dump s'écrit d'abord en `.partial` et ne prend son nom définitif que si `pg_dump` réussit. Un dump interrompu ne laisse aucun fichier. Les dumps de plus de 14 jours partent à chaque passe.

`backup` devient malade quand aucun dump réussi n'a moins de 26 heures. C'est le signal qu'une sauvegarde a sauté.

Lister les dumps :

```sh
docker exec "$P-backup-1" ls -l --full-time /backups
```

Forcer un dump maintenant (la commande rend la main après une dizaine de secondes), puis lire le résultat :

```sh
docker restart "$P-backup-1"
docker logs --tail 3 "$P-backup-1"
```

La dernière ligne dit `Sauvegarde écrite : golf-<horodatage>.dump`, ou `Sauvegarde en échec : pg_dump est sorti en erreur`.

Lancer le healthcheck à la main (0 : un dump de moins de 26 h existe ; 1 : aucun) :

```sh
docker exec "$P-backup-1" sh -c "find /backups -name 'golf-*.dump' -mmin -1560 | grep -q ."; echo $?
docker inspect "$P-backup-1" --format '{{.State.Health.Status}} streak={{.State.Health.FailingStreak}}'
```

Les dumps restent sur le même disque que la base : une panne de disque emporte les deux. Copier de temps en temps un dump hors du serveur, par exemple sur son poste :

```sh
docker cp "$P-backup-1:/backups/golf-<horodatage>.dump" .
```

Puis `scp` depuis le poste. Les dumps contiennent toutes les données du groupe et les sessions : les garder hors de tout dossier partagé.

## Restauration

Restaurer un dump remplace toute la base. Les sessions ouvertes avant ce dump redeviennent valides, celles ouvertes après disparaissent.

Essai sur la stack locale : 2026-10-28, réussi (dump pris avec une session ouverte, restauration dans une base vide, session toujours valide, session ouverte après le dump refusée).

Essai sur staging : **à faire (tâche 8.3 de `production-deployment`), date à inscrire ici.**

1. Choisir le dump :

    ```sh
    docker exec "$P-backup-1" ls -l --full-time /backups
    ```

    Pour restaurer un dump copié hors du serveur, le remettre d'abord dans le volume sous un nom qui ne commence pas par `golf-`, pour qu'il ne compte ni dans la rotation ni dans la santé de `backup` :

    ```sh
    docker cp golf-<horodatage>.dump "$P-backup-1:/backups/import-<horodatage>.dump"
    ```

2. Si la base actuelle a encore de la valeur, forcer d'abord un dump d'elle (`docker restart "$P-backup-1"`).

3. Arrêter ce qui écrit dans la base :

    ```sh
    docker stop "$P-web-1" "$P-worker-1"
    ```

4. Remplacer la base par une base vide. `backup` a déjà l'hôte, l'utilisateur et le mot de passe dans son environnement :

    ```sh
    docker exec "$P-backup-1" dropdb golf
    docker exec "$P-backup-1" createdb golf
    ```

5. Restaurer :

    ```sh
    docker exec "$P-backup-1" pg_restore --no-owner -d golf /backups/<fichier>.dump
    ```

    La commande ne dit rien quand elle réussit.

6. Appliquer les migrations plus récentes que le dump, s'il y en a (la sortie dit `Nothing to migrate.` sinon) :

    ```sh
    docker start -a "$P-migrate-1"
    ```

    Le même conteneur recalcule ensuite l'historique d'index de tous les joueurs à partir des rounds et des ratings du dump : la sortie finit par « N joueurs recalculés. ».

7. Redémarrer, puis vérifier :

    ```sh
    docker start "$P-worker-1" "$P-web-1"
    docker ps -a --filter "label=com.docker.compose.project=$P" --format '{{.Names}}  {{.Status}}'
    ```

    `web` et `worker` repassent `(healthy)` en quelques secondes. Ouvrir l'application depuis un appareil connecté avant le dump : il entre sans ressaisir le code.

8. Si un dump `import-…` a servi, le retirer : `docker exec "$P-backup-1" rm /backups/import-<horodatage>.dump`.

## Retour arrière

Revenir à l'image précédente : dans Dokploy, remettre l'ancien `IMAGE_TAG` (par exemple `v0.1.0`), puis Deploy. Vérifier `/api/version`, qui doit répondre l'ancienne `version`.

`migrate` ne défait jamais rien : l'ancienne image trouve la base avec les migrations de la nouvelle. Ça marche tant que ces migrations ajoutent seulement (table, colonne facultative, index), parce que l'ancien code ignore ce qu'il ne connaît pas. Une migration irréversible change la donne : colonne supprimée ou renommée, colonne rendue obligatoire, données transformées. L'ancien code ne sait alors plus lire la base, et le retour arrière demande en plus de restaurer le dump pris juste avant le déploiement (« Déployer une version », étape 2), avec la section Restauration. Tout ce qui a été saisi entre ce dump et le retour arrière est perdu.

Pour savoir si une version apporte des migrations, lister celles ajoutées depuis la version en place, dans le dépôt :

```sh
git diff --name-only <ancien-tag> <nouveau-tag> -- database/migrations
```

Puis lire la méthode `up()` de chacune.

Le retour à l'image précédente sera essayé à la première paire de versions publiées, `v0.1.0` puis `v0.1.1`, et noté ici.

### Retour arrière de la recherche des ratings

La version qui apporte la recherche SGT rend `rating_t` et `slope` facultatifs dans `course_ratings`, que l'ancien code ne sait pas lire. Il faut donc défaire sa migration, et la défaire avec la nouvelle image : l'ancienne n'a pas le fichier de la migration, et `migrate:rollback` y passerait la ligne en écrivant `Migration not found`, sans rien défaire.

1. Arrêter le worker, pour qu'aucune recherche n'écrive pendant le retour :

    ```sh
    docker stop "$P-worker-1"
    ```

2. Défaire la migration depuis le web, qui tourne encore sur la nouvelle image :

    ```sh
    docker exec "$P-web-1" php artisan migrate:rollback --step=1 --force
    ```

    La sortie nomme `2026_10_29_000011_add_lookup_to_course_ratings_table` suivi de `DONE`. Les ratings confirmées restent, avec `rating_t` et `slope` de nouveau obligatoires ; les lignes sans valeur confirmée (propositions, recherches en cours) sont effacées.

3. Dans Dokploy, remettre l'ancien `IMAGE_TAG`, puis Deploy. Les jobs `LookUpCourseRating` restés dans la file échouent sur l'ancienne image et partent dans `failed_jobs`, sans toucher aux données.

Les recherches perdues repartent au redéploiement suivant de la nouvelle version : `golf:queue-rating-lookups` les remet en file.

## Bascule depuis Next

La bascule remplace l'application Next par cette stack, sur le même domaine. `golf:import-sqlite` copie la base SQLite de Next et ses photos dans la base PostgreSQL et le volume `storage`, en une transaction. Elle recalcule l'historique d'index et le compare à celui de Next : un écart annule tout. Les décisions sont dans l'ADR 0014.

Chaque étape est faite par le mainteneur, une à la fois : lancer la commande, lire la sortie comme indiqué, puis seulement passer à la suivante. Une sortie différente arrête la bascule ; pour recommencer, suivre « Reprendre après un arrêt », en fin de section. La commande d'import a tourné sur une copie locale de la base de production le 2026-10-29 (essai à blanc, puis import). Le 2026-10-29, en local, sur une base de test en WAL : le relevé de `NEXT_IMAGE` et `NEXT_VOLUME` sur un conteneur arrêté, la copie `VACUUM INTO` en lecture seule avec l'image `node:24.22.0-trixie-slim` de Next (copie hors WAL), la copie des photos, `docker cp -a` et l'effacement de `/tmp/next` dans l'image Laravel ont tourné. Les autres commandes `docker` ont seulement été relues : l'étape 1 les essaie sur le staging.

Variables, en plus de `P` et `URL` (voir « Lancer les commandes de cette page ») :

```sh
NEXT_WEB=<conteneur web de Next>
NEXT_WORKER=<conteneur worker de Next>
COPY=/root/golf-cutover
```

Les noms des conteneurs de Next changent à chaque déploiement. Les relever sur le serveur, dans l'onglet de chaque application Next de Dokploy ou avec :

```sh
docker ps --format '{{.Names}}  {{.Image}}' | grep -i golf
```

Relever ensuite l'image et le volume de Next, qui servent à l'étape 3 (répétition comprise). `docker inspect` lit aussi un conteneur arrêté :

```sh
NEXT_IMAGE="$(docker inspect --format '{{.Config.Image}}' "$NEXT_WEB")"
NEXT_VOLUME="$(docker inspect --format '{{range .Mounts}}{{if eq .Destination "/data"}}{{.Name}}{{end}}{{end}}' "$NEXT_WEB")"
echo "image=$NEXT_IMAGE volume=$NEXT_VOLUME"
```

La sortie nomme l'image de Next et un volume qui finit par `golf-data`. `image=` ou `volume=` vide : `NEXT_WEB` est faux.

### 1. Répétition sur le staging

Mainteneur. Faire les étapes 3, 4 et 6 avec `P` et `URL` du staging, sans arrêter Next (étape 2) : `VACUUM INTO` lit une image cohérente d'une base en service. Puis comparer à l'œil le staging à Next : accueil, handicaps (index de chaque joueur), trois rounds avec leur photo, Parcours, Importer. Le staging met en file les recherches de rating importées : son worker appelle Simulator Golf Tour, et l'IA si `ANTHROPIC_API_KEY` y est réglée.

### 2. Arrêter Next

Mainteneur, le jour J. `NEXT_IMAGE` et `NEXT_VOLUME` sont relevés (bloc des variables). Arrêter le web de Next dans Dokploy (Stop sur son application). Plus personne n'écrit ; le worker finit ce qui reste dans sa file. Lire la file jusqu'à ce qu'elle soit vide :

```sh
docker exec "$NEXT_WORKER" node -e "const { DatabaseSync } = require('node:sqlite'); const db = new DatabaseSync('/data/app.db', { readOnly: true }); console.log(db.prepare(\"select status, count(*) n from jobs where status in ('queued', 'running') group by status\").all())"
```

Attendu : `[]`. Une lecture de photo dure au plus 2 minutes ; relancer la commande jusqu'à `[]`. Arrêter ensuite le worker de Next dans Dokploy. Garder les deux applications et leur volume : c'est le retour arrière.

### 3. Copier la base et les photos

Mainteneur. La base Next est en WAL : copier `app.db` seul peut perdre les dernières écritures, et `golf:import-sqlite` refuse une base en WAL (« Base en WAL : copier par VACUUM INTO … »). `VACUUM INTO` écrit une copie complète, hors WAL, que la commande vérifie ; la base de Next est ouverte en lecture seule. `$COPY` ne doit contenir ni `app.db` ni `uploads` : `VACUUM INTO` refuse un fichier qui existe, et `cp` copierait les photos dans `uploads/uploads` (voir « Reprendre après un arrêt »). Le conteneur jetable tourne sous l'utilisateur `node` (uid 1000) de l'image Next, qui doit pouvoir écrire dans `$COPY` :

```sh
install -d -o 1000 -g 1000 -m 700 "$COPY"
docker run --rm -i -v "$NEXT_VOLUME:/data" -v "$COPY:/out" --entrypoint node "$NEXT_IMAGE" - <<'JS'
const { DatabaseSync } = require('node:sqlite');
const db = new DatabaseSync('/data/app.db', { readOnly: true });
db.exec("VACUUM INTO '/out/app.db'");
db.close();
const copy = new DatabaseSync('/out/app.db', { readOnly: true });
console.log('integrity_check', copy.prepare('PRAGMA integrity_check').get().integrity_check);
for (const { name } of copy.prepare("select name from sqlite_master where type = 'table' order by name").all()) {
    console.log(`${name}\t${copy.prepare(`select count(*) n from "${name}"`).get().n}`);
}
JS
```

Attendu : `integrity_check ok`, puis le nombre de lignes de chaque table. Noter ces comptes : le rapport de l'étape 4 doit donner les mêmes. Puis les photos :

```sh
docker run --rm -v "$NEXT_VOLUME:/data:ro" -v "$COPY:/out" --entrypoint cp "$NEXT_IMAGE" -a /data/uploads /out/uploads
ls "$COPY/uploads" | wc -l
```

Attendu : le nombre de photos, au moins le nombre de rounds avec photo. L'import vérifie chaque fichier référencé et nomme ceux qui manquent.

### 4. Importer

Mainteneur. Mettre la copie dans le conteneur `web` de la stack Laravel :

```sh
docker exec "$P-web-1" mkdir -p /tmp/next
docker cp -a "$COPY/app.db" "$P-web-1:/tmp/next/app.db"
docker cp -a "$COPY/uploads" "$P-web-1:/tmp/next/uploads"
```

Aucune erreur quand tout va bien (`docker cp` affiche seulement la taille copiée). `-a` garde le propriétaire des fichiers, l'uid 1000 de l'image Next, qui est aussi celui de l'utilisateur `app` du conteneur Laravel : il lit les copies et peut les effacer. Lancer l'essai à blanc. La base a déjà les joueurs de `INITIAL_PLAYERS`, créés par `migrate` : l'essai passe donc aussi `--force`, dans un terminal interactif (`docker exec -it`) :

```sh
docker exec -it "$P-web-1" php artisan golf:import-sqlite /tmp/next/app.db --uploads=/tmp/next/uploads --force --dry-run
```

La commande demande « Vider … avant l'import ? » en nommant chaque table non vide avec son compte. En production, elle nomme seulement les tables des joueurs initiaux (`players (3), player_aliases (3)` avec `INITIAL_PLAYERS=Gab,Manu,Sean`) ; sur le staging, après une répétition, les tables remplies par l'import précédent. Répondre `yes` seulement si elle ne nomme rien d'autre (l'essai n'écrit rien) ; sinon répondre `no`, la commande sort sur « Import abandonné. », et chercher d'où viennent ces lignes. Lire le rapport :

- chaque ligne du tableau a le même compte en `SQLite` et en `PostgreSQL`, et ces comptes sont ceux de l'étape 3 ;
- « Statistiques identiques : N / N », N étant le compte de `player_round_stats` ;
- « Photos lues : N », « Recherches de rating à remettre en file : N » ;
- la dernière ligne est « Essai à blanc : import annulé, rien n'a été écrit. ».

Une autre fin (« Statistiques différentes de la base SQLite : », « Photos manquantes : », « Ligne refusée : … », « Échec du recalcul des statistiques : … », « Base en WAL : … ») arrête la bascule : rien n'a été écrit, la commande sort en 1. Redémarrer le web et le worker de Next dans Dokploy (Start), corriger la donnée dans Next, puis suivre « Reprendre après un arrêt ».

Puis l'import réel, même commande sans `--dry-run` :

```sh
docker exec -it "$P-web-1" php artisan golf:import-sqlite /tmp/next/app.db --uploads=/tmp/next/uploads --force
```

La question nomme les mêmes tables et les mêmes comptes qu'à l'essai : répondre `yes` à cette condition seulement. Si une ligne a été écrite entre la question et l'import, la commande refuse (« Tables modifiées depuis la confirmation : … ») sans rien écrire. Le rapport est celui de l'essai, avec « Photos copiées : N » et « Recherches de rating remises en file : N », et finit par « Import terminé. ». Une dernière ligne « Import terminé ; échec de … » dit que l'import est fait, mais qu'une étape d'après n'a pas abouti : une recherche de rating à relancer depuis la page Parcours (« Relancer la recherche »), ou une ancienne photo restée sur le disque, nommée. Ne pas relancer l'import. Sans terminal (`--no-interaction`, ou `docker exec` sans `-it`), la réponse est non et la commande sort en 1 sur « Import abandonné. », sans rien écrire. Un Ctrl-C pendant l'import attend la fin de la transaction, puis arrête la commande.

Le worker Laravel traite ensuite les recherches remises en file, dans les minutes qui suivent (« Déployer une version », étape 6).

### 5. Basculer le domaine

Mainteneur. Dans Dokploy, retirer le domaine de l'application web de Next, puis le relier au service `web` de la stack Laravel (« Première installation », étape 5). Vérifier :

```sh
curl -si "$URL/api/version"
```

Attendu : `200` et la `version` déployée. Ouvrir ensuite `/login`, entrer le code, puis l'accueil, Handicaps (l'index de chaque joueur est celui de Next) et un round avec sa photo.

### 6. Effacer les copies

Mainteneur. La copie contient toutes les données du groupe :

```sh
docker exec -u root "$P-web-1" rm -rf /tmp/next
rm -rf "$COPY"
docker exec "$P-web-1" ls /tmp/next; ls "$COPY"
```

Attendu : deux erreurs, `ls: cannot access '/tmp/next': No such file or directory` et la même pour `$COPY`. `-u root` efface aussi des fichiers que `docker cp` aurait copiés sans `-a`, sous un autre propriétaire. Faire de même sur le staging après la répétition. Le conteneur `web` perd aussi `/tmp/next` à son prochain redéploiement : `/tmp` n'est pas sur un volume.

### 7. Retour arrière

Mainteneur, dans les 30 jours qui suivent la bascule. Dans Dokploy, retirer le domaine du service `web` Laravel et le relier de nouveau à l'application web de Next, puis redémarrer le web et le worker de Next (Start). Leur volume `golf-data` n'a pas bougé depuis l'étape 2 : Next repart sur la base d'avant la bascule. Ne rien toucher à la stack Laravel. Ce qui a été saisi dans Laravel depuis la bascule n'existe pas dans Next.

Après 30 jours, supprimer les applications Next et leur volume dans Dokploy.

### Reprendre après un arrêt

Mainteneur, avant toute reprise après un arrêt de la bascule ou de la répétition. Effacer les copies d'une tentative précédente, sur l'hôte et dans le conteneur :

```sh
rm -rf "$COPY/app.db" "$COPY/uploads"
docker exec -u root "$P-web-1" rm -rf /tmp/next
ls -A "$COPY"; docker exec "$P-web-1" ls /tmp/next
```

Attendu : rien pour `ls -A "$COPY"` (ou `No such file or directory` si l'étape 6 l'a effacé : l'étape 3 le recrée), puis `ls: cannot access '/tmp/next': No such file or directory`. Repartir ensuite de l'étape 2, ou de l'étape 3 pour une répétition, qui n'arrête pas Next.

## Fermer toutes les sessions

« Se déconnecter » ferme la session d'un seul navigateur. Pour fermer toutes les sessions de tous les appareils, changer `APP_PASSCODE` dans Dokploy, puis Deploy. La session garde une empreinte du code, et plus aucune ne correspond (ADR 0007). Chacun ressaisit le nouveau code, l'ancien est refusé.

Changer `APP_KEY` ferme aussi toutes les sessions, mais rend illisible tout ce que la clé chiffre. Le réserver à une clé compromise.

Essayé sur la stack locale le 2026-10-28 : après le redéploiement avec un nouveau code, une session ouverte avant est renvoyée vers `/login`, l'ancien code est refusé et le nouveau accepté.
