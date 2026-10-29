# 0011. Lecture des cartes par l'IA et photos en WebP

## Statut

Acceptée, 2026-10-28 (changement OpenSpec `scorecard-ai-import`).

## Contexte

Le port lit la photo d'une carte de score du simulateur pour pré-remplir l'éditeur des rounds. L'application Next le faisait en appelant le SDK PHP d'Anthropic depuis un job, après avoir redressé la carte. Le port garde la lecture par l'IA, mais sur la photo entière (décision 2 du port), sans cadrage ni marque par case (décision 3). La lecture coûte de l'argent à chaque appel : il faut un plafond mensuel qui tienne même quand la réponse est inexploitable, et aucun test ne doit joindre l'API, ni par un fake oublié, ni par la clé que le `.env` de développement contient. Les photos arrivent des téléphones en JPEG ou en HEIC, jusqu'à une dizaine de mégaoctets, avec leurs métadonnées (position GPS comprise), alors que l'image de production limitait l'envoi à 2 Mo.

## Alternatives

- **SDK `anthropic-ai/sdk` appelé directement, comme Next** : appel, parse, erreurs et doubles de test à écrire, alors que `laravel/ai` fournit sortie structurée, pièces jointes et fakes, et servira aussi à `course-rating-sgt`.
- **Agent mis en file par le SDK (`->queue()`)** : le statut de l'import, le budget vérifié avant l'appel et l'usage compté sur une réponse inexploitable demandent un job à nous.
- **Date ISO rendue par l'IA** : elle se tromperait de sens sur « 03/04/2026 » sans que le code le voie. L'IA recopie le texte, le serveur le lit en mois/jour/année (`SimulatorDate`).
- **Photo gardée telle qu'envoyée** : métadonnées gardées, poids et formats variables, HEIC illisible par certains navigateurs.
- **GD au lieu d'Imagick** : pas de HEIC, pas de profil ICC, orientation EXIF à appliquer à la main.

## Décision

- **Intégration hors du domaine.** `App\Integrations\Ai\ScorecardPhotoReader` est un agent du Laravel AI SDK (`laravel/ai` v1.3) : `Agent`, `HasStructuredOutput`, `HasProviderOptions`, fournisseur Anthropic, modèle `config('golf.ai.model')` (`AI_MODEL`, `claude-sonnet-5-5` par défaut), consigne fixe sans aucun nom de joueur, sortie structurée native (`output_config.format`), effort `low`, `max_tokens` 4 000. Le schéma est permissif (tout nullable, sans bornes, que le SDK retirerait de toute façon) ; la forme se contrôle en PHP par `ScorecardDraft::fromArray()`. Seul `App\Jobs\ReadScorecardImport` utilise l'intégration, et `App\Domain` n'utilise ni `App\Integrations` ni `Laravel\Ai` (règles d'architecture). `ScorecardImage` réduit le WebP stocké à 1568 px de grand côté et l'envoie en JPEG qualité 85, le format avec lequel Next avait mesuré ses bornes.
- **Un job, le budget avant l'appel.** `ReadScorecardImport` (2 essais, 30 s d'attente, 120 s au plus) : `StartReading` verrouille l'import et ne lit qu'un import `queued` ou `reading` ; sans clé ou sans budget, l'import échoue sans appel ; après l'appel, l'usage facturé est compté dans `ai_usage` avant toute autre décision, même quand la carte est vide ou hors forme (« Lecture IA indisponible », sans nouvel essai) ; deux erreurs passagères mènent au même échec par `failed()`. `AiBudget::canSpend()` compare le cumul du mois plus le coût de l'appel le plus cher (4 000 tokens en entrée et en sortie) au plafond `AI_MONTHLY_BUDGET_USD`. Le mois se fige à l'écriture dans le fuseau `AI_BUDGET_TIME_ZONE` ; le coût est en micro-dollars entiers d'après la grille de `config/golf.php`.
- **Démarrage refusé sans tarif.** `AppServiceProvider::boot()` lève une exception qui nomme la variable quand `AI_MODEL` n'a pas de tarif dans `config/golf.php` (avec ou sans clé) ou quand `AI_BUDGET_TIME_ZONE` n'est pas un fuseau connu. Tout processus échoue, `migrate` compris : la stack garde `web` et `worker` arrêtés plutôt que de dépenser sans compter.
- **Relance par la même file.** « Relancer la lecture » (`RetryImportReading`) remet un import en échec à `queued`, sa photo inchangée, après le même contrôle du budget, et pousse le même job après le commit.
- **Photos en WebP sans métadonnées.** `intervention/image` v4 sur Imagick : `CreateImport` décode la photo reçue (orientation EXIF appliquée), la réduit à 3000 px de grand côté et l'écrit en WebP qualité 90 avec `strip`, qui retire EXIF et XMP et garde le profil ICC pour que les couleurs ne changent pas. L'image de production installe `imagick` et `docker/php/uploads.ini` (`upload_max_filesize = 20M`, `post_max_size = 32M`) ; le navigateur réencode déjà la photo en JPEG de 3000 px au plus avant l'envoi.
- **Faux obligatoires dans les tests.** `phpunit.xml` vide `ANTHROPIC_API_KEY` ; un `beforeEach` global appelle `ScorecardPhotoReader::fake()->preventStrayPrompts()` et `Http::preventStrayRequests()`. Un test du chemin « avec clé » pose une clé de test et fournit la réponse, usage facturé compris (`StructuredTextResponse` avec un `TextUsage`).

