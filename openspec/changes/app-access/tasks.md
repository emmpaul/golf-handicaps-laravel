# Tasks

Avant de commencer : `ROADMAP.md` entrées 1 et 2 faites ; relire `proposal.md` (tableau des requirements Next, décisions du mainteneur du 2026-10-28), `design.md` et les trois deltas de specs. Les fichiers de référence Next sont listés dans `design.md`, « Context ». Chaque test nomme dans un commentaire la mutation qui le rend rouge ; elle est appliquée, vue rouge, puis retirée. Les valeurs attendues sont des littéraux de la spec (`birdie-2026`, `Code incorrect`, `Trop d'essais. Réessaie dans une minute.`, `Session requise`).

## 1. Configuration

- [ ] 1.1 Ajouter `passcode` à `config/app.php` (`env('APP_PASSCODE')`), `APP_PASSCODE=birdie-2026` à `phpunit.xml`, `APP_PASSCODE=` et `SESSION_LIFETIME=129600` à `.env.example` ; passer dans `config/session.php` `lifetime` à `129600` et `encrypt` à `true` par défaut (D5, D10) ; ajouter `APP_PASSCODE` à son `.env` local ; vérifier `make test-backend` vert
- [ ] 1.2 Écrire `tests/Unit/PasscodeTest.php` : `matches('birdie-2026')` vrai, `matches('Birdie-2026')` et `matches('')` faux, `fingerprint()` change quand `app.passcode` devient `eagle-2027` et ne contient pas `birdie-2026` ; le voir rouge ; mutation nommée : `matches` en `strcasecmp(...) === 0` ; écrire `App\Support\Passcode` (D4) ; vérifier `make test-backend FILES=tests/Unit/PasscodeTest.php` vert

## 2. Refus sans session

- [ ] 2.1 Écrire `tests/Feature/AccessTest.php` : sans session, `/` et `/rounds/0190a6b2-7c3e-7000-8000-000000000001` redirigent vers `/login` ; `/icon-admin` redirige vers `/login` sans session et répond 404 avec `withPasscodeSession()` ; `GET /api/version` et `GET /up` répondent 200 ; `GET /api/version/x` et `GET /api/versions` répondent 404 ; une session dont l'empreinte vient de `birdie-2026` est redirigée après `config(['app.passcode' => 'eagle-2027'])` ; le voir rouge ; mutations nommées : `RequirePasscode` retiré du groupe `web`, comparaison d'empreinte remplacée par `session()->has('access.passcode')`
- [ ] 2.2 Ajouter `TestCase::withPasscodeSession()` (D12), `RequirePasscode` au groupe `web` (D1, D3) et la route de repli 404 ; faire passer `HomePageTest`, `SectionPlaceholderTest`, `DocumentHeadTest`, `PageAssetsTest` et `SessionStorageTest` par l'assistant ; vérifier `make test-backend` vert
- [ ] 2.3 Écrire dans `AccessTest` les écritures refusées (D3, D12) : une route `POST` de test sous `web` qui incrémente un compteur ; sans session, `postJson` répond 401 avec `{ "error": "Session requise" }` exactement ; avec l'en-tête `X-Inertia`, redirection vers `/login` ; le compteur reste à 0 dans les deux cas ; avec un jeton CSRF qui ne correspond pas à la session, la réponse JSON est 401, pas 419 ; le voir rouge sur le 419 ; mutation nommée : `RequirePasscode` placé après `PreventRequestForgery`
- [ ] 2.4 Ordonner `RequirePasscode` entre `StartSession` et `PreventRequestForgery` (D2, API vérifiée par `search-docs`) ; vérifier `make test-backend FILES=tests/Feature/AccessTest.php` vert
- [ ] 2.5 Écrire `tests/Feature/ProtectedRoutesTest.php` (D1) : la liste des routes sans `RequirePasscode` dans leur pile résolue est égale à une liste littérale (`GET|HEAD login`, `POST login`, `GET|HEAD api/version`, `GET|HEAD up`, routes de paquets nommées une à une) ; mutation nommée : une route `Route::get('/x', …)->withoutMiddleware(RequirePasscode::class)` ajoutée à `routes/web.php` ; vérifier vert, puis rouge sous la mutation

