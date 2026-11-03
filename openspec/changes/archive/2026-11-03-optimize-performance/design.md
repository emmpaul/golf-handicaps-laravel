## Context

La motivation est dans `proposal.md`. Voici ce qui contraint la façon de faire.

- `web` exécute FrankenPHP 1.13 en mode classique, derrière Traefik (Dokploy) puis Cloudflare (`docker/Caddyfile`, ADR 0008, D1). Chaque requête relance `bootstrap/app.php` et les fournisseurs de services. `php artisan optimize` tourne au démarrage du conteneur, et OPcache garde le code compilé (`validate_timestamps=0`).
- La session, le cache et la file sont en base (ADR 0002). La session passe par `AnonymousDatabaseSessionHandler`, déclaré dans `AppServiceProvider::configureSessionStorage()`. Une requête protégée lit puis réécrit la session : deux requêtes SQL, plus le compteur `pendingImports` partagé par `HandleInertiaRequests`.
- Les requêtes de lecture sont déjà regroupées (`RoundList` : cinq requêtes quel que soit le nombre de rounds). Les données font 9 rounds, 3 joueurs et 9 parcours. La production compte moins de 1,5 Ko de props par page.
- Les polices passent par `fontsource()` de `laravel-vite-plugin` 3.2.0 (`vite.config.ts`), avec les sous-ensembles `latin` et `latin-ext`. Le plugin écrit les règles `@font-face` en ligne dans `<head>` : une règle par fichier et par plage `unicode-range`, la WOFF2 d'abord, la WOFF ensuite (`generateFontFace`, `dist/index.js`, l. 499-530). Les deux règles ont les mêmes descripteurs, donc la dernière, la WOFF, l'emporte. Le plugin précharge les WOFF2 (l. 778).
- Aucun `<Link>` ne précharge (`prefetch` absent de `resources/js`). La sauvegarde automatique de l'écran de correction écrit par `fetch` (`round-editor/autosave.ts`), hors du routeur Inertia.

## Mesure de référence (2026-10-29, version 0.1.0 en production)

Mesure faite depuis le poste du mainteneur (Montréal, Cloudflare YYZ) par `curl`, connexion neuve à chaque essai. Une seule ouverture de session, puis des GET seulement. La valeur est `time_starttransfer − time_appconnect` : le temps entre la fin de la poignée de main TLS et le premier octet, donc un aller-retour réseau plus le temps du serveur. 20 essais par page.

| Adresse        | Médiane | 90e centile | Min    | Ce qui répond                                 |
| -------------- | ------- | ----------- | ------ | --------------------------------------------- |
| `/robots.txt`  | 50 ms   | 121 ms      | 42 ms  | Caddy seul, fichier statique (plancher réseau) |
| `/up`          | 83 ms   | 104 ms      | 61 ms  | Laravel, sans session                         |
| `/api/version` | 94 ms   | 138 ms      | 58 ms  | Laravel, sans session                         |
| `/login`       | 109 ms  | 146 ms      | 87 ms  | Laravel, session créée                        |
| `/`            | 132 ms  | 228 ms      | 106 ms | page complète, session                        |
| `/handicaps`   | 134 ms  | 230 ms      | 103 ms |                                               |
| `/strokes`     | 146 ms  | 213 ms      | 116 ms |                                               |
| `/rounds`      | 204 ms  | 352 ms      | 122 ms |                                               |
| `/players`     | 165 ms  | 223 ms      | 119 ms |                                               |
| `/courses`     | 171 ms  | 223 ms      | 118 ms |                                               |
| `/import`      | 131 ms  | 154 ms      | 109 ms |                                               |

Autres constats du même jour :

