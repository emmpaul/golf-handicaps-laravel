# Architecture

Monolithe Laravel 13 + Inertia 3 + React 19, PostgreSQL 18, monté selon le squelette Laravel du mainteneur. Chaque décision d'architecture a son ADR sous `docs/adr/` (index dans `docs/adr/README.md`) ; les écarts au squelette sont dans l'ADR 0005.

## Structure (ADR 0001)

- Code métier sous `app/Domain/<Domaine>`, indépendant de HTTP, d'Inertia et des intégrations :
    - `Actions/` : une classe par écriture, méthode `handle()`, qui ouvre sa transaction ;
    - `Queries/` : une classe par lecture complexe ;
    - `Exceptions/` : sous-classes de `App\Domain\DomainException`, avec un message pour la personne qui utilise l'application. Elles sont rendues au même endroit pour toute l'application, dans `bootstrap/app.php`. La classe et son rendu arrivent avec la première Action qui lève une erreur métier.
- Un dossier de domaine naît avec son premier code, jamais vide d'avance.
- Intégrations tierces (IA, Simulator Golf Tour) sous `app/Integrations`, sans dépendance vers `App\Http` ni Inertia ; le domaine ne les appelle pas.
- Contrôleurs minces : Form Request, puis une Action ou une Query, puis la réponse.
- Les frontières sont des règles d'architecture Pest (`tests/Arch/ArchitectureTest.php`, `make arch-test`).

## Données (ADR 0002)

- PostgreSQL partout : local, tests (base `testing`) et CI.
- Chaque modèle de `App\Models` utilise `HasUuids` (UUID v7) ; chaque clé étrangère applicative est un `foreignUuid` avec contrainte. Les tables techniques du framework gardent leurs identifiants.
- Aucune table de comptes. Sessions, cache et files en base, sans Redis. Un worker de file tourne dès le départ : l'import IA et la recherche SGT l'exigent.

## Heures et fuseau (ADR 0004)

- `app.timezone` reste `UTC`. Chaque instant envoyé à une page passe par `App\Domain\LocalTime`, qui le convertit vers `app.display_timezone` (`America/Montreal`).
- Les bornes d'un jour se calculent en heure locale (`LocalTime::startOfDay`) puis se ramènent en UTC.

## Accès (ADR 0007)

