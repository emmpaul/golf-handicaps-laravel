# Design

## Context

État hérité du bootstrap (voir `proposal.md`, « Why ») :

- Kit React vierge, sans Fortify, `components/ui` ni mode sombre. `app/` ne contient que `Models/User.php`, `Http/Middleware/HandleInertiaRequests.php` (qui partage `auth.user`), `Http/Controllers/Controller.php` et `Providers/AppServiceProvider.php` (défauts de production de la couche 2.6 déjà posés).
- Migrations du kit encore en entiers : `users`, `password_reset_tokens`, `sessions.user_id` (`foreignId`).
- `phpstan.neon` (niveau 7, porté à 8 par ce changement) et `pint.json` sont déjà là, sans cible pour les lancer. `phpunit.xml` n'a que les suites `Unit` et `Feature`, sans `DB_CONNECTION=pgsql`.
- `vite.config.ts` n'a pas de bloc `test` et son lint n'a pas `typeCheck`. Aucune dépendance Vitest côté DOM (`jsdom`), ni jscpd, ni Paratest.
- Pas de `.github/`, pas de `Makefile`. Les scripts Composer concurrents sont retirés, mais `post-update-cmd` ne lance pas `boost:update`.
- PNPM 12.15.0 (`packageManager`), Node de l'hôte pour le hook `commit-msg`, Sail avec `laravel.test` et `pgsql` (port hôte 5433).

`docs/PORT_TO_LARAVEL.md` prime sur `SKELETON.md`. Les décisions du mainteneur du 2026-10-27 sont dans `proposal.md`.

## Goals / Non-Goals

**Goals:**

- Reprendre les couches 4, 5, 6 et 2.8 du squelette sans les réinterpréter : mêmes recettes, mêmes seuils, mêmes noms de cibles.
- Chaque nouvelle règle vérifiable par la machine a un test qui devient rouge quand la règle est enfreinte.

**Non-Goals:**

- Aucune règle métier, aucun modèle applicatif. `App\Models` est vide à la fin du changement.
- Pas de réglage de performance de la suite : elle compte quelques tests.

## Decisions

### D1. Makefile repris de la couche 5, sans `fresh` ni `demo`

Le Makefile reprend l'en-tête (`RUN` vide quand `CI=true`, sinon Sail), les recettes de `test-backend`, `lint`, `check`, `changed-code`, `dev-stop`, `build` et `browser` telles que le squelette les donne. `CHECK_STEPS := lint analyse duplicates test-backend test-frontend`. `fresh` et `demo` dépendent de `DemoSeeder` et arrivent avec `players-and-rounds`.

`DOCS_PATHS` ajoute `version.txt` à la liste blanche du squelette : `^([^/]+\.md|version\.txt|docs/.+|openspec/.+|\.ai/guidelines/.+)$` (décision 5).

`setup` installe `vendor` par l'image Composer éphémère de Sail quand `vendor/` manque (`--ignore-platform-reqs`, piège de la couche 1), puis enchaîne `up`, `composer install`, `key:generate` si la clé est vide, `pnpm install --frozen-lockfile`, `build`, `boost:update`, `migrate --force`.

Alternative écartée : scripts Composer ou `package.json`, deux interfaces concurrentes (couche 5, « Pourquoi »).

### D2. Suppression des comptes du kit

Décision 1 du mainteneur. Concrètement :

- La migration `0001_01_01_000000_create_users_table.php` est réécrite en place (aucune donnée réelle) et renommée `0001_01_01_000000_create_sessions_table.php` : elle ne crée plus que `sessions`, sans `user_id`.
- `App\Models\User`, `Database\Factories\UserFactory`, `resources/js/types/auth.ts` et l'appel de `DatabaseSeeder` à la fabrique sont supprimés. `DatabaseSeeder::run()` reste vide jusqu'à `players-and-rounds`.
- `HandleInertiaRequests::share()` ne partage plus `auth`.
- `config/auth.php` ne référence plus de modèle : le provider `users` et les réglages de réinitialisation de mot de passe sont retirés. Le garde `web` reste déclaré, sans provider utile, parce que le framework résout le contrat `Guard`.

