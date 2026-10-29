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
- Shared passcode access: every web route requires a session opened with `APP_PASSCODE`; only `/login`, `GET /api/version`, `/up` and the static files of `public/` stay open.
- `/login` page outside the app shell, with the brand, the title "Handicaps golf", a masked "Code d'accès" field, an "Entrer" button, "Connexion…" while the passcode is sent, "Code incorrect" for a wrong or empty passcode, and the version line.
- "Se déconnecter" button at the bottom of the home page; `POST /logout` closes the session of this browser only.
- The session stores an HMAC fingerprint of the passcode, never the passcode itself: changing `APP_PASSCODE` ends the sessions of every device.
- Writes without a session are rejected without running: 401 with `{ "error": "Session requise" }`, or a redirect to `/login` for an Inertia visit; the passcode check runs before the CSRF check, so a stale token gets 401, not 419.
- Unknown addresses answer 404 through a fallback route, and redirect to `/login` without a session.
- Login limiter: 10 attempts a minute per client IP, then "Trop d'essais. Réessaie dans une minute.".
- Every HTTP request, `/up` included, fails with an error naming `APP_PASSCODE` when it is empty; artisan commands still run.
- Security headers on every response, error pages included: `X-Frame-Options: DENY`, `Content-Security-Policy: frame-ancestors 'none'` unless the response sets its own, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.
- Trusted proxies read from `TRUSTED_PROXIES` in `config/trustedproxy.php`; empty trusts none, and the `*` wildcard is dropped.
- `make setup` warns when `APP_PASSCODE` is empty in `.env`, without writing a value.
- Test helper `withPasscodeSession()` for tests that open a protected page, and a guard test that keeps test files out of `resources/js/pages`, which the Inertia Vite plugin bundles as pages.
- ADR 0007 (shared passcode access) and an Access section in `ARCHITECTURE.md`.
- Production image built by a multi-stage `Dockerfile`: FrankenPHP with PHP 8.5, compiled Vite assets, no development dependencies, non-root user, the commit sha in `APP_COMMIT`, logs on stderr; the same image runs the web, the queue worker and the migrations.
- Production `docker-compose.yml` stack for Dokploy: `pgsql`, a one-shot `migrate` service, `web` and `worker` started only after the migrations succeed, health checks, and the named volumes `pgdata`, `storage` and `backups`.
- `backup` service: a PostgreSQL dump at startup then every 24 hours, kept 14 days, unhealthy when the latest successful dump is older than 26 hours.
- Worker heartbeat: `queue:work` writes `/tmp/worker-heartbeat` on every loop and after every job, and the worker health check fails after 300 seconds without one.
- `Strict-Transport-Security: max-age=31536000` on every response served over HTTPS.
- Makefile targets `make image`, `make image-smoke` and `make image-tags`.
- CI image smoke test on every code change: build the image, start the stack with `docker compose up --wait`, check `/up` and `/login`.
- CI publication of the tested image to `ghcr.io/emmpaul/golf-handicaps-laravel`, `linux/amd64` only: `main` and `<sha>` from `main`, `vX.Y.Z`, `latest` and `<sha>` from a `vX.Y.Z` tag that matches `version.txt`.
- Queue worker job timeout of 420 seconds (`--timeout=420`), after the worker health check has turned unhealthy for a stuck job, and a `database` queue `retry_after` of 450 seconds by default, so a job is never handed out twice while it runs.
- Production image assets built with `VITE_APP_NAME="Handicaps golf"`: browser tabs read `Connexion - Handicaps golf`, not `Connexion - Laravel`.
- Logged query exceptions keep their SQL with `?` placeholders and drop the bound values, so a session identifier never reaches the logs; the error response is unchanged.
- `COMPOSE_FILE=compose.yaml` exported by the Makefile and set in `.env.example`: Sail reads only the development stack and no longer warns about two compose files (an existing clone adds the line to its `.env`).
- `docs/deployment.md`: Dokploy setup, variables, `TRUSTED_PROXIES`, deploying a version, `/api/version` check, worker health, backup and restore, rollback, closing every session.
- ADR 0008 (single image and Dokploy stack) and a Deployment section in `ARCHITECTURE.md`.
- `/release` skill, launched by the maintainer only: release PR, `vX.Y.Z` tag, published image and `/api/version` checks, GitHub Release, guided Dokploy deployment; commit scope `release`.
- Players and rounds data model: `players`, `player_aliases`, `rounds`, `round_holes`, `round_players` and `scores` tables in UUID v7, with case-insensitive unique names and aliases, composite foreign keys and `CHECK` constraints; models and factories for each.
- `/players` page: list of players with their aliases, add, rename, add and remove an alias; a name is unique regardless of case ("Ce joueur existe déjà") and an alias added to a player is taken from any other.
- `golf:initial-players` artisan command: creates the players of `INITIAL_PLAYERS` on a base without players, and exits with 1 on a duplicate name.
- `/rounds` list from the latest round, round page with header, gross scores, scorecard and photo, manual entry at `/rounds/new`, correction at `/rounds/{id}/edit` and confirmed deletion.
- Round editor ported from the Next.js correction screen, without photo or autosave: course, tees, calendar date, par, stroke index, distance, 1 to 4 player lines, computed OUT, IN and TOT, coherence checks on every keystroke, known-course prefill, player creation from a line, duplicate round warning with "Enregistrer quand même".
- Coherence checks replayed by the server before any write (`RoundCardChecks`), kept aligned with the editor's by the shared case table `tests/fixtures/round-checks.json`.
- Round photos on a `photos` disk that `storage/{path}` never serves, read through `GET /rounds/{round}/photo` behind the session and deleted with the round.
- `App\Domain\DomainException` and its single rendering: an error toast on Inertia visits, `422 { "error": message }` on JSON requests.
- `formatPlayedOn` in `resources/js/lib/format.ts`: a date of play read as a civil day, without going through UTC.
- Development data: `DatabaseSeeder` writes Gab, Manu and Sean; `DemoDatabaseSeeder` adds the reference round `IMG_3064` with its photo (`database/data/img3064.webp`) and 24 generated rounds on three courses, the same at every run, on an empty base only. Every seeder extends `DemoSeeder`, which refuses production, enforced by an architecture rule.
- `make fresh` and `make demo` targets.
- ADR 0009 (rounds data model and coherence checks) and a Players and rounds section in `ARCHITECTURE.md`.
- WHS handicap index ported from the Next.js app, to the tenth: adjusted score (par + 4 per hole), differential from the course rating and slope (par and 113 without rating), index over the best differentials of the last 20 rounds with the WHS table and adjustments, capped at 54.0, provisional below 3 rounds. Pure computation in `App\Domain\Handicaps\Whs` and `NetRanking`, kept free of the framework by an architecture rule.
- `player_round_stats` table: gross, adjusted, differential, index before and after and `counts_in_current` of each player on each round, in integer tenths, recomputed for every touched player inside the transaction of each round write and rating change, under a lock on the players.
- `course_ratings` table and `/courses` page: course rating and slope typed by hand per course and tees ("Rating entre 40,0 et 90,0", "Slope entre 55 et 155"), removed with "Retirer la rating"; a change applies to every round of that course and tees.
- Net leaderboard on the round page (gross, playing handicap, net, net rank, "pas encore d'index"), rating line linked to `/courses`, "Gagnant net" column and "Parcours" link on `/rounds`, current index of each player on `/players`.
- "Comment l’index est calculé" explanation, folded by default, on the round page, `/players` and `/courses`.
- `golf:recompute-stats` artisan command: recomputes the index history of every player who has a round, idempotent.
- Demonstration data: `DemoCourseRatingsSeeder` types 71,4 / 144 on BETHPAGE BLACK / Red, so `make demo` shows a course with and without rating.
- ADR 0010 (index in integer tenths and derived table) and an Index and ratings section in `ARCHITECTURE.md`.