- Code d'accès partagé (`APP_PASSCODE`, lu par `config('app.passcode')`) et session, sans comptes ni rôles.
- Fermé par défaut : `RequirePasscode` est dans le groupe `web` (`bootstrap/app.php`). Une route ajoutée à `routes/web.php` exige la session sans rien déclarer.
- Exceptions : `GET` et `POST /login` s'en retirent par `withoutMiddleware()`. `GET /api/version` (groupe `api`), `/up` et les fichiers statiques de `public/` sont hors du groupe `web`. `tests/Feature/ProtectedRoutesTest.php` compare la liste des routes ouvertes à une liste littérale : ouvrir une route demande de la mettre à jour.
- Adresse inconnue : la route de repli de `routes/web.php` répond 404 avec session et redirige vers `/login` sans session. Sous `api/`, 404 dans tous les cas.
- Refus : une lecture de page ou une visite Inertia est redirigée vers `/login` (303 pour une visite `PUT`/`PATCH`/`DELETE`, par le middleware global `EnsureGetOnRedirect` d'Inertia), toute autre requête reçoit 401 `{ "error": "Session requise" }`. `RequirePasscode` tourne avant `PreventRequestForgery` : un jeton CSRF périmé donne 401, pas 419.
- Empreinte : `App\Support\Passcode::fingerprint()` est le HMAC SHA-256 du code, avec `APP_KEY` pour clé. La connexion (`LoginController::store`) régénère l'identifiant de session et y range cette empreinte sous `access.passcode`, jamais le code. Changer `APP_PASSCODE` ou `APP_KEY` ferme toutes les sessions.
- Sessions en base, chiffrées (`encrypt` à `true` par défaut), 90 jours glissants (`SESSION_LIFETIME=129600` minutes). `POST /logout` ferme la session de ce navigateur seulement.
- Limiteur : 10 essais par minute et par IP sur `POST /login` (`LoginRequest::authenticate`), réussites comprises.
- Sans `APP_PASSCODE`, `EnsurePasscodeConfigured`, premier contrôle de la pile globale, fait répondre 500 à toute requête HTTP, `/up` compris, avec un message qui nomme la variable. Les commandes artisan tournent sans elle.
- En-têtes : `AddSecurityHeaders`, en tête de la pile globale, juste avant `EnsurePasscodeConfigured`, pose `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` et `Content-Security-Policy: frame-ancestors 'none'` sur toute réponse, erreurs (le 500 d'un code manquant aussi), redirections, `/up` et JSON compris. Une réponse qui pose sa propre `Content-Security-Policy` la garde.
- Mandataires : `config/trustedproxy.php` lit `TRUSTED_PROXIES` (adresses ou plages CIDR séparées par des virgules) pour le `TrustProxies` de Laravel. Vide, aucun mandataire n'est de confiance ; `*` et `**` sont retirés. Le limiteur voit ainsi l'IP du client derrière le proxy de Dokploy.
- Hors périmètre jusqu'à `production-deployment` : HSTS, témoin de session `Secure` et valeur de production de `TRUSTED_PROXIES`.

## Frontend (ADR 0006)

- Inertia 3 + React 19, sans rendu serveur. Pages sous `resources/js/pages`, sans fichier de test : le plugin Vite d'Inertia emballe tout `.tsx` de ce dossier comme page, les tests de page vont sous `resources/js/tests/pages` ; composants sous `resources/js/components`, primitives base-ui possédées par le projet sous `resources/js/components/ui`. Thème, tokens et règles d'interface : `DESIGN.md`.
- `resources/js/layouts/app-layout.tsx` est le layout par défaut de chaque page (option `layout` de `createInertiaApp` dans `app.tsx`) : navigation (`AppNav`), `main`, ligne de version (`AppVersion`) en dernier enfant de `main`, puis `Toaster`.
- Données partagées par `HandleInertiaRequests::share()` : `name` et `app: { version, commit }`, typées dans `resources/js/types/global.d.ts`. `App\Support\AppBuild` lit la version et valide le commit (sha de 40 caractères hexadécimaux minuscules, sinon `null`) ; `VersionController` passe par la même classe.
- Messages éphémères par le flash Inertia : clé `toast`, `{ type: 'error' | 'success', message }`, typée par `flashDataType`. Le serveur l'émet par `Inertia::flash('toast', …)` ; le layout l'écoute par `router.on('flash', …)` et `showFlashToast` (`resources/js/lib/flash-toast.ts`) l'affiche. Aucun code serveur ne l'émet encore : le rendu de `DomainException` l'utilisera.
- Routes : `/` sert `home`. `/import`, `/rounds`, `/handicaps`, `/strokes`, `/players` et leurs sous-adresses servent `section-placeholder` par `Route::inertia`, sous les noms `<section>.index`. La navigation lit leurs adresses dans les fonctions Wayfinder ; chaque changement de section remplace la route sous le même nom.
- Section courante : `inSection` et `SECTION_PREFIXES` (`resources/js/lib/sections.ts`) rattachent `/imports/…` à Importer et `/courses/…` à Rounds.
- `public/manifest.webmanifest`, `public/icons/` et `public/apple-touch-icon.png` sont statiques, servis par le serveur web sans session. `php artisan app:icons` régénère les PNG.

## Outillage (ADR 0003)

- Chaque changement passe par OpenSpec, en TDD.
- Le Makefile est l'interface unique, en local et en CI : `make` liste les cibles, `make check` lance tous les contrôles.
- Version tenue à la main dans `version.txt`, changements notés dans `CHANGELOG.md` par chaque pull request de code (ADR 0005).