Piège : `DatabaseSessionHandler::addUserInformation()` écrit `user_id` dès que le contrat `Guard` est résolu, ce qui est toujours le cas. Sans la colonne, chaque écriture de session échouerait. Le pilote `database` est donc remplacé, par `Session::extend('database', …)` dans `AppServiceProvider`, par une sous-classe `App\Http\Session\AnonymousDatabaseSessionHandler` qui n'ajoute pas `user_id` (et garde `ip_address`, `user_agent`). Le test « Session ouverte » de `persistence-identifiers` couvre ce chemin avec `SESSION_DRIVER=database` forcé dans le test.

Alternatives écartées : garder `users` en UUID comme le squelette (table morte) ; garder `sessions.user_id` seul (colonne morte qui suggère un lien vers une personne).

### D3. UUID par règle d'architecture

`App\Models` est vide : la règle `expect('App\Models')->classes()->toUseTrait(HasUuids::class)` passe à vide, ce que la couche 2.3 accepte (« elle devient rouge au premier import interdit »). Le scénario « Enregistrement créé » de `persistence-identifiers` sera prouvé par le premier modèle (`players-and-rounds`) ; ici, la preuve est la règle d'architecture, vérifiée par mutation (une classe temporaire `App\Models\Probe` sans `HasUuids` la rend rouge, puis est retirée).

### D4. Fuseau : `config('app.display_timezone')` et `App\Domain\LocalTime`

- `config/app.php` : `'timezone' => 'UTC'` (inchangé) et `'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Montreal')`. `.env.example` reçoit `APP_DISPLAY_TIMEZONE=America/Montreal`.
- `App\Domain\LocalTime`, classe finale à méthodes statiques, sans dépendance HTTP :
  - `format(CarbonInterface|string $instant, string $pattern = 'Y-m-d H:i'): string` : une chaîne est lue `CarbonImmutable::parse($raw, 'UTC')`, puis l'instant passe au fuseau d'affichage avant `format()`.
  - `date(CarbonInterface|string $instant): string` : raccourci `Y-m-d`.
  - `startOfDay(string $date): CarbonImmutable` : minuit local du jour `AAAA-MM-JJ`, rendu en UTC. La fin exclue d'un jour est `startOfDay` du lendemain.
- Tests Unit avec les littéraux de la spec (2026-11-01, 2026-03-08, 2026-07-15). Mutation qui les rend rouges : utiliser `config('app.timezone')` au lieu de `app.display_timezone`, ou `addHours(24)` pour la fin du jour.
- Vitest : `test.env.TZ = 'America/Montreal'` dans `vite.config.ts`. Premier test `resources/js/tests/timezone.test.ts` : `new Date('2026-11-02T04:30:00Z').getHours()` vaut `23` (heure normale), `new Date('2026-07-15T03:30:00Z').getHours()` vaut `23` (heure avancée) et `new Date('2026-11-02').getDate()` vaut `1`. Le nom du fuseau n'est pas vérifié, car ICU renomme `America/Montreal` en son alias canonique `America/Toronto`. Mutation : retirer `env.TZ` (le conteneur est en UTC, le test rougit).

Alternatives écartées : `app.timezone` local, cast par modèle, formatage côté client (couche 2.8, « Pourquoi »).

### D5. Version : `version.txt`, `APP_COMMIT` et route hors du groupe `web`

- `version.txt` à la racine contient `0.0.0` jusqu'à la première release (décision 4). `config/app.php` lit `'version' => trim((string) @file_get_contents(base_path('version.txt')))` ; `config:cache` la fige au build de l'image.
- `'commit' => env('APP_COMMIT')`. Le contrôleur rend `null` si la valeur ne correspond pas à `/^[0-9a-f]{40}$/`. L'image de `production-deployment` passera `APP_COMMIT` en argument de build.
- `routes/api.php`, enregistré par `withRouting(api: …)` : le groupe `api` de Laravel n'a ni session, ni cookie, ni CSRF, ce qui donne « sans `Set-Cookie` » sans code. Pas de Sanctum (`install:api` n'est pas lancé). `Route::get('/version', VersionController::class)` répond aussi à `HEAD` ; toute autre méthode reçoit 405 (décision 2).
- `App\Http\Controllers\VersionController` (invocable) rend `response()->json([...])->header('Cache-Control', 'no-store')`.
- `app-access` devra garder `/api/version` hors du middleware du code d'accès ; ce changement le note dans `ROADMAP.md`, entrée 3.
- Tests Feature : JSON exact avec littéraux, `Cache-Control`, absence de `Set-Cookie`, `HEAD`, 405 sur `POST`, base injoignable (`DB_HOST` pointé vers un hôte invalide dans le test). Test du fichier : `version.txt` correspond à `/^\d+\.\d+\.\d+\n?$/` ; si la version n'est pas `0.0.0`, `CHANGELOG.md` contient `## [<version>] - `.

