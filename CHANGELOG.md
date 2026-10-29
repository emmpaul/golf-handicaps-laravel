# Changelog

All notable changes to this application, version by version. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The current version is in `version.txt`.

## [Unreleased]

### Changed

- `docs/deployment.md` records the first install on Dokploy: `APP_NAME` is required, the domain needs a deploy to take effect, Dokploy adds `dokploy-network` itself, Autodeploy stays off, and the login limiter sees the client IP behind Cloudflare.
- `ROADMAP.md` records the move from the Next.js app to the Laravel stack on `golf.emmanuelpaul.com` on 2026-10-29.
- On phones, the « Golf » row at the top of each page, login included, starts at the bottom of the safe area instead of 10 px below it (`max(54px, safe-top)`): as a home screen app on iPhone it sits at 59 px instead of 69 px.
- The side margins of the content are 16 px instead of 20 px on every page, login included, aligned with the floating tab bar.

## [0.1.0] - 2026-10-29

First release of the Laravel port of the golf handicap app. It takes over from the Next.js app with the same data and the same WHS computation, a redesigned interface, and a single Docker image deployed on Dokploy.

### Added

#### Access

- Shared passcode access: every page requires a session opened at `/login` with `APP_PASSCODE`. Only `/login`, `GET /api/version`, `/up` and the static files of `public/` stay open.
- Sessions last 90 days from the last request, are encrypted, and store an HMAC fingerprint of the passcode, never the passcode itself. Changing `APP_PASSCODE` signs out every device; "Se déconnecter" signs out this browser only.
- Login limiter: 10 attempts a minute per client IP, then "Trop d'essais. Réessaie dans une minute.".
- Writes without a session are rejected before they run: `401 { "error": "Session requise" }`, or a redirect to `/login` for an Inertia visit. The passcode check runs before the CSRF check, so a stale token gets 401, not 419.
- Security headers on every response, error pages included: `Strict-Transport-Security` over HTTPS, `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`. Trusted proxies come from `TRUSTED_PROXIES`.

#### Players and rounds

- `/players`: every player with their aliases and current index. Add, rename, add or remove an alias; names are unique regardless of case.
- `/rounds`: every round from the latest, with its net winner and course. The round page shows the gross scores, the scorecard, the photo and the net leaderboard (gross, playing handicap, net, rank).
- Round editor at `/rounds/new` and `/rounds/{id}/edit`: course, tees, date, par, stroke index, distance, 1 to 4 players, computed OUT, IN and TOT, known-course prefill, and a duplicate round warning with "Enregistrer quand même".
- Coherence checks on every keystroke, replayed by the server before any write.
- Round photos are served only behind the session and deleted with their round.

#### Scorecard import by AI

- `/import`: a photo of a scorecard is re-encoded in the browser as a WebP of 3000 px at most, without EXIF or XMP, then read by a queued job through the Laravel AI SDK. The list follows each import live ("En attente", "Lecture en cours", "Prêt à vérifier", "Échec"), and the Importer button counts the imports to check.
- Correction screen `/imports/{id}`: the round editor beside the photo, the names read, the totals read checked against the holes ("Somme aller 36 ≠ OUT 38"), empty cells highlighted, and an autosave one second after the last change.
- Saving an import creates its round with the photo and learns the names read as aliases of their player.
- Monthly AI budget (`AI_MONTHLY_BUDGET_USD`, 5 USD by default), checked before every call. Every billed call is counted in `ai_usage`, and the app refuses to boot when `AI_MODEL` has no known price.

#### Handicaps

- WHS handicap index to the tenth: adjusted score (par + 4 per hole), differential from the course rating and slope, best differentials of the last 20 rounds with the WHS table and adjustments, capped at 54.0, provisional below 3 rounds.
- Index history of each player stored per round and recomputed in the same transaction as every round or rating change; `golf:recompute-stats` rebuilds it.
- Home dashboard: the index of each player with the change from their latest round, the latest round with its net winners, and the imports to review.
- Handicaps page: one player at a time with their index, the history of their rounds, the differentials that count drawn as bars, and a STATS block (average and best gross, pars or better, score distribution, average on par 3, 4 and 5).
- Match strokes page: the course handicap of 2 to 4 players on a known course, in « Écart » or « Complet » mode, with the strokes received on each hole.
- "Comment l’index est calculé" explanation on the round, players and courses pages.

