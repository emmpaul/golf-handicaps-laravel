# Tasks

Chaque tâche de code suit RED, GREEN, REFACTOR. Le test vient d'abord et doit échouer pour la raison qu'il nomme, puis vient le code. Les valeurs attendues sont des littéraux tirés de la spec. Avant de commencer : `laravel/octane` est approuvé à la relecture de la proposition (dépendance nouvelle), et les entrées 4 et 11 du ROADMAP sont faites ou en production.

## 1. `Server-Timing`

- [ ] 1.1 Tests Pest (`tests/Feature/ServerTimingTest.php`) : `/rounds` avec session porte `app;dur=`, `db;dur=` et `db-count;dur=` suivis de nombres. `/api/version` sans cookie porte `db;dur=0` et `db-count;dur=0`. La redirection de `/rounds` sans session porte l'en-tête. Avec un joueur « Zoé », l'en-tête de `/players` ne contient ni `Zoé`, ni `select`, ni `players`. Deux requêtes de suite : `db-count` de la seconde ne cumule pas celui de la première. Mutations qui doivent faire échouer un test : `QueryClock` liée en `singleton` au lieu de `scoped`, nom de mesure `db_count`, middleware ajouté au seul groupe `web`.
- [ ] 1.2 Écrire `AddServerTiming` et `QueryClock` (design D1), puis inscrire le middleware dans `bootstrap/app.php` juste après `AddSecurityHeaders`. Vérifier : `make test-backend FILES=tests/Feature/ServerTimingTest.php` vert, `tests/Feature` au complet vert.
- [ ] 1.3 Ajouter une ligne `### Added` dans `CHANGELOG.md` (« Server-Timing header on every response »).

## 2. Mesure reproductible

- [ ] 2.1 Test unitaire de la classe de calcul : `[100, 120, 110, 300, 105]` donne une médiane de 110, un 90e centile de 300 et un minimum de 100. `[50]` donne 50, 50 et 50. Lecture de `Server-Timing` : `app;dur=12.5, db;dur=3, db-count;dur=4` donne `app` 12,5 et `db-count` 4. En-tête absent : `null`, affiché « — ». Mutations à nommer : 90e centile par `floor` au lieu du rang le plus proche, médiane prise sur la liste non triée.
- [ ] 2.2 Tests Pest de `golf:perf-probe` avec `Http::fake` : un seul `POST /login`, puis seulement des `GET` (`Http::assertSentCount` et un contrôle de chaque méthode). Les onze adresses de la spec sont mesurées, plus les cinq sections avec `X-Inertia: true`. Un code refusé (réponse 302 vers `/login`) arrête la commande en échec avec « Code d'accès refusé », sans GET protégé. Avec `PERF_PASSCODE=code-de-test`, aucune question n'est posée et le rapport ne contient ni `code-de-test` ni les valeurs des témoins. Sans la variable, `secret()` est appelée. Le rapport est écrit dans `reports/perf/2026-11-02-golf.emmanuelpaul.com.md` (date figée par `travelTo`).
- [ ] 2.3 Écrire la commande (design D2 : connexion neuve par essai, `starttransfer − appconnect`, version Inertia lue dans `data-page`). Vérifier : `make test-backend FILTER=PerfProbe` vert.
- [ ] 2.4 Ajouter la cible `make perf-probe URL=… RUNS=…` : elle passe `PERF_PASSCODE` de l'hôte sans l'écrire et s'affiche dans `make`. Créer `docs/performance.md` : méthode, la commande, et le tableau de référence du 2026-10-29 tiré de `design.md` (« Mesure de référence »), avec les constats navigateur. Ajouter une ligne `### Added` dans `CHANGELOG.md`. Vérifier : `make` liste la cible, et la commande de la doc tourne telle quelle.
- [ ] 2.5 Lancer `PERF_PASSCODE=… make perf-probe URL=https://golf.emmanuelpaul.com` contre la version en place, avant tout déploiement de ce changement : GET seulement, aucun round ni import créé. Recopier le rapport dans `docs/performance.md` comme mesure « avant », sans le code. Vérifier : `git diff` ne contient pas le code d'accès.