## 3. Démarrage sans code

- [ ] 3.1 Écrire `tests/Feature/MissingPasscodeTest.php` : avec `app.passcode` vide, `GET /up` et `GET /login` répondent 500 et le message journalisé (`Log::spy()` ou exception rapportée) contient `APP_PASSCODE` ; `Artisan::call('config:cache')` renvoie 0 avec `APP_PASSCODE` vide (puis `config:clear`) ; le voir rouge ; mutation nommée : contrôle dans `AppServiceProvider::boot()` au lieu de la pile HTTP (la commande échoue)
- [ ] 3.2 Écrire `EnsurePasscodeConfigured` en tête de la pile globale (D8) ; vérifier `make test-backend FILES=tests/Feature/MissingPasscodeTest.php` vert

## 4. Connexion, limiteur, durée, déconnexion

- [ ] 4.1 Écrire `tests/Feature/LoginTest.php` : `GET /login` sans session rend le composant `login` ; avec session, redirige vers `/` ; `POST /login` avec `birdie-2026` redirige vers `/`, puis `/` répond 200, et l'identifiant de session a changé ; `Birdie-2026` et la chaîne vide donnent l'erreur `passcode` « Code incorrect » et `/` redirige encore ; `passcode` n'est pas dans la session flash après un échec ; le voir rouge ; mutations nommées : `regenerate()` retiré, `passcode` absent de `dontFlash`
- [ ] 4.2 Écrire `LoginController`, `LoginRequest` (D6), les routes `login` (GET, POST, hors `RequirePasscode`) et `dontFlash` ; vérifier `make test-backend FILES=tests/Feature/LoginTest.php` vert
- [ ] 4.3 Écrire dans `LoginTest` le limiteur : 10 codes faux depuis `203.0.113.7`, puis le bon code donne « Trop d'essais. Réessaie dans une minute. » et aucune session ; le bon code depuis `203.0.113.8` ouvre la session ; après `travel(61)->seconds()`, le bon code depuis `203.0.113.7` ouvre la session ; le voir rouge ; mutations nommées : limite à 11, clé du limiteur sans l'IP ; implémenter ; vérifier vert
- [ ] 4.4 Écrire `tests/Feature/SessionLifetimeTest.php` sur le pilote `database` : connexion, `travel(89)->days()`, `/` répond 200, encore `travel(89)->days()` (178 jours après la connexion), `/` répond 200 ; une autre session laissée 91 jours sans requête est redirigée vers `/login` ; mutation nommée : `lifetime` à `120` ; vérifier vert, puis rouge sous la mutation
- [ ] 4.5 Écrire `tests/Feature/LogoutTest.php` : deux sessions `database` ouvertes ; `POST /logout` sur la première redirige vers `/login`, sa ligne disparaît, `/` la redirige ; la seconde ouvre toujours `/` ; `postJson('/logout')` sans session répond 401 ; le voir rouge ; mutation nommée : `invalidate()` remplacé par `DB::table('sessions')->delete()` ; écrire la route et l'action (D7) ; vérifier vert

## 5. Sécurité des requêtes

- [ ] 5.1 Écrire `tests/Feature/SecurityHeadersTest.php` : l'accueil avec session, `/login`, `/up`, `GET /api/version`, `GET /api/versions` (404) et la redirection de `/` sans session portent les quatre en-têtes aux valeurs littérales de `web-request-security` ; une route de test qui pose `Content-Security-Policy: default-src 'self'` la garde ; le voir rouge ; mutation nommée : middleware ajouté au groupe `web` au lieu de la pile globale (`/up` et `/api/versions` rouges) ; écrire `AddSecurityHeaders` (D9) ; vérifier vert
- [ ] 5.2 Écrire `tests/Feature/TrustedProxiesTest.php` avec une route de test qui renvoie `$request->ip()` : les quatre scénarios de `Mandataires de confiance` (`10.0.0.0/8`, mandataire inconnu, vide, `*`) avec les IP littérales ; le voir rouge ; mutation nommée : `*` gardé dans la liste ; écrire `config/trustedproxy.php` (D9) ; vérifier vert
- [ ] 5.3 Écrire dans `SessionStorageTest` le scénario « Ligne de session après connexion » : après `POST /login` sur le pilote `database`, `payload` ne contient ni `birdie-2026` ni `access.passcode` ni `_token` ; mutation nommée : `encrypt` à `false` ; vérifier vert, puis rouge sous la mutation

