# Performance

Cette page garde les mesures de vitesse de l'application déployée, prises toujours de la même façon, pour comparer deux versions chiffre contre chiffre. La méthode vient du changement OpenSpec `optimize-performance` (design D2).

## Méthode

La commande `golf:perf-probe` mesure une adresse en lecture seule :

- un `GET /login` pour le témoin XSRF et la version Inertia, puis un seul `POST /login` avec le code d'accès, puis des `GET` seulement. Aucun round, joueur, import ni rating n'est créé, modifié ou supprimé ;
- onze adresses en page complète : `/robots.txt`, `/up`, `/api/version` et `/login` sans témoin, puis `/`, `/handicaps`, `/strokes`, `/rounds`, `/players`, `/courses` et `/import` avec la session ouverte. Les cinq sections de la barre (`/rounds`, `/handicaps`, `/strokes`, `/players`, `/import`) sont mesurées une seconde fois en visite Inertia (`X-Inertia: true`) ;
- chaque adresse est demandée 20 fois (option `RUNS`), chaque essai sur une connexion neuve ;
- la valeur retenue est `starttransfer − appconnect` : le temps entre la fin de la poignée de main TLS et le premier octet, donc un aller-retour réseau plus le temps du serveur. En HTTP sans TLS (stack locale), elle compte aussi l'ouverture de la connexion ;
- pour chaque ligne : médiane, 90e centile et minimum de ce temps (rang le plus proche sur les essais triés), médianes de `app` et `db-count` lues dans l'en-tête `Server-Timing` (« — » sans l'en-tête), médiane de la taille reçue, compressée : chaque requête envoie `Accept-Encoding: gzip`, comme un navigateur.

La commande affiche une ligne par adresse au début de sa mesure, puis le tableau complet seulement à la fin : compter environ 2 minutes pour 20 essais contre la production.

Le code d'accès vient de la variable `PERF_PASSCODE`, ou la commande le demande sans l'afficher. Le code, le témoin de session et le jeton XSRF restent en mémoire : ni la sortie, ni le rapport ne les contiennent. Le rapport est un tableau Markdown écrit dans `reports/perf/<date>-<hôte>.md` (`reports/` est ignoré par git). Les mesures qu'on garde sont recopiées à la main plus bas.

La commande tourne dans le conteneur de Sail. L'adresse est donc vue depuis ce conteneur : `http://localhost` vise l'application de développement, et `http://host.docker.internal:8080` vise la stack de production locale de `make image-smoke`, publiée sur le port 8080 de l'hôte.

## Lancer la mesure

Contre la production, depuis le poste du mainteneur, toujours le même. La commande demande le code d'accès :

```sh
make perf-probe URL=https://golf.emmanuelpaul.com
```

Pour fournir le code sans question, le passer par l'environnement du seul appel. La cible le transmet au conteneur par son nom (`exec -e PERF_PASSCODE`), sans en écrire la valeur :

```sh
read -rs PERF_PASSCODE && export PERF_PASSCODE
make perf-probe URL=https://golf.emmanuelpaul.com
unset PERF_PASSCODE
```

Contre l'application de développement (`make up`), en 3 essais par page :

```sh
make perf-probe URL=http://localhost RUNS=3
```

Un code refusé arrête la commande en erreur avec « Code d'accès refusé », avant toute page protégée. La colonne « Statut » doit valoir 200 partout : un 302 sur une page protégée veut dire que la session s'est perdue en route.

Cloudflare peut retirer `Server-Timing` des réponses : les colonnes `app` et `db-count` affichent alors « — ». La même mesure contre la stack locale lit l'en-tête sans Cloudflare.

## Mesure de référence (2026-10-29, version 0.1.0 en production)

Mesure faite depuis le poste du mainteneur (Montréal, Cloudflare YYZ) par `curl`, avant la commande, avec la même méthode : connexion neuve à chaque essai, une seule ouverture de session, puis des GET seulement, `time_starttransfer − time_appconnect`, 20 essais par page.