## 3. Polices

- [ ] 3.1 Test Vitest ou Pest sur le HTML rendu de `/login` après `make build` (même approche que les tests de thème existants) : cinq `<link rel="preload" as="font">` exactement, tous `.woff2`. Aucune URL `.woff"` dans les règles `@font-face`. Une règle par graisse (Geist 400, 500, 600 ; Geist Mono 500, 600). Les fichiers préchargés sont exactement ceux des règles. Une règle de repli « Geist fallback » existe. Mutation à nommer : remettre `subsets: ['latin', 'latin-ext']` doit faire échouer le test.
- [ ] 3.2 Passer `vite.config.ts` au fournisseur local du plugin, WOFF2 latin seul, avec `optimizedFallbacks` (design D3). Si le fournisseur ne calcule pas le repli, appliquer le plan B de D3 et le noter dans `design.md`. Vérifier : test 3.1 vert, `make build`, tests de `visual-theme` verts (« Titre de page » en Geist 600 et Geist Mono 500).
- [ ] 3.3 Mettre à jour `DESIGN.md` (polices : sous-ensemble latin, WOFF2, repli) et ajouter une ligne `### Fixed` dans `CHANGELOG.md` (« fonts downloaded twice, WOFF overriding the preloaded WOFF2 »).

## 4. Préchargement des pages

