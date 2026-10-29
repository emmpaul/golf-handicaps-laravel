# Architecture

Monolithe Laravel 13 + Inertia 3 + React 19, PostgreSQL 18, monté selon le squelette Laravel du mainteneur. Chaque décision d'architecture a son ADR sous `docs/adr/` (index dans `docs/adr/README.md`) ; les écarts au squelette sont dans l'ADR 0005.

## Structure (ADR 0001)

- Code métier sous `app/Domain/<Domaine>`, indépendant de HTTP, d'Inertia et des intégrations :
    - `Actions/` : une classe par écriture, méthode `handle()`, qui ouvre sa transaction ;
    - `Queries/` : une classe par lecture complexe ;
    - `Exceptions/` : sous-classes de `App\Domain\DomainException`, avec un message pour la personne qui utilise l'application. Elles sont rendues au même endroit pour toute l'application, dans `bootstrap/app.php` : en visite Inertia, retour arrière avec le flash `toast` `{ type: 'error', message }` ; en requête JSON hors Inertia, 422 `{ "error": message }`.
- Un dossier de domaine naît avec son premier code, jamais vide d'avance.
- Intégrations tierces (IA, Simulator Golf Tour) sous `app/Integrations`, sans dépendance vers `App\Http` ni Inertia ; le domaine ne les appelle pas.
- Contrôleurs minces : Form Request, puis une Action ou une Query, puis la réponse.
- Les frontières sont des règles d'architecture Pest (`tests/Arch/ArchitectureTest.php`, `make arch-test`).

## Données (ADR 0002)

- PostgreSQL partout : local, tests (base `testing`) et CI.
- Chaque modèle de `App\Models` utilise `HasUuids` (UUID v7) ; chaque clé étrangère applicative est un `foreignUuid` avec contrainte. Les tables techniques du framework gardent leurs identifiants.
- Aucune table de comptes. Sessions, cache et files en base, sans Redis. Un worker de file tourne dès le départ : l'import IA et la recherche SGT l'exigent.

## Joueurs et rounds (ADR 0009)

- Domaines `app/Domain/Players` (Actions `AddPlayer`, `RenamePlayer`, `AssignAlias`, `RemoveAlias`, `CreateInitialPlayers` ; normalisation `PlayerName`) et `app/Domain/Rounds` (Actions `CreateRound`, `UpdateRound`, `DeleteRound` ; Queries `RoundList`, `RoundDetail`, `KnownCourses`, `FindDuplicateRound`).
- Tables, toutes en UUID v7 avec leur modèle et leur fabrique :

| Table            | Rôle                                           | Contraintes principales                                                               |
| ---------------- | ---------------------------------------------- | ------------------------------------------------------------------------------------- |
| `players`        | joueurs du groupe                              | unique sur `lower(name)`                                                              |
| `player_aliases` | noms lus sur la carte, en majuscules           | FK en cascade, unique sur `lower(alias)`                                              |
| `rounds`         | parcours, tees, `played_on`, `photo_path`      | index `(played_on, created_at)`                                                       |
| `round_holes`    | par, index et distance de chaque trou du round | uniques `(round_id, hole)` et `(round_id, stroke_index)`, `CHECK`                     |
| `round_players`  | lignes de la carte, dans l'ordre               | FK `players` en `restrict`, uniques `(round_id, player_id)` et `(round_id, position)` |
| `scores`         | brut par joueur et par trou                    | FK composite vers `round_players`, unique `(round_id, player_id, hole)`, `CHECK` 1–15 |