### D6. Journal des changements tenu par les agents, tags manuels

Décision 3 : pas de release-please. `CHANGELOG.md` suit le format « Keep a Changelog », en français, avec les rubriques « Ajouté », « Modifié », « Retiré », « Corrigé ». Une section `## [Non publié]` en tête reçoit une ligne par effet visible de chaque PR de code, écrite dans cette PR. Pour publier, le mainteneur renomme la section en `## [X.Y.Z] - AAAA-MM-JJ`, met `X.Y.Z` dans `version.txt`, fusionne, puis pose le tag `vX.Y.Z` sur `main`. La règle entre dans `.ai/guidelines/golf-handicaps.md`, section « Journal des changements », puis `AGENTS.md` est régénéré.

Alternatives écartées : release-please avec `GITHUB_TOKEN` (PR de release sans CI) ou avec un PAT (secret à entretenir), au choix du mainteneur.

### D7. Contrôles de qualité

- `phpunit.xml` : suites `Unit`, `Feature`, `Arch` ; ajout de `DB_CONNECTION=pgsql` ; retrait de `PULSE_ENABLED`, `TELESCOPE_ENABLED`, `NIGHTWATCH_ENABLED` (paquets absents). `tests/Pest.php` : retrait de l'exemple `toBeOne` et de `something()`.
- `tests/TestCase.php` : `seed()` avec `ANALYZE` sur PostgreSQL (couche 4). Pas de `skipUnlessFortifyHas` (pas de Fortify).
- `tests/Arch/ArchitectureTest.php` : preset php, helpers de débogage, UUID des modèles, `App\Domain` sans `Illuminate\Http`, `Inertia`, `App\Http` ni `App\Integrations`, `App\Integrations` sans `App\Http` ni `Inertia`. Pas de règle des seeders (`DemoSeeder` n'existe pas encore).
- `tests/Feature/AgentGuidelinesTest.php` : garde d'`AGENTS.md` (couche 9), liste littérale des prescriptions interdites.
- `tests/Feature/MakefileHelpTest.php` : lit `Makefile`, extrait chaque cible publique (`^[a-z-]+:`), échoue en nommant une cible sans `## `. `wayfinder` est interne mais porte quand même sa description.
- `.jscpd.json` de la couche 4, sans exclusion propre au projet. `jscpd` épinglé en dépendance de développement PNPM.
- `brianium/paratest` en `require-dev` pour `--parallel`.
- `vite.config.ts` : `test` (`jsdom`, `include: ['resources/js/**/*.test.{ts,tsx}']`, `env.TZ`), `lint.options.typeCheck: true`, `fmt.ignorePatterns` complété (`AGENTS.md`, `boost.json`, `.mcp.json`, `openspec/**`, `CHANGELOG.md`). Pas de `setupFiles` ni de Testing Library : ils arrivent avec les premiers tests de composants (`visual-theme-port`).
- Les deux `ExampleTest` du kit sont retirés : celui d'Unit ne peut pas devenir rouge ; celui de Feature est réécrit en Pest (`tests/Feature/HomePageTest.php`, la page d'accueil répond 200), qui sert aussi de garde du manifeste Vite en CI.

### D8. CI de la couche 6, sans release-please

`.github/workflows/ci.yml` (jobs `changes`, `static`, `test-frontend`, `test-backend`, `report`), `.github/actions/setup/action.yml`, `.github/workflows/zizmor.yml`, `.github/zizmor.yml`, `.github/dependabot.yml`, tels que la couche 6 les décrit. Adaptations :

