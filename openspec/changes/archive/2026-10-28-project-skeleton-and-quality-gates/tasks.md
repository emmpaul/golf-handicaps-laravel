# Tasks

Avant de commencer : `ROADMAP.md` entrée 0 (bootstrap) est faite ; relire `proposal.md` (décisions du mainteneur), `design.md` et les six deltas de specs. Tant que le Makefile n'existe pas (groupe 2), passer par `vendor/bin/sail`.

## 1. Dépendances et configuration des outils

- [x] 1.1 Ajouter `brianium/paratest` en `require-dev` et `@php artisan boost:update --ansi` à `post-update-cmd` dans `composer.json` ; vérifier que `vendor/bin/sail composer validate` passe et que `vendor/bin/sail composer show brianium/paratest` répond
- [x] 1.2 Ajouter `jscpd` et `jsdom` en dépendances de développement par `vendor/bin/sail pnpm add -D` ; vérifier que `pnpm-lock.yaml` les contient et que `vendor/bin/sail pnpm install --frozen-lockfile` passe
- [x] 1.3 Créer `.jscpd.json` de la couche 4 (D7) ; vérifier que `vendor/bin/sail pnpm exec jscpd` sort en code 0 sur le dépôt actuel
- [x] 1.4 Mettre à jour `phpunit.xml` (suite `Arch`, `DB_CONNECTION=pgsql`, retrait des variables Pulse, Telescope, Nightwatch) et `tests/Pest.php` (retrait des exemples) ; vérifier que `vendor/bin/sail php vendor/bin/pest --parallel` passe
- [x] 1.5 Compléter `vite.config.ts` (bloc `test` avec `jsdom`, `include` et `env.TZ`, `lint.options.typeCheck`, `fmt.ignorePatterns` de D7) ; vérifier que `vendor/bin/sail pnpm exec vp check` passe

## 2. Makefile

- [x] 2.1 Écrire le test `tests/Feature/MakefileHelpTest.php` (chaque cible publique a une description `##`) et le voir rouge faute de Makefile ; mutation nommée : une cible sans `##`
- [x] 2.2 Écrire le `Makefile` de D1 (en-tête `RUN`, toutes les cibles de la proposition sauf `fresh` et `demo`, `DOCS_PATHS` avec `version.txt`) ; vérifier que `make` affiche l'aide en français et que `make test-backend FILES=tests/Feature/MakefileHelpTest.php` passe
- [x] 2.3 Vérifier à la main les scénarios de `quality-gates` qui portent sur le Makefile : `make lint` sur un fichier PHP et un TSX volontairement mal formatés (les deux nommés, `git status` propre), `make format` puis `make lint` vert, `make check` avec une duplication volontaire (les cinq étapes lancées, fin du log affichée), `make test-backend SHARD=1/4 FILES=…` (code 2), `make test-backend SHARD=1/2` puis `2/2` ; revenir sur chaque fichier modifié
- [x] 2.4 Vérifier `make changed-code` sur des plages construites dans une branche jetable : `openspec/` + `ROADMAP.md` (`false`), `version.txt` + `CHANGELOG.md` (`false`), `resources/js/README.md` (`true`), déplacement `app/Foo.php` vers `docs/` (`true`), `.github/workflows/ci.yml` (`true`) ; supprimer la branche
- [x] 2.5 Vérifier `make dev`, `make dev-stop` (pendant un `make test-frontend` qui va au bout, puis sans rien à arrêter), `make build` avec un `public/hot` périmé, `make browser` (deux lancements, un seul Chrome sur 9333), `make openspec ARGS="validate --all --strict"`, `make migrate`

## 3. Suppression des comptes et identifiants UUID