### Changed

- Composer runs `boost:update` after every `composer update` (`post-update-cmd`).
- PHPUnit runs on the PostgreSQL `testing` database and gains an `Arch` suite.
- Vitest runs under jsdom, and `vp check` also checks types.
- Agent guidelines: the Makefile comes before `vendor/bin/sail`, and every code pull request records its changes in this changelog; `AGENTS.md` regenerated.
- Fonts are served by `fontsource()` instead of `bunny()`, so the build no longer downloads them from Bunny.
- `GET /api/version` validates the commit through `App\Support\AppBuild`.
- `app.blade.php` drops the `appearance` dark class, sets `lang="fr"` and uses `APP_NAME` as the default title; `.env.example` sets `APP_NAME="Handicaps golf"`.
- Sessions last 90 days from the last request (`SESSION_LIFETIME=129600`) instead of 120 minutes.
- Sessions are encrypted by default (`SESSION_ENCRYPT` defaults to `true`).
- `.env.example` documents `APP_PASSCODE`, `SESSION_LIFETIME` and `TRUSTED_PROXIES`, and `phpunit.xml` sets `APP_PASSCODE`.
- The session cookie is `Secure` by default when `APP_ENV=production`; `SESSION_SECURE_COOKIE` still forces either value, and `.env.example` explains it.
- ADR 0005 points layer 12 to ADR 0008.
- The `migrate` service of `docker-compose.yml` runs `golf:initial-players` after the migrations; `.env.example` and `docs/deployment.md` document `INITIAL_PLAYERS`.
- `/rounds` and `/players` serve their own pages instead of "Section à venir"; an unknown sub-address under them answers 404.
- The `migrate` service of `docker-compose.yml` runs `golf:recompute-stats` after `golf:initial-players`; a failed recompute keeps `web` and `worker` stopped. `docs/deployment.md` documents it.

### Removed

- Starter kit accounts: `users` and `password_reset_tokens` tables, `sessions.user_id` column, `User` model and its factory.
- Account configuration in `config/auth.php`: Eloquent provider, password reset and password confirmation timeout.
- The kit's `ExampleTest` files and the authentication types in `resources/js` (`auth.ts`, shared `auth` prop).
- `rounds/round-gross` component, replaced by the net leaderboard.
- The starter kit welcome page (`welcome.tsx`) and the kit's `favicon.ico`.

### Fixed

- The production stack no longer fails at random on a fresh `storage` volume: the `migrate` service mounts it too, so Docker fills it once, when `migrate` is created, instead of twice at the same time for `web` and `worker` (`file exists`, issue #14).
