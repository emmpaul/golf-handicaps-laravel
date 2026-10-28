# Déploiement

L'application tourne sur un hôte Docker piloté par [Dokploy](https://dokploy.com), en projet compose. La stack est `docker-compose.yml`, à la racine du dépôt. Elle lance l'image que la CI publie sur GitHub Container Registry, `ghcr.io/emmpaul/golf-handicaps-laravel`. Les décisions sont dans l'ADR 0008.

## La stack

| Service   | Rôle                                                                                                   | Sain quand                              |
| --------- | ------------------------------------------------------------------------------------------------------ | --------------------------------------- |
| `pgsql`   | PostgreSQL 18, données dans le volume `pgdata`                                                         | `pg_isready` répond                     |
| `migrate` | `php artisan migrate --force`, puis `golf:initial-players`, puis `golf:recompute-stats`, puis s'arrête | sort en 0 (il reste alors `Exited (0)`) |
| `web`     | FrankenPHP sur le port 8080, sans port publié                                                          | `/up` répond 200                        |
| `worker`  | `php artisan queue:work`, relancé chaque heure par `--max-time=3600`                                   | son battement a moins de 300 s          |
| `backup`  | `pg_dump` au démarrage puis toutes les 24 h dans le volume `backups`                                   | un dump réussi a moins de 26 h          |

`web` et `worker` démarrent seulement après la sortie en 0 de `migrate`. Si une migration, la création des joueurs initiaux ou le recalcul de l'historique d'index échoue, ils ne démarrent pas sur la nouvelle image et restent sur `Created`. Lire alors `docker logs "$P-migrate-1"`, corriger, redéployer. Ce que deviennent les conteneurs de l'ancienne version pendant ce temps reste à observer sur le staging. Ils partagent le volume `storage`, monté sur `storage/app`. Les journaux partent sur la sortie d'erreur des conteneurs (`docker logs`, onglet Logs de Dokploy). Aucun fichier de journal ne grossit dans un conteneur.

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

Toutes les commandes de cette page ont été lancées telles quelles sur la stack locale le 2026-10-28, restauration comprise. Celles qui ne marchent que sur le serveur le disent.

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

| Variable               | Exemple                    | Rôle                                                                                                                                                                                                                                                              |
| ---------------------- | -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `IMAGE_TAG`            | `main`, `v0.1.0`           | Tag de l'image. `main` par défaut.                                                                                                                                                                                                                                |
| `APP_KEY`              | `base64:…`                 | Obligatoire, compose refuse de démarrer sans. Chiffre les sessions.                                                                                                                                                                                               |
| `APP_PASSCODE`         | `birdie-2026`              | Code d'accès partagé. Sans lui, `/up` répond 500 et `web` n'est jamais sain (ADR 0007).                                                                                                                                                                           |
| `APP_URL`              | `https://golf.example.org` | Adresse publique, en `https`.                                                                                                                                                                                                                                     |
| `DB_PASSWORD`          | une chaîne aléatoire       | Obligatoire. Lu une seule fois, à la création du volume `pgdata` (voir plus bas).                                                                                                                                                                                 |
| `TRUSTED_PROXIES`      | `10.0.1.0/24`              | Sous-réseau du proxy de Dokploy (étape 6). Jamais `*`.                                                                                                                                                                                                            |
| `INITIAL_PLAYERS`      | `Gab,Manu,Sean`            | Facultatif. Joueurs créés par `migrate` sur une base sans joueur, chacun avec son nom en majuscules pour alias. Sans effet dès qu'un joueur existe. Un nom en double, sans tenir compte de la casse, fait échouer `migrate` : `web` et `worker` ne démarrent pas. |
| `APP_NAME`             | `Handicaps golf`           | Facultatif, c'est le défaut.                                                                                                                                                                                                                                      |
| `DB_DATABASE`          | `golf`                     | Facultatif, c'est le défaut. Les commandes de cette page supposent `golf`.                                                                                                                                                                                        |
| `DB_USERNAME`          | `golf`                     | Facultatif, c'est le défaut.                                                                                                                                                                                                                                      |
| `LOG_LEVEL`            | `info`                     | Facultatif, c'est le défaut.                                                                                                                                                                                                                                      |
| `APP_DISPLAY_TIMEZONE` | `America/Montreal`         | Facultatif, c'est le défaut.                                                                                                                                                                                                                                      |

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

Avec `IMAGE_TAG=main`, si `/api/version` montre encore l'ancien `commit`, l'hôte a gardé l'ancienne image `:main` en cache. Tirer la nouvelle sur le serveur, puis redéployer :

```sh
docker pull ghcr.io/emmpaul/golf-handicaps-laravel:main
```

Serveur seulement. Le comportement de Dokploy face à un tag qui bouge reste à confirmer sur le staging.

Changer une variable demande un redéploiement, pas un simple redémarrage. `docker restart` relance le conteneur avec l'environnement qu'il avait à sa création ; seul le redéploiement recrée les conteneurs avec les nouvelles valeurs.

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

## Fermer toutes les sessions

« Se déconnecter » ferme la session d'un seul navigateur. Pour fermer toutes les sessions de tous les appareils, changer `APP_PASSCODE` dans Dokploy, puis Deploy. La session garde une empreinte du code, et plus aucune ne correspond (ADR 0007). Chacun ressaisit le nouveau code, l'ancien est refusé.

Changer `APP_KEY` ferme aussi toutes les sessions, mais rend illisible tout ce que la clé chiffre. Le réserver à une clé compromise.

Essayé sur la stack locale le 2026-10-28 : après le redéploiement avec un nouveau code, une session ouverte avant est renvoyée vers `/login`, l'ancien code est refusé et le nouveau accepté.
