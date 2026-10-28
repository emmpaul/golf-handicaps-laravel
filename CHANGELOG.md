# Changelog

All notable changes to this application, version by version. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The current version is in `version.txt`.

## [Unreleased]

### Added

- Makefile at the repository root, the single command interface locally and in CI: `make help` lists every target.
- Local environment targets: `make setup` for a fresh clone, `make up` and `make down` for the containers, `make dev` and `make dev-stop` for Vite, `make browser` for a throwaway headless Chrome used by the browser pass, `make migrate` for pending migrations.
- Tooling targets: `make openspec` for the pinned OpenSpec CLI, `make commitlint` to check the commit messages of a range, `make changed-code` to tell whether a range touches anything beyond documentation.
- Quality checks: Pint, Larastan level 8, jscpd, Pest tests in parallel on PostgreSQL, architecture tests and Vitest under the `America/Montreal` timezone, run in one pass by `make check`.
- Guard tests: every Makefile target has a French help line, `AGENTS.md` prescribes no command that bypasses the Makefile, `version.txt` holds a `MAJOR.MINOR.PATCH` version and every released version has its section in this changelog.
- GitHub Actions continuous integration, workflow audit by zizmor and action updates by Dependabot.
- UUID v7 identifiers enforced on every model by an architecture rule.
- Display timezone `America/Montreal`, separate from UTC storage, and the `App\Domain\LocalTime` conversion class.
- `LocalTime::startOfDay` rejects a day that does not exist.
- Anonymous database session handler: a session is written without a user identifier.
- `GET /api/version` route, which returns the version and the sha of the built commit, without session or database access.
- `version.txt` file and this changelog, both maintained by hand.
- Development dependencies Paratest, jscpd and jsdom.
- OpenSpec specs for six capabilities under `openspec/specs/`: `app-version`, `ci-pipeline`, `date-time-presentation`, `local-development-environment`, `persistence-identifiers` and `quality-gates`.
- ADRs 0001 to 0005 under `docs/adr/`.
- Scoreboard theme from the Next.js app: light and dark tokens in `resources/css/tokens.css`, closed palette (Tailwind default colours, text sizes, radii and shadows dropped), shadcn variables mapped to the tokens, dark mode that follows the device setting.
- Self-hosted fonts Sofia Sans Condensed and IBM Plex Sans from the `@fontsource` packages.
- Base UI primitives copied from the Next.js app under `resources/js/components/ui/` (`alert-dialog`, `alert`, `badge`, `button`, `calendar`, `field`, `input`, `label`, `native-select`, `popover`, `separator`, `table`) and `components.json`, with 44 px touch targets and 16 px field text under 768 px.
- Theme components `BrandMark`, `PageTitle`, `ScoreMark` and `ScrollFrame`.
- App shell: default Inertia layout with a sticky header, top navigation from 768 px, bottom tab bar below it, current section marked with `aria-current`, safe-area insets.
- Placeholder pages "Section à venir" for `/import`, `/rounds`, `/handicaps`, `/strokes` and `/players` and their sub-addresses, and a new home page.
- Web app manifest, home screen icons of 180, 192 and 512 px drawn by the `app:icons` artisan command, light and dark `theme-color` tags and `viewport-fit=cover`.
- Version line at the bottom of every page, linked to the GitHub release except for `0.0.0`, fed by the shared `app` Inertia prop and `App\Support\AppBuild`.
- Toast for the Inertia `toast` flash data, built on sonner and styled with the tokens.
- Pending indicator (`Pending`) built on `thinking-orbs`, which follows the dark mode and stops under reduced motion.
- Frontend test tooling: Testing Library (`react`, `user-event`, `jest-dom`) and the Vitest setup file `resources/js/tests/setup.ts`.
- Theme guard tests: text and mark contrast in both modes, no hard-coded colour or global transition in `resources/js`, reduced motion rule, no page asset outside the application.
- Dependencies `@base-ui/react`, `class-variance-authority`, `lucide-react`, `sonner`, `thinking-orbs`, `tw-animate-css`, `react-day-picker` and `date-fns`.
- ADR 0006 (design system), `DESIGN.md` completed and a Frontend section in `ARCHITECTURE.md`.

### Changed

- Composer runs `boost:update` after every `composer update` (`post-update-cmd`).
- PHPUnit runs on the PostgreSQL `testing` database and gains an `Arch` suite.
- Vitest runs under jsdom, and `vp check` also checks types.
- Agent guidelines: the Makefile comes before `vendor/bin/sail`, and every code pull request records its changes in this changelog; `AGENTS.md` regenerated.
- Fonts are served by `fontsource()` instead of `bunny()`, so the build no longer downloads them from Bunny.
- `GET /api/version` validates the commit through `App\Support\AppBuild`.
- `app.blade.php` drops the `appearance` dark class, sets `lang="fr"` and uses `APP_NAME` as the default title; `.env.example` sets `APP_NAME="Handicaps golf"`.

### Removed

- Starter kit accounts: `users` and `password_reset_tokens` tables, `sessions.user_id` column, `User` model and its factory.
- Account configuration in `config/auth.php`: Eloquent provider, password reset and password confirmation timeout.
- The kit's `ExampleTest` files and the authentication types in `resources/js` (`auth.ts`, shared `auth` prop).
- The starter kit welcome page (`welcome.tsx`) and the kit's `favicon.ico`.
