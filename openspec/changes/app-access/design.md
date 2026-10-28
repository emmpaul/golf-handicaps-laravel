# Design

## Context

- Toutes les routes de `routes/web.php` (`/` et les cinq sections) sont ouvertes. `routes/api.php` ne sert que `GET /api/version`, groupe `api` sans session. `/up` est déclaré par `withRouting(health: '/up')`, hors de tout groupe : ni session ni cookie.
- Sessions en base (`SESSION_DRIVER=database`) par `AnonymousDatabaseSessionHandler`, sans `user_id` (ADR 0002). `.env.example` a déjà `SESSION_ENCRYPT=true`, mais `config/session.php` garde `false` par défaut et `SESSION_LIFETIME=120`.
- Le groupe `web` de Laravel 13 vaut `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession`, `PreventRequestForgery` (le contrôle CSRF), `SubstituteBindings`, puis `HandleInertiaRequests` et `AddLinkHeadersForPreloadedAssets` ajoutés par `bootstrap/app.php`.
- `Illuminate\Http\Middleware\TrustProxies` est déjà dans la pile globale ; il lit `config('trustedproxy.proxies')` quand `proxies()` est vide. Ce fichier de configuration n'existe pas encore.
- `resources/js/app.tsx` pose `AppLayout` sur chaque page « sauf si elle déclare son propre layout ».
- Référence Next (`/Users/epaul/www/z_perso/golf-handicaps`) : `src/proxy.ts`, `src/lib/auth/access.ts` (liste exacte des chemins ouverts), `src/lib/auth/login.ts` (limiteur, messages), `src/app/login/` (page et formulaire), `src/components/logout-button.tsx`.

## Goals / Non-Goals

**Goals**

- Fermé par défaut : une route ajoutée au groupe `web` exige la session sans rien déclarer.
- Les requirements de `app-access`, `web-request-security` et l'attente « Connexion… » de `loading-feedback`.
- Aucun paquet ajouté.

**Non-Goals**

- HSTS, témoin `Secure`, valeur de production de `TRUSTED_PROXIES` : `production-deployment`.
- Tout écran ou toute donnée métier.

## Decisions

### D1. Le contrôle de session est dans le groupe `web`, avec des exceptions explicites

`App\Http\Middleware\RequirePasscode` est ajouté au groupe `web`. Les routes ouvertes s'en retirent par `->withoutMiddleware(RequirePasscode::class)` : `GET /login` et `POST /login` seulement. `/api/version` et `/up` n'entrent pas dans le groupe `web` et n'en ont pas besoin.

Une route de repli `Route::fallback()` dans `routes/web.php` répond 404 : elle passe par le groupe `web`, donc une adresse inconnue sans session redirige vers `/login` (scénario `/icon-admin`) et répond 404 avec session.

Le garde-fou « toute nouvelle route protégée » est un test Pest qui parcourt `Route::getRoutes()` et compare à une liste littérale les routes dont la pile de middleware résolue (`gatherMiddleware()`, exclusions comprises) ne contient pas `RequirePasscode` : `login` (GET, POST), `api.version`, `/up`, plus les routes de développement des paquets (`_boost/*`, `storage/{path}` si présentes) nommées une à une.

**Alternative écartée** : un groupe de routes protégées dans `routes/web.php`. Une route posée hors du groupe serait ouverte par oubli ; le test la verrait, mais le défaut serait ouvert.

### D2. Le contrôle passe avant le CSRF

Après une déconnexion, le navigateur garde un jeton CSRF que la nouvelle session ne connaît pas. Si `PreventRequestForgery` passait en premier, une écriture répondrait 419 au lieu de 401. `RequirePasscode` doit donc tourner après `StartSession` et avant `PreventRequestForgery`. Le moyen visé : `$middleware->appendToPriorityList(after: StartSession::class, append: RequirePasscode::class)` plus une entrée qui place `PreventRequestForgery` après lui ; l'API exacte de tri se vérifie par `search-docs` à l'apply, et le scénario « Jeton CSRF périmé après déconnexion » (401, pas 419) prouve l'ordre.

### D3. Réponse d'un refus

Dans `RequirePasscode`, sans session valide :