- Contrôles doubles : `App\Domain\Rounds\RoundCardChecks` (serveur) et `resources/js/lib/round-checks.ts` (éditeur, à chaque frappe) donnent les mêmes problèmes avec les mêmes messages. `CreateRound` et `UpdateRound` les rejouent dans leur transaction et lèvent `IncoherentRoundCard` avant toute écriture ; les Form Requests ne valident que la forme. Les `CHECK` de la base sont une seconde ligne.
- Table de cas : `tests/fixtures/round-checks.json`, problèmes attendus écrits à la main, parcourue par `tests/Unit/Rounds/RoundCardChecksTest.php` et `resources/js/lib/round-checks.test.ts`. Un contrôle modifié se change dans les deux codes et dans la table.
- Doublon : `FindDuplicateRound` (parcours et tees sans casse, date, bruts par joueur) est appelé par le contrôleur quand `force` est faux ; il revient à l'éditeur avec le flash `duplicate` sans rien écrire. Ce n'est pas un invariant, donc pas dans l'Action.
- Photos : disque `photos` (`storage/app/photos`, `serve => false`), hors de la racine du disque `local` que sert `storage/{path}`. `photo_path` est relatif à ce disque. `GET /rounds/{round}/photo` (`rounds.photo`) la sert derrière la session ; `DeleteRound` l'efface après le commit.
- Joueurs initiaux : le service `migrate` de `docker-compose.yml` lance `php artisan migrate --force && php artisan golf:initial-players && php artisan golf:recompute-stats`. `golf:initial-players` crée les joueurs de `INITIAL_PLAYERS` sur une base sans joueur ; une liste en double la fait sortir en 1, et `web` et `worker` ne démarrent pas.
- Données de développement : chaque seeder étend `Database\Seeders\DemoSeeder`, dont `__invoke()` refuse la production (règle d'architecture). `make fresh` recrée la base avec Gab, Manu et Sean, sans round. `make demo` la recrée avec `DemoDatabaseSeeder` : les mêmes joueurs, le round de référence `IMG_3064` et sa photo (`database/data/img3064.webp`), 24 rounds générés par `CreateRound` avec `fake()->seed(2026)`, puis la rating 71,4 / 144 de BETHPAGE BLACK / Red saisie par `SaveCourseRating` (`DemoCourseRatingsSeeder`). Le jeu refuse une base qui a déjà un joueur ou un round.

## Index et ratings (ADR 0010)

- Calcul pur : `App\Domain\Handicaps\Whs` (score ajusté, différentiel, index, handicap de jeu et de parcours) et `NetRanking` (rang net de compétition), ports un pour un de `handicap.ts` et `strokes.ts` de Next, sur des valeurs `final readonly` (`CourseRating`, `RatedCourse`, `RoundDifferential`, `HandicapIndex`, `NetEntry`, `RankedNet`). Une règle d'architecture leur interdit `Illuminate` et `App\Models` ; leurs tests reprennent les valeurs des tests Next.
- Dixièmes entiers : différentiels, index et ratings sont des entiers en dixièmes (`differential_t`, `index_before_t`, `index_after_t`, `rating_t`). Chaque division flottante est suivie de `(int) floor($x + 0.5)`, l'arrondi de Next. Le front formate (`formatTenths(174)` → « 17,4 »).
- Tables, en UUID v7 avec leur modèle et leur fabrique :

| Table                | Rôle                                                                                       | Contraintes principales                                                                                                   |
| -------------------- | ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------- |
| `player_round_stats` | brut, ajusté, différentiel, index d'avant et d'après, `counts_in_current`, sans timestamps | FK composite `(round_id, player_id)` vers `round_players` en cascade, unique `(round_id, player_id)`, `CHECK` index ≤ 540 |
| `course_ratings`     | rating et slope saisis par parcours et tees                                                | unique sur `(lower(trim(course_name)), lower(trim(tees)))`, `CHECK` `rating_t` 400–900 et `slope` 55–155                  |

- Recalcul : `App\Domain\Handicaps\Actions\RecomputePlayerStats` rejoue l'historique complet de chaque joueur touché (date de jeu, puis `created_at`, puis `id`), dans la transaction de l'écriture et après ses contrôles. Elle verrouille les joueurs par `lockForUpdate()` dans l'ordre des `id`, puis supprime et réécrit leurs lignes. Appelée par `CreateRound`, `UpdateRound` (joueurs d'avant et d'après la correction), `DeleteRound`, `SaveCourseRating` et `RemoveCourseRating` (joueurs qui ont un round sur la paire). Sans rating, le calcul prend le par du round et le slope 113.
- Ratings : domaine `app/Domain/Courses` (Actions `SaveCourseRating`, `RemoveCourseRating` ; Queries `CourseOverview`, `CourseRounds` ; conversion `RatingInput` de « 71,4 » en 714). Une paire se compare toujours en SQL avec `lower(trim(...))`, la même expression que l'index unique et que `KnownCourses`. La saisie fait un `insert … on conflict … do update` ; une paire sans round lève `UnknownCourse`, un retrait sans rating `CourseHasNoRating`. Routes `GET /courses`, `PUT` et `DELETE /courses/rating`.
- Lectures : le handicap de jeu et le net ne sont pas stockés. `RoundLeaderboard` les calcule depuis `index_before_t` et la rating courante du round ; `RoundList` en tire les gagnants nets ; `PlayerOverview` lit l'index d'après le dernier round joué et le marque provisoire sous 3 rounds.
- `golf:recompute-stats` : recalcule tous les joueurs qui ont un round, dans une transaction, et affiche « N joueurs recalculés. » (code 0, même sur une base sans round). Idempotente. Le service `migrate` la lance à chaque déploiement après `golf:initial-players` : le staging, une restauration de dump et l'import obtiennent leur historique sans étape à la main.

## Import des cartes (ADR 0011)

- Domaines `app/Domain/Imports` (Actions `CreateImport`, `StartReading`, `MarkImportReady`, `FailImport`, `SaveImportCorrection`, `SaveImportedRound`, `RetryImportReading`, `DeleteImport` ; Query `ImportList` ; valeurs `ScorecardDraft`, `SimulatorDate`, `ImportCorrection`, `ImportEditorState`, `WebpPhoto`) et `app/Domain/AiUsage` (`AiBudget`, `AiPricing`). La reconnaissance des noms lus est `App\Domain\Players\PlayerNameMatcher`.
- Tables, en UUID v7 avec leur modèle et leur fabrique :

| Table      | Rôle                                                                                                   | Contraintes principales                                                                                                            |
| ---------- | ------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| `imports`  | photo envoyée, statut, brouillon lu (`draft`), correction de l'autosave, raison d'un échec, round créé | `CHECK` du statut, `CHECK` d'un brouillon présent quand `ready`, FK `round_id` unique vers `rounds` en cascade, index `created_at` |
| `ai_usage` | un appel facturé : mois du budget (`YYYY-MM`), modèle, tokens d'entrée et de sortie, coût              | `CHECK` des trois compteurs ≥ 0, `cost_micro_usd` en micro-dollars entiers, index `month`                                          |

- Statuts (`ImportStatus`) : `queued` « En attente », `reading` « Lecture en cours », `ready` « Prêt à vérifier », `failed` « Échec » avec sa raison dans `failure` (`ReadingFailure`). Un import relié à un round est enregistré : sa page redirige vers le round, il ne se corrige, ne se relance ni ne se supprime plus. Supprimer le round supprime l'import (cascade).
- Job : `CreateImport` et `RetryImportReading` poussent `App\Jobs\ReadScorecardImport` après le commit. Le job passe l'import à `reading` sous verrou (rien si l'import a disparu ou n'attend plus), refuse sans clé ou sans budget, appelle l'IA, compte l'usage dans `ai_usage` avant toute décision, puis écrit `ready` avec le brouillon ou `failed` « Lecture IA indisponible ». Deux essais (`$tries = 2`) pour les erreurs passagères du SDK ; `failed()` écrit le même échec.
- Budget : `AiBudget::canSpend($now)` vrai tant que le cumul du mois plus le coût de l'appel le plus cher (`max_input_tokens` et `max_output_tokens`, 4 000 chacun) tient sous `AI_MONTHLY_BUDGET_USD`. Mois dans le fuseau `AI_BUDGET_TIME_ZONE`, prix par token dans `config/golf.php` (`golf.ai.prices`). Vérifié avant chaque appel et à la demande de relance. `AppServiceProvider::boot()` refuse de démarrer quand `AI_MODEL` n'a pas de tarif ou que le fuseau est inconnu.
- Intégration hors du domaine : `app/Integrations/Ai` (`ScorecardPhotoReader`, agent du Laravel AI SDK en sortie structurée ; `ScorecardImage`, JPEG de 1568 px envoyé à l'IA ; `ReadingResult`). Seul `App\Jobs` l'utilise ; `App\Domain` n'utilise ni `App\Integrations` ni `Laravel\Ai` (règles d'architecture). Le domaine reçoit la carte lue en tableau et la contrôle par `ScorecardDraft::fromArray()`.
- Faux obligatoires des tests : `phpunit.xml` vide `ANTHROPIC_API_KEY` ; `tests/Pest.php` appelle avant chaque test qui démarre l'application `ScorecardPhotoReader::fake()->preventStrayPrompts()` et `Http::preventStrayRequests()`. Un test qui lit une carte pose `config(['ai.providers.anthropic.key' => 'test-key'])` et fournit la réponse, usage compris (`StructuredTextResponse` avec un `TextUsage`). Un prompt sans réponse fournie lève.
- Photos des imports : disque `photos`, sous `imports/<uuid>.webp`, en WebP de 3000 px au plus sans EXIF ni XMP (`WebpPhoto`, Intervention sur Imagick). `GET /imports/{import}/photo` (`imports.photo`) la sert derrière la session. Le round créé depuis un import garde le même `photo_path` ; `DeleteRound` efface le fichier, `DeleteImport` aussi pour un import pas enregistré, toujours après le commit.
- Correction : l'éditeur des rounds envoie son état par `PUT /imports/{import}/correction` une seconde après la dernière modification (`autosave.ts`), gardé dans `imports.correction` (`{ version: 1, card }`) ; `ImportEditorState` rouvre la correction, sinon le brouillon, sinon un écran vide. L'enregistrement (`POST /imports/{import}/round`) passe par `CreateRound`, les contrôles des totaux lus compris, puis apprend en alias les noms lus qui ne désignaient pas exactement leur joueur.
- Données de développement : `make demo` ajoute par `DemoImportsSeeder` un import « Prêt à vérifier » de la carte de référence avec une ligne lue « JULIEN », un « Échec » « Budget IA du mois atteint », un « En attente » sans job, chacun avec sa photo, et 3 lignes d'`ai_usage` du mois courant. Aucun appel à l'IA.

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

## Déploiement (ADR 0008)

- Une seule image de production (`Dockerfile`, FrankenPHP en mode classique, PHP 8.5), sans secret, sous l'utilisateur `app`. La commande lancée choisit le rôle : web (`frankenphp run`, port 8080, `docker/Caddyfile`), worker (`queue:work`) ou migrations. `docker/entrypoint.sh` construit les caches Laravel (`php artisan optimize`) au démarrage, une fois l'environnement connu.
- La CI construit l'image, lance la stack (`make image`, `make image-smoke`) et publie sur `ghcr.io/emmpaul/golf-handicaps-laravel` l'image qu'elle a testée : `:main` et `:<sha>` depuis `main`, `:vX.Y.Z`, `:latest` et `:<sha>` depuis un tag dont `version.txt` porte la version (`make image-tags`).
- Stack `docker-compose.yml` pour Dokploy : `pgsql`, `migrate` (une fois par déploiement), `web` et `worker` (après la réussite de `migrate`), `backup`. Volumes nommés `pgdata`, `storage` (monté sur `storage/app` pour `web` et `worker`) et `backups`. Aucun port publié.
- Santé : `web` est sain quand `/up` répond 200. `worker` l'est tant que `App\Support\WorkerHeartbeat` a écrit `/tmp/worker-heartbeat` il y a moins de 300 s ; le battement suit `Queue::looping`, `JobProcessed` et `JobFailed`, donc seul `queue:work` l'écrit. `backup` l'est tant qu'un dump réussi a moins de 26 h.
- Sauvegardes : `pg_dump -Fc` au démarrage puis toutes les 24 h, gardées 14 jours. La restauration est dans `docs/deployment.md`.
- TLS chez Traefik (Dokploy). `AddSecurityHeaders` pose `Strict-Transport-Security: max-age=31536000` sur toute réponse servie en HTTPS, d'après `isSecure()` lu après les mandataires de confiance. Le témoin de session est `Secure` par défaut quand `APP_ENV=production` (`config/session.php`). `TRUSTED_PROXIES` vaut en production le sous-réseau du proxy de Dokploy, jamais `*`.
- L'image installe `imagick` (conversion des photos en WebP) et `docker/php/uploads.ini` (envoi jusqu'à 20 Mo). `ANTHROPIC_API_KEY`, `AI_MODEL`, `AI_MONTHLY_BUDGET_USD` et `AI_BUDGET_TIME_ZONE` arrivent par l'environnement de `migrate`, `web` et `worker`, jamais par l'image.
- Journaux sur la sortie d'erreur du conteneur (`LOG_CHANNEL=stderr`), gardés et tournés par Docker.
- Procédures (installation, version, santé, sauvegarde, restauration, retour arrière, fermeture des sessions) : `docs/deployment.md`.