- [ ] 4.1 Tests Vitest de `NavLink` et `AppNav` : chaque onglet et le bouton Importer rendent un `Link` avec `prefetch="mount"` et `cacheFor` `['30s', '5m']`, sauf l'onglet de la page courante, sans préchargement. Mutation à nommer : préchargement aussi sur l'onglet courant.
- [ ] 4.2 Tests Vitest des liens de liste (rounds de `/rounds`, de l'accueil et de `round-history`, imports de `import-list`) : `prefetch` vaut `['hover', 'click']`, même `cacheFor`.
- [ ] 4.3 Test Vitest du vidage : une visite `post` réussie appelle `router.flushAll()` une fois. Une visite `get` ne l'appelle pas. Une visite `post` en erreur de validation ne l'appelle pas. Une sauvegarde automatique 2xx l'appelle, une sauvegarde en échec non. Mutations à nommer : vidage sur `finish` au lieu de `success`, vidage oublié dans `autosave.ts`.
- [ ] 4.4 Implémenter D5 (`nav-link.tsx`, `app-nav.tsx`, liens de liste, `app.tsx`, `round-editor/autosave.ts`) en vérifiant d'abord l'API de préchargement d'Inertia 3 par `search-docs` (`prefetch`, `cacheFor`, `flushAll`, événement `success` et méthode de la visite). Vérifier : `make test-frontend` vert, `make lint` vert.
- [ ] 4.5 Ajouter une ligne `### Added` dans `CHANGELOG.md` (« prefetched section tabs and list links, cache flushed after every write »).

## 5. `/favicon.ico`

- [ ] 5.1 Test Pest : `GET /favicon.ico` sans cookie ne passe pas par le routeur Laravel. Dans les tests, l'absence de route `favicon.ico` est vérifiée et `public/favicon.ico` existe, de type `image/x-icon`. La passe 7.3 vérifie le 200 sans `Set-Cookie` derrière Caddy. Mutation à nommer : fichier supprimé.
- [ ] 5.2 Produire `public/favicon.ico` (16 et 32 px) depuis `public/favicon.svg`. Ajouter une ligne `### Fixed` dans `CHANGELOG.md` (« /favicon.ico redirected to /login and opened a session »).

## 6. Mode worker (Octane et FrankenPHP)

- [ ] 6.1 Vérifier par `search-docs` l'installation d'Octane avec FrankenPHP sous Laravel 13 (`octane:install --server=frankenphp`, option `--caddyfile`, gabarit Caddyfile du worker), puis ajouter `laravel/octane` par `vendor/bin/sail composer require` et `config/octane.php`. Vérifier : `vendor/bin/sail composer show laravel/octane` donne la version, et `make test-backend` reste vert.
- [ ] 6.2 Tests Pest de la spec « Aucun état d'une requête dans la suivante », deux requêtes de suite dans la même application comme sous Octane. A ouvre une session puis B sans témoin demande `/rounds` : redirection `/login`. A reçoit le flash « Round enregistré. » puis B ouvre `/rounds` : pas de toast. A reçoit une erreur sur le nom du joueur puis B ouvre `/players` : `errors` vide. Session ouverte avec l'ancien code, code changé : redirection `/login`. Mutation à nommer : `Passcode` qui mémorise le résultat en propriété statique.
- [ ] 6.3 Écrire l'ADR `docs/adr/0015-mode-worker-octane-frankenphp.md` (contexte de mesure, décision D4, alternatives, conséquences), sa ligne dans `docs/adr/README.md`, et marquer D1 de l'ADR 0008 « remplacée par 0015 ». Mettre à jour `ARCHITECTURE.md` (déploiement, état entre requêtes).
- [ ] 6.4 Adapter `docker/Caddyfile` (bloc worker, réglages actuels gardés), le `Dockerfile` (`OCTANE_SERVER=frankenphp`, `CMD`) et la commande de `web` dans `docker-compose.yml` (`octane:frankenphp … --max-requests=500`). Vérifier : `make image image-smoke` vert, `/up` sain, `/login` 200.
- [ ] 6.5 Sur la stack locale de `docs/deployment.md` (« Lancer les commandes de cette page »), mesurer l'image d'avant ce groupe puis l'image d'après, avec `PERF_PASSCODE=smoke-passcode make perf-probe URL=http://127.0.0.1:8080`. Avant chaque mesure, ouvrir deux navigateurs (code `smoke-passcode`) et vérifier qu'aucune session, aucun flash et aucune erreur ne passe de l'un à l'autre. Recopier les deux tableaux dans `docs/performance.md`. Vérifier : la médiane de `app` sur `/rounds` est au moins deux fois plus basse après.
- [ ] 6.6 Mettre à jour `docs/deployment.md` : tableau de la stack (`web` en mode worker), relancer la mesure après un déploiement, réglage Cloudflare Web Analytics (design D7) avec sa vérification. Ajouter une ligne `### Changed` dans `CHANGELOG.md` (« web served by FrankenPHP worker mode through Laravel Octane »).

## 7. Intégration

- [ ] 7.1 Ajouter l'entrée 13 `optimize-performance` au `ROADMAP.md` (dépend de 4 et 11, statut, ce qui reste au mainteneur).
- [ ] 7.2 `make check` vert. Après un échec, lire `reports/check/<étape>.log`.
- [ ] 7.3 Passe navigateur : `make up`, `make dev`, `make browser`, puis le skill `browser-use` sur `http://localhost`, cache vide. Compter les requêtes de police (au plus cinq, toutes `.woff2`) et relever le CLS (sous 0,01). Vérifier que toucher un onglet après 2 s n'envoie aucune requête vers la section, que supprimer un round de démonstration puis ouvrir Handicaps donne la liste à jour, et que `/favicon.ico` répond 200 sans `Set-Cookie`. Lire la console. Captures dans `reports/captures/optimize-performance/`. Finir par `make dev-stop`.
- [ ] 7.4 Relire chaque scénario des quatre specs delta, et noter dans le rapport d'apply ceux qui ne se prouvent qu'après le déploiement.

## Workflow follow-up

- Déployer la version par Dokploy, puis relancer `make perf-probe URL=https://golf.emmanuelpaul.com` depuis le même poste que la mesure « avant ». Recopier le tableau « après » dans `docs/performance.md`, avec la passe navigateur en production (polices, FCP, navigation entre onglets).
- Ouvrir deux navigateurs sur la production et vérifier qu'aucune session ni aucun message ne passe de l'un à l'autre. Lire la mémoire de `web` dans `docker stats` (question ouverte du design).
- Couper Cloudflare Web Analytics sur la zone (design D7), puis vérifier que `/login` ne charge plus `cloudflareinsights`.
- Signaler en amont à `laravel/vite-plugin` le défaut des règles WOFF qui l'emportent sur les WOFF2.
- Archiver le changement une fois ces mesures inscrites.