- [x] 3.1 Écrire `tests/Feature/SessionStorageTest.php` : après migrations, `users` et `password_reset_tokens` n'existent pas, `sessions` n'a pas `user_id`, et une requête web avec `SESSION_DRIVER=database` écrit une ligne de session ; le voir rouge sur le schéma actuel
- [x] 3.2 Réécrire la migration initiale en `0001_01_01_000000_create_sessions_table.php` (sessions seules, sans `user_id`), supprimer `App\Models\User`, `UserFactory`, `resources/js/types/auth.ts`, le partage `auth` de `HandleInertiaRequests` et le contenu de `DatabaseSeeder::run()` ; nettoyer `config/auth.php` (D2)
- [x] 3.3 Ajouter `App\Http\Session\AnonymousDatabaseSessionHandler` et son enregistrement dans `AppServiceProvider` (D2) ; vérifier que `make test-backend FILES=tests/Feature/SessionStorageTest.php` passe et que `make analyse` passe
- [x] 3.4 Écrire `tests/Arch/ArchitectureTest.php` (règles de D7) ; vérifier `make arch-test` vert, puis la mutation : une classe temporaire `App\Models\Probe` sans `HasUuids` et un `dump()` temporaire dans `app/` rendent la suite rouge ; retirer les deux
- [x] 3.5 Remplacer les `ExampleTest` du kit par `tests/Feature/HomePageTest.php` (la page d'accueil répond 200) ; ajouter `TestCase::seed()` avec `ANALYZE` ; vérifier `make test-backend`
- [x] 3.6 Écrire `docs/adr/0002-postgresql-et-uuid.md` et sa ligne dans `docs/adr/README.md` ; puis `make migrate` ne suffit pas en local : lancer `vendor/bin/sail artisan migrate:fresh` et vérifier que `http://localhost` répond

## 4. Heures et fuseau d'affichage

- [x] 4.1 Écrire `tests/Unit/LocalTimeTest.php` avec les littéraux de `date-time-presentation` (2026-11-02T04:30Z, 2026-07-15T03:30Z, chaîne brute, bornes des 2026-07-15, 2026-11-01 et 2026-03-08) et le voir rouge faute de classe
- [x] 4.2 Ajouter `app.display_timezone` à `config/app.php` et `APP_DISPLAY_TIMEZONE` à `.env.example`, puis `App\Domain\LocalTime` (D4) ; vérifier `make test-backend FILES=tests/Unit/LocalTimeTest.php` vert, puis la mutation `app.timezone` au lieu de `app.display_timezone` qui le rend rouge
- [x] 4.3 Écrire `resources/js/tests/timezone.test.ts` (D4) ; vérifier `make test-frontend` vert, puis rouge sans `env.TZ`
- [x] 4.4 Écrire `docs/adr/0004-utc-et-fuseau-affichage.md` et sa ligne dans `docs/adr/README.md`

## 5. Version et journal des changements

- [x] 5.1 Écrire `tests/Feature/VersionRouteTest.php` (JSON exact, `Cache-Control: no-store`, sans `Set-Cookie`, `HEAD`, 405 sur `POST`, commit mal formé, base injoignable) et le voir rouge faute de route
- [x] 5.2 Créer `version.txt` (`0.0.0`), les clés `app.version` et `app.commit`, `routes/api.php` enregistré dans `bootstrap/app.php`, `VersionController` (D5) ; ajouter `APP_COMMIT=` à `.env.example` ; vérifier `make test-backend FILES=tests/Feature/VersionRouteTest.php` vert
- [x] 5.3 Écrire `tests/Feature/VersionFileTest.php` (format de `version.txt`, section de `CHANGELOG.md` pour toute version autre que `0.0.0`) ; vérifier rouge avec `v0.1` puis avec `0.2.0` sans section, vert avec `0.0.0`
- [x] 5.4 Créer `CHANGELOG.md` (format de D6, section `## [Non publié]` qui décrit ce changement) ; ajouter la section « Journal des changements » (D6) à `.ai/guidelines/golf-handicaps.md`

## 6. Consignes d'agent et documentation

- [x] 6.1 Écrire `tests/Feature/AgentGuidelinesTest.php` (liste littérale de `quality-gates`) ; vérifier vert, puis rouge en supprimant `.ai/guidelines/pint/core.blade.php` et en relançant `boost:update` ; restaurer
- [x] 6.2 Mettre à jour `.ai/guidelines/golf-handicaps.md` (le Makefile existe ; journal des changements), puis `vendor/bin/sail artisan boost:update` ; vérifier que `make test-backend FILES=tests/Feature/AgentGuidelinesTest.php` passe
- [x] 6.3 Écrire `docs/adr/0001-monolithe-modulaire.md`, `0003-openspec-tdd-makefile.md` et `0005-ecarts-au-squelette.md` (D9), avec leurs lignes dans `docs/adr/README.md`
- [x] 6.4 Compléter `ARCHITECTURE.md` (conventions Actions, Queries, exceptions de domaine, fuseau, UUID, renvoi aux ADR), remplacer le défaut « Versionnement » de `docs/PORT_TO_LARAVEL.md` et l'entrée 1 de `ROADMAP.md` (release-please retiré), et noter à l'entrée 3 que `/api/version` reste hors du code d'accès

## 7. Intégration continue

- [x] 7.1 Créer `.github/actions/setup/action.yml` et `.github/workflows/ci.yml` (D8), actions épinglées par sha avec version en commentaire, `persist-credentials: false`, `timeout-minutes` de `ci-pipeline`, `CI` et `SKIP_INSTALL_SIMPLE_GIT_HOOKS`, validation OpenSpec dans `static`, artefacts JUnit et job `report` de D8 ; vérifier localement avec `make lint` (fichiers YAML exclus du formateur)
- [x] 7.2 Créer `.github/workflows/zizmor.yml`, `.github/zizmor.yml` et `.github/dependabot.yml` (préfixe `ci`, `include: scope`, délai de 7 jours, groupe unique)
- [x] 7.3 Pousser la branche et vérifier sur la PR : `changes` sort `code=true`, `static`, `test-frontend`, `test-backend` et `report` passent, zizmor passe ; surveiller par `gh run list --commit <sha> --json status,conclusion` jusqu'à `completed`

## 8. Vérification finale

- [x] 8.1 Lancer `make check` et le voir vert ; en cas d'échec, lire `reports/check/<étape>.log` et corriger dans le code
- [x] 8.2 Passe navigateur : `make up`, `make dev` en arrière-plan, `make browser`, puis le skill `browser-use` sur `http://localhost` (page d'accueil, console sans erreur) et sur `http://localhost/api/version` (JSON `{"version":"0.0.0","commit":null}`) ; rapporter ce qui a été vu ; `make dev-stop`
- [x] 8.3 Relire chaque scénario des six deltas de specs et noter, pour chacun, le test ou la vérification manuelle qui le couvre ; signaler tout scénario non vérifié (ceux de Dependabot et de l'annulation des exécutions se vérifient sur GitHub après fusion)

## Workflow follow-up

- PR de code `feat/project-skeleton-and-quality-gates` vers `main`, CI verte, fusion par rebase.
- Après la fusion : `migrate:fresh` en local, puis archivage sur `docs/archive-project-skeleton-and-quality-gates` dans une PR de documentation séparée (`ROADMAP.md` entrée 1 à « fait »).