## Frontend (ADR 0006)

- Inertia 3 + React 19, sans rendu serveur. Pages sous `resources/js/pages`, sans fichier de test : le plugin Vite d'Inertia emballe tout `.tsx` de ce dossier comme page, les tests de page vont sous `resources/js/tests/pages` ; composants sous `resources/js/components`, primitives base-ui possédées par le projet sous `resources/js/components/ui`. Thème, tokens et règles d'interface : `DESIGN.md`.
- `resources/js/layouts/app-layout.tsx` est le layout par défaut de chaque page (option `layout` de `createInertiaApp` dans `app.tsx`) : navigation (`AppNav`), `main`, ligne de version (`AppVersion`) en dernier enfant de `main`, puis `Toaster`.
- Données partagées par `HandleInertiaRequests::share()` : `name` et `app: { version, commit }`, typées dans `resources/js/types/global.d.ts`. `App\Support\AppBuild` lit la version et valide le commit (sha de 40 caractères hexadécimaux minuscules, sinon `null`) ; `VersionController` passe par la même classe.
- Messages éphémères par le flash Inertia : clé `toast`, `{ type: 'error' | 'success', message }`, typée par `flashDataType`. Le serveur l'émet par `Inertia::flash('toast', …)` ; le layout l'écoute par `router.on('flash', …)` et `showFlashToast` (`resources/js/lib/flash-toast.ts`) l'affiche. Le rendu de `DomainException` l'émet en `error`, les contrôleurs de rounds en `success` (« Round enregistré. », « Round supprimé. »).
- Routes : `/` sert `home`. `/rounds` et `/players` ont leurs pages (section « Joueurs et rounds »), `/import` et `/imports/{import}` les leurs (section « Import des cartes »). `/handicaps`, `/strokes` et leurs sous-adresses servent `section-placeholder` par `Route::inertia`, sous les noms `<section>.index`. La navigation lit leurs adresses dans les fonctions Wayfinder ; chaque changement de section remplace la route sous le même nom.
- Section courante : `inSection` et `SECTION_PREFIXES` (`resources/js/lib/sections.ts`) rattachent `/imports/…` à Importer et `/courses/…` à Rounds.
- `public/manifest.webmanifest`, `public/icons/` et `public/apple-touch-icon.png` sont statiques, servis par le serveur web sans session. `php artisan app:icons` régénère les PNG.

## Outillage (ADR 0003)

- Chaque changement passe par OpenSpec, en TDD.
- Le Makefile est l'interface unique, en local et en CI : `make` liste les cibles, `make check` lance tous les contrôles.
- Version tenue à la main dans `version.txt`, changements notés dans `CHANGELOG.md` par chaque pull request de code (ADR 0005).