#### Course ratings

- `/courses`: course rating and slope per course and tees, typed by hand or proposed by a lookup. A confirmed rating applies to every round of that course and tees; nothing changes before a confirmation.
- Lookup on Simulator Golf Tour, run by the worker only: it finds the course and its scorecard image, and the AI reads the "Slope/Rating" line under the shared monthly budget. Each course shows its status ("Proposition à confirmer", "Tee à choisir", "Introuvable sur SGT", "Confirmé"…) with the "Carte SGT" link and the actions "Confirmer", "Retenir", "Choisir" and "Relancer la recherche".
- `SGT_LOOKUP` switch (`on` by default): `off` ends every lookup in "Saisie manuelle" without any request to SGT.

#### Interface

- Dark scoreboard design: closed palette of tokens, self-hosted Geist and Geist Mono, one 390 px column at every width, floating tab bar with four sections and a round Importer button, bottom sheets for confirmations, pill toasts, and motion that stops under reduced motion.
- A colour per player, and score marks from the double circle of an eagle to the double square of a double bogey.
- Installable web app: manifest, home screen icons, dark `theme-color`, safe-area insets.
- Version line at the bottom of every page, linked to its GitHub release.

#### Migration from the Next.js app

- `golf:import-sqlite`: one-time strict import of the Next.js SQLite base and its photos, in one transaction. It refuses a non-empty base unless `--force` is confirmed, offers a `--dry-run`, recomputes the handicap history and cancels everything on any difference with Next.
- Cutover procedure in `docs/deployment.md` (« Bascule depuis Next »): the Laravel stack first runs on `golf2.emmanuelpaul.com` with an imported copy and `SGT_LOOKUP=off` while Next stays in production, then moves to the main domain.

#### Deployment

- Production image built by a multi-stage `Dockerfile`: FrankenPHP with PHP 8.5, compiled assets, `imagick`, photos up to 20 MB, non-root user, logs on stderr. The same image runs the web, the queue worker and the migrations.
- `docker-compose.yml` stack for Dokploy: PostgreSQL 18, a one-shot `migrate` service (migrations, `golf:initial-players`, `golf:recompute-stats`, `golf:queue-rating-lookups`), then `web` and `worker`, all with health checks.
- `backup` service: a PostgreSQL dump at startup then every 24 hours, kept 14 days.
- Worker heartbeat health check, 420 s job timeout and 450 s `retry_after`, so a job is never handed out twice.
- Logged query exceptions drop their bound values, so a session identifier never reaches the logs.
- `GET /api/version` returns the version and the commit of the running image.
- `docs/deployment.md`: installation, variables, deploying a version, worker health, backup and restore, rollback, closing every session.

#### Development

- Makefile as the single command interface, locally and in CI (`make help`): setup, containers, Vite, demo data (`make demo`, `make fresh`), OpenSpec, commitlint.
- `make check` runs Pint, Larastan level 8, jscpd, Pest in parallel on PostgreSQL, architecture tests and Vitest in one pass.
- GitHub Actions CI with an image smoke test, publication of the tested image to `ghcr.io/emmpaul/golf-handicaps-laravel`, workflow audit by zizmor and updates by Dependabot.
- Case tables shared by Pest and Vitest for the coherence checks and the course handicap.
- Demonstration data on an empty base: three players, 25 rounds on three courses with photos, imports and ratings in every state.
- `/release` skill, launched by the maintainer only; `version.txt` and this changelog are kept by hand.
- ADRs 0001 to 0014, `ARCHITECTURE.md`, `DESIGN.md` and the OpenSpec specs.
