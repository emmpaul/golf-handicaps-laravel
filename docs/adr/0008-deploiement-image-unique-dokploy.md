# 0008. Déploiement par image unique et stack Dokploy

## Statut

Acceptée, 2026-10-28 (changement OpenSpec `production-deployment`). Remplacée en partie par [0015](0015-mode-worker-octane-frankenphp.md), 2026-10-29 : la décision « FrankenPHP en mode classique » (D1). Le reste reste en vigueur.

## Contexte

Jusqu'ici, l'application ne tournait que sur le poste du mainteneur. Le serveur cible est celui de l'application Next.js : un hôte Docker piloté par Dokploy, dont le proxy Traefik termine le TLS. Les changements suivants ont besoin d'un staging pour être essayés sur téléphone, et le cutover (`sqlite-import-command`) a besoin d'une stack de production déjà éprouvée.

L'application a quatre rôles : servir le web, traiter la file (import par l'IA, recherche SGT), appliquer les migrations et garder la base PostgreSQL. Les données d'un groupe d'amis tiennent dans une base de quelques mégaoctets, mais en perdre une saison serait grave. Le trafic se compte en quelques personnes. La couche 12 du squelette (ADR 0005) restait à remplir par le premier déploiement réel.

## Alternatives

- **Octane ou le mode worker de FrankenPHP** : un paquet de plus, et un état partagé d'une requête à l'autre (singletons, configuration) à surveiller, pour un trafic de quelques personnes.
- **nginx et php-fpm** : deux processus à superviser dans un conteneur, ou deux conteneurs pour un seul rôle.
- **Caches Laravel construits au build** : `config:cache` figerait les variables vides du build, et changer une variable demanderait une nouvelle image.
- **Aucun cache Laravel** : chaque requête relit tous les fichiers de `config/` et de routes. Le coût est faible ici, mais `optimize` est le défaut du squelette et ne coûte qu'une seconde au démarrage.
- **Migrations au démarrage du web, comme Next** : deux réplicas les lanceraient en même temps, et un web dont la migration échoue redémarrerait en boucle sans bloquer le worker.
- **Sauvegardes de volume de Dokploy vers S3** : elles demandent un stockage externe à configurer, et copient les fichiers de PostgreSQL à chaud au lieu d'un dump cohérent.
- **Commande de sauvegarde documentée seulement** : personne ne la lance à heure fixe, et rien ne vire au rouge quand on l'oublie.
- **Reconstruire l'image dans le job de publication** (`docker/build-push-action`) : l'image publiée ne serait plus celle que le test de fumée a vue saine, et le job ajouterait deux actions tierces.
- **Publication multi-architecture, comme Next** : aucun serveur `arm64` ne la demande, et l'émulation allonge le build.

## Décision

