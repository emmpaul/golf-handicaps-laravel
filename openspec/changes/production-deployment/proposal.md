# Proposal

## Why

L'application Laravel ne tourne que sur le poste du mainteneur. Rien ne la construit en image, rien ne la publie, et aucun serveur ne l'a encore servie derrière le proxy de Dokploy. Les changements suivants (joueurs, import IA, recherche SGT) ont besoin d'un staging pour être essayés sur téléphone. Le cutover (`sqlite-import-command`) a besoin d'une stack de production déjà éprouvée. Ce changement correspond à l'entrée 4 de `ROADMAP.md`. Sa dépendance, l'entrée 3 (`app-access`), est faite et archivée : la page protégée et `/up` existent.

Il remplit aussi la couche 12 du squelette (ADR 0005). Il reprend enfin ce qu'`app-access` a laissé de côté : HSTS, témoin de session `Secure` et valeur de production de `TRUSTED_PROXIES`.

## What Changes

- **Image de production unique** construite par un `Dockerfile` multi-étapes : FrankenPHP (PHP 8.5), assets Vite compilés dans l'image, `composer install --no-dev`, utilisateur non root, port 8080. La même image sert le web, le worker et les migrations. Elle ne contient aucun secret. Le sha du commit y est inscrit (`APP_COMMIT`) pour `GET /api/version`. Les caches Laravel (`optimize`) sont construits au démarrage du conteneur, une fois l'environnement connu.
- **Stack `docker-compose.yml` de production** pour Dokploy : `migrate` (one-shot, `php artisan migrate --force`), `web` (healthcheck `/up`), `worker` (`queue:work --tries=2`, healthcheck du battement), `pgsql` 18 (volume `pgdata`, `pg_isready`) et `backup`. Les volumes sont `pgdata`, `storage` (monté sur web et worker) et `backups`. Le tag de l'image vient de `IMAGE_TAG`. `web` et `worker` attendent que `migrate` ait réussi.
- **Publication GHCR par la CI** (décision du mainteneur, 2026-10-28) : chaque push sur `main` publie `ghcr.io/emmpaul/golf-handicaps-laravel:main` et `:<sha>`, que le staging suit. Un tag `vX.Y.Z` publie `:vX.Y.Z`, `:latest` et `:<sha>` pour la production. La publication n'a lieu que si tous les contrôles de l'exécution sont verts. Un tag qui ne correspond pas à `version.txt` ne publie rien. L'image est `linux/amd64` seulement (décision du mainteneur, 2026-10-28).
- **Test de fumée de l'image en CI** : sur toute PR de code, la CI construit l'image et lance la stack avec `docker compose up --wait`. Le contrôle échoue si `web` ou `worker` n'atteint pas l'état sain, ou si `/up` et `/login` ne répondent pas.
- **Santé du worker** (décision du mainteneur, 2026-10-28 : ici plutôt qu'au changement 9) : le worker écrit un battement dans le `/tmp` de son conteneur à chaque tour de boucle et après chaque job. Le healthcheck le déclare malade au-delà de 5 minutes sans battement.
- **Sauvegardes PostgreSQL** (décision du mainteneur, 2026-10-28) : le service `backup` fait un `pg_dump` au démarrage puis chaque jour dans le volume `backups`, et garde 14 jours. Son healthcheck vire au rouge quand le dernier dump a plus de 26 heures. La restauration est documentée, puis essayée sur staging.
- **TLS en production** : en-tête `Strict-Transport-Security` sur les réponses servies en HTTPS. Témoin de session `Secure` par défaut quand `APP_ENV=production`. Valeur de `TRUSTED_PROXIES` documentée pour le réseau du proxy de Dokploy.
- **Journaux** sur la sortie d'erreur du conteneur en production (`LOG_CHANNEL=stderr`). Leur rotation est laissée à Docker.
- **Makefile** : cibles `image` (construit l'image locale) et `image-smoke` (lance la stack de production en local et attend l'état sain). La CI passe par elles.
- **Documentation** : `docs/deployment.md` couvre la première installation Dokploy, les variables, le déploiement d'une version, la vérification par `/api/version`, la santé du worker, la sauvegarde et la restauration, le retour arrière et la fermeture de toutes les sessions. S'y ajoutent l'ADR 0008 (déploiement), la couche 12 notée dans l'ADR 0005, `ARCHITECTURE.md`, `ROADMAP.md` et `CHANGELOG.md`.
- **Staging Dokploy** monté par le mainteneur à partir de `docs/deployment.md`, image `:main`, avec un domaine HTTPS. La restauration d'une sauvegarde y est essayée une fois.

### Hors périmètre

- La stack de production sur le domaine actuel : elle se monte au cutover (`sqlite-import-command`). La procédure est écrite ici, mais rien ne remplace l'application Next.js avant ce changement.
- L'image `arm64`, le planificateur (`schedule:run`) et Redis : aucune fonctionnalité ne les demande.
- Le déploiement automatique depuis la CI (webhook Dokploy) : le mainteneur redéploie à la main dans Dokploy, comme pour l'application Next.js.
- La copie des sauvegardes hors du serveur : à la main, ou par les sauvegardes de volume de Dokploy, décrites dans la doc sans être configurées par le dépôt.
- Octane et le mode worker de FrankenPHP : FrankenPHP sert l'application en mode classique, une requête par exécution PHP.
- L'extension Imagick, qui arrive avec `scorecard-ai-import`.

## Requirements des specs Next

| Spec Next | Requirement | Sort | Raison ou adaptation |
| --- | --- | --- | --- |
| `worker-health` | Signal de vie du worker | Porté | Le cas « en attente des migrations » devient « le worker ne démarre qu'après le service `migrate` » : Laravel n'a pas de boucle d'attente des migrations, la stack ordonne les services. |
| `worker-health` | Worker vu malade quand il se fige | Porté | « Lecture OCR puis complétion par IA » devient « lecture par l'IA » (OCR supprimé, décision 2 du port). |
| `ci-pipeline` | Publication multi-arch native | Remplacé | `amd64` seul (décision du mainteneur). La publication suit `main` et les tags `vX.Y.Z` au lieu des releases de release-please (ADR 0005). |
| `ci-pipeline` | PR fusionnable quand des jobs sont sautés (image cassée) | Porté | Devient le job `image` de la CI Laravel : build et `docker compose up --wait` sur chaque PR de code. |
| `app-access` (Next) | Témoin de session `Secure` | Porté | Le témoin est `Secure` dès que `APP_ENV=production`, sans dépendre d'une variable de plus. |

## Capabilities

### New Capabilities

- `deployment` : l'image de production, la stack Dokploy (services, ordre de démarrage, healthchecks, volumes), la configuration par l'environnement, les sauvegardes et leur restauration, le retour arrière.
- `worker-health` : le signal de vie du worker de file et le healthcheck qui le rend visible à l'orchestrateur.

### Modified Capabilities

- `ci-pipeline` : ajoute le test de fumée de l'image sur les PR de code et la publication de l'image sur GHCR depuis `main` et les tags `vX.Y.Z`.
- `web-request-security` : ajoute HSTS sur les réponses HTTPS et le témoin de session `Secure` en production.

## Impact

- **Code** : nouveau `Dockerfile`, `.dockerignore`, `docker-compose.yml` de production, configuration FrankenPHP, script de démarrage du conteneur, classe de battement du worker et son enregistrement, `AddSecurityHeaders` (HSTS), `config/session.php` (défaut de `secure`).
- **CI** : `.github/workflows/ci.yml` gagne les jobs `image` et `publish`, et se déclenche aussi sur les tags `v*`. Le job `publish` est le seul qui écrit des paquets (`packages: write`).
- **Makefile** : cibles `image` et `image-smoke`. `docker-compose.yml`, `Dockerfile` et `.dockerignore` comptent comme du code pour `make changed-code`.
- **Tests** : Pest pour le battement du worker, HSTS et le témoin `Secure`. Le test de fumée de la CI couvre l'image et la stack, qu'aucun test Pest ne peut démarrer.
- **Dépendances** : aucun paquet Composer ni PNPM. Images de base `dunglas/frankenphp` et `postgres:18-alpine`, épinglées par digest.
- **Systèmes** : GHCR (paquet public, comme le dépôt), un projet Dokploy de staging, un domaine HTTPS de staging.
- **Confidentialité** : aucun secret dans l'image ni dans le dépôt. Les variables vivent dans l'environnement Dokploy. Les sauvegardes contiennent toutes les données du groupe et restent sur le serveur, dans le volume `backups`. Les journaux sur stderr ne contiennent ni le code d'accès ni le contenu des sessions.
- **Retour arrière** : annuler la pull request retire l'image, la stack et la publication. Aucune migration et aucune donnée ne sont touchées. Sur un serveur, revenir à l'image précédente se fait en remettant l'ancien `IMAGE_TAG` dans Dokploy et en redéployant, comme le décrit `docs/deployment.md`.
