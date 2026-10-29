# Design

## Context

- Base : le changement `players-and-rounds` (entrée 5), implémenté mais pas encore archivé au moment de cette proposition. Ce qu'il fournit et que ce changement réutilise :
    - `App\Domain\Rounds\RoundCard` (parcours, tees, date, `holes[{par, strokeIndex, distance}]`, `lines[{playerId, strokes[18]}]`), `RoundCardChecks` (messages littéraux, `ensureCoherent`), `RoundCardWriter` ;
    - Actions `CreateRound`, `UpdateRound`, `DeleteRound` (photo effacée par `DB::afterCommit`), Query `FindDuplicateRound` ;
    - `App\Domain\Players\Actions\AssignAlias` (retire l'alias de même `lower()` où qu'il soit, puis l'insère), `PlayerName::alias()` (trim, `mb_strtoupper`) ;
    - disque `photos` (`storage/app/photos`, `serve => false`), `rounds.photo_path` relatif à ce disque, `RoundPhotoController` ;
    - `App\Domain\DomainException` et son rendu commun (flash `toast` ou 422 `{ error }`) ;
    - éditeur `resources/js/components/round-editor/*` (`form-state.ts` : `EditorState` = carte + lignes avec `key`), `resources/js/lib/round-checks.ts` et la table de cas partagée `tests/fixtures/round-checks.json`.
- `routes/web.php` sert encore `/import/{path?}` par `section-placeholder` sous le nom `import.index`. `resources/js/lib/sections.ts` rattache déjà `/imports/…` à Importer.
- Worker : `queue:work` sur la connexion `database` (ADR 0008), `--tries=2` dans la stack. Le battement de `worker-health` suit déjà chaque job.
- Dépendances PHP actuelles (`composer.json`) : framework, Inertia, Wayfinder, Tinker. Ni `laravel/ai`, ni `intervention/image`. L'image de production installe `pdo_pgsql pcntl intl opcache zip`, sans `imagick`, avec le `php.ini-production` (envoi limité à 2 Mo).
- Référence Next (`/Users/epaul/www/z_perso/golf-handicaps`) :
    - forme du brouillon : `src/scorecard/draft.ts` ; contrôles des totaux : `sumProblems` de `src/scorecard/checks.ts` ;
    - imports : `src/jobs/imports.ts`, `src/jobs/ocr-scorecard.ts`, `src/server/imports/{correction,list,refusals}.ts`, `src/app/api/imports/**`, `src/app/(app)/import/page.tsx`, `src/app/(app)/imports/[id]/page.tsx` ;
    - IA : `src/ai/{budget,pricing,model,claude-call,card,completer}.ts` (consigne fixe, effort `low`, bornes de tokens mesurées) ;
    - joueurs : `src/domain/player-match.ts`, `src/server/players/learn-from-form.ts` ;
    - interface : `src/components/import/{import-flow,import-list,decode-photo,delete-import,unreadable-photo}.tsx`, `src/components/round-editor/{autosave,line-players}.ts(x)` ;
    - fixtures : `tests/fixtures/scorecards/gspro-img3064.{jpg,draft.json}`, `gspro-calanova.jpg`, `gspro-paynes-valley.jpg`.

## Goals / Non-Goals

**Goals**

- Les requirements des six deltas, avec deux paquets nouveaux (`laravel/ai`, `intervention/image`) et une extension PHP (`imagick`).
- Une seule façon de lire une carte : le job appelle l'IA, puis des Actions du domaine écrivent l'état de l'import. Le domaine n'appelle jamais l'intégration (ARCHITECTURE.md).
- Aucun test ne joint l'API d'Anthropic, ni par oubli d'un fake, ni par une clé présente dans l'environnement de développement.
- Un budget que `course-rating-sgt` (9) réutilise tel quel.

**Non-Goals**

- Cadrage, coins, recadrage, fusion d'une lecture locale, marque IA par case (décisions 2 et 3).
- Recalcul des index et mise en file SGT : ils viennent avec `CreateRound` / `DeleteRound` quand 6 et 9 les y ajoutent.
- Plusieurs workers : le budget est vérifié sans verrou (voir Risks).

## Decisions

### D1. Deux tables en UUID

| Table | Colonnes | Contraintes |
| --- | --- | --- |
| `imports` | `id`, `status` (`varchar`), `photo_path`, `draft` (`jsonb`, nullable), `correction` (`jsonb`, nullable), `failure` (`varchar(200)`, nullable), `round_id` (nullable), timestamps | `CHECK status IN ('queued','reading','ready','failed')` ; `CHECK (status <> 'ready' OR draft IS NOT NULL)` ; FK `round_id` vers `rounds` en cascade, unique ; index `created_at` |
| `ai_usage` | `id`, `month` (`char(7)`, `YYYY-MM`), `model`, `input_tokens`, `output_tokens`, `cost_micro_usd` (`bigint`), `created_at` | `CHECK` des trois compteurs `>= 0` ; index `month` |

- `failure` remplace `ai_note` de Next : il n'existe plus de « Prêt à vérifier » avec un message, seulement la raison d'un « Échec ». L'import SQLite (10) y copie `ai_note`.
- `round_id` en cascade : supprimer un round supprime son import (requirement « Import retiré avec son round »), sans toucher `DeleteRound`. La photo partagée par le round et l'import est effacée une fois, par `DeleteRound`, après le commit.
- Statuts : enum PHP `ImportStatus` (`Queued`, `Reading`, `Ready`, `Failed`) castée sur le modèle, avec `label()` : « En attente », « Lecture en cours », « Prêt à vérifier », « Échec ».
- `ai_usage` garde la forme de Next pour que l'import SQLite (10) la copie sans calcul ; le mois est figé à l'écriture dans le fuseau du budget.
- Pas de colonne `corners`, `corners_source`, `ai_cells` (décision 3).

### D2. Le brouillon : une valeur du domaine, la forme de `src/scorecard/draft`

- `App\Domain\Imports\ScorecardDraft` : objet immuable, la forme de Next (`courseName`, `tees`, `playedOn`, `par{holes[18], out, in, total}`, `strokeIndex[18]`, `distance{…}`, `players[{name, scores{…}}]`), chaque case `int|null`. `fromArray(array): ?self` refuse (renvoie `null`) toute ligne qui n'a pas 18 trous, un total non entier, plus de 4 lignes : c'est le test « hors de la forme attendue ». `toArray()` est ce que garde `imports.draft`.
- La date : l'IA rend le texte lu (`dateText`, « 10/17/2026 ») ; `SimulatorDate::parse(?string): ?string` cherche `\d{1,2}/\d{1,2}/\d{4}`, lit mois/jour/année et vérifie `checkdate`. Faire lire la date au serveur garde la règle « mois/jour/année » testable sans IA.
- `RoundCard` gagne deux champs facultatifs, à `null` pour une saisie manuelle : `parTotals` (`{out, in, total}` lus) et, par ligne, `readName` et `totals`. `RoundCardChecks::problems` ajoute, quand des totaux sont présents, « Case vide » sur un total vide et les trois contrôles de `sumProblems` de Next (« Somme aller 36 ≠ OUT 38 », « Somme retour … ≠ IN … », « OUT + IN 72 ≠ TOT 73 »), avec les clés de case `par:out`, `player0:in`… Les distances lues ne sont pas contrôlées et l'éditeur calcule toujours leurs totaux.
- `round-checks.ts` reçoit le même ajout ; `tests/fixtures/round-checks.json` gagne les cas des totaux (somme aller fausse, OUT + IN faux, total lu vide, carte sans totaux). Les totaux ne sont pas enregistrés : un round garde ses trous, comme dans l'entrée 5.
- `ImportEditorState::for(Import, today)` construit l'état d'ouverture (requirement « Reprise ») : la correction enregistrée si elle passe la validation de D7, sinon le brouillon (lignes associées par D5, date lue ou `today`), sinon l'écran vide daté de `today` (`LocalTime`). L'état dit aussi si les cases vides sont surlignées dès l'ouverture (`fromReading: true`).

**Alternative écartée** : laisser l'IA rendre la date ISO. Elle se tromperait de sens sur « 03/04/2026 » sans que le code puisse le voir.

### D3. L'intégration : un agent du Laravel AI SDK, en sortie structurée

- `App\Integrations\Ai\ScorecardPhotoReader` implémente `Agent`, `HasStructuredOutput` et `HasProviderOptions` (trait `Promptable`). `instructions()` : la consigne fixe de `src/ai/completer.ts`, adaptée à la photo entière (« la carte peut n'occuper qu'une partie de la photo, prise de biais ; recopie sans corriger ni calculer ; null pour une case vide ou illisible ; ignore les lignes de joueur vides ») et à la date (« recopie le texte de la date tel qu'affiché »). `schema(JsonSchema $schema)` : la forme de D2, toutes les valeurs `integer` ou `string` nullables, `dateText` en plus. Relevé de la tâche 1.2 : chaque propriété porte `->required()` (sans lui, elle est facultative) et `->nullable()` ; tableaux par `$schema->array()->items(…)`, objets par `$schema->object([…])`. Le SDK envoie la sortie structurée native d'Anthropic (`output_config.format` `json_schema`, `anyOf` avec `null`, `additionalProperties: false` partout), sans `tool_choice` forcé, que Sonnet 5.5 et Opus 5.5 refusent ; il retire de lui-même `minimum`, `maximum`, `maxItems`, `pattern`. Le schéma ne porte donc pas de bornes.
- Fournisseur `Lab::Anthropic`, modèle passé à chaque appel depuis `config('golf.ai.model')` (`AI_MODEL`). La clé est celle de `config/ai.php` (`ANTHROPIC_API_KEY`). Appel : `prompt($consigne, attachments: [Image::fromPath($jpeg, 'image/jpeg')], provider: Lab::Anthropic, model: config('golf.ai.model'), timeout: 90)` ; le délai HTTP du SDK vaut 60 s par défaut, il reste sous le `$timeout` de 120 s du job. Bornes (relevé 1.2, le SDK expose les deux) : une méthode `maxTokens(): int` qui rend `golf.ai.max_output_tokens` (4 000 ; sans elle le SDK envoie `max_tokens` 64 000), et `providerOptions(Lab|string $provider)` qui rend `['output_config' => ['effort' => 'low']]`, fusionné par le SDK à côté de `format`. Sur Sonnet 5.5 et Opus 5.5 la réflexion est toujours active : ses tokens comptent dans `max_tokens` et dans `output_tokens`, donc dans le coût ; la mesure de 9.3 dira si 4 000 suffit.
- `App\Integrations\Ai\ScorecardImage::forReading(string $path): string` : lit le WebP du disque `photos` par Intervention (driver Imagick), le réduit pour que le grand côté fasse au plus 1568 px (sans agrandir), l'encode en JPEG qualité 85 sans métadonnées, dans un fichier temporaire joint par `Files\Image::fromPath()`. JPEG comme Next, dont les bornes de tokens ont été mesurées avec ce format.
- L'intégration rend `ReadingResult { array $card, TextUsage $usage }` (`Laravel\Ai\Responses\Data\TextUsage` : `inputTokens`, cache lu et écrit compris, `outputTokens`, réflexion comprise). Relevé 1.2 : un refus (`stop_reason` `refusal`), une sortie tronquée (`max_tokens`) ou un texte qui n'est pas du JSON ne lèvent rien ; `prompt()` rend une `StructuredAgentResponse` dont `toArray()` vaut `[]`, avec l'usage facturé. Un JSON valide de mauvaise forme revient tel quel : le SDK ne le confronte pas au schéma. Il n'y a donc pas de `ReadingOutcome` : `ScorecardDraft::fromArray()` (D2) tranche, une carte vide ou hors forme donne `null`. La raison d'arrêt (`$response->steps->last()->finishReason`) n'est pas lue : le faux du SDK rend toujours `Stop`, un test ne pourrait pas la faire varier.
- Erreurs passagères, remontées telles quelles pour que la file réessaie : `Laravel\Ai\Exceptions\FailoverableException` (`ProviderConnectionException` qui enveloppe `ConnectionException`, `RateLimitedException` 429, `ProviderOverloadedException` 529, 502, 503, 504, 520, 522, 524, `InsufficientCreditsException` 402) et `Illuminate\Http\Client\RequestException` d'un statut 5xx (500 n'est pas converti par le SDK). Les autres `RequestException` (400, 401) remontent aussi ; aucune n'a d'usage. Le SDK refait déjà un essai dans le processus quand `Retry-After` vaut 10 s ou moins.

**Alternative écartée** : le SDK `anthropic-ai/sdk` en PHP appelé directement, comme Next. Plus de code (appel, parse, erreurs, fakes à écrire), alors que `laravel/ai` fournit sortie structurée, pièces jointes et fakes, et sert aussi 9. Décision 2 du port l'impose.

### D4. Le job et le budget

- `App\Jobs\ReadScorecardImport` (`ShouldQueue`, `$tries = 2`, `$backoff = 30`, `$timeout = 120`), mis en file par `CreateImport` après le commit (`afterCommit`). `handle()` :
    1. `StartReading::handle($importId)` : verrouille la ligne (`lockForUpdate`), ne fait rien si l'import n'existe plus ou n'est ni `queued` ni `reading` (suppression, ou job déjà traité), sinon passe à `reading`. Un import supprimé avant ce point n'est jamais envoyé à l'IA.
    2. Sans clé : `FailImport` avec « Lecture des photos désactivée : aucune clé d’IA n’est configurée. ».
    3. `AiBudget::canSpend(now)` faux : `FailImport` avec « Budget IA du mois atteint », sans appel.
    4. Appel de D3. Toute réponse porte un usage (zéro si l'API n'en donne pas) : `AiBudget::record()` aussitôt, dans sa propre écriture, avant toute autre décision. Une exception (D3) n'en a pas : rien n'est compté.
    5. `Read` et `ScorecardDraft::fromArray()` non nul : `MarkImportReady` (brouillon). Sinon : `FailImport` « Lecture IA indisponible », sans relancer.
- `failed(Throwable)` (deux erreurs passagères, ou timeout) : `FailImport` « Lecture IA indisponible ». `FailImport` et `MarkImportReady` n'écrivent que sur un import encore `reading`.
- `App\Domain\AiUsage\AiBudget` : `canSpend(CarbonImmutable $now): bool` = cumul du mois + `maxCallCost()` ≤ plafond ; `record(model, inputTokens, outputTokens, now)`. Mois : `$now->setTimezone(config('golf.ai.budget_time_zone'))->format('Y-m')`. Coût : `AiPricing::cost(model, in, out)` = `in × prix d'entrée + out × prix de sortie`, prix en micro-dollars par token dans `config('golf.ai.prices')`. `maxCallCost()` = coût de `max_input_tokens` (4 000) et `max_output_tokens` (4 000) au modèle configuré.
- Valeurs par défaut de `config/golf.php` : `AI_MODEL` = `claude-sonnet-5-5` (le Sonnet le plus récent, défaut du port) ; prix relevés le 2026-10-28 dans la grille d'Anthropic (en cache au 2026-10-06), à revérifier à l'apply : `claude-sonnet-5-5` 2 / 10, `claude-opus-5-5` 4 / 20 micro-dollars par token. Coût maximal d'un appel au modèle par défaut : 4 000 × 2 + 4 000 × 10 = 48 000 micro-dollars ; le plafond de 5 $ permet donc au moins 104 lectures par mois.
- Fuseau : `AppServiceProvider::boot()` vérifie `AI_BUDGET_TIME_ZONE` par `in_array(…, DateTimeZone::listIdentifiers())` et lève une exception qui nomme la variable. Tout processus échoue donc, `migrate` compris : la stack garde `web` et `worker` arrêtés, comme pour une migration en échec.
- Tarif du modèle : le même `boot()` vérifie que `config('golf.ai.model')` est une clé de `config('golf.ai.prices')`, avec ou sans clé d'API, et lève sinon une exception dont le message nomme `AI_MODEL` (« AI_MODEL claude-unknown-1 n’a pas de tarif dans config/golf.php »). Sans tarif, le budget ne peut ni compter un appel ni borner le suivant : mieux vaut une stack arrêtée qu'une lecture qui dépense sans compter. Passer à un modèle nouveau demande donc d'ajouter son tarif à `config/golf.php` dans la même pull request.

**Alternative écartée** : `->queue()` du SDK (agent mis en file par le SDK). Le statut de l'import, le budget avant l'appel et le compte de l'usage facturé demandent un job à nous ; le SDK n'y gagnerait qu'une indirection.

### D5. Reconnaissance des noms et apprentissage des alias

- `App\Domain\Players\PlayerNameMatcher::match(string $read, Collection $players): ?PlayerMatch` : port de `matchPlayerName` (`exact` ou `close`, distance d'édition ≤ 1, joueur unique). La distance se calcule sur des tableaux de caractères (`mb_str_split`), pas avec `levenshtein()` de PHP qui compte des octets (« ÉMILE » / « EMILE » vaudrait 2).
- `App\Domain\Imports\Actions\SaveImportedRound::handle(Import, RoundCard): Round`, en une transaction : verrouille l'import (`lockForUpdate`) ; s'il a déjà un round, `ImportAlreadySaved` (« Cette carte est déjà enregistrée ») et rien n'est écrit ; sinon appelle `CreateRound` (contrôles, totaux lus compris, transaction imbriquée), copie `photo_path` sur le round, relie l'import, puis pour chaque ligne qui a un `readName` et un joueur : si `PlayerNameMatcher` ne donne pas `exact` vers ce joueur, `AssignAlias`. Un refus (`IncoherentRoundCard`) ou un doublon (détecté avant, D6) n'apprend donc rien.

### D6. Routes et contrôleurs

| Méthode | Adresse | Nom | Contrôleur |
| --- | --- | --- | --- |
| GET | `/import` | `import.index` | `ImportController@index` |
| POST | `/imports` | `imports.store` | `ImportController@store` |
| GET | `/imports/{import}` | `imports.show` | `ImportController@show` |
| DELETE | `/imports/{import}` | `imports.destroy` | `ImportController@destroy` |
| GET | `/imports/{import}/photo` | `imports.photo` | `ImportPhotoController` |
| PUT | `/imports/{import}/correction` | `imports.correction.update` | `ImportCorrectionController` |
| POST | `/imports/{import}/round` | `imports.round.store` | `ImportRoundController` |
| POST | `/imports/{import}/reading` | `imports.reading.store` | `ImportReadingController` |

- `{import}` passe par `whereUuid()`. La route `section-placeholder` d'Importer disparaît ; `/import/xyz` tombe dans la route de repli (404).
- `index` : props `imports` (Query `ImportList`, 20 derniers, `label`, `failure`, `roundId`, actions permises, dont `retry` pour un import `failed` sans round quand la clé est configurée), `readingEnabled` (clé configurée). La page relance `router.reload({ only: ['imports'] })` toutes les 2 s tant qu'un import est `queued` ou `reading` (`usePoll` d'Inertia, démarré et arrêté selon les props).
- `store` : `StoreImportRequest` (`photo` : `required|file|max:20480`, message « La photo dépasse 20 Mo ») ; sans clé, `ImportReadingDisabled` (`DomainException`) ; `CreateImport` (D8) ; retour avec le flash `toast` succès « Lecture en file d'attente. Tu peux envoyer la photo suivante. ».
- `show` : import avec round → redirection vers `rounds.show` ; sinon la page `imports/show` (éditeur, photo, `failure`, joueurs, parcours connus, état de D2).
- `destroy` : `DeleteImport` (verrou, refus `ImportBeingRead` « Lecture en cours » et `ImportAlreadySaved` « Cette carte est déjà enregistrée », photo effacée après le commit) ; retour vers `import.index`.
- `correction` : JSON seulement (`fetch` avec `keepalive`, en-tête `X-XSRF-TOKEN`), 204 si accepté ; `SaveImportCorrection` fait un seul `UPDATE … WHERE id = ? AND round_id IS NULL AND status IN ('ready','failed')`, puis, sans ligne touchée, dit pourquoi : 409 `{ error: "Cette carte est déjà enregistrée" }` ou `{ error: "Lecture en cours" }`. 404 « Import introuvable » par la liaison de route (`missing()`). Corps invalide : 422.
- `round` : même Form Request que `rounds.store` (classe de base de l'entrée 5) étendue des totaux et noms lus ; d'abord, un import qui a déjà un round est refusé par `ImportAlreadySaved` (toast « Cette carte est déjà enregistrée »), avant `FindDuplicateRound` qui trouverait sinon le round de cet import et répondrait « Ce round semble déjà enregistré » ; puis `FindDuplicateRound` sans `force` comme `RoundController@store` (flash `duplicate`) ; puis `SaveImportedRound`, qui refait le contrôle sous verrou pour deux envois simultanés ; redirection vers `rounds.index` avec « Round enregistré. ».
- `reading` (relance) : sans clé, `ImportReadingDisabled` ; sinon `App\Domain\Imports\Actions\RetryImportReading::handle(Import, CarbonImmutable $now)`, en une transaction : verrouille l'import ; `queued` ou `reading` → `ImportBeingRead` (« Lecture en cours ») ; round présent → `ImportAlreadySaved` ; `ready` → `ImportNotFailed` (« Cet import n’est pas en échec ») ; `AiBudget::canSpend($now)` faux → `AiBudgetReached` (« Budget IA du mois atteint »), l'import garde son statut et sa raison ; sinon `status = queued`, `failure`, `draft` et `correction` à `null`, `photo_path` inchangé, et `ReadScorecardImport` mis en file après le commit comme dans `CreateImport`. Retour vers `import.index` avec le toast « Lecture relancée. ». Le job ne change pas : `StartReading` accepte un import `queued`, et le budget est vérifié de nouveau avant l'appel (D4), qui peut encore mener à « Budget IA du mois atteint » si d'autres lectures ont consommé le reste entre-temps.
- `ProtectedRoutesTest` : liste ouverte inchangée.

### D7. Correction enregistrée et autosave

- Forme : `{ version: 1, card: RoundCard }`, où `card` est l'état de l'éditeur sans les `key` (totaux et noms lus compris). Validée à l'écriture (Form Request, `playerId` UUID ou `null`, 4 lignes au plus) et à la lecture (`ImportCorrection::fromArray(): ?RoundCard`, illisible : on repart du brouillon). Un `playerId` qui n'existe plus devient `null` à l'ouverture.
- `resources/js/components/round-editor/autosave.ts` : port de `createCorrectionAutosave` (1 s après la dernière modification, un envoi à la fois, envoi immédiat avec `keepalive` sur `visibilitychange` caché et `pagehide`, nouvel essai 5 s après une erreur réseau ou une 5xx, arrêt sur 409 avec le message du serveur, sur 401, 404 et 422). L'URL vient de l'action Wayfinder `ImportCorrectionController`.
- L'éditeur de l'entrée 5 gagne : le repère « Carte lue par l’IA, vérifie chaque case » en tête, en `text-ai` (token `--color-ai`, aujourd'hui inutilisé depuis le retrait de `ai_cells`), affiché quand `imports.draft` n'est pas nul (prop `readByAi` de `ImportEditorState`, vrai aussi quand l'écran part d'une correction enregistrée) ; la photo (`photoUrl`), la note d'échec, l'indicateur d'enregistrement (`role="status"`), « lu « … » » dans `line-players`, les cases de totaux modifiables quand ils sont lus, `fromReading` pour le surlignage d'ouverture. `rounds/create` et `rounds/edit` ne passent aucun de ces props et ne changent pas.

### D8. Envoi et conversion des photos

- Navigateur : port de `decode-photo.ts` (`createImageBitmap`, JPEG qualité 0,9, 3000 px au plus) et de `unreadable-photo.tsx`. Envoi par `router.post(imports.store(), { photo }, { forceFormData: true })`.
- `App\Domain\Imports\Actions\CreateImport::handle(UploadedFile): Import` : `WebpPhoto::fromUpload()` (Intervention v4, installée en 4.4 par la tâche 1.1 : `ImageManager::usingDriver(Intervention\Image\Drivers\Imagick\Driver::class)`, `decodeSplFileInfo()` ou `decodePath()` applique l'orientation EXIF (`autoOrientation` vrai par défaut), `scaleDown(3000, 3000)`, `encode(new WebpEncoder(quality: 90, strip: true))` ; v4 n'a plus `ImageManager::imagick()`, `read()` ni `toWebp()`) ; exception de lecture → `UnreadablePhoto` « Photo illisible » (`DomainException`). Le WebP s'écrit sur `photos` sous `imports/<uuid>.webp`, puis la transaction crée l'import `queued` et met le job en file ; si elle échoue, le fichier est effacé.
- `ImportPhotoController` : comme `RoundPhotoController` (type `image/webp`, `Cache-Control: private, max-age=86400`, 404 « Photo introuvable »).
- Production : `imagick` ajouté à `install-php-extensions` ; `docker/php/uploads.ini` (`upload_max_filesize = 20M`, `post_max_size = 21M`) copié dans `conf.d`. La stack de fumée vérifie qu'un JPEG de 8 Mo passe (tâche 9.2).

### D9. Faux de l'IA dans les tests

- `phpunit.xml` force `ANTHROPIC_API_KEY=` (vide) : aucun test n'hérite de la clé du `.env` de développement.
- `tests/Pest.php` : `beforeEach` global qui appelle `ScorecardPhotoReader::fake()->preventStrayPrompts()` et `Http::preventStrayRequests()`. Un appel non prévu par le test lève une exception au lieu de partir (`RuntimeException` « Attempted prompt [...] without a fake agent response. ») ; une requête HTTP qui contournerait le fake de l'agent aussi. Les tests de `tests/Unit` qui ne démarrent pas l'application n'ont ni façade ni conteneur : le `beforeEach`, posé sur `Feature` et `Unit`, ne s'applique qu'aux tests qui étendent `Tests\TestCase`. Le faux du SDK répond sans clé configurée.
- Les tests du chemin « avec clé » posent `config(['ai.providers.anthropic.key' => 'test-key'])` (clé exacte de `config/ai.php` relevée en 1.2) et fournissent la réponse : `ScorecardPhotoReader::fake([[…brouillon de gspro-img3064.draft.json + dateText…]])`, ou une closure `fn (string $prompt, Collection $attachments, TextProvider $provider, string $model)` (relevé 1.2 : la closure du faux ne reçoit pas d'`AgentPrompt`) qui vérifie l'image jointe pendant l'appel, quand le fichier temporaire existe encore (une seule, `LocalImage`, `getimagesize($attachments->first()->path)` 1568×1176), et l'absence des noms de joueurs dans `$prompt`. `assertPrompted(fn (AgentPrompt $prompt) => …)` reçoit, lui, l'`AgentPrompt` (`->prompt`, `->attachments`, `->model`). Assertions : `assertPrompted`, `assertPromptedTimes(1)`, `assertNeverPrompted()` (budget, sans clé, import supprimé).
- Usage facturé (relevé 1.2) : la réponse fausse est `new StructuredTextResponse($card, json_encode($card), new TextUsage(3000, 1200), new Meta)` (`Laravel\Ai\Responses\StructuredTextResponse`, `Laravel\Ai\Responses\Data\{TextUsage, Meta}`), dans la liste ou rendue par la closure ; `prompt()` la rend avec cet usage. Un tableau nu donne un usage nul. Pas de méthode protégée ni de double de test. Refus et sortie tronquée se simulent par la même réponse avec une carte `[]` : c'est ce que rend la vraie passerelle ; une sortie hors forme, par la carte de 17 scores.
- `phpunit.xml` met `QUEUE_CONNECTION=sync` : un test d'envoi qui ne regarde que la création de l'import appelle `Queue::fake()` et vérifie `Queue::assertPushed(ReadScorecardImport::class)` ; sans lui, le job tourne aussitôt contre le fake de l'agent.
- Erreurs passagères : le fake lève l'exception passagère du SDK dans une closure (`throw ProviderOverloadedException::forProvider('anthropic')`) ; le test exécute le job deux fois (`$job->handle()` puis `failed()`), sans worker.
- Arch test : seul `App\Jobs\ReadScorecardImport` utilise `App\Integrations\Ai` ; `App\Domain` n'utilise ni `App\Integrations` ni `Laravel\Ai`.

### D10. Données de développement

- `ImportFactory` : états `queued()`, `reading()`, `ready(?array $draft)` (par défaut le brouillon de `IMG_3064`), `failed(string $failure)`, `withPhoto()` (copie `database/data/img3064.webp` de l'entrée 5), `savedAs(Round)`. `AiUsageFactory` : état `inMonth('2026-10')`.
- `DemoImportsSeeder` (garde de production de `DemoSeeder`), appelé par `DemoDatabaseSeeder` : un import « Prêt à vérifier » de la carte de référence avec une ligne lue « JULIEN », un import « Échec » « Budget IA du mois atteint », un import « En attente » sans job (il resterait en attente : l'état sert à voir le motif), et 3 lignes d'`ai_usage` du mois courant. Aucun appel à l'IA.
- Test de découverte des fabriques : la liste littérale passe à huit modèles.

### D11. ADR

« Lecture des cartes par l'IA et photos en WebP » : D3 (Laravel AI SDK, sortie structurée, intégration hors du domaine), D4 (job, budget avant l'appel, usage compté même inexploitable, démarrage refusé sans tarif du modèle, relance par la même file), D8 (Intervention et Imagick, WebP sans métadonnées), D9 (faux obligatoires dans les tests). Numéro : le suivant libre à l'apply (0009 est pris par l'entrée 5, 0010 l'est probablement par `handicap-index`). `docs/adr/README.md`, `ARCHITECTURE.md` (section « Import des cartes ») et `docs/deployment.md` (variables de l'IA) sont mis à jour.

## Risks / Trade-offs

- [API du Laravel AI SDK connue par sa documentation, pas encore par le code] → tâche 1.2 : installer le paquet, relever dans `vendor/laravel/ai` le schéma structuré, les pièces jointes, l'usage, les exceptions de refus et de sortie invalide, et les fakes avant d'écrire le premier test ; corriger ce design si un point diffère.
- [Le SDK lève sur une sortie hors schéma et perd l'usage] → écarté par le relevé 1.2 : le SDK ne lève pas, il rend une carte vide ou telle quelle avec l'usage. Le schéma reste permissif (tout nullable, pas de bornes) et la forme se contrôle en PHP (`ScorecardDraft::fromArray`) ; l'usage n'est perdu que si l'API répond par une erreur HTTP, qui n'est pas facturée.
- [Deux workers vérifient le budget en même temps] → un seul worker dans la stack ; le dépassement reste borné à un appel par worker en trop. Un verrou consultatif PostgreSQL s'ajoutera si la stack en lance plusieurs.
- [Le coût maximal repose sur des bornes mesurées dans Next avec un autre modèle et le panneau redressé] → tâche 9.3 : mesurer l'usage réel de trois photos de référence sur le staging et ajuster `max_input_tokens` / `max_output_tokens`.
- [Une photo entière, de biais, se lit moins bien que le panneau redressé] → assumé par la décision 2 ; l'écran de correction et les contrôles des totaux lus restent la garde. La passe sur le staging (9.3) lit les trois photos réelles du dépôt Next.
- [`round_id` en cascade : un round supprimé emporte son import et sa correction] → c'est le comportement demandé ; la photo est effacée par `DeleteRound`.
- [L'éditeur commun grossit de props propres à l'import] → props facultatifs, groupés sous `importContext` ; les pages `rounds/*` n'en passent aucun et leurs tests ne changent pas.

## Migration Plan

1. Pull request de code : deux migrations nouvelles ; `composer require laravel/ai intervention/image`, publication de `config/ai.php` (relevé 1.2 : la seule migration du SDK, `create_agent_conversations_table`, sert aux agents qui retiennent une conversation ; le SDK ne la charge pas, elle n'est pas publiée) ; `imagick` et `uploads.ini` dans le `Dockerfile`.
2. Staging : ajouter `ANTHROPIC_API_KEY` (et au besoin `AI_MODEL`, `AI_MONTHLY_BUDGET_USD`, `AI_BUDGET_TIME_ZONE`) dans Dokploy, redéployer, importer les trois photos de référence.
3. Retour arrière : redéployer l'image précédente puis `php artisan migrate:rollback` des deux migrations ; les rounds créés depuis un import restent.

## Open Questions

Aucune.