- **FrankenPHP en mode classique** (remplacée par 0015 : mode worker par Laravel Octane). Le web tourne sous `frankenphp run` avec `docker/Caddyfile` : `auto_https off`, `admin off`, écoute HTTP sur `:8080`. Caddy sert les fichiers de `public/` sans PHP et envoie toute autre adresse à `index.php`. Les assets de `/build/*` portent une empreinte et partent avec `Cache-Control: public, max-age=31536000, immutable`.
- **Une image, quatre étapes.** Le `Dockerfile` part de `dunglas/frankenphp` en PHP 8.5, épinglé par tag exact et digest. L'étape `base` ajoute les extensions (`pdo_pgsql`, `pcntl`, `intl`, `opcache`), `php.ini-production` et l'utilisateur `app` (uid 1000). L'étape `vendor` lance `composer install --no-dev` depuis `composer.json` et `composer.lock` seuls. L'étape `assets` compile Vite avec Node 22 et PNPM, et seul `public/build` en sort. L'étape `runtime` assemble le code, `vendor` et `public/build`, pose `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, et inscrit le sha du commit dans `APP_COMMIT`. La même image sert le web, le worker et les migrations selon la commande lancée. Elle ne contient aucun secret.
- **Caches au démarrage.** `docker/entrypoint.sh` lance `php artisan optimize`, puis la commande du service. L'environnement de Dokploy est alors connu, et changer une variable demande un redéploiement, pas une image.
- **Stack `docker-compose.yml` en cinq services.** `pgsql` (PostgreSQL 18, volume `pgdata`, `pg_isready`), `migrate` (`php artisan migrate --force`, une fois par déploiement), `web` (sain quand `/up` répond 200), `worker` (`queue:work --tries=2 --sleep=3 --timeout=420 --max-time=3600`, sain tant que son battement a moins de 300 s) et `backup`. `web` et `worker` ne démarrent qu'après la sortie en 0 de `migrate`. Ils partagent le volume `storage`. Aucun port n'est publié : Dokploy relie son domaine au port 8080 de `web`. `APP_KEY` et `DB_PASSWORD` sont exigés par compose. `APP_PASSCODE` ne l'est pas, pour que son absence fasse tomber `/up` (ADR 0007).
- **Sauvegarde par un service de la stack.** `backup` part de l'image de `pgsql`, donc avec un `pg_dump` de la même version majeure. Il fait un `pg_dump -Fc` au démarrage puis toutes les 24 heures dans le volume `backups`. Le dump s'écrit sous un nom `.partial` et ne prend son nom `golf-<horodatage>.dump` que si `pg_dump` sort en 0. Les dumps de plus de 14 jours sont supprimés. Le service devient malade quand aucun dump réussi n'a moins de 26 heures. Son script vit dans `docker-compose.yml`, pour que Dokploy puisse lire la stack seule.
- **La CI publie ce qu'elle a testé.** Le job `image` construit l'image (`make image GIT_COMMIT=<sha>`), lance la stack (`make image-smoke`) et garde l'image en artefact. Le job `publish` ne tourne que sur un push vers `main` ou un tag `vX.Y.Z`, après la réussite de tous les contrôles. Il charge cet artefact, calcule les tags par `make image-tags`, puis pousse sur `ghcr.io/emmpaul/golf-handicaps-laravel`. `main` donne `:main` et `:<sha>`. `vX.Y.Z` donne `:vX.Y.Z`, `:latest` et `:<sha>`, et seulement si `version.txt` vaut `X.Y.Z`. L'image est `linux/amd64` seulement. Le job `publish` est le seul à écrire des paquets, et il ne restaure aucun cache.

## Conséquences

- Le staging suit `:main` et la production des tags `vX.Y.Z`. Revenir en arrière consiste à remettre l'ancien `IMAGE_TAG` et à redéployer. Une migration irréversible demande en plus de restaurer le dump pris avant le déploiement (`docs/deployment.md`).
- Une migration en échec laisse l'ancienne version en place : `migrate` sort en erreur, et `web` et `worker` ne démarrent pas sur la nouvelle image.
- Les dumps restent sur le même disque que la base. Une panne de disque perd les deux. Les copier hors du serveur reste un geste manuel, décrit dans `docs/deployment.md`.
- Docker ne redémarre pas un conteneur `unhealthy`. Un worker figé se voit dans Dokploy, mais quelqu'un doit le relancer.
- Le seuil de 300 s du worker vit à deux endroits, le healthcheck de `docker-compose.yml` et `docs/deployment.md`. Un commentaire les lie.
- Monter les versions de FrankenPHP, PostgreSQL, Composer ou Node demande de changer un digest, que la revue voit.
- Chaque PR de code paie un build d'image et un démarrage de la stack en CI, et garde un artefact `image.tar` pendant un jour.

## Couche 12 sur le serveur

> **À remplir après le staging (tâche 8.4 de `production-deployment`).** Ce qui a marché sur Dokploy : réseau rejoint par la stack (« Isolated Deployment » ou réseau externe `dokploy-network`), valeur de `TRUSTED_PROXIES` retenue, prise en compte de `depends_on` et des healthchecks par Dokploy, version de Docker Engine de l'hôte, date du premier déploiement réussi.