## 6. Interface

- [ ] 6.1 Écrire `resources/js/pages/login.test.tsx` (`<Form>` et `usePage` simulés au besoin) : titre « Handicaps golf », champ « Code d'accès » de type `password` avec `autocomplete="current-password"`, bouton « Entrer », aucun lien de section ; avec l'erreur `passcode` « Code incorrect », un `role="alert"` portant ce texte et le champ en `aria-invalid` relié par `aria-describedby` ; pendant `processing`, « Connexion… » dans la région d'attente et « Entrer » désactivé ; le voir rouge ; mutations nommées : bouton non désactivé pendant l'envoi, libellé « Connexion » sans « … »
- [ ] 6.2 Écrire `pages/login.tsx` et son layout nu (D11), par l'action Wayfinder de `LoginController::store` ; vérifier `make test-frontend FILES=resources/js/pages/login.test.tsx` vert, `make lint` vert
- [ ] 6.3 Écrire `resources/js/components/logout-button.test.tsx` : un bouton « Se déconnecter » dans un formulaire qui vise l'action Wayfinder `logout` en `POST` ; mutation nommée : méthode `get` ; porter `LogoutButton`, l'ajouter en bas de `home.tsx` ; compléter `HomePageTest` si la page reçoit une donnée nouvelle ; vérifier `make test-frontend` vert

## 7. Documentation

- [ ] 7.1 Écrire l'ADR 0007 (D13) et l'ajouter à `docs/adr/README.md`
- [ ] 7.2 Mettre à jour `ARCHITECTURE.md` (section « Accès » : fermé par défaut, exceptions, empreinte, en-têtes, mandataires), `.env.example` commenté, et les lignes de `CHANGELOG.md` sous `## [Unreleased]`
- [ ] 7.3 Faire signaler par `make setup` un `APP_PASSCODE` vide dans `.env` (message qui nomme la variable), sans écrire de valeur

## 8. Vérification finale

- [ ] 8.1 Lancer `make check` et le voir vert ; en cas d'échec, lire `reports/check/<étape>.log` et corriger dans le code
- [ ] 8.2 Passe navigateur : `make up`, `make dev` en arrière-plan, `make browser`, puis le skill `browser-use` sur `http://localhost`, à 390 et 1440 px, en clair puis en sombre : l'accueil sans cookie mène à `/login` sans navigation ; un code faux affiche « Code incorrect » sous le champ ; pendant l'envoi, « Connexion… » et « Entrer » désactivé (réseau ralenti) ; le bon code mène à l'accueil ; « Se déconnecter » ramène à `/login` et l'accueil y renvoie de nouveau ; `/login` connecté renvoie à l'accueil ; en-têtes de sécurité lus sur l'accueil ; console sans erreur ; rapporter ce qui a été vu et ce qui n'a pas pu l'être ; `make dev-stop`
- [ ] 8.3 Relire chaque scénario des trois deltas de specs et noter, pour chacun, le test ou l'étape de 8.2 qui le vérifie ; un scénario sans preuve se signale au mainteneur avant la PR de code ; finir par `make openspec ARGS="validate --all --strict"` vert

## Workflow follow-up

- Ouvrir la PR de code, avec ses lignes de `CHANGELOG.md`.
- Après la fusion, archiver le changement dans une PR de documentation séparée et passer l'entrée 3 de `ROADMAP.md` à « fait ».