- Réponses Inertia (`X-Inertia`) compressées : 455 o (`/players`) à 1 401 o (`/courses`). Pages complètes : 2,3 à 3,3 Ko compressés, 14 Ko décompressés pour `/`.
- Assets : `Cache-Control: public, max-age=31536000, immutable`, mis en cache par Cloudflare (`cf-cache-status: HIT` au second essai). Le paquet commun (`wayfinder-*.js`, React et Inertia) pèse 122 Ko compressé et 379 Ko décompressé.
- Navigateur (Playwright, profil iPhone, WebKit), premier chargement après ouverture de session : TTFB 400 ms, premier rendu 580 ms, FCP 820 ms. Les 10 WOFF2 préchargées (98 Ko) partent à 415 ms. Les WOFF effectivement utilisées partent ensuite : 3 à 780 ms, 2 encore à 2,9 s et 3,4 s (≈ 85 Ko, non préchargées).
- Navigation entre onglets (clic sur un lien de la barre jusqu'au rendu) : `/rounds` 317 ms (requête de 292 ms, puis le code de la page), `/handicaps` 158 ms, `/` 189 ms, `/rounds` à nouveau 166 ms. Tout ce temps est l'aller-retour Inertia.
- `/favicon.ico` : 302 vers `/login`, avec `Set-Cookie` et une session créée en base à chaque demande.
- Cloudflare injecte `static.cloudflareinsights.com/beacon.min.js` et envoie des balises `/cdn-cgi/rum`.

Lecture : le plancher réseau (Cloudflare jusqu'à l'origine) vaut environ 50 ms. Laravel sans session en ajoute 30 à 45, une page protégée 80 à 150. Les données ne pèsent rien. Le gain est donc dans le démarrage de Laravel, les allers-retours de navigation et les octets de polices.

## Goals / Non-Goals

**Goals:**

- Une mesure qu'on relance à l'identique avant et après chaque déploiement, sans écrire de donnée ni versionner de secret.
- Diviser au moins par deux la part du serveur (`app`) d'une page protégée, et ramener à moins de 50 ms la navigation vers un onglet préchargé.
- Ramener les polices du premier chargement de ≈ 183 Ko et 20 fichiers déclarés à ≈ 55 Ko et 5 fichiers.

**Non-Goals:**

- Toucher au schéma ou aux requêtes de lecture : elles ne coûtent rien aujourd'hui, et `Server-Timing` dira quand elles commenceront à coûter.
- Mettre en cache des réponses côté serveur ou côté Cloudflare.
- Changer de pilote de session, de cache ou de file.

## Decisions

### D1. `Server-Timing` par un middleware global et une horloge par requête

Le middleware `AddServerTiming` est ajouté en tête de la pile globale, juste après `AddSecurityHeaders`. Il écrit `app;dur=<ms>, db;dur=<ms>, db-count;dur=<n>` sur toute réponse. `app` part de `$request->server('REQUEST_TIME_FLOAT')`, posé par le SAPI au début de la requête : en mode classique, ce temps compte le démarrage de Laravel, ce qui rend visible le gain du mode worker (D4). Un `DB::listen` enregistré une fois au démarrage additionne `QueryExecuted::$time` dans une `QueryClock` liée en `scoped()` : Octane la remet à zéro à chaque requête, et elle reste correcte en mode classique.

Alternatives écartées : Debugbar ou Telescope, trop lourds en production et sources de fuite de données ; un journal par requête, illisible depuis l'extérieur.

### D2. Commande de mesure `golf:perf-probe`

`php artisan golf:perf-probe {url} {--runs=20}`, lancée par `make perf-probe URL=…`. La cible passe `PERF_PASSCODE` de l'hôte au conteneur (`exec -e PERF_PASSCODE`) sans jamais l'écrire. Sans la variable, la commande demande le code par `secret()`.

- Client HTTP de Laravel avec la poignée de cURL, `CURLOPT_FRESH_CONNECT` et `CURLOPT_FORBID_REUSE` : chaque essai rouvre sa connexion, comme la mesure de référence. La valeur reprend la même définition : `starttransfer_time − appconnect_time`, lus dans les statistiques de transfert (`on_stats`).
- Ouverture de session : `GET /login` pour le témoin XSRF, puis un seul `POST /login`. Une réponse autre qu'une redirection vers `/` arrête la commande avec « Code d'accès refusé ». Ensuite, des `GET` seulement. La liste des adresses est une constante de la commande, et un test vérifie qu'aucun autre verbe ne part (`Http::fake` et `Http::assertSent`).
- Visites Inertia : `X-Inertia: true` et `X-Inertia-Version` lu dans `data-page` de la première page.
- Calcul : médiane et 90e centile au rang le plus proche (`ceil(0,9 × n)`), sur les essais triés. La logique de calcul vit dans une petite classe pure, testée avec des littéraux. `Server-Timing` absent donne « — ».
- Rapport : tableau Markdown dans `reports/perf/<date>-<hôte>.md` (`reports/` est ignoré par git), recopié à la main dans `docs/performance.md` pour les mesures qu'on garde. Le témoin de session et le jeton XSRF restent en mémoire.

Alternative écartée : un script `bash`/`curl` comme la mesure de référence. Il est plus simple, mais pas testable par Pest, et le projet veut le Makefile et Sail comme seule interface. La commande tourne dans le conteneur : l'écart dû au NAT de Docker est négligeable devant les 50 ms du plancher. Pour comparer à armes égales, la tâche 2.5 relance de toute façon la commande contre la production avant le déploiement, et cette mesure devient la référence chiffrée du rapport.

### D3. Polices : fournisseur `local` du plugin, WOFF2 latin seul

`fontsource()` est remplacé par le fournisseur local du même plugin, avec une variante par graisse qui vise le seul fichier `node_modules/@fontsource/geist/files/geist-latin-<poids>-normal.woff2` (et de même pour `geist-mono`). Il y a une règle `@font-face` par graisse, sans `unicode-range` ni WOFF, et le plugin précharge exactement ces cinq fichiers. `optimizedFallbacks: true` produit la police de repli aux métriques ajustées. Le français tient dans le sous-ensemble latin (U+0000-00FF couvre é, è, à, ç, œ via U+0152-0153). Un caractère hors latin s'affiche dans la police de repli, sans téléchargement.

Alternatives écartées : garder `fontsource()` avec `preload: false`, qui laisse les WOFF télécharger en premier ; écrire les `@font-face` à la main dans `app.css`, possible, mais qui perd le préchargement et le repli calculé que donne le plugin. Si le fournisseur local ne sait pas calculer le repli, c'est le plan B : `@font-face` dans `resources/css/app.css`, préchargement par `Vite::asset()` dans `app.blade.php`, et `size-adjust` du repli écrit en jeton. Le défaut du plugin est à signaler en amont (`laravel/vite-plugin`) ; c'est une action du mainteneur, hors de ce changement.

Constat de l'apply (tâche 3.2) : le fournisseur local de `laravel-vite-plugin` 3.2.0 écrit bien une règle par graisse et précharge les cinq WOFF2, mais il ne calcule le repli qu'avec le paquet optionnel `fontaine`, absent du projet. Sans lui, le build avertit (« Optimized font fallbacks require the optional "fontaine" package ») et n'écrit aucun repli. Ajouter `fontaine` est un changement de dépendance, non approuvé. Le plan B s'applique donc au seul repli : `local()` garde les règles `@font-face` et le préchargement (`optimizedFallbacks: false`, `fallbacks: ['"Geist fallback"']` et `['"Geist Mono fallback"']`, qui placent le repli dans `--font-geist` et `--font-geist-mono`), et les deux règles de repli sont écrites dans `resources/css/app.css` avec des valeurs littérales. Un descripteur de `@font-face` n'accepte pas `var()`, d'où des littéraux et non des jetons. Les valeurs viennent de la formule de `generateFallbackMetrics()` du plugin, appliquée une fois, hors du projet, avec `fontaine` 0.8.0 à `geist-latin-400-normal.woff2` (Arial, `size-adjust` 105,8 %, `ascent-override` 94,99 %, `descent-override` 27,88 %) et `geist-mono-latin-500-normal.woff2` (Arial, 135,93 %, 73,94 %, 21,7 %). Si `fontaine` est un jour approuvé, `optimizedFallbacks: true` remplace ces deux règles.

### D4. Mode worker par Laravel Octane et FrankenPHP, ADR 0015

`laravel/octane` est ajouté (version compatible Laravel 13, à confirmer par `search-docs` et `composer why-not`). Le service `web` passe à `php artisan octane:frankenphp --host=0.0.0.0 --port=8080 --caddyfile=/etc/caddy/Caddyfile --workers=auto --max-requests=500`, porté par le `CMD` de l'image. Le service `web` de `docker-compose.yml` ne déclare pas de `command` : chaque image lance sa propre commande, et remettre l'ancien `IMAGE_TAG` suffit à revenir au mode classique (une image d'avant lance `frankenphp run --config /etc/caddy/Caddyfile`). `docker/Caddyfile` garde ses réglages (`admin off`, `auto_https off`, `encode`, en-tête `immutable` de `/build/*`) et remplace `php_server` par le bloc worker du gabarit d'Octane. `OCTANE_SERVER=frankenphp` est fixé dans l'image. Le healthcheck `/up` ne change pas. Le worker de file et `migrate` ne changent pas.

État entre requêtes, à vérifier dans le code de l'apply et à couvrir par des tests : les fournisseurs ne gardent aucun état par requête (`AppServiceProvider` déclare des callbacks et des valeurs de configuration lues au démarrage). `AnonymousDatabaseSessionHandler` est recréé par le gestionnaire de session, qu'Octane vide à chaque requête. `Passcode::opened()` lit la session de la requête. `AppBuild` lit la configuration. Une recherche de `static $`, `singleton(` et `instance(` dans `app/` ne trouve aucun état statique par requête au 2026-10-29.

Alternatives écartées : garder le mode classique et retirer quelques millisecondes ailleurs, ce qui laisse le plus gros poste (démarrage) intact ; le mode worker FrankenPHP sans Octane, que Laravel ne supporte pas sans le pont d'Octane ; Swoole ou RoadRunner, qui obligent à changer d'image.

### D5. Préchargement Inertia et vidage après écriture

- `NavLink` (onglets) et le bouton Importer : la page est préchargée à l'affichage, avec `cacheFor` `['30s', '5m']`. La page courante n'est pas préchargée : `NavLink` connaît déjà la section courante. Le préchargement passe par un effet de `NavLink` (`router.prefetch(href, { method: 'get' }, { cacheFor })`), relancé à chaque page affichée (dépendances `href`, « page courante » et l'objet page d'Inertia, neuf à chaque visite) : la barre reste montée, et une écriture vide le cache avant de rediriger, parfois vers la même adresse. Une copie encore fraîche (moins de 30 s) ou en cours ne part pas en requête (`PrefetchedRequests.add` d'Inertia), une copie de 30 s à 5 min est rechargée. Ce n'est pas `prefetch="mount"` du `Link` : la barre reste montée d'une page à l'autre, et basculer `prefetch` entre `false` et `'mount'` change la taille du tableau de dépendances d'un `useEffect` du `Link` d'Inertia 3.9.1. React le signale (« changed size between renders ») et l'onglet qui cesse d'être la page courante ne se précharge plus.
- Liens de liste (rounds de `/rounds`, de l'accueil et de l'historique d'un joueur, imports de `/import`) : `prefetch="hover"` plus un `onTouchStart` qui appelle `router.prefetch(href, { method: 'get' }, { cacheFor })`, donnés par `listLinkPrefetch()` de `resources/js/lib/prefetch.ts`. Dans Inertia 3.9.1, le mode `click` n'écoute que `mousedown`, qu'un toucher n'émet qu'au relâchement : il ne précharge pas au contact. D'où l'écoute de `touchstart`. Les options de visite `{ method: 'get' }` sont celles d'un clic sur un `Link` simple, donc la visite trouve la copie préchargée. Même `cacheFor`.
- `app.tsx` : `flushPrefetchAfterWrites()` vide tout (`router.flushAll()`) quand la réponse d'une visite qui n'est pas un GET arrive sans erreur de validation. Le vidage se fait à l'événement `beforeUpdate`, avant l'affichage de la nouvelle page, et non à `success` : dans Inertia 3.9.1, `success` part après l'affichage, quand les onglets ont déjà relancé le préchargement des sections ; vider à ce moment effaçait ces préchargements, et toucher Handicaps 2 s après la suppression d'un round repartait au serveur (passe navigateur de la revue). Une visite refusée par la validation revient avec `errors` et garde le cache. La sauvegarde automatique appelle `router.flushAll()` après une réponse 2xx.
- Les 30 s puis 5 min de `cacheFor` sont la règle de fraîcheur de la spec : passé 30 s, une page préchargée s'affiche puis se recharge en arrière-plan. Le badge des imports en attente suit cette règle.

Alternative écartée : les étiquettes de cache (`cacheTags`) par domaine. Elles sont plus fines, mais une erreur d'étiquette affiche une donnée périmée, et vider tout à chaque écriture ne coûte presque rien pour un groupe d'amis.

### D6. `/favicon.ico` statique

`public/favicon.ico` (16 et 32 px, tiré de `favicon.svg`) est servi par Caddy avant `index.php`, comme `robots.txt`. Ni PHP ni session.

### D7. Script Cloudflare

Ce n'est pas du code : le mainteneur coupe « Web Analytics » (injection automatique RUM) sur la zone `emmanuelpaul.com` dans Cloudflare. `docs/deployment.md` le note avec la vérification (`curl -s $URL/login | grep -c cloudflareinsights` donne 0, après connexion).

## Risks / Trade-offs

- [Fuite d'état en mode worker : session, flash ou code d'accès d'une personne vus par une autre] → tests de la spec « Aucun état d'une requête dans la suivante » sous Octane (deux requêtes successives dans le même processus de test, `Octane` en mode test). Passe sur la stack `make image-smoke`, puis sur la production avec deux navigateurs. `--max-requests=500` limite toute fuite de mémoire.
- [Cloudflare retire ou réécrit `Server-Timing`] → la commande l'affiche « — », et la même mesure contre la stack locale `make image-smoke` le lit sans Cloudflare. Constat à noter dans `docs/performance.md`.
- [Le préchargement multiplie les requêtes : 4 par page affichée] → requêtes de moins de 1,5 Ko, servies en quelques millisecondes une fois en mode worker. `cacheFor` évite de reprécharger dans les 30 s.
- [Page périmée affichée jusqu'à 30 s après l'écriture d'un autre téléphone] → la règle est écrite dans la spec. Les écritures du même navigateur vident tout de suite. Les pages d'édition ne sont pas préchargées.
- [Le fournisseur local des polices ne sait pas tout faire (préchargement, repli)] → plan B de D3, décidé pendant l'apply sans changer la spec.
- [Nouvelle dépendance `laravel/octane`] → approuvée avec cette proposition. ADR 0015. Retour arrière par l'image précédente.
- [La mesure dépend du poste et de l'heure] → toujours depuis le même poste, 20 essais, médiane et 90e centile. La mesure « avant » est relancée juste avant le déploiement, la mesure « après » juste après.

## Migration Plan

1. Livrer dans une seule version : `Server-Timing`, commande de mesure, polices, préchargement, favicon, mode worker.
2. Avant le déploiement : `make perf-probe URL=https://golf.emmanuelpaul.com` contre la version en place, et rapport recopié dans `docs/performance.md` comme référence « avant ».
3. Déployer l'image par Dokploy (`docs/deployment.md`, « Déployer une version »). `docker-compose.yml` retire la `command` de `web`, que donne désormais le `CMD` de l'image. Le reste de la stack est inchangé.
4. Après : même commande, même poste. Rapport « après » dans `docs/performance.md`, avec la passe navigateur (polices, FCP, navigation).
5. Couper le script Cloudflare (D7).

Retour arrière : redéployer l'image précédente (`IMAGE_TAG`). Aucune migration de base, et les sessions restent valides dans les deux sens.

## Open Questions

- Le nombre de workers FrankenPHP (`auto` = 2 × cœurs) convient-il à la mémoire de l'hôte Dokploy ? À lire dans `docker stats` après le déploiement. Fixer `--workers=2` si la mémoire est juste ; ni la spec ni les tâches ne changent.
