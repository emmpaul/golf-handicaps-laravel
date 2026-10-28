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

### Changed

- Composer runs `boost:update` after every `composer update` (`post-update-cmd`).
- PHPUnit runs on the PostgreSQL `testing` database and gains an `Arch` suite.
- Vitest runs under jsdom, and `vp check` also checks types.
- Agent guidelines: the Makefile comes before `vendor/bin/sail`, and every code pull request records its changes in this changelog; `AGENTS.md` regenerated.

### Removed

- Starter kit accounts: `users` and `password_reset_tokens` tables, `sessions.user_id` column, `User` model and its factory.
- Account configuration in `config/auth.php`: Eloquent provider, password reset and password confirmation timeout.
- The kit's `ExampleTest` files and the authentication types in `resources/js` (`auth.ts`, shared `auth` prop).