## Écarts relevés pendant l'implémentation

- Le SDK ne lève rien sur un refus, une sortie tronquée ou un texte qui n'est pas du JSON : il rend une carte vide avec l'usage facturé, et un JSON de mauvaise forme tel quel. Il n'y a donc pas de type de résultat par issue ; `ScorecardDraft::fromArray()` tranche, et la raison d'arrêt n'est pas lue (le faux du SDK rend toujours `Stop`).
- L'usage est un `Laravel\Ai\Responses\Data\TextUsage` : `inputTokens` comprend les tokens de cache lus et écrits, `outputTokens` la réflexion.
- Sur Sonnet 5.5 et Opus 5.5 la réflexion est toujours active : ses tokens comptent dans `max_tokens` (4 000) et dans le coût. La mesure sur le staging (tâche 9.3) dira si la borne suffit.
- Intervention v4 n'a plus `ImageManager::imagick()`, `read()` ni `toWebp()` : le gestionnaire se construit par `ImageManager::usingDriver(Imagick\Driver::class)` et encode par `WebpEncoder(quality: 90, strip: true)`, qui garde le profil ICC.
- `CreateImport::handle()` reçoit un `SplFileInfo`, pas un `UploadedFile` : `UploadedFile` est dans `Illuminate\Http`, que les règles d'architecture interdisent au domaine.
- `America/Montreal`, le fuseau par défaut, est un lien de compatibilité absent de `DateTimeZone::listIdentifiers()` sans argument : le contrôle du démarrage lit `listIdentifiers(DateTimeZone::ALL_WITH_BC)`.
- `post_max_size` vaut 32M et non 21M : au-dessus de `post_max_size`, le middleware global `ValidatePostSize` de Laravel répond 413 avant la Form Request, sans session ni message. Avec 32M, une photo de 20 à 32 Mo arrive sans fichier (`UPLOAD_ERR_INI_SIZE`) et reçoit « La photo dépasse 20 Mo ». Vérifié sur la stack de fumée : 8 Mo créent l'import, 25 Mo reçoivent le message, 40 Mo reçoivent 413.
- L'extension `imagick` est déjà chargée dans le conteneur Sail `laravel.test` : le runtime de développement ne change pas.

## Conséquences

- Passer à un modèle nouveau demande d'ajouter son tarif à `config/golf.php` dans la même pull request ; sans lui, la stack ne démarre pas.
- Le budget est vérifié sans verrou : avec un seul worker, il n'est jamais dépassé ; avec plusieurs, de un appel par worker en trop au plus. Un verrou consultatif PostgreSQL s'ajoutera si la stack en lance plusieurs.
- Un envoi de plus de 32 Mo reçoit 413 sans le message « La photo dépasse 20 Mo ». Le navigateur réencode la photo avant l'envoi, ce cas ne vient donc que d'un client qui contourne l'écran d'import.
- Un nouvel appel à l'IA passe par un agent de `app/Integrations/Ai`, appelé depuis un job, et ses tests fournissent la réponse du faux ; le garde-fou global fait échouer tout test qui l'oublie.
- La photo d'un import et celle du round qui en naît sont le même fichier sur le disque `photos` ; `DeleteRound` l'efface une fois, après le commit.
