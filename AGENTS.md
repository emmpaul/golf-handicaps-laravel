<laravel-boost-guidelines>
=== .ai/golf-handicaps rules ===

# Golf Handicaps

Ces règles priment sur les consignes génériques de Boost.

## Sources de vérité

- Lire `PRD.md`, `ARCHITECTURE.md`, `DESIGN.md`, `ROADMAP.md` et `docs/PORT_TO_LARAVEL.md` avant de planifier un changement.
- L'application est le port de l'application Next.js `golf-handicaps`. Ses specs OpenSpec sont la référence de comportement, sauf ce que `docs/PORT_TO_LARAVEL.md` retire.

## OpenSpec

- Chaque changement, correctif ou mise à jour d'outillage compris, passe par un changement OpenSpec.
- La CLI est épinglée dans `package.json` : `make openspec ARGS="…"` (par exemple `ARGS="validate --all --strict"`). Jamais d'installation globale.
- Une règle métier non tranchée, une tâche ambiguë ou un conflit avec `design.md` arrête l'apply jusqu'à la décision du mainteneur.

## ADR

- Une décision d'architecture s'écrit en ADR numérotée sous `docs/adr/`, dans le même lot que le code qu'elle décide, avec sa ligne dans `docs/adr/README.md`.

## Tests

- TDD : RED, GREEN, REFACTOR. Un test mérite sa place s'il devient rouge quand le code de production est faux. Nommer la mutation qui le fait échouer.
- Valeurs attendues en littéraux tirés de la spec, jamais recalculées par le code testé. Comparaisons par valeur.
- Aucun test navigateur ou E2E versionné.

## Makefile

- Le Makefile est l'interface unique, en local et en CI. Tests visés : `make test-backend FILES=…` ou `FILTER=…`. Après un `make check` rouge, lire `reports/check/<étape>.log`.
- `make` sans argument liste les cibles. `vendor/bin/sail` sert seulement pour une commande qu'aucune cible ne couvre.

## Données

- Tout nouveau modèle arrive avec sa fabrique, ses états et son seeder de démonstration.
- Identifiants UUID partout (`HasUuids`, `foreignUuid`).

## Accès

- Un code d'accès partagé et une session protègent l'application. Pas de comptes, pas de rôles, pas de Fortify.

## Heures et fuseau

- `app.timezone` reste `UTC`. Chaque instant envoyé à une page passe par la classe de conversion vers `app.display_timezone` (`America/Montreal`).

## PNPM

- PNPM seulement, dans le conteneur : `vendor/bin/sail pnpm …`. Jamais npm.

## Messages de commit

- Conventional Commits : `type(scope): sujet`, sujet sans majuscule initiale ni point final. Scope facultatif, pris dans la liste fermée de `commitlint.config.js`.
- Corps : 100 caractères au plus par ligne, coupés à la main. C'est la première cause de rejet par le hook.
- Branches `<type>/<change>`, créées avec `git switch -c <nom> --no-track`. Jamais `--no-verify`.

## Journal des changements

- Chaque pull request de code inscrit, dans le même lot, chacun de ses changements sous `## [Unreleased]` de `CHANGELOG.md` : fonctionnalité, outillage, CI, dépendances, consignes d'agent, suppression. Une ligne par changement, rangée sous `### Added`, `### Changed`, `### Removed` ou `### Fixed`. Format Keep a Changelog, en anglais.
- Une pull request de documentation seule (proposition ou archive OpenSpec) n'y touche pas.
- Une version se publie seulement par le skill `/release`, que le mainteneur lance. `tests/Feature/VersionFileTest.php` exige la section de toute version autre que `0.0.0`.

## Langue

- Prose en français : interface, documentation, artefacts OpenSpec, ADR. Identifiants de code en anglais. Exception : `CHANGELOG.md` s'écrit en anglais.

## Interface

- L'interface suit `DESIGN.md` (design « Golf handicap app redesign ») : thème sombre seul, palette fermée de `resources/css/tokens.css`, primitives possédées par le projet dans `components/ui`. Chaque couleur, taille de texte, rayon ou ombre vient d'un token.

## Boost et fichiers générés

- `AGENTS.md` est généré par `vendor/bin/sail artisan boost:update` depuis `.ai/guidelines/`. Ne jamais le modifier à la main. Pas de `CLAUDE.md` dans le dépôt.

## Passe navigateur

- En fin d'apply, après `make check` : `make up`, `make dev`, `make browser`, puis le skill `browser-use` sur `http://localhost`. Lire la console, rapporter ce qui a été vu, finir par `make dev-stop`.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `vendor/bin/sail composer show --direct` to list direct dependencies with versions, or `vendor/bin/sail composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `make build`. For hot reload, run `make dev` and stop it with `make dev-stop`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- This application deploys as a docker-compose stack on Dokploy, with one image published to GHCR by the CI. It does not use Laravel Cloud or Forge. See `docs/PORT_TO_LARAVEL.md` until `docs/deployment.md` exists.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. The Makefile is the command interface: run `make help` and use its target when one exists (`make up`, `make down`, `make test`, `make lint`, `make check`, `make build`, `make dev`).
- For a command without a target, go through Sail: prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP or TypeScript files, run `make format` before finalizing changes, then `make lint` to confirm nothing is left.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `vendor/bin/sail artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run tests through the Makefile only. Run the narrowest set that covers the change: `make test-backend FILES=tests/Feature/SomeTest.php` or `make test-backend FILTER=testName`.
- Rerun a test after each change to it.
- Before declaring work done, run `make check` yourself. When a step fails, read `reports/check/<step>.log`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
