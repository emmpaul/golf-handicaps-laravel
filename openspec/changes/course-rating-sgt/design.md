# Design

## Context

- **Ce que l'entrée 6 a livré** (`handicap-index`, archivée) :
    - table `course_ratings` (`id`, `course_name`, `tees`, `rating_t` et `slope` non nuls, timestamps), index unique sur `(lower(trim(course_name)), lower(trim(tees)))`, `CHECK` 400–900 et 55–155. Une ligne n'existe qu'après une saisie ; son design D3 annonçait que l'entrée 9 rendrait `rating_t` et `slope` nullables et ajouterait statut, parcours SGT et tees lus, comme Next ;
    - `app/Domain/Courses` : `SaveCourseRating` (`insert … on conflict … do update`, puis `RecomputePlayerStats` des joueurs de la paire), `RemoveCourseRating` (`delete`, `CourseHasNoRating` si rien n'est effacé), `CourseOverview` (un `distinct on` des paires jointes à `course_ratings`), `CourseRounds` (`exist`, `playerIds`), `RatingInput` ;
    - `RecomputePlayerStats`, `RoundList` et `RoundLeaderboard` lisent `course_ratings` par une jointure à gauche sur la même expression ; les deux premiers retombent déjà sur le par et 113 quand `rating_t` est nul, `RoundLeaderboard` seulement quand la ligne manque ;
    - `CourseRatingController` (`PUT` et `DELETE /courses/rating`, paire dans le corps), page `courses/index`, composant `courses/manual-rating-form` ; `DemoCourseRatingsSeeder` saisit Bethpage Black / Red après les rounds ; `CourseRatingFactory` (états `bethpageRed()`, `bounds()`).
- **Ce que l'entrée 8 a livré** (`scorecard-ai-import`, archivée le 2026-10-29) et que ce changement réutilise sans le refaire (relu dans le code à la tâche 1.2) :
    - `laravel/ai` v1.3.0, `config/ai.php` (clé lue par `config('ai.providers.anthropic.key')`, depuis `ANTHROPIC_API_KEY`) ; `App\Domain\Imports\AiReading::enabled()` dit si une clé est posée ; `config/golf.php` : `ai.model`, `ai.monthly_budget_usd`, `ai.budget_time_zone`, `ai.prices` (micro-dollars par token), `ai.max_input_tokens` = 6 000 et `ai.max_output_tokens` = 4 000 (bornes de la lecture d'une photo) ;
    - `App\Domain\AiUsage\AiBudget` : `canSpend(CarbonImmutable $now): bool` (cumul du mois plus `AiPricing::maxCallCost()`, soit 6 000 × 2 + 4 000 × 10 = 52 000 micro-dollars au modèle par défaut, au plus égal au plafond) et `record(string $model, int $inputTokens, int $outputTokens, CarbonImmutable $now): AiUsage` ; `AiPricing::cost(string $model, int $inputTokens, int $outputTokens): int` et `AiPricing::maxCallCost(): int` ; table `ai_usage` ; contrôle au démarrage de `AI_MODEL` et `AI_BUDGET_TIME_ZONE` dans `AppServiceProvider::ensureAiConfiguration()`, appelée par `boot()` (`InvalidArgumentException` dont le message commence par le nom de la variable) ;
    - motif `App\Integrations\Ai\ScorecardPhotoReader` : agent `Agent, HasProviderOptions, HasStructuredOutput` avec `Promptable`, `maxTokens()` lu dans la config, `providerOptions()` à l'effort `low`, appel `prompt($texte, attachments: [Image…], provider: Lab::Anthropic, model: config('golf.ai.model'), timeout: 90)` ; il rend `ReadingResult { array $card, TextUsage $usage }` : `card` vaut `[]` pour un refus, une sortie tronquée ou un texte qui n'est pas du JSON, l'usage est toujours présent (zéros quand l'API ne dit rien) ; les erreurs passagères (`FailoverableException`, `RequestException`) remontent. Motif du job `ReadScorecardImport` (`$tries = 2`, `$backoff = 30`, `$timeout = 120`, mis en file `afterCommit`, clé puis budget vérifiés avant l'appel, `record()` aussitôt après l'appel, avant toute décision, `failed()`) ;
    - garde-fous des tests : `ANTHROPIC_API_KEY` vidé de force dans `phpunit.xml` (avec `AI_MODEL`, `AI_MONTHLY_BUDGET_USD` et `AI_BUDGET_TIME_ZONE`), `beforeEach` global de `tests/Pest.php` (`ScorecardPhotoReader::fake()->preventStrayPrompts()` et `Http::preventStrayRequests()`, seulement sur un `TestCase`), `tests/Feature/Imports/AiFakeGuardTest.php` ; faux avec usage : `new StructuredTextResponse($carte, json_encode($carte), new TextUsage($entrée, $sortie), new Meta)` (une réponse en tableau a un usage nul ; aide `billedAnswer()` à 3 000 / 1 200 dans `tests/Pest.php`) ; règles d'architecture « `App\Domain` n'utilise ni `App\Integrations` ni `Laravel\Ai` », « `App\Integrations\Ai` n'est utilisé que par `App\Jobs` et `App\Integrations\Ai` », « `App\Integrations` n'utilise ni `App\Http` ni `Inertia` ».
- **Worker** : `queue:work --tries=2 --timeout=420` sur la file `database` ; battement `App\Support\WorkerHeartbeat` à chaque tour et après chaque job (spec `worker-health`). Pas de worker dans la stack de développement (`compose.yaml`) : les jobs y restent en file.
- **`migrate`** : `php artisan migrate --force && php artisan golf:initial-players && php artisan golf:recompute-stats` (`docker-compose.yml`).
- **Référence Next** (`/Users/epaul/www/z_perso/golf-handicaps`, changement `add-course-rating`, design décisions 2 à 6) :
    - `src/server/courses/sgt.ts` (liste, normalisation, correspondance, tees, liens, client avec `User-Agent`, délai 20 s, cache 24 h), `src/jobs/course-rating.ts` (étapes de la recherche, `onFailed`), `src/ai/rating-reader.ts` (consigne, schéma), `src/server/courses/{enqueue,manage}.ts` (mise en file, confirmation, choix, relance, vue de la page), `src/app/(app)/courses/page.tsx` (libellés des statuts, liens, proposition, candidats) ;
    - bornes de tokens de la lecture de carte : `RATING_MAX_INPUT_TOKENS = 2000`, `RATING_MAX_TOKENS = 1500` (`src/ai/model.ts`) ;
    - fixtures `tests/fixtures/sgt/courses.html` (14 parcours réels, format d'origine) et `scorecard_30.jpg` (Bethpage Black, 905×527 : Blue 77.5/155, White 74.2/148, Red 71.4/144, Junior 71.0/120, PAR3 54.0/120, par 71), avec leur `README.md`.

## Goals / Non-Goals

**Goals**

- Aucun nombre ne change tant que rien n'est confirmé : les calculs de l'entrée 6 restent identiques pour une ligne sans valeur confirmée.
- Le même schéma de `course_ratings` que Next (statuts, colonnes, unités), pour que l'import SQLite (10) copie les lignes sans traduire.
- Le domaine reste pur : correspondance des noms et des tees, transitions d'état et confirmations dans `app/Domain/Courses` ; réseau et IA dans `app/Integrations`, appelés par le seul job.
- Aucun test ne joint SGT ni Anthropic.

**Non-Goals**

- Mise en page du design (11), lien depuis la page Coups (7).
- Plusieurs workers : comme en 8, le budget se vérifie sans verrou.
- Cache des images SGT : une image par parcours recherché, quelques par mois.

## Decisions

### D1. `course_ratings` étendue, une ligne par paire

Une migration modifie la table :

| Colonne | Type | Rôle |
| --- | --- | --- |
| `rating_t`, `slope` | `smallint`, **nullables** | valeurs confirmées seulement |
| `source` | `varchar(10)`, nullable | `sgt` ou `manual`, avec les valeurs confirmées |
| `status` | `varchar(20)`, non nul | `pending`, `proposed`, `choose_course`, `choose_tee`, `manual`, `not_found`, `failed`, `confirmed` |
| `sgt_course_id` | `integer`, nullable | parcours SGT retenu |
| `sgt_course_name`, `sgt_course_location` | `varchar(200)`, nullables | nom et lieu SGT du parcours retenu |
| `candidates` | `jsonb`, nullable | `[{id, name, location, par}]` (`choose_course`) |
| `tees_read` | `jsonb`, nullable | `[{name, ratingT, slope}]` lus sur la carte, rating en dixièmes |
| `proposed_tee` | `varchar(100)`, nullable | nom du tee de `tees_read` proposé ou retenu |
| `note` | `varchar(200)`, nullable | raison affichée |

- Contraintes : `CHECK status IN (…)` ; `CHECK ((rating_t IS NULL) = (slope IS NULL))` ; `CHECK ((rating_t IS NULL) = (source IS NULL))` ; `CHECK (status <> 'confirmed' OR rating_t IS NOT NULL)` ; `CHECK source IN ('sgt','manual')` ; les `CHECK` de bornes restent (un `NULL` les passe).
- Reprise : la colonne `status` est ajoutée avec le défaut `'confirmed'`, les lignes existantes reçoivent `source = 'manual'`, puis le défaut est retiré. Toute ligne de 6 est une saisie, donc confirmée.
- `down()` : efface les lignes sans valeur confirmée, retire les colonnes, remet `rating_t` et `slope` non nuls.
- `CourseRating` : casts `status` → enum `CourseRatingStatus` (huit cas, `label()` donne les libellés exacts de la spec), `candidates` et `tees_read` → `array`. `#[Fillable]` étendu.
- Lectures : `RecomputePlayerStats` et `RoundList` traitent déjà `rating_t` nul comme « sans rating » ; `RoundLeaderboard::rating()` passe du test « ligne absente » au test « `rating_t` nul ». Aucune autre lecture ne change.

**Alternative écartée** : une table `course_rating_lookups` séparée, `course_ratings` gardant les seules valeurs confirmées. Aucune lecture de 6 ne changerait, mais deux lignes par paire à garder en accord, une jointure de plus sur la page, et un import SQLite (10) qui scinderait chaque ligne de Next. Le design de 6 avait annoncé l'extension.

### D2. Correspondance pure dans le domaine

- `App\Domain\Courses\Sgt\SgtCourseMatcher`, port de `normalizeCourseName`, `matchSgtCourse`, `teeKey`, `matchTee` : normalisation (NFD par `Normalizer`, marques retirées, minuscules, `&` → ` and `, non alphanumérique → espace, espaces réduits, `the ` de tête retiré) ; correspondance au même par (un nom égal → retenu ; plusieurs égaux, sinon sous-chaînes dans les deux sens → candidats, 10 au plus, dans l'ordre de la liste ; aucun → rien) ; tees sans distance finale (`/\s*\d+\s*(yd|yds|y|m)$/i`), sans espaces, en minuscules.
- Valeurs `final readonly` : `SgtCourse { id, name, location, par }`, `SgtTee { name, ratingT, slope }`, `SgtCard { par, list<SgtTee> }`, `SgtMatch` (retenu, candidats ou aucun).
- `App\Domain\Courses\Sgt\SgtLinks` : `scorecardUrl(int)` → `https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_{id}.jpg` ; `courseViewUrl(string)` → slug du nom SGT (minuscules, suites non alphanumériques → `-`, tirets de bord retirés).
- Règle d'architecture : ces classes n'utilisent ni `Illuminate` ni `App\Models`, comme `Whs`.
- La normalisation se fait en PHP, pas en SQL : elle compare un nom de round à une liste SGT en mémoire, jamais deux lignes de la base. La comparaison des paires de la base reste en SQL (`lower(trim(...))`, D7 de 6).

### D3. Intégration Simulator Golf Tour

- `App\Integrations\Sgt\SgtClient` : `Http::withUserAgent('golf-handicaps-laravel (+https://github.com/emmpaul/golf-handicaps-laravel)')->connectTimeout(10)->timeout(20)`, puis `->throw()`.
    - `courses(): list<SgtCourse>` : `Cache::remember('sgt:courses', now()->addDay(), …)` sur le store par défaut (la base en production) ; une exception dans la closure ne met rien en cache ;
    - `scorecard(int $id): string` : octets du JPEG ;
    - toute erreur (connexion, délai, statut HTTP, format) devient `SgtUnavailable` (exception de l'intégration).
- `App\Integrations\Sgt\SgtCoursesPage::parse(string $html): list<SgtCourse>` : bloc `<script id='courses-data'>` par expression régulière, `json_decode` en `JSON_THROW_ON_ERROR`, `cardUrl` qui contient `{COURSEID}`, chaque ligne `[int id, string nom, string lieu, int par, …]` ; une ligne mal formée rend toute la page illisible (`SgtUnavailable`), comme le schéma zod de Next.
- Seul le job appelle `SgtClient` ; règle d'architecture : `App\Domain` n'utilise pas `App\Integrations\Sgt`.

**Alternative écartée** : garder la liste dans la mémoire du processus du worker, comme Next. `queue:work --max-time=3600` redémarre le processus toutes les heures et perdrait le cache ; le store `database` est déjà là.

### D4. Lecture de la carte par l'IA

- `App\Integrations\Ai\SgtScorecardReader`, sur le modèle de `ScorecardPhotoReader` (8) : agent `Agent, HasProviderOptions, HasStructuredOutput` avec `Promptable`, fournisseur `Lab::Anthropic`, modèle `config('golf.ai.model')`, `maxTokens()` = `golf.ai.rating_max_output_tokens`, effort `low`, `timeout` 90 s comme en 8. `instructions()` : la consigne de `src/ai/rating-reader.ts` (ligne « Slope/Rating » au-dessus des colonnes de tees, format `rating/slope`, une entrée par colonne dans l'ordre, nom tel qu'écrit, par total, `found = false` si l'image n'est pas une carte SGT). Schéma : `found` booléen, `par` entier, `tees[{name, rating, slope}]`, sans bornes (le contrôle est en PHP).
- L'image est le JPEG de SGT tel que servi (905×527), joint sans réencodage ni réduction par `Image::fromBase64(base64_encode($octets), 'image/jpeg')` (`Base64Image` de `laravel/ai`, aucun fichier temporaire) : il est déjà petit, et rien n'y est à retirer.
- Résultat : `SgtCardReading { ?SgtCard $card, TextUsage $usage }`, sur le modèle de `ReadingResult` (8) : l'usage est toujours présent, `card` nul quand la lecture est inutilisable (« `Unusable` » dans la suite) : refus, sortie tronquée ou hors schéma (`toArray()` vide ou de mauvaise forme), `found` faux, rating hors 40–90, slope hors 55–155 ou non entier. Rating convertie en dixièmes par `(int) round($rating * 10)`.
- Budget : `golf.ai.rating_max_input_tokens` = 2 000 et `golf.ai.rating_max_output_tokens` = 1 500, bornes de Next. `AiBudget::canSpend(CarbonImmutable $now, ?int $maxCallCostMicroUsd = null)` : le paramètre ajouté vaut par défaut `AiPricing::maxCallCost()`, le coût maximal de la lecture d'une photo (8), 52 000 micro-dollars au modèle par défaut ; la recherche passe `AiPricing::cost(model, 2000, 1500)`, soit 19 000 micro-dollars au modèle par défaut (2 / 10 micro-dollars par token). `record()` ne change pas.

### D5. Job `LookUpCourseRating`

`App\Jobs\LookUpCourseRating` (`ShouldQueue`, `$tries = 2`, `$backoff = 30`, `$timeout = 120`), payload : l'`id` de la ligne. `handle()` :

1. `StartCourseRatingLookup::handle($id): ?CourseRatingLookupTarget` lit la ligne : absente ou pas `pending` → `null`, le job s'arrête sans rien faire. La cible porte nom, tees, `sgt_course_id` déjà posé (candidat choisi) et le par total du round le plus récent de la paire (`CourseRounds::latestPar()`, `null` sans round).
2. `SGT_LOOKUP` à `off` → issue `manual`, « Recherche SGT désactivée ».
3. Par `null` → `manual`, « Aucun round sur ce parcours ».
4. Sans `sgt_course_id` : `SgtClient::courses()`, puis `SgtCourseMatcher::match`. Aucun → `not_found` ; candidats → `choose_course` ; retenu → id, nom et lieu.
5. Sans clé (`AiReading::enabled()` faux) → `manual`, « Lecture IA désactivée » ; `canSpend` faux → `manual`, « Budget IA du mois atteint ». Ces deux issues gardent le parcours SGT retenu (lien de la carte).
6. `SgtClient::scorecard()`, puis `SgtScorecardReader`. `AiBudget::record(config('golf.ai.model'), $usage->inputTokens, $usage->outputTokens, now)` aussitôt, quelle que soit la réponse (l'usage est toujours présent, comme en 8). `card` nul → `manual`, « Lecture IA impossible ».
7. Par lu différent → `manual`, « Par de la carte SGT différent » ; tee trouvé → `proposed` avec `proposed_tee` ; sinon `choose_tee`.
8. `FinishCourseRatingLookup::handle($id, CourseRatingLookupOutcome)` écrit l'issue sous `lockForUpdate`, seulement si la ligne est encore `pending` ; elle efface d'abord `note`, `candidates`, `tees_read` et `proposed_tee`, puis écrit ceux de l'issue. Elle ne touche jamais `rating_t`, `slope` ni `source`.

- `SgtUnavailable` et les erreurs passagères de l'IA remontent : la file réessaie une fois après 30 s. `failed()` appelle `FinishCourseRatingLookup` avec `failed`, « SGT injoignable ».
- Un job sur une ligne qui n'est plus `pending` ne fait rien : deux relances rapprochées donnent deux jobs, dont le second s'arrête à l'étape 1 (spec « Double relance »).
- Le job n'est pas `ShouldBeUnique` : la garde de l'étape 1 suffit et ne dépend pas du cache.

### D6. Mise en file

- `App\Domain\Courses\Actions\QueueCourseRatingLookup::handle(string $courseName, string $tees): bool` : `insert … (status 'pending') on conflict ((lower(trim(course_name))), (lower(trim(tees)))) do nothing returning id` ; une ligne insérée → `LookUpCourseRating::dispatch($id)->afterCommit()`, `true`.
- `CreateRound` et `UpdateRound` l'appellent pour la paire de la carte, dans leur transaction, après l'écriture et le recalcul. `SaveImportedRound` (8) passe par `CreateRound`. Un refus (`IncoherentRoundCard`) arrive avant : rien n'est mis en file.
- Commande `golf:queue-rating-lookups` : parcourt les paires des rounds du plus ancien au plus récent (la ligne garde le texte du premier round, comme Next), appelle `QueueCourseRatingLookup` dans une transaction, affiche « N recherches de rating mises en file. » (« 0 recherche de rating mise en file. », « 1 recherche de rating mise en file. »), code 0.
- `migrate` : `… && php artisan golf:recompute-stats && php artisan golf:queue-rating-lookups`. Le web et `migrate` ne font qu'écrire dans `jobs` ; seul le worker joint SGT.

### D7. Actions de la page Parcours

| Action | Écrit | Recalcul | Refus |
| --- | --- | --- | --- |
| `ConfirmCourseRatingProposal::handle(CourseRating)` | `rating_t`, `slope` du tee proposé, `source = sgt`, `status = confirmed`, `note = null` | oui | `NoProposalToConfirm` « Aucune proposition à confirmer » |
| `ChooseCourseRatingTee::handle(CourseRating, string $tee)` | idem, `proposed_tee = $tee` | oui | `TeeNotRead` « Tee absent de la carte SGT » |
| `ChooseSgtCandidate::handle(CourseRating, int $sgtCourseId)` | `sgt_course_*` du candidat, `status = pending`, `note = null`, job mis en file | non | `CandidateNotListed` « Parcours SGT absent des candidats » |
| `RelaunchCourseRatingLookup::handle(CourseRating)` | `sgt_course_*` à `null`, `status = pending`, `note = null`, job mis en file | non | — |

- Toutes ouvrent leur transaction et verrouillent la ligne (`lockForUpdate`). Les deux premières appellent `RecomputePlayerStats` avec `CourseRounds::playerIds()` de la paire, comme `SaveCourseRating`. Les jobs partent `afterCommit`.
- `SaveCourseRating` : l'`insert` pose `status = confirmed`, `source = manual` ; le `do update` aussi, sans toucher aux colonnes de la recherche.
- `RemoveCourseRating` : au lieu d'effacer la ligne, met `rating_t`, `slope`, `source` et `note` à `null` et le statut à `proposed` si `proposed_tee` est dans `tees_read`, sinon `choose_tee` si `tees_read` n'est pas vide, sinon `choose_course` si `candidates` n'est pas vide, sinon `manual`. `CourseHasNoRating` quand la ligne manque ou que `rating_t` est déjà nul.
- Routes, `{courseRating}` par `whereUuid()` (404 sinon), dans le groupe `web` (session exigée) :

| Méthode | Adresse | Nom | Toast |
| --- | --- | --- | --- |
| POST | `/courses/ratings/{courseRating}/confirmation` | `courses.ratings.confirmation.store` | « Rating confirmée. » |
| POST | `/courses/ratings/{courseRating}/tee` | `courses.ratings.tee.store` | « Rating confirmée. » |
| POST | `/courses/ratings/{courseRating}/candidate` | `courses.ratings.candidate.store` | « Recherche relancée. » |
| POST | `/courses/ratings/{courseRating}/lookup` | `courses.ratings.lookup.store` | « Recherche relancée. » |

- `CourseRatingLookupController` (quatre méthodes minces), `ChooseCourseRatingTeeRequest` (`tee` requis, 100 caractères au plus), `ChooseSgtCandidateRequest` (`sgtCourseId` entier positif). `ProtectedRoutesTest` : liste des routes ouvertes inchangée.

### D8. Vue de la page Parcours

- `CourseOverview` garde sa requête unique et ajoute de la ligne jointe : `id`, `status`, `label`, `note`, `source`, `sgtCourseName`, `sgtCourseLocation`, `proposal` (`{name, ratingT, slope}` du tee proposé, ou `null`), `teesRead`, `candidates` (chacun avec son `scorecardUrl`), `scorecardUrl` et `courseViewUrl` (D2), ou `null` sans parcours SGT retenu. Une paire sans ligne (balayage pas encore passé) a `id` et `status` à `null` : la saisie à la main reste, sans action de recherche.
- La page reçoit aussi `lookupPending` (au moins une ligne `pending`).

### D9. Interface, dans le thème actuel

- `courses/index` : colonne SGT entre la rating et la saisie à partir de 1 024 px (variante `sgt` des colonnes de Next), empilée en dessous. Statut en `Badge`, note dessous, indicateur `Pending` sur une ligne `pending`.
- Composants nouveaux : `courses/lookup-status` (statut, note, indicateur), `courses/sgt-outcome` (parcours SGT, liens « Carte SGT » et « The Course View » en `target="_blank" rel="noreferrer"`, proposition et « Confirmer », tees lus et « Retenir », candidats et « Choisir »), `courses/course-action` (bouton : `router.post` sur l'action Wayfinder, `preserveScroll`, état d'attente propre à la ligne, libellé d'attente de la spec `loading-feedback`, nom accessible de la spec).
- « Relancer la recherche » sous la saisie de chaque ligne qui a un `id`, `pending` compris (Q6).
- Rafraîchissement : `usePoll(2000, { only: ['courses', 'lookupPending'] }, { autoStart: false })` d'Inertia, démarré quand `lookupPending` est vrai, arrêté sinon. Les formulaires de saisie gardent leur clé `${ratingT}-${slope}` : une relecture qui ne change pas la valeur confirmée ne remet pas les champs à zéro.

### D10. Configuration

- `config/golf.php` : `sgt.lookup` (`env('SGT_LOOKUP', 'on')`), `ai.rating_max_input_tokens` (2 000), `ai.rating_max_output_tokens` (1 500).
- `AppServiceProvider::boot()` : à côté de `ensureAiConfiguration()` (8), une méthode `ensureSgtConfiguration()` : `sgt.lookup` doit valoir `on` ou `off`, sinon `InvalidArgumentException` dont le message commence par `SGT_LOOKUP`, sur le modèle de celui d'`AI_MODEL`. Le web, le worker et `migrate` échouent donc tous, comme pour `AI_MODEL`.
- `docker-compose.yml` : `SGT_LOOKUP: ${SGT_LOOKUP:-on}` dans l'environnement commun. `.env.example` : `SGT_LOOKUP=on`, commenté.

### D11. Faux dans les tests

- `phpunit.xml` : `SGT_LOOKUP=off`. Avec `QUEUE_CONNECTION=sync`, chaque `CreateRound` d'un test exécute la recherche aussitôt ; à `off`, elle finit en `manual` sans requête. Les tests de la recherche posent `config(['golf.sgt.lookup' => 'on'])` et `Http::fake()` sur `simulatorgolftour.com/*`.
- `Http::preventStrayRequests()` (8) fait échouer toute requête oubliée ; `SgtScorecardReader::fake()->preventStrayPrompts()` s'ajoute au `beforeEach` global de 8 (`tests/Pest.php`), et `AiFakeGuardTest` gagne le cas du nouvel agent.
- Réponse facturée de l'agent : `new StructuredTextResponse($lecture, json_encode($lecture), new TextUsage(1500, 300), new Meta)`, comme `billedAnswer()` de 8 ; une réponse en tableau simple a un usage nul. Les tests qui ont besoin d'une clé la posent par `config(['ai.providers.anthropic.key' => 'test-key'])`.
- Fixtures copiées de Next sous `tests/fixtures/sgt/` (`courses.html`, `scorecard_30.jpg`, `README.md` mis à jour), servies par `Http::fake` avec `Http::response(file_get_contents(…))`.
- Cache : `CACHE_STORE=array` dans `phpunit.xml` ; le test des 24 h avance l'horloge (`$this->travel(25)->hours()`) et compte les requêtes par `Http::assertSentCount`.
- Délai : le test de `SgtClient` fait lever `ConnectionException` par `Http::fake` et vérifie `SgtUnavailable` ; un test lit les options de la requête envoyée (`timeout` 20 et `connect_timeout` 10) dans la closure de `Http::fake`, qui les reçoit en second argument : `Http::assertSent` ne voit que la requête, sans ses options (relu dans `PendingRequest::buildStubHandler()`, tâche 4.2).

### D12. Données de développement

- `CourseRatingFactory` : la définition reste une rating confirmée (`status = confirmed`, `source = manual`), pour les tests de 6. États nouveaux, chacun sans `rating_t` ni `slope` : `pending()`, `proposed()` (Bethpage Black / Red, parcours SGT 30, tees lus de `scorecard_30.jpg`, tee proposé Red), `chooseCourse()` (candidats 477 et 1951), `chooseTee()` (tees de 30, aucun proposé), `manual(string $note)`, `notFound()`, `failed()` (« SGT injoignable ») ; `confirmedFromSgt()` (`proposed()` puis 714 / 144, `source = sgt`).
- `DemoCourseRatingsSeeder` passe **avant** `DemoRoundsSeeder` et écrit trois lignes par la fabrique : BETHPAGE BLACK / Red `proposed`, PAYNES VALLEY / Blue `proposed` (parcours SGT 573, tee Blue à 72,9 / 131, valeurs inventées pour la démonstration et dites comme telles dans le seeder), CALA NOVA / White `not_found`. `CreateRound` trouve donc une ligne pour chaque paire et ne met rien en file. Après les rounds, `DemoConfirmedRatingSeeder` confirme Bethpage Black par `ConfirmCourseRatingProposal`, qui recalcule.
- Test de découverte des fabriques : liste inchangée (aucun modèle nouveau).

### D13. ADR

« Recherche des ratings sur Simulator Golf Tour », numéro libre suivant à l'apply : source SGT et lien The Course View, recherche dans le worker seul, IA sur une image publique sous le budget commun, aucune valeur sans confirmation, schéma de Next gardé pour l'import, `SGT_LOOKUP`. Ligne dans `docs/adr/README.md`.

## Décisions par défaut (orchestrateur, nuit du 2026-10-28)

Le mainteneur n'était pas joignable. Chaque question ouverte a reçu l'option recommandée ; chacune se conteste avant l'apply, et un changement de l'une d'elles passe par `/opsx:update`.

| # | Question | Décision prise | Alternative |
| --- | --- | --- | --- |
| Q1 | Où garder l'état de la recherche ? | Dans `course_ratings`, colonnes nullables, comme Next et comme l'annonçait 6 (D1). | Table séparée. |
| Q2 | Que devient le statut après une relance d'un parcours confirmé ? | La recherche écrit son issue (« Proposition à confirmer »…) ; la valeur confirmée reste appliquée jusqu'à une nouvelle confirmation. Statut `confirmed` gardé dans l'enum, comme Next. | Statut de recherche séparé de la confirmation. |
| Q3 | Que fait « Retirer la rating » (ajout de 6, absent de Next) sur un parcours recherché ? | Garde la recherche : la proposition, les tees lus ou les candidats reviennent (D7). | Remettre « Saisie manuelle » et perdre la lecture. |
| Q4 | Où balayer les parcours déjà enregistrés ? | Commande lancée par `migrate` après le recalcul (D6), au lieu du démarrage du web de Next. | Démarrage du web, ou commande à lancer à la main. |
| Q5 | Où garder la liste SGT 24 h ? | Cache Laravel, store par défaut (la base) (D3). | Mémoire du worker, perdue toutes les heures par `--max-time`. |
| Q6 | « Relancer la recherche » sur une ligne `pending` ? | Proposée ; le job ne fait rien sur une ligne qui n'est plus `pending`, donc pas de double lecture. Permet de débloquer une recherche dont le job s'est perdu. | Cachée pendant la recherche, comme Next. |
| Q7 | Coût maximal d'une lecture de carte | Bornes de Next, 2 000 / 1 500 tokens, 19 000 micro-dollars au modèle par défaut ; `AiBudget::canSpend` gagne un paramètre (D4). | Coût maximal de la lecture d'une photo (52 000, bornes 6 000 / 4 000 de 8), qui bloquerait la recherche plus tôt. |
| Q8 | Texte de la note sans clé | « Lecture IA désactivée », celui de Next. | Le message de 8 (« Lecture des photos désactivée… »), qui parle de photos. |
| Q9 | Recherche dans les tests | `SGT_LOOKUP=off` dans `phpunit.xml`, activée par test avec `Http::fake` (D11). | Activée partout avec un faux global de SGT. |
| Q10 | Jeu de démonstration | Lignes écrites avant les rounds pour qu'aucun job ne parte ; Bethpage confirmé depuis SGT, Paynes Valley à confirmer (valeurs inventées), Cala Nova introuvable (D12). | Saisie à la main de Bethpage comme en 6, sans état de recherche visible. |
| Q11 | Rafraîchissement de `/courses` | Toutes les 2 s tant qu'une ligne est `pending`, comme la liste des imports de 8 (D9). | Pas de rafraîchissement : recharger à la main. |
| Q12 | `User-Agent` | Nomme le dépôt Laravel : `golf-handicaps-laravel (+https://github.com/emmpaul/golf-handicaps-laravel)`. | Celui de Next. |
| Q13 | Bornes de temps | Requête SGT 20 s (connexion 10 s), job 120 s, 2 essais à 30 s d'écart (D5). | Délai par défaut du client HTTP (30 s), job à la borne du worker (420 s). |
| Q14 | `SGT_LOOKUP` invalide | Démarrage refusé, message qui nomme la variable (D10), comme `AI_MODEL` en 8. | Valeur inconnue traitée comme `on`. |
| Q15 | Image envoyée à l'IA | Le JPEG de SGT tel que servi, sans réduction (D4). | Passage par la préparation d'image de 8 (réduction à 1568 px, inutile à 905 px). |
| Q16 | ADR | Oui : source tierce, requêtes sortantes, budget partagé (D13). | Section d'`ARCHITECTURE.md` seule. |

## Risks / Trade-offs

- [SGT change le format de sa page ou de ses cartes] → la page illisible lève `SgtUnavailable` et la ligne finit « Recherche échouée » ; une carte mal lue finit « Saisie manuelle ». La saisie à la main reste toujours possible. Test de lecture sur la copie réduite de la page.
- [Récupérer des données de SGT sans accord explicite] → une liste par 24 h, une image par parcours nouveau, un `User-Agent` qui nomme l'application, quelques parcours par mois. Si SGT le demande, `SGT_LOOKUP=off`.
- [Homonyme SGT au même par] → la page montre « SGT : nom » et le lieu à côté du lien de la carte ; rien n'est appliqué sans confirmation.
- [Lecture IA fausse] → aucune valeur sans confirmation ; le lien de la carte est à côté de la proposition.
- [`QUEUE_CONNECTION=sync` des tests : chaque round de test exécute une recherche] → `SGT_LOOKUP=off` la termine sans réseau ; les tests de 6 qui comptent les lignes de `course_ratings` sont mis à jour (tâche 2.3).
- [Une ligne `pending` dont le job a été perdu (file vidée, base restaurée)] → « Relancer la recherche » reste proposée sur une ligne `pending` (Q6).
- [Retour arrière : les recherches non confirmées sont perdues] → assumé ; le balayage les relance au redéploiement suivant.
- [Le budget de 8 change de signature] → paramètre facultatif ; les appels de 8 ne changent pas. La tâche 1.2 a relu 8 archivé et aligné D4, D5, D10 et D11 sur son code.

## Migration Plan

1. Pull request de code, après l'archivage de 8 : une migration qui modifie `course_ratings` (lignes existantes « Confirmé », source `manual`), la commande `golf:queue-rating-lookups` au bout de la commande de `migrate`, `SGT_LOOKUP` dans `docker-compose.yml`.
2. Staging : redéployer ; `docker logs "$P-migrate-1"` montre « N recherches de rating mises en file. » ; le worker les traite ; ouvrir `/courses`, vérifier chaque proposition contre sa carte SGT et confirmer. Rien ne change dans les index avant une confirmation.
3. Retour arrière : redéployer l'image précédente puis `php artisan migrate:rollback --step=1`. Les ratings confirmées restent (`rating_t` et `slope` remis non nuls), les lignes sans valeur confirmée sont effacées. Les jobs `LookUpCourseRating` restés dans `jobs` échouent sur l'ancienne image et partent dans `failed_jobs`, sans effet sur les données.

## Open Questions

Aucune.
