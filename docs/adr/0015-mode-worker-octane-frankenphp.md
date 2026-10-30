# 0015. Mode worker par Laravel Octane et FrankenPHP

## Statut

Acceptée, 2026-10-29 (changement OpenSpec `optimize-performance`). Remplace la décision D1 de l'ADR 0008 (FrankenPHP en mode classique).

## Contexte

L'ADR 0008 avait écarté Octane et le mode worker : un paquet de plus et un état partagé entre requêtes à surveiller, pour un trafic de quelques personnes. Depuis le 2026-10-29, l'application tourne en production sur `golf.emmanuelpaul.com`, et la mesure de référence du même jour (`docs/performance.md`) montre où part le temps. Les données ne pèsent rien : moins de 1,5 Ko de props par page. Mais chaque requête relance `bootstrap/app.php` et les fournisseurs de services. Derrière Cloudflare, un fichier statique servi par le même Caddy répond en 50 ms (médiane), `/up` en 83 ms, une page protégée en 130 à 200 ms. Le démarrage de Laravel coûte donc 30 à 45 ms sans session, et 80 à 150 ms sur une page protégée.

L'objectif du changement est de diviser au moins par deux la part du serveur (`app` dans l'en-tête `Server-Timing`) d'une page protégée.

## Alternatives

- **Garder le mode classique** et gagner quelques millisecondes ailleurs (session, requêtes) : le plus gros poste, le démarrage, reste entier à chaque requête.
- **Mode worker de FrankenPHP sans Octane** : FrankenPHP sait garder un script en mémoire, mais Laravel n'a pas de boucle de worker à lui. Il faudrait réécrire ce que fait Octane : cloner l'application à chaque requête et remettre à zéro session, authentification, configuration, URL et gestionnaires.
- **Octane avec Swoole ou RoadRunner** : il faut changer d'image de base (extension Swoole, ou binaire `rr` à côté de PHP), alors que l'image part déjà de `dunglas/frankenphp`.

## Décision

- **`laravel/octane` (2.22) sert le web par FrankenPHP en mode worker.** Le `CMD` de l'image lance `php artisan octane:frankenphp --host=0.0.0.0 --port=8080 --caddyfile=/etc/caddy/Caddyfile --workers=auto --max-requests=500`. Le service `web` de `docker-compose.yml` ne déclare pas de `command` : chaque image lance sa propre commande, ce qui garde le retour arrière par `IMAGE_TAG` seul. Octane 2.22 exige FrankenPHP 1.13.0 ou plus, et l'image de base est en 1.13.0. `OCTANE_SERVER=frankenphp` est fixé dans l'image, et c'est aussi le défaut de `config/octane.php`.
- **`docker/Caddyfile` suit le gabarit d'Octane et garde ses réglages.** Le bloc global garde `auto_https off` et `admin off`, et déclare le `worker` de FrankenPHP sur `public/frankenphp-worker.php`. Le site garde `encode zstd gzip` et `Cache-Control: public, max-age=31536000, immutable` sur `/build/*`. `php_server` sert les fichiers de `public/` (`favicon.ico`, `robots.txt`, `build/`) sans PHP, et envoie toute autre adresse au worker. Octane fournit le chemin de `public/`, le port et le nombre de workers par des variables `{$…}`.
- **Le script du worker est posé au build.** L'étape `runtime` du `Dockerfile` copie le gabarit d'Octane dans `public/frankenphp-worker.php`. `octane:frankenphp` le copierait au démarrage, mais l'utilisateur `app` ne peut pas écrire dans `public/`.
- **Rien d'une requête ne passe à la suivante.** Octane clone l'application à chaque requête et lance les écouteurs `RequestReceived` de `config/octane.php`, qui vident la session, l'authentification, les témoins en file et le cache `array`. Le code de l'application ne garde aucun état par requête : pas de propriété statique, et une seule liaison du conteneur, `QueryClock`, en `scoped()`. `Passcode::opened()` lit la session de la requête. `AnonymousDatabaseSessionHandler` hérite de `DatabaseSessionHandler`, qu'Octane relie au conteneur de chaque requête. `tests/Feature/WorkerRequestIsolationTest.php` fait passer deux navigateurs dans un même worker d'Octane et vérifie qu'aucune session, aucun flash et aucune erreur de validation ne passe de l'un à l'autre, et qu'un code changé ferme les sessions ouvertes.
- **Le worker de file et `migrate` ne changent pas.** Ils lancent toujours `php artisan` en ligne de commande.

## Conséquences

- Le démarrage de Laravel ne se paie plus qu'une fois par worker, puis toutes les 500 requêtes. Sur la stack locale (`make image-smoke`), `/up` répond avec `app;dur=4` et `/rounds` avec `app;dur=5` à `7`. La mesure complète, avant et après, est dans `docs/performance.md`.
- Un nouveau code d'application ne doit garder aucun état de requête dans une propriété statique ou un `singleton()`. Ce qui dépend de la requête passe par `scoped()`, par la requête, ou par la liste `flush` de `config/octane.php`. Une fuite se voit dans `WorkerRequestIsolationTest` si elle touche la session, le flash, les erreurs ou le code d'accès. Une fuite ailleurs passe inaperçue des tests, d'où la relecture de chaque `static` et `singleton(` en revue.
- `--max-requests=500` borne une fuite de mémoire. FrankenPHP remplace un worker tombé sans arrêter le service. Le nombre de workers (`auto`, deux par cœur pour FrankenPHP) se relit dans `docker stats` après le déploiement. S'il manque de mémoire, `--workers=2` dans le `CMD` du `Dockerfile` le fixe, par une nouvelle image.
- L'API d'administration de Caddy reste coupée. `octane:reload` et `octane:stop` ne servent donc pas : un nouveau code arrive par une nouvelle image et un redéploiement, qui recrée le conteneur. `docker stop` arrête `web` en une seconde, avec le code 0.
- `config/octane.php` est un fichier de plus à suivre aux montées de version d'Octane.
- Retour arrière : la commande de `web` vient de l'image, pas de `docker-compose.yml`. Remettre l'ancien `IMAGE_TAG` suffit : une image d'avant ce changement lance son propre `CMD`, `frankenphp run --config /etc/caddy/Caddyfile`, en mode classique (`docs/deployment.md`, « Retour arrière du mode worker »). Aucune migration ne change, et les sessions restent valides dans les deux sens.