- requête `GET`/`HEAD` qui n'attend pas de JSON : `redirect('/login')` ;
- en-tête `X-Inertia` présent : `redirect('/login')`. Le middleware Inertia, qui tourne après dans la pile et reçoit la réponse au retour, change le 302 en 303 pour `PUT`/`PATCH`/`DELETE` ; le client Inertia suit et affiche `/login` sans fenêtre d'erreur ;
- sinon (écriture hors Inertia, ou `expectsJson()`) : `response()->json(['error' => 'Session requise'], 401)`.

L'action de la route n'est jamais appelée : rien n'est enregistré.

### D4. Empreinte du code dans la session

`App\Support\Passcode` (même place qu'`AppBuild`, hors domaine) :

- `fingerprint()` : `hash_hmac('sha256', config('app.passcode'), config('app.key'))` ;
- `matches(string $input)` : `hash_equals(fingerprint(), hash_hmac(…$input…))`. Comparer deux HMAC de même longueur ne révèle ni le code ni sa longueur par le temps de réponse.

À la connexion, la session reçoit `access.passcode` = `fingerprint()`. `RequirePasscode` accepte la session si `hash_equals(fingerprint(), session('access.passcode', ''))`. Quand `APP_PASSCODE` change (ou `APP_KEY`), l'empreinte change et toutes les sessions sont refusées (décision du mainteneur). Le code lui-même n'est jamais en session.

**Alternative écartée** : un booléen `access.granted`. Plus simple, mais un code changé laisserait entrer tous les appareils déjà connectés.

### D5. Durée glissante de 90 jours

`config/session.php` : `lifetime` à `env('SESSION_LIFETIME', 129600)`, `expire_on_close` à `false`. Le gestionnaire `database` de Laravel compare déjà `last_activity` à `lifetime` et pousse le cookie à chaque réponse : la durée glissante est le comportement natif, rien à coder. Les scénarios de durée se testent avec `$this->travel()` sur le pilote `database`, comme `SessionStorageTest`.

### D6. Connexion

- `App\Http\Controllers\LoginController` : `create()` rend la page Inertia `login` (ou redirige vers `/` si la session est valide), `store(LoginRequest)` ouvre la session.
- `App\Http\Requests\LoginRequest` : `passcode` `nullable|string` (le vide donne « Code incorrect », pas un message de validation générique), puis `authenticate()` sur le modèle des kits Laravel : `RateLimiter::tooManyAttempts('login:'.$this->ip(), 10)` → `ValidationException` « Trop d'essais. Réessaie dans une minute. » sur `passcode` ; sinon `RateLimiter::hit($key, 60)` puis `Passcode::matches()` → « Code incorrect ». Un succès ne remet pas le compteur à zéro : la limite compte les essais, pas les échecs, comme dans Next.
- Succès : `$request->session()->regenerate(true)` (l'ancienne ligne de session est supprimée), `put('access.passcode', …)`, `redirect('/')`.
- `passcode` est ajouté à `$dontFlash` (déjà le cas de `password` dans le gestionnaire d'exceptions) pour qu'un code faux ne revienne jamais en session flash.

### D7. Déconnexion

`POST /logout` (nom `logout`), protégé : `$request->session()->invalidate()` puis `regenerateToken()`, redirection vers `/login`. `invalidate()` supprime la seule ligne de ce navigateur ; les autres appareils gardent la leur.

### D8. Démarrage sans code

`App\Http\Middleware\EnsurePasscodeConfigured`, en tête de la pile globale (`$middleware->prepend()`), lève une `RuntimeException('APP_PASSCODE must be set: the shared passcode protects every page.')` quand `config('app.passcode')` est vide. Elle est journalisée et rend 500, `/up` compris : le healthcheck de Dokploy tombe. Les commandes artisan ne passent pas par la pile HTTP et tournent sans code, ce qu'exige le build de l'image (`config:cache`).

**Alternative écartée** : lever l'exception dans `AppServiceProvider::boot()`. Elle casserait `composer install`, `config:cache` et chaque commande artisan sans le secret.

### D9. En-têtes et mandataires

- `App\Http\Middleware\AddSecurityHeaders` ajouté à la pile globale (`$middleware->append()`), pour couvrir pages d'erreur, redirections, `/up` et JSON. Il pose les quatre en-têtes et ne remplace pas une `Content-Security-Policy` déjà présente.
- `config/trustedproxy.php` : `proxies` = liste tirée de `TRUSTED_PROXIES` (séparée par des virgules, espaces retirés, entrée `*` ou `**` retirée), `null` si la liste est vide. Rien dans `bootstrap/app.php`.

### D10. Sessions chiffrées

`config/session.php` : `encrypt` à `env('SESSION_ENCRYPT', true)`. Le chiffrement ne dépend plus d'un `.env` complet. Les sessions locales déjà ouvertes en clair deviennent illisibles : une reconnexion, sans conséquence avant la production.

### D11. Page `login`

- `resources/js/pages/login.tsx` déclare son propre layout (une page nue centrée, sans `AppNav`) et reprend la page Next : `BrandMark` + « Golf », titre « Handicaps golf », formulaire, `AppVersion` en bas.
- Formulaire par le composant `<Form>` d'Inertia et l'action Wayfinder de `LoginController::store` ; `processing` désactive « Entrer » et alimente `<Pending label="Connexion…" orb="send" />` ; l'erreur `passcode` s'affiche en `role="alert"` relié au champ par `aria-describedby`. API de `<Form>` vérifiée par `search-docs` à l'apply.
- `home.tsx` reçoit `LogoutButton` (formulaire `<Form>` vers `logout`, bouton variante `link`), en bas de la page.

### D12. Tests

- Assistant `TestCase::withPasscodeSession()` : `withSession(['access.passcode' => Passcode::fingerprint()])`. Les tests de pages existants l'appellent ; `SessionStorageTest` passe par `/login` ou par l'assistant selon ce qu'il vérifie.
- `phpunit.xml` : `APP_PASSCODE=birdie-2026`.
- Le scénario « Écriture d'une visite Inertia » utilise une route `POST` déclarée dans le test, sous le groupe `web`, dont l'action incrémente un compteur ; on vérifie la redirection et le compteur à zéro.
- Les scénarios de mandataires se testent avec `REMOTE_ADDR` et `X-Forwarded-For` fixés par `withServerVariables()` et une route de test qui renvoie `$request->ip()`.

### D13. ADR et documentation

- ADR 0007 « Accès par code partagé » : fermé par défaut dans le groupe `web`, empreinte HMAC du code en session (rotation = fin des sessions), durée glissante, refus de servir sans code. Index `docs/adr/README.md` à jour.
- `ARCHITECTURE.md` section « Accès » ; `.env.example` (`APP_PASSCODE=`, `SESSION_LIFETIME=129600`) ; `CHANGELOG.md` sous `## [Unreleased]`.
- `make setup` crée `.env` depuis `.env.example` : il signale un `APP_PASSCODE` vide au lieu d'en inventer un.

## Risks / Trade-offs

- **Ordre des middlewares** (D2) : le tri par priorité de Laravel peut changer d'une version à l'autre. Le test 401-pas-419 le garde.
- **Un seul code pour tous** : qui le connaît entre. Changer le code ferme tout (D4) ; c'est le seul moyen de retirer l'accès à une personne.
- **Limiteur par IP** : derrière un proxy mal déclaré, toutes les requêtes partagent l'IP du proxy et le groupe entier est bloqué après 10 essais. `TRUSTED_PROXIES` correct est la parade ; `production-deployment` en fixe la valeur.
- **Cache `database`** pour le limiteur : une requête de plus par essai, négligeable pour un groupe d'amis.
- **Sessions locales** : le passage à `encrypt=true` par défaut et l'empreinte demandent une reconnexion en local.

## Migration Plan

Aucune migration de base. Déploiement : ajouter `APP_PASSCODE` à l'environnement avant de déployer, sinon l'application répond 500 partout (voulu). Retour arrière : annuler la pull request ; les lignes de `sessions` restent valides ou sont ignorées.

## Open Questions

Aucune : durée glissante et fin des sessions au changement de code tranchées par le mainteneur le 2026-10-28.