- `pnpm/action-setup` lit la version dans `packageManager` (12.15.0) ; `actions/setup-node` en Node 22 avec `cache: pnpm`.
- Chaque action épinglée par sha relevé au moment de l'apply, version en commentaire.
- `env` du workflow : `CI: true` (le Makefile n'appelle pas Sail) et `SKIP_INSTALL_SIMPLE_GIT_HOOKS: 1` (pas de hook écrit dans le runner). `make test-frontend` préfixe `LARAVEL_BYPASS_ENV_CHECK=1`, sinon `laravel-vite-plugin` refuse de charger `vite.config.ts` sous `CI=true`.
- L'action `setup` met en cache les archives Composer (jamais `vendor/`), lance `composer install`, `pnpm install --frozen-lockfile`, puis `cp .env.example .env && php artisan key:generate`. `static` et `test-frontend` n'ont pas de service PostgreSQL : `wayfinder:generate` et PHPStan n'ouvrent pas de connexion.
- `static` lance aussi `make openspec ARGS="validate --all --strict"` (couche 8) : une PR de proposition ou d'archive est alors vérifiée sans lancer les tests.
- `upload-artifact` avec `if: always()` et `if-no-files-found: error` ; `report` par `mikepenz/action-junit-report` (`check_name: Tests`, `require_tests: true`, `fail_on_failure: false`), seul job avec `checks: write`.
- zizmor : `version:` épinglée dans `zizmor.yml` (Dependabot ne la relève pas, piège de la couche 6), `advanced-security: false`, règle `self-repository` désactivée dans `.github/zizmor.yml`.
- Pas de job de release, pas de publication d'image : `production-deployment` ajoutera la publication GHCR, déclenchée par le tag `vX.Y.Z`.

Le dépôt est public : la protection de `main` et les checks obligatoires sont disponibles, mais leur réglage reste une action du mainteneur dans GitHub, hors du dépôt.

### D9. ADR

Écrites dans le même lot que le code, chacune avec sa ligne dans `docs/adr/README.md` :

- `0001-monolithe-modulaire.md` : couche 2.3. Structure `app/Domain/<Domaine>` (`Actions/` une classe par écriture avec `handle()` et transaction, `Queries/` une classe par lecture complexe, `Exceptions/` sous-classes de `DomainException`), `app/Integrations`, contrôleurs minces, règles d'architecture. `ARCHITECTURE.md` reprend ces conventions en quelques lignes et renvoie à l'ADR.

Ce changement fixe les conventions et les règles d'architecture, pas leur code : aucune classe `Action` ni `Query` n'est créée, et `App\Domain\DomainException` avec son rendu dans `bootstrap/app.php` arrivent avec la première Action qui lève une erreur métier (`players-and-rounds`). Un dossier de domaine naît avec son premier code (couche 2.3), et le rendu Inertia passe par un toast que `visual-theme-port` fournit.
- `0002-postgresql-et-uuid.md` : PostgreSQL partout (local, tests, CI), UUID v7, tables techniques exclues.
- `0003-openspec-tdd-makefile.md` : chaque changement par OpenSpec, TDD avec test qui peut rougir, Makefile interface unique.
- `0004-utc-et-fuseau-affichage.md` : D4.
- `0005-ecarts-au-squelette.md` : pas de Fortify, de comptes ni de rôles (tables du kit supprimées, D2) ; pas de Mailpit ; worker de file dès le départ en production ; pas de tests navigateur ; couche 12 remplie par le projet ; versionnement manuel avec `CHANGELOG.md` au lieu de release-please (D6).

## Risks / Trade-offs

- [Le remplacement du pilote de session `database` diverge du framework] → Sous-classe minimale qui ne surcharge qu'`addUserInformation()` ; un test écrit une vraie session en base. À relire à chaque version majeure de Laravel.
- [La règle UUID passe à vide tant qu'aucun modèle n'existe] → Mutation vérifiée pendant l'apply avec une classe temporaire ; le premier modèle la rendra effective.
- [Un agent oublie la ligne de `CHANGELOG.md`] → Consigne dans `AGENTS.md` et revue de PR. Aucun contrôle automatique : décider d'une ligne utile n'est pas vérifiable par la machine.
- [`version.txt` classé comme documentation : une PR qui ne change que lui saute les tests] → Le test du format de `version.txt` tourne à la PR de code suivante ; la valeur n'est lue qu'au build.
- [`make browser` dépend du Chrome de l'hôte] → Message explicite et variable `CHROME` ; la cible ne sert pas en CI.
- [La première exécution de la CI sur ce dépôt peut révéler des écarts de lint déjà présents dans le kit] → Corrigés dans le code pendant l'apply, jamais par un seuil relevé.

## Migration Plan

1. Fusion de la PR de code. En local, après `git pull` : `vendor/bin/sail artisan migrate:fresh` une fois, la migration initiale ayant changé.
2. Le mainteneur active, s'il le souhaite, la protection de `main` avec les checks `static` et `Tests`.
3. Retour arrière : revenir sur le commit de fusion, puis `migrate:fresh` en local. Aucune donnée réelle n'existe.