| Adresse        | Médiane | 90e centile | Min    | Ce qui répond                                  |
| -------------- | ------- | ----------- | ------ | ---------------------------------------------- |
| `/robots.txt`  | 50 ms   | 121 ms      | 42 ms  | Caddy seul, fichier statique (plancher réseau) |
| `/up`          | 83 ms   | 104 ms      | 61 ms  | Laravel, sans session                          |
| `/api/version` | 94 ms   | 138 ms      | 58 ms  | Laravel, sans session                          |
| `/login`       | 109 ms  | 146 ms      | 87 ms  | Laravel, session créée                         |
| `/`            | 132 ms  | 228 ms      | 106 ms | page complète, session                         |
| `/handicaps`   | 134 ms  | 230 ms      | 103 ms |                                                |
| `/strokes`     | 146 ms  | 213 ms      | 116 ms |                                                |
| `/rounds`      | 204 ms  | 352 ms      | 122 ms |                                                |
| `/players`     | 165 ms  | 223 ms      | 119 ms |                                                |
| `/courses`     | 171 ms  | 223 ms      | 118 ms |                                                |
| `/import`      | 131 ms  | 154 ms      | 109 ms |                                                |

Autres constats du même jour :

- Réponses Inertia (`X-Inertia`) compressées : 455 o (`/players`) à 1 401 o (`/courses`). Pages complètes : 2,3 à 3,3 Ko compressés, 14 Ko décompressés pour `/`.
- Assets : `Cache-Control: public, max-age=31536000, immutable`, mis en cache par Cloudflare (`cf-cache-status: HIT` au second essai). Le paquet commun (`wayfinder-*.js`, React et Inertia) pèse 122 Ko compressé et 379 Ko décompressé.
- Navigateur (Playwright, profil iPhone, WebKit), premier chargement après ouverture de session : TTFB 400 ms, premier rendu 580 ms, FCP 820 ms. Les 10 WOFF2 préchargées (98 Ko) partent à 415 ms. Les WOFF effectivement utilisées partent ensuite : 3 à 780 ms, 2 encore à 2,9 s et 3,4 s (≈ 85 Ko, non préchargées).
- Navigation entre onglets (clic sur un lien de la barre jusqu'au rendu) : `/rounds` 317 ms (requête de 292 ms, puis le code de la page), `/handicaps` 158 ms, `/` 189 ms, `/rounds` à nouveau 166 ms. Tout ce temps est l'aller-retour Inertia.
- `/favicon.ico` : 302 vers `/login`, avec `Set-Cookie` et une session créée en base à chaque demande.
- Cloudflare injecte `static.cloudflareinsights.com/beacon.min.js` et envoie des balises `/cdn-cgi/rum`.

Lecture : le plancher réseau (Cloudflare jusqu'à l'origine) vaut environ 50 ms. Laravel sans session en ajoute 30 à 45, une page protégée 80 à 150. Les données ne pèsent rien. Le gain est donc dans le démarrage de Laravel, les allers-retours de navigation et les octets de polices.

## Mesure « avant » en production (2026-10-29)

Mesure de la version en production avant ce changement, prise le 2026-10-29 avec `golf:perf-probe`, 20 essais par page, depuis le poste du mainteneur et à travers le conteneur de Sail (`make perf-probe URL=https://golf.emmanuelpaul.com`). C'est la mesure de comparaison de la mesure « après » (design D2).

| Adresse        | Visite  | Statut | Médiane (ms) | 90e centile (ms) | Min (ms) | app (ms) | db-count | Taille (o) |
| -------------- | ------- | ------ | -----------: | ---------------: | -------: | -------: | -------: | ---------: |
| `/robots.txt`  | page    | 200    |          136 |              556 |       43 |        — |        — |         24 |
| `/up`          | page    | 200    |          172 |              633 |       73 |        — |        — |       3315 |
| `/api/version` | page    | 200    |          164 |              338 |      105 |        — |        — |         71 |
| `/login`       | page    | 200    |          209 |              403 |      141 |        — |        — |      13216 |
| `/`            | page    | 200    |          196 |              293 |      120 |        — |        — |      14624 |
| `/handicaps`   | page    | 200    |          216 |              286 |      168 |        — |        — |      15847 |
| `/strokes`     | page    | 200    |          238 |              360 |      115 |        — |        — |      14913 |
| `/rounds`      | page    | 200    |          225 |              463 |      148 |        — |        — |      15561 |
| `/players`     | page    | 200    |          269 |              510 |      140 |        — |        — |      14540 |
| `/courses`     | page    | 200    |          241 |              517 |      154 |        — |        — |      19292 |
| `/import`      | page    | 200    |          193 |              305 |      108 |        — |        — |      16136 |
| `/rounds`      | Inertia | 200    |          200 |              318 |       98 |        — |        — |       2557 |
| `/handicaps`   | Inertia | 200    |          188 |              209 |      116 |        — |        — |       2271 |
| `/strokes`     | Inertia | 200    |          190 |              368 |      110 |        — |        — |       1909 |
| `/players`     | Inertia | 200    |          201 |              263 |      124 |        — |        — |       1082 |
| `/import`      | Inertia | 200    |          174 |              219 |      148 |        — |        — |       2330 |

Lecture :

- `app` et `db-count` valent « — » : la version en production n'envoie pas encore `Server-Timing`.
- La colonne « Taille » de cette mesure compte les octets décompressés. La commande n'envoyait pas encore `Accept-Encoding`, et le serveur répondait sans compression. Le défaut est corrigé depuis : cette colonne ne se compare pas aux mesures suivantes.
- Les médianes sont plus hautes que celles de la mesure de référence par `curl` : les conditions diffèrent (depuis le conteneur de Sail, à une autre heure). C'est cette mesure-ci qui sert de point de comparaison à la mesure « après », prise de la même façon.

## Mode worker sur la stack locale (2026-10-29)

Cette mesure compare deux images sur la stack locale de `docs/deployment.md` (« Lancer les commandes de cette page »), sur le même poste et à quelques minutes d'écart. Elle isole la part du serveur, sans réseau ni Cloudflare :

- avant : `golf-handicaps-laravel:pre-octane`, construite juste avant le mode worker, servie par `frankenphp run` en mode classique ;
- après : `golf-handicaps-laravel:local` (`make image`), servie par `php artisan octane:frankenphp … --workers=auto --max-requests=500`, le `CMD` de l'image.

Commande, 20 essais par page :

```sh
PERF_PASSCODE=smoke-passcode make perf-probe URL=http://host.docker.internal:8080
```

Deux écarts avec la stack telle que `docs/deployment.md` la lance :

- `http://127.0.0.1:8080` ne répond pas depuis le conteneur de Sail, où `127.0.0.1` est le conteneur lui-même. L'adresse de la stack vue du conteneur est `http://host.docker.internal:8080`.
- L'image tourne avec `APP_ENV=production`, donc ses témoins portent `Secure`. Le client HTTP de la commande (Guzzle 8) refuse un témoin `Secure` reçu en HTTP : sans réglage, la commande s'arrête sur « ne donne pas le jeton XSRF ou la version Inertia ». Les deux mesures ont donc tourné avec `SESSION_SECURE_COOKIE=false` sur `web`, posé par un fichier compose de plus, hors du dépôt. Le réglage ne change pas le travail du serveur.

L'image d'avant ne contient pas Octane. Au moment de la mesure, `docker-compose.yml` déclarait encore la commande Octane pour `web` : l'image d'avant a donc tourné avec sa commande à elle, `frankenphp run --config /etc/caddy/Caddyfile`, posée par le même fichier compose de plus. Depuis, `web` ne déclare plus de commande et chaque image lance son propre `CMD` : l'image d'avant tourne telle quelle.

### Avant : mode classique

| Adresse        | Visite  | Statut | Médiane (ms) | 90e centile (ms) | Min (ms) | app (ms) | db-count | Taille (o) |
| -------------- | ------- | ------ | -----------: | ---------------: | -------: | -------: | -------: | ---------: |
| `/robots.txt`  | page    | 200    |            2 |                2 |        1 |        — |        — |         24 |
| `/up`          | page    | 200    |            3 |                4 |        3 |      1.8 |        0 |       1834 |
| `/api/version` | page    | 200    |            3 |                5 |        3 |      1.8 |        0 |         33 |
| `/login`       | page    | 200    |           15 |               25 |       12 |     13.1 |        3 |       4562 |
| `/`            | page    | 200    |           19 |               24 |       16 |     16.3 |        6 |       5016 |
| `/handicaps`   | page    | 200    |           20 |               27 |       16 |     17.1 |        5 |       5327 |
| `/strokes`     | page    | 200    |           18 |               22 |       15 |     15.1 |        6 |       4667 |
| `/rounds`      | page    | 200    |           17 |               20 |       15 |     15.1 |        6 |       4651 |
| `/players`     | page    | 200    |           17 |               19 |       15 |     14.2 |        5 |       5120 |
| `/courses`     | page    | 200    |           16 |               18 |       15 |     14.3 |        4 |       5355 |
| `/import`      | page    | 200    |           17 |               21 |       13 |     14.7 |        4 |       5498 |
| `/rounds`      | Inertia | 200    |           21 |               29 |       18 |     18.8 |        6 |        263 |
| `/handicaps`   | Inertia | 200    |           17 |               19 |       15 |     15.0 |        5 |        352 |
| `/strokes`     | Inertia | 200    |           18 |               24 |       15 |     15.9 |        6 |        279 |
| `/players`     | Inertia | 200    |           17 |               19 |       15 |     14.6 |        5 |        266 |
| `/import`      | Inertia | 200    |           14 |               16 |       13 |     11.8 |        4 |        287 |

### Après : mode worker

| Adresse        | Visite  | Statut | Médiane (ms) | 90e centile (ms) | Min (ms) | app (ms) | db-count | Taille (o) |
| -------------- | ------- | ------ | -----------: | ---------------: | -------: | -------: | -------: | ---------: |
| `/robots.txt`  | page    | 200    |            1 |                2 |        1 |        — |        — |         24 |
| `/up`          | page    | 200    |            4 |                6 |        2 |      1.1 |        0 |       1764 |
| `/api/version` | page    | 200    |            2 |                4 |        2 |      0.8 |        0 |         33 |
| `/login`       | page    | 200    |            6 |                7 |        5 |      4.2 |        3 |       4562 |
| `/`            | page    | 200    |            7 |                8 |        7 |      5.2 |        6 |       5016 |
| `/handicaps`   | page    | 200    |            7 |               10 |        7 |      5.2 |        5 |       5327 |
| `/strokes`     | page    | 200    |           10 |               14 |        8 |      7.2 |        6 |       4667 |
| `/rounds`      | page    | 200    |            9 |               11 |        8 |      6.4 |        6 |       4651 |
| `/players`     | page    | 200    |           10 |               15 |        8 |      7.4 |        5 |       5120 |
| `/courses`     | page    | 200    |           11 |               13 |        7 |      8.1 |        4 |       5355 |
| `/import`      | page    | 200    |            9 |               12 |        7 |      6.2 |        4 |       5498 |
| `/rounds`      | Inertia | 200    |            7 |                9 |        6 |      5.4 |        6 |        263 |
| `/handicaps`   | Inertia | 200    |            7 |                7 |        6 |      4.6 |        5 |        352 |
| `/strokes`     | Inertia | 200    |            8 |               11 |        6 |      5.6 |        6 |        279 |
| `/players`     | Inertia | 200    |            9 |               15 |        6 |      6.2 |        5 |        266 |
| `/import`      | Inertia | 200    |            7 |               10 |        6 |      5.2 |        4 |        287 |

Lecture : la médiane de `app` sur `/rounds` passe de 15,1 ms à 6,4 ms en page complète, et de 18,8 ms à 5,4 ms en visite Inertia. Les autres pages protégées gagnent 6 à 12 ms de `app`, alors que le nombre de requêtes SQL ne change pas. Le gain vient donc du démarrage de Laravel, que le mode worker ne refait plus. `/up` et `/api/version` ne coûtaient déjà que 2 ms de `app` et gagnent peu. Les chiffres de production restent à prendre après le déploiement, depuis le même poste que la mesure de référence.

Avant chaque mesure, deux navigateurs séparés (deux contextes Chrome sans témoin commun) ont ouvert la stack avec le code `smoke-passcode`. Sur les deux images :

- A ouvre une session, puis B, sans témoin, demande `/rounds` et `/players` : redirigé vers `/login` ;
- A envoie un joueur sans nom et reçoit « Le nom est obligatoire » sur le champ du nom, puis B ouvre une session et `/players` : `errors` vide, aucun champ en erreur ;
- A reçoit un message flash, que B ne voit pas sur `/rounds`, et que A voit bien à sa visite suivante. La base de la stack étant vide, ce message est le toast d'erreur « Parcours introuvable » d'une rating sur un parcours inconnu, et non « Rating enregistrée. ».

Sur l'image d'après, 40 allers-retours de suite, une requête de A avec sa session puis une requête de B sans témoin, ont donné 40 réponses 200 à A et 40 redirections vers `/login` à B. Les captures sont dans `reports/captures/optimize-performance/` (`worker-isolation-*`).
