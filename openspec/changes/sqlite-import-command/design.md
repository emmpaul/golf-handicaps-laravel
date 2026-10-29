# Design

## Context

- Contrat : `docs/PORT_TO_LARAVEL.md`, sections « Commande d'import SQLite » et « Cutover », décisions 6 (UUID, correspondance en mémoire), 9 (photos), 11 et 12.
- Schéma cible sur `main` : `players`, `player_aliases`, `rounds`, `round_holes`, `round_players`, `scores`, `player_round_stats`, `course_ratings` (entrées 5 et 6). Chaque table a un `id` UUID ; `timestamps()` crée des colonnes `timestamp(0) without time zone` (précision 0 de `Schema\Builder::$defaultTimePrecision`, aucun réglage contraire dans l'application), lues en UTC (ADR 0004). `CHECK` utiles ici : `round_holes.distance BETWEEN 1 AND 999`, `par` 3–5, `stroke_index` 1–18, `round_players.position` 0–3, `scores.strokes` 1–15, index ≤ 540 ; unicités `lower(name)`, `lower(alias)`, `(round_id, stroke_index)` ; FK composite de `scores` et `player_round_stats` vers `round_players`.
- Tables de 8, 9 et 11 (fusionnés et archivés le 2026-10-29), que la commande remplit :
    - `imports` et `ai_usage` de 8 (`feat/scorecard-ai-import`, design D1) : `imports(id, status, photo_path, draft jsonb, correction jsonb, failure varchar(200), round_id unique en cascade, timestamps)`, `CHECK status IN ('queued','reading','ready','failed')` et `CHECK (status <> 'ready' OR draft IS NOT NULL)` ; `ai_usage(id, month char(7), model, input_tokens, output_tokens, cost_micro_usd bigint, created_at)`. `failure` remplace `ai_note` de Next. Les photos d'import vivent sur le disque `photos` sous `imports/<uuid>.webp`, et le round enregistré depuis un import garde le même chemin. `App\Domain\Imports\WebpPhoto::fromPath(string $path): string` rend les octets WebP d'une image et lève `UnreadablePhoto` (sous-classe de `DomainException`) sur un fichier illisible ; `ScorecardDraft::fromArray(array): ?self` valide un brouillon de la forme de Next et rend `null` sur une forme refusée, et son `toArray()` retire des tees la distance lue après le nom (« Red 6241yd » → « Red ») ; `ImportCorrection::fromArray(?array): ?RoundCard` lit une correction `{ version: 1, card }` et rend `null` sur une forme refusée, `ImportCorrection::toArray(RoundCard)` l'écrit ; `card` est un `RoundCard` (`courseName`, `tees`, `playedOn`, `holes[{par, strokeIndex, distance}]`, `lines[{playerId, strokes, readName?, totals?}]` de 1 à 4 lignes, `parTotals?`), validé par `RoundCardShape::rules('card.', withReading: true, knownPlayers: false)` (`playerId` UUID ou `null`).
    - Colonnes de recherche de `course_ratings` de 9 (design D1, décision Q1) : `rating_t` et `slope` nullables, `source`, `status` (huit valeurs), `sgt_course_id`, `sgt_course_name`, `sgt_course_location`, `candidates` et `tees_read` en `jsonb`, `proposed_tee`, `note`, avec leurs `CHECK`. Le job `App\Jobs\LookUpCourseRating` (constructeur : l'`id` UUID de la ligne) passe par `StartCourseRatingLookup`, qui ne rend rien pour une ligne absente ou qui n'est plus `pending` : le job s'arrête sans effet. Le service `migrate` lance `golf:queue-rating-lookups` après `golf:recompute-stats`.
    - Couleur de joueur de 11 (design D6, décision Q11) : `(row_number() over (order by created_at, id) - 1) % 6`, calculée, jamais stockée.
- Recalcul : `App\Domain\Handicaps\Actions\RecomputePlayerStats::handle(iterable $playerIds)` rejoue l'historique (date de jeu, `created_at`, `id`) dans la transaction de l'appelant, sans rating confirmée par le par et 113. `golf:recompute-stats` l'appelle pour tous les joueurs qui ont un round.
- Déploiement : `migrate` lance `php artisan migrate --force && php artisan golf:initial-players && php artisan golf:recompute-stats && php artisan golf:queue-rating-lookups`. `golf:initial-players` crée les joueurs de `INITIAL_PLAYERS` (`Gab,Manu,Sean` en production) avec leur alias sur une base sans joueur. L'image de production contient `pdo_sqlite` et `sqlite3` (relevé le 2026-10-28 par `php -m` sur `golf-handicaps-laravel:local`).
- Source Next (`/Users/epaul/www/z_perso/golf-handicaps`, lecture seule) : `src/db/schema.ts`, `drizzle/0000_init.sql` à `0004_course_ratings.sql` et `drizzle/meta/_journal.json`, `src/server/imports/correction.ts`, `src/scorecard/form-schema.ts` (`savedCorrectionSchema` : `{ version: 1, card: {courseName, tees, playedOn, par: {holes[18], out, in, total}, strokeIndex[18], distance: {…}}, readCardTotals, lines: [{name, playerId, scores: {holes, out, in, total}, readTotals}], aiCells }`), `src/jobs/ocr-scorecard.ts` (`ai_note` : « Budget IA du mois atteint », « Complétion IA indisponible »), `src/components/import/import-list.tsx` (échec sans coins : « Panneau introuvable sur la photo »).
- Relevé du code livré par 8, 9 et 11 (tâche 1.2, 2026-10-29) : la formule de `colorSlot` (`Player::colorSlots()`), le chemin `imports/<uuid>.webp` des photos d'import, la garde de `LookUpCourseRating`, les colonnes de `imports`, `ai_usage` et `course_ratings` sont ceux de ce design. Écarts corrigés ci-dessus : signatures de `ScorecardDraft`, `ImportCorrection` et `WebpPhoto` (retour `null` ou exception), champs `courseName`, `tees`, `playedOn` du `RoundCard` (D10), commande du service `migrate`, nom des fichiers de photo (D9). `AiBudget` n'expose pas le cumul du mois : un test le lit par `canSpend()`.

### Base réelle, relevée le 2026-10-28

Sauvegarde `backups/golf-handicaps-next-20261028-182752.db` (copie `VACUUM INTO` de la base live, en WAL, par `backups/pull-live-db.sh`) et ses photos, privées et ignorées par git. Seuls le schéma et des comptes ont été lus.

- Niveau : `__drizzle_migrations` a 5 lignes, la dernière datée de 1792624190600, le `when` de `0004_course_ratings` dans le journal Drizzle. Le schéma est exactement celui de `0000` à `0004`, `imports` reconstruite par `0003` (`corners` nullable, `corners_source`).
- Tables : `players` 3, `player_aliases` 7, `rounds` 9 (9 avec photo), `round_holes` 162, `round_players` 22, `scores` 396, `player_round_stats` 22, `course_ratings` 9 (6 `pending`, 2 `choose_course`, 1 `proposed`, aucune confirmée), `imports` 9 (tous `ready`, tous reliés à un round distinct, 2 avec correction, aucun `ai_note`, 9 `ai_cells`), `ai_usage` 19 (mois `2026-10`, modèle `claude-opus-5-5`), `jobs` 24 (6 recherches de rating en échec, 3 faites, 15 lectures faites). Plus `__drizzle_migrations` et `sqlite_sequence`.
- Photos : 9 fichiers `<uuid>.webp` dans le dossier, 9 chemins `uploads/<uuid>.webp` (49 caractères), chaque round partage le chemin de son import.
- Données hors du port : **une distance 0** (`CHECK` 1–999 de la cible) ; 26 distances vides. **Deux joueurs créés dans la même milliseconde.** Aucun round de même date de création à la seconde. Les identifiants croissent avec la date de création dans `players` et `rounds`. Pas d'autre valeur hors bornes (pars, index, positions 0–2, coups 2–9, longueurs de noms), pas d'alias en minuscules, pas de rating sans round, pas de doublon de paire.

### Écarts au contrat de `docs/PORT_TO_LARAVEL.md`

1. « `created_at` ms → `timestamptz` UTC » : les colonnes sont `timestamp(0) without time zone` en UTC. Les millisecondes sont tronquées (D6).
2. L'ordre de création ne survit pas seul à la troncature ni aux UUID aléatoires : deux joueurs réels partagent leur date. Les UUID suivent l'ordre SQLite (D5).
3. La distance 0 réelle violerait un `CHECK` : elle devient vide (D8).
4. `imports` : `ai_note` devient `failure` seulement pour un échec ; la correction de Next n'a pas la forme de 8 et se traduit ou s'abandonne (D10).
5. Photos : elles vont sous `imports/` du disque `photos`, chemin partagé par le round et l'import (D9).
6. Cutover : la base Next est en WAL, une copie de `app.db` seul peut perdre des écritures ; la base Laravel de production a déjà les joueurs initiaux, donc l'import passe par `--force` (D14). Les noms de conteneurs `golf-web` et `golf-worker` du contrat sont à vérifier : le script de sauvegarde trouve un seul conteneur `golfhandicaps-app-*`.

## Goals / Non-Goals

**Goals**

- Une base Laravel qui donne, page pour page, les mêmes joueurs, rounds, index, ratings et imports que Next au moment de la bascule, prouvée par la comparaison des statistiques.
- Tout ou rien : un import qui échoue laisse la base cible et le disque comme avant.
- Une commande que le mainteneur peut répéter sans risque (`--dry-run`) sur sa machine, puis sur le staging.

**Non-Goals**

- Réutiliser les Actions d'écriture (`CreateRound`, `AddPlayer`…) : elles rejouent des contrôles, recalculent à chaque round et mettent des recherches en file (D3).
- Fusionner avec des données Laravel existantes, importer plusieurs fois de façon incrémentale.
- Déployer : le staging et la production restent des tâches du mainteneur.

## Decisions

### D1. Commande mince, domaine `LegacyImport`

- `App\Console\Commands\ImportSqliteCommand` : `#[Signature('golf:import-sqlite {db : Chemin de la base SQLite de Next} {--uploads= : Dossier des photos de Next} {--force : Vider les tables métier après confirmation} {--dry-run : Tout faire puis tout annuler}')]`. Elle lit les options, pose la question de `--force` (`$this->confirm(…, false)` ; en `--no-interaction`, la réponse est non), appelle le domaine, affiche le rapport ou le message d'erreur, sort en 0 ou 1.
- `app/Domain/LegacyImport/` :
    - `LegacyDatabase` : déclare la connexion `legacy` à la volée et lit les tables (D2) ;
    - `LegacySchema` : liste littérale des tables et colonnes attendues au niveau `0004` (D4) ;
    - `IdMap` : correspondance `table → id SQLite → UUID` en mémoire (D5) ;
    - `LegacyPhotos` : contrôle, lecture, conversion et copie des photos (D9) ;
    - `LegacyImportCorrection` : traduction d'une correction Next (D10) ;
    - `StatsComparison` : comparaison des statistiques (D11) ;
    - `Actions/ImportLegacyDatabase::handle(string $dbPath, ?string $uploadsDir, ?array $confirmedCounts, bool $dryRun): ImportReport` (`$confirmedCounts` : les tables et comptes confirmés avec `--force`, `null` sans `--force` ; correction après revue, L4) : le déroulé de D3 ;
    - `ImportReport` : comptes et lignes du rapport (D13) ;
    - `Exceptions/LegacyImportFailed` (message pour l'opérateur, liste de détails), hors de `DomainException` : aucune requête HTTP ne la rend.
- Règle d'architecture : seul `App\Console\Commands\ImportSqliteCommand` utilise `App\Domain\LegacyImport` ; `App\Domain\LegacyImport` n'utilise pas `App\Http`.

**Alternative écartée** : `app/Integrations/NextSqlite`. Une base que l'on quitte n'est pas un service tiers appelé par l'application ; et le code écrit dans les tables du domaine.

### D2. Connexion `legacy` en lecture seule

- `config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => $path, 'foreign_key_constraints' => false, 'options' => [Pdo\Sqlite::ATTR_OPEN_FLAGS => Pdo\Sqlite::OPEN_READONLY]]])`, puis `DB::purge('legacy')`. Le fichier doit exister et être lisible, sinon « Base SQLite introuvable : <chemin> ». Le drapeau de lecture seule fait échouer toute écriture accidentelle ; le test de la spec compare l'empreinte SHA-256 du fichier.
- Relevé à la tâche 2.1 : le driver SQLite de Laravel 13 transmet `options` à PDO (`Connector::getOptions()` fusionne `$config['options']` aux options par défaut, `SQLiteConnector::connect()` les passe à `PDO::connect()`), et `Pdo\Sqlite::OPEN_READONLY` existe sous PHP 8.5 : la connexion garde la forme ci-dessus, sans URI `file:…?mode=ro`. Le driver lève `SQLiteDatabaseDoesNotExistException` sur un chemin absent : la commande contrôle le fichier avant de déclarer la connexion.
- Base en WAL refusée (correction après revue, M1) : une base en WAL ouverte même en `OPEN_READONLY` reçoit des fichiers `-wal` et `-shm` (vu rouge : les deux apparaissent sans le contrôle) et, sans son WAL, peut être lue dans un état périmé. Avant de déclarer la connexion, `LegacyDatabase::open()` lit par `fopen` les octets 18 et 19 de l'en-tête (versions d'écriture et de lecture du format : 1 en journal classique, 2 en WAL) ; un 2 donne « Base en WAL : copier par VACUUM INTO (docs/deployment.md, Bascule depuis Next). ». Une copie `VACUUM INTO` d'une base en WAL a un en-tête à 1 (vérifié avec SQLite 3.54). Le test qui comparait l'empreinte après un import réussi puis refusé ne rougissait sous aucune mutation (l'import n'écrit jamais sur la source, drapeau de lecture seule ou non) : il est remplacé par le test de la base en WAL, qui compare aussi l'empreinte ; le scénario « Fichier inchangé » est prouvé par ce test et par celui de l'écriture refusée sur la connexion `legacy`.
- Lectures par `DB::connection('legacy')->table(…)->orderBy('id')` (ou par la clé primaire composite), en une fois : la base réelle tient en moins de 700 lignes ; une base dix fois plus grosse tient encore en mémoire.

### D3. Déroulé et transaction

1. **Contrôles sans écriture** : base lisible ; schéma `0004` (D4) ; tables cibles vides, ou `--force` confirmé (D12) ; photos : `--uploads` présent si un chemin existe, chaque chemin de la forme `uploads/<nom>`, chaque fichier présent et lisible (D9). Chaque contrôle liste toutes ses fautes avant de sortir.
2. **Transaction** (`DB::transaction`), dans cet ordre :
    1. avec `--force`, `TRUNCATE players, player_aliases, rounds, round_holes, round_players, scores, player_round_stats, course_ratings, imports, ai_usage` (transactionnel en PostgreSQL), après avoir relevé les `photo_path` des rounds et des imports vidés ;
    2. `players`, `player_aliases`, `rounds`, `round_holes`, `round_players`, `scores`, `course_ratings`, `imports`, `ai_usage`, par `DB::table(…)->insert()` ligne par ligne, chaque insertion entourée d'un `try` qui transforme une `QueryException` en `LegacyImportFailed` nommant la table, la clé SQLite et le message de PostgreSQL (« Ligne refusée : round_holes, round 2, trou 7 : … ») ;
    3. photos : copie ou conversion vers le disque `photos` (D9), chaque fichier écrit noté ; en essai à blanc, lecture et décodage seulement ;
    4. `RecomputePlayerStats::handle()` sur tous les joueurs importés qui ont un round, puis `StatsComparison` (D11) ;
    5. en essai à blanc, `LegacyImportFailed` interne « dry-run » qui fait annuler la transaction, attrapée aussitôt pour rendre le rapport.
3. **Échec** : la transaction est annulée ; un `finally` efface chaque photo écrite par cet import.
4. **Après le commit** (import réel seulement) : effacement des photos relevées en 2.1 qui ne sont pas référencées par la base importée ; `LookUpCourseRating::dispatch($id)` pour chaque ligne `course_ratings` importée au statut `pending` (D12).

- Échecs hors insertion (correction après revue, L5) : dans la transaction, une `QueryException` du recalcul (`RecomputePlayerStats`) ou du comptage des lignes devient « Échec du recalcul des statistiques : <première ligne du message de PostgreSQL> » ou « Échec du comptage des lignes : … », sans passer par le journal. Le test du recalcul pose un trigger PostgreSQL qui refuse les lignes de `player_round_stats`. Le comptage n'a plus de cause d'échec reproductible depuis que `jobs` est contrôlée (D4) : sa conversion a été vue rouge par le test de la base sans `jobs` avant ce contrôle (« Échec du comptage des lignes : no such table »). La copie des photos transforme toute `Exception` autre que `LegacyImportFailed` (Imagick hors `UnreadablePhoto`, disque qui lève) en « Échec de la copie des photos : uploads/<nom> → imports/<nom>.webp : <message> », et un `put()` qui rend `false` (disque par défaut, `throw` faux) en la même ligne sans message : avant, une photo non écrite passait en silence. Les `Error` PHP (bugs) ne sont pas attrapées. Une vraie exception d'Imagick hors `UnreadablePhoto` ne se provoque pas dans un test (Intervention enveloppe les erreurs de décodage, de redimensionnement et d'encodage) : le test passe par un disque qui lève, dans le même `catch`.
- Après le commit, l'import est gardé : un `dispatch` qui lève (file injoignable) ou un effacement de photo qui échoue (`delete()` à `false` ou exception) ne sort plus de la commande. Le rapport finit alors par une ligne par échec au lieu de « Import terminé. » : « Import terminé ; échec de la mise en file de 1 recherche de rating : la relancer depuis la page Parcours (« Relancer la recherche »). », « Import terminé ; échec de l'effacement de 1 photo des lignes vidées : rounds/ancienne.webp ». Sortie 0 : les données sont importées, relancer l'import ne servirait à rien. Rien n'est écrit au journal (D13). Écart à la consigne de revue, qui renvoyait à `golf:queue-rating-lookups` : cette commande ne met en file que les paires sans ligne `course_ratings` (`QueueCourseRatingLookup`, `on conflict do nothing`), elle ne relancerait aucune des lignes `pending` importées ; le bouton « Relancer la recherche » de la page Parcours (`RelaunchCourseRatingLookup`) relance une ligne `pending`.

- Insertions directes, pas les Actions : `CreateRound` rejouerait `RoundCardChecks` (refus possibles d'un round que Next a accepté), recalculerait l'historique à chaque round (coût quadratique) et mettrait une recherche SGT en file par paire ; `AssignAlias` déplacerait un alias. La base reçoit les données telles que Next les a validées ; les `CHECK` et contraintes de PostgreSQL restent la garde, et la comparaison des statistiques prouve le résultat.
- Une transaction pour tout : la base réelle s'importe en quelques secondes ; aucun verrou long ne gêne, la stack n'a pas encore d'utilisateur au moment du cutover.

### D4. Schéma attendu

- `LegacySchema::TABLES` : liste littérale des colonnes de chaque table reprise au niveau `0004`, lue de `drizzle/0000` à `0004` : `players(id, name, created_at)`, `player_aliases(id, player_id, alias)`, `rounds(id, course_name, tees, played_on, created_at, photo_path)`, `round_holes(round_id, hole, par, stroke_index, distance)`, `round_players(round_id, player_id, position)`, `scores(round_id, player_id, hole, strokes)`, `player_round_stats(round_id, player_id, gross, adjusted, differential_t, index_before_t, index_after_t, counts_in_current)`, `imports(id, status, photo_path, corners, draft, round_id, created_at, ai_cells, ai_note, correction, corners_source)`, `ai_usage(id, created_at, month, model, input_tokens, output_tokens, cost_micro_usd)`, `course_ratings(id, course_name, tees, rating_t, slope, source, status, sgt_course_id, sgt_course_name, sgt_course_location, candidates, tees_read, proposed_tee, note, updated_at)`.
- Lecture par `PRAGMA table_info`. Une colonne de plus est tolérée (une base plus récente que `0004` serait signalée par la colonne manquante d'une autre table, pas par l'ajout) ; une table ou une colonne manquante donne « Base SQLite au mauvais niveau : migration Drizzle 0004 attendue. » suivi d'une ligne par manque.
- `jobs` est lue pour le compte du rapport : sa présence est contrôlée (`'jobs' => ['id']`), sinon le compte, après toutes les insertions, échouait sur « no such table » (correction après revue, L5).
- `__drizzle_migrations` n'est pas lu : son `hash` dépend du texte exact des fichiers ; le schéma suffit et se teste.

### D5. Identifiants dans l'ordre de Next

- Pour chaque table à `id` (`players`, `player_aliases`, `rounds`, `course_ratings`, `imports`, `ai_usage`) : générer autant de `Str::uuid7()` que de lignes, les trier (`sort`, ordre des octets, celui de PostgreSQL sur `uuid`), et les attribuer dans l'ordre croissant des `id` SQLite. Les tables à clé composite (`round_holes`, `round_players`, `scores`) reçoivent un UUID v7 par ligne, sans ordre à garder.
- Effet : `order by created_at, id` rend l'ordre de Next quand deux lignes ont la même seconde (cas réel de deux joueurs, couleur de 11 ; ordre d'historique des rounds d'un même jour dans `RecomputePlayerStats`, qui départage aussi par `id`). Comme les `id` SQLite croissent avec la date de création (relevé sur la base réelle), l'ordre par `(created_at, id)` est celui de Next.
- `IdMap` garde la correspondance en mémoire le temps de la commande, jamais en base (décision 6 du port). Une clé étrangère SQLite qui ne trouve pas sa ligne (base incohérente) fait échouer l'import : « Référence absente : scores, round 4, joueur 9 ».

**Alternative écartée** : décaler d'une seconde la date des lignes à égalité. Cela modifie une donnée pour en corriger une autre.

### D6. Dates

- `created_at` SQLite (entier, millisecondes) → `CarbonImmutable::createFromTimestampMs($ms, 'UTC')->format('Y-m-d H:i:s')`, millisecondes tronquées (PostgreSQL arrondirait à la seconde la plus proche : la troncature garde l'ordre et reste explicite).
- `updated_at` : `players`, `rounds`, `imports` = leur `created_at` ; `course_ratings` : `created_at` et `updated_at` = son `updated_at` SQLite ; `player_aliases` : l'instant de l'import (un seul `now()` pour toute la commande) ; `ai_usage` n'a que `created_at`.
- `played_on` (`AAAA-MM-JJ`) repris tel quel ; une valeur qui n'est pas une date valide fait échouer l'import.
- `ai_usage.month` repris tel quel : Next le calcule à l'heure de Montréal, comme le budget de 8 (`AI_BUDGET_TIME_ZONE`, `America/Montreal` par défaut).

### D7. Correspondance des colonnes

| SQLite | PostgreSQL | Traitement |
| --- | --- | --- |
| `players` | `players` | `name` tel quel. |
| `player_aliases` | `player_aliases` | `player_id` remappé, `alias` tel quel (déjà en majuscules dans Next). |
| `rounds` | `rounds` | `course_name`, `tees`, `played_on` tels quels ; `photo_path` réécrit (D9). |
| `round_holes` | `round_holes` | `round_id` remappé ; distance hors de 1–999 → `null` (D8). |
| `round_players` | `round_players` | FK remappées, `position` telle quelle. |
| `scores` | `scores` | FK remappées. |
| `player_round_stats` | `player_round_stats` | Non copiée : recalculée puis comparée (D11). |
| `course_ratings` | `course_ratings` | Toutes les colonnes ; `candidates` et `tees_read` décodés (`json_decode`, erreur → échec « Valeur illisible : course_ratings.candidates ») puis écrits en `jsonb`. |
| `imports` | `imports` | D10. |
| `ai_usage` | `ai_usage` | Toutes les colonnes. |
| `jobs` | — | Ignorée, comptée dans le rapport. |

`counts_in_current` SQLite (entier 0/1) se lit en booléen pour la comparaison.

### D8. Distances hors bornes

- `round_holes.distance` hors de 1 à 999 (0 réel, peut-être un trou que le simulateur n'affiche pas) → `null`, compté. La distance est facultative, n'entre dans aucun calcul et la spec `rounds` la refuserait à la correction suivante.
- Aucune autre normalisation : tout autre refus de PostgreSQL fait échouer l'import (D3). Le mainteneur corrige alors la donnée dans Next avant le cutover.

### D9. Photos

- Chemins référencés : l'union des `photo_path` de `rounds` et `imports`. Chaque chemin doit valoir `uploads/<nom>` où `<nom>` passe `^[A-Za-z0-9._-]+$` et n'est ni `.` ni `..` ; sinon « Chemin de photo refusé : <chemin> ». Le fichier lu est `<--uploads>/<nom>`, jamais un chemin construit autrement.
- Destination : disque `photos`, `imports/<nom sans extension>.webp`, le dossier que 8 donne à un import et à son round. 8 nomme le fichier d'après l'UUID du nouvel import (`CreateImport`) ; ici le nom vient de Next (un UUID dans la base réelle), ce que rien dans 8 ne suppose : la photo n'est lue et effacée que par `photo_path`. Un même chemin source donne une seule copie ; tous les rounds et imports qui le référencent reçoivent le nouveau chemin.
- Format : signature `RIFF????WEBP` dans les 12 premiers octets → copie octet pour octet ; sinon `WebpPhoto::fromPath()` de 8 (orientation EXIF, réduction à 3 000 px, qualité 90, sans métadonnées). Une image illisible (`UnreadablePhoto` de `WebpPhoto`) fait échouer l'import (« Photo illisible : <chemin> »).
- Deux chemins source différents qui donnent le même fichier de destination (`uploads/a.webp` et `uploads/a.jpg` → `imports/a.webp`) font échouer l'import avant toute écriture : « Photos de même destination : » suivi d'une ligne par destination, « uploads/a.jpg et uploads/a.webp → imports/a.webp » (décision de l'orchestrateur, 2026-10-29 : refus strict plutôt qu'un renommage, aucun cas réel). Sans ce refus, la seconde copie écraserait la première et un round pointerait vers la photo d'un autre.
- Un fichier du dossier `--uploads` qu'aucune ligne ne référence est ignoré et compté.
- Essai à blanc : chaque fichier est lu et, s'il n'est pas en WebP, converti en mémoire ; rien n'est écrit.

### D10. Imports

- Statut : `ready` → `ready` ; `failed` → `failed`, `failure` = `ai_note` ou, sans note, « Panneau introuvable sur la photo » (le seul échec de Next, panneau non trouvé) ; `queued` et `reading` → `failed`, « Import interrompu par la migration ». La file Next est vidée avant le cutover : ces deux cas ne viennent que d'une copie faite pendant une lecture.
- `ai_note` d'un import `ready` (« Budget IA du mois atteint », « Complétion IA indisponible » : complétion IA sautée sur une lecture locale) est abandonnée : 8 n'affiche `failure` que pour un échec.
- `draft` : décodé puis passé par `ScorecardDraft::fromArray()` ; le résultat de `toArray()` est écrit (les tees y perdent une distance lue après leur nom, comme à une lecture de 8). Un brouillon refusé (`fromArray()` rend `null`) fait échouer l'import (« Brouillon illisible : import 3 ») ; un `ready` sans brouillon aussi (le `CHECK` de 8 le refuserait).
- `correction` : import relié à un round → `null`, compté parmi les abandons (l'écran de vérification redirige vers le round : elle ne sert plus). Import sans round → `LegacyImportCorrection::translate(array $next, IdMap): ?array` : `card.courseName`, `card.tees`, `card.playedOn` → `courseName`, `tees`, `playedOn` (le `RoundCard` de 8 les porte) ; `card.par.holes[i]`, `card.strokeIndex[i]`, `card.distance.holes[i]` → `holes[i]` ; chaque `lines[k]` (au premier niveau de la correction Next, à côté de `card`) → `{ playerId: UUID du joueur ou null, strokes: scores.holes, readName: name si non nul, totals: scores.{out,in,total} si readTotals }` ; `parTotals` = `card.par.{out,in,total}` si `readCardTotals` ; puis `{ version: 1, card }` vérifié par `ImportCorrection::fromArray()` (qui rend un `RoundCard` ou `null`) et écrit par `ImportCorrection::toArray()`. Forme non reconnue, ou correction sans ligne (`RoundCardShape` exige 1 à 4 lignes) → `null`, comptée. `aiCells` est abandonné (décision 3 du port).
- `round_id` remappé ; `photo_path` réécrit (D9) ; `corners`, `corners_source`, `ai_cells` ignorés.

### D11. Comparaison des statistiques

- Après `RecomputePlayerStats`, lire les lignes recalculées et celles de SQLite, indexées par `(UUID du round, UUID du joueur)` via `IdMap`. Comparer `gross`, `adjusted`, `differential_t`, `index_before_t`, `index_after_t`, `counts_in_current` par égalité stricte d'entiers (ou de `null`).
- Différence → une ligne « <parcours> / <tees>, <date>, <joueur> : <colonne> SQLite <v>, recalculé <v> » ; ligne d'un seul côté → « <…> : absente de SQLite » ou « absente du recalcul ». Toutes les différences sont listées (la base réelle a 22 lignes), puis « Import annulé : rien n'a été écrit. ». Ordre : les lignes SQLite par `(round_id, player_id)`, une ligne par colonne qui diffère, puis les lignes recalculées absentes de SQLite. Un index vide s'écrit « aucun », « compte dans l'index » « oui » ou « non » (les mots du scénario « Statistiques identiques »).
- Une valeur d'un autre type qu'un entier, un booléen ou `null`, d'un côté ou de l'autre, fait échouer l'import (« Valeur inattendue : player_round_stats.gross recalculé, de type string ») au lieu d'être lue comme `null` : une erreur de lecture ne doit pas devenir un écart, ni une égalité `null` = `null` (correction après revue, L6).
- Le rapport compte « Statistiques identiques : N / N ».

### D12. Refus, `--force`, remise en file

- Tables contrôlées : les dix tables de la spec. Les tables du framework (`jobs`, `failed_jobs`, `sessions`, `cache`, `migrations`) ne comptent pas.
- `--force` : la commande compte les lignes, demande « Vider players (3), player_aliases (3) avant l'import ? » (seules les tables non vides), puis passe `force: true` au domaine, qui vide dans la transaction. Une base cible vide ne pose pas de question, avec ou sans `--force`. `--dry-run --force` pose aussi la question : la règle reste simple et rien n'est gardé.
- Comptes confirmés (correction après revue, L4) : la commande passe au domaine les comptes qu'elle a montrés dans la question (`[]` si la base cible était vide), au lieu d'un booléen. Le domaine relit `TargetTables::nonEmpty()` à ses contrôles et refuse s'ils diffèrent : « Tables modifiées depuis la confirmation : players (4), player_aliases (3) au lieu de players (3), player_aliases (3). Relancer l'import. » (« au lieu de tables vides » quand rien n'était à vider). Ainsi `--force` ne vide jamais une ligne que l'opérateur n'a pas vue comptée.
- Jobs déjà en file dans Laravel (lecture d'un import, recherche de rating) qui visent une ligne vidée : leur garde (« ligne absente → ne rien faire ») les termine sans effet.
- Remise en file : `LookUpCourseRating::dispatch($uuid)` pour chaque ligne `pending`, après le commit, hors essai à blanc. Avec `SGT_LOOKUP=off`, le job finit en « Saisie manuelle », comme toute recherche.

### D13. Rapport

```
Table              SQLite PostgreSQL
players                 3          3
player_aliases          7          7
rounds                  9          9
round_holes           162        162
round_players          22         22
scores                396        396
player_round_stats     22         22
course_ratings          9          9
imports                 9          9
ai_usage               19         19
Photos copiées : 9
Fichiers non référencés ignorés : 0
Statistiques identiques : 22 / 22
Distances hors de 1 à 999 vidées : 1
Corrections abandonnées : 2
Recherches de rating remises en file : 6
Jobs Next ignorés : 24
Import terminé.
```

- Tableau par `$this->table()`, style `compact`, comptes alignés à droite (le style de colonne est une copie du style `compact` avec `STR_PAD_LEFT` : un `TableStyle` neuf garderait l'en-tête en décalage). En essai à blanc : « Photos lues : N », « Recherches de rating à remettre en file : N », puis « Essai à blanc : import annulé, rien n'a été écrit. ».
- Valeurs ci-dessus : celles attendues sur la sauvegarde réelle du 2026-10-28 (comptes relevés), la cible de la tâche 7.2.
- Aucun appel à `Log::` dans le domaine ; une `QueryException` attrapée est transformée sans être rapportée (le gestionnaire d'exceptions de la console ne la voit pas). Le test de la spec vérifie le fichier de journal.

### D14. Cutover documenté, exécuté par le mainteneur

Section « Bascule depuis Next » de `docs/deployment.md`, une étape à la fois, chacune avec ce qu'il faut lire dans la sortie :

1. Répétition sur le staging : `pull-live-db.sh` (copie `VACUUM INTO`, intégrité, SHA-256, photos vérifiées), `docker cp` de la base et des photos dans le conteneur `web` du staging, `golf:import-sqlite … --dry-run`, puis import réel avec `--force` dans `docker exec -it`, comparaison à l'œil avec Next (accueil, handicaps, trois rounds, Parcours, Importer).
2. Jour J : arrêter le web de Next ; attendre que sa file soit vide ; arrêter son worker (noms réels des conteneurs à relever dans Dokploy).
3. Copier la base par `VACUUM INTO` et les photos, comme le script, depuis le conteneur arrêté ou un conteneur qui monte le volume `golf-data`.
4. `docker cp` dans le `web` Laravel de production, `--dry-run`, puis `--force` en interactif : la confirmation nomme `players (3), player_aliases (3)` des joueurs initiaux. Lire le rapport : comptes égaux, « Statistiques identiques : N / N ».
5. Basculer le domaine ; vérifier `/login`, l'accueil, les handicaps, un round avec sa photo.
6. Effacer les copies de la base et des photos dans le conteneur (`docker exec … rm`).
7. Retour arrière dans les 30 jours : rebasculer le domaine, redémarrer les applications Next ; leur volume est intact.

`docs/PORT_TO_LARAVEL.md` est corrigé sur les écarts 1, 5 et 6.

### D15. Fixture des tests

- `tests/fixtures/legacy-sqlite/drizzle/0000_init.sql` à `0004_course_ratings.sql` : copies exactes des migrations de Next (schéma seul, aucune donnée), avec un `README.md` qui dit leur origine et interdit d'y mettre une base réelle.
- `tests/Support/LegacySqlite/LegacySqliteFixture` : crée un fichier temporaire, applique les fichiers jusqu'à un niveau donné (découpés sur `--> statement-breakpoint`, comme Drizzle ; `0000` à `0003` pour le test du mauvais niveau), et offre des méthodes d'insertion (`player`, `alias`, `round` avec trous, lignes et scores, `stats`, `courseRating`, `import`, `aiUsage`, `job`) en valeurs littérales. Un dossier temporaire tient les photos : `database/data/img3064.webp` de l'entrée 5 et un petit JPEG généré par GD.
- Jeu de base : la carte `IMG_3064` (BETHPAGE BLACK / Red, 2026-10-17, Gab 72, Manu 88) avec ses statistiques sans rating écrites à la main (Gab 72 / 72 / 10 / null / 10 / vrai, Manu 88 / 88 / 170 / null / 170 / vrai), un round antérieur où Gab joue +17 le 2026-10-10 sur un par 71 sans trou au-delà de par + 4 (Gab y vaut 88 / 88 / 170 / null / 170 / vrai, puis sur la carte de référence 72 / 72 / 10 / 170 / 90 / vrai), deux joueurs à la même milliseconde. Ces valeurs se recalculent à la main par la table WHS (index provisoire de deux rounds : moyenne, (170 + 10) / 2 = 90) et se vérifient contre le calcul de Next avant d'être écrites.
- Aucun fichier `app.db` versionné : la fixture se reconstruit à chaque test, et une base réelle ne peut pas s'y glisser par erreur.

### D16. ADR

« Import unique de la base SQLite de Next » : insertions directes hors des Actions et pourquoi, transaction unique avec les photos, UUID attribués dans l'ordre SQLite, statistiques recalculées et comparées plutôt que copiées, connexion `legacy` à la volée en lecture seule, commande gardée jusqu'à l'archivage de Next. Numéro libre suivant à l'apply ; ligne dans `docs/adr/README.md`.

## Décisions par défaut (orchestrateur, nuit du 2026-10-28)

Le mainteneur n'était pas joignable. Chaque question ouverte a reçu l'option recommandée ; chacune se conteste avant l'apply, et un changement de l'une d'elles passe par `/opsx:update`.

| # | Question | Décision prise | Alternative |
| --- | --- | --- | --- |
| Q1 | Où vit le code ? | `app/Domain/LegacyImport`, réservé à la commande par une règle d'architecture (D1). | `app/Integrations/NextSqlite`. |
| Q2 | Écrire par les Actions ou directement ? | Insertions directes ; contraintes de la base et comparaison des statistiques comme garde (D3). | `CreateRound` et consorts : contrôles rejoués, recalcul par round, recherches SGT mises en file. |
| Q3 | Comment garder l'ordre de création (couleur de 11, historique) ? | UUID v7 triés, attribués dans l'ordre des `id` SQLite (D5). | Décaler d'une seconde les dates à égalité. |
| Q4 | Précision des dates | Seconde, millisecondes tronquées, colonnes `timestamp(0)` existantes (D6). | Migration des colonnes en `timestamp(3)` : touche toutes les tables pour une valeur que rien n'affiche. |
| Q5 | Dates que Next n'a pas | `updated_at` = `created_at` ; alias à l'instant de l'import ; ratings sur leur `updated_at` (D6). | `null`. |
| Q6 | Distance hors de 1 à 999 (une distance 0 réelle) | Vidée et comptée (D8). | Échec de l'import, correction à la main dans Next. |
| Q7 | Contrôle du niveau de la base | Tables et colonnes attendues au niveau `0004`, colonnes en plus tolérées (D4). | Lecture de `__drizzle_migrations` et comparaison des hashes. |
| Q8 | `--force` sans terminal, et avec `--dry-run` | Refus sans terminal ; la question est posée aussi en essai à blanc (D12). | `--force` sans question en `--no-interaction` ; pas de question en essai à blanc. |
| Q9 | Où vont les photos ? | `imports/<nom>.webp` du disque `photos`, une copie partagée par le round et l'import (D9). | Un dossier `rounds/` et une copie par ligne. |
| Q10 | Photos des lignes vidées par `--force` | Effacées après le commit si plus rien ne les référence (D3). | Laissées sur le disque. |
| Q11 | `ai_note` | Raison de l'échec pour un import `failed` ; abandonnée sur un `ready` (D10). | Copiée partout dans `failure`. |
| Q12 | Correction de Next | Traduite pour un import sans round ; abandonnée pour un import enregistré (D10). | Copiée telle quelle (ignorée par 8, donnée morte), ou toujours abandonnée. |
| Q13 | Brouillon refusé par `ScorecardDraft` | Échec de l'import (D10). | Import passé en « Échec » sans brouillon. |
| Q14 | Combien de différences de statistiques lister ? | Toutes (D11). | Les 20 premières. |
| Q15 | Fixture versionnée ou générée ? | Migrations Drizzle versionnées, base générée à chaque test, aucun `app.db` dans le dépôt (D15). | Un `app.db` binaire versionné. |
| Q16 | Garder la commande après le cutover ? | Oui, jusqu'à l'archivage du dépôt Next. | La retirer dans l'archive de ce changement. |
| Q17 | Base de production qui a déjà les joueurs initiaux | Cutover avec `--force` en interactif (D14). | Vider `INITIAL_PLAYERS` avant le premier déploiement de production. |
| Q18 | ADR | Oui (D16) : écritures hors des Actions, contraire à la règle d'`ARCHITECTURE.md`. | Section d'`ARCHITECTURE.md` seule. |

## Risks / Trade-offs

- [8, 9 ou 11 archivés avec une autre forme que leur design (colonnes, `WebpPhoto`, `ScorecardDraft`, `ImportCorrection`, `LookUpCourseRating`, formule de couleur)] → tâche 1.2 : relever le code livré et corriger ce design par `/opsx:update` avant le premier test.
- [Le recalcul du port diffère de Next sur un cas absent des tests] → c'est le rôle de la comparaison : l'import échoue et liste l'écart ; l'essai à blanc sur la sauvegarde réelle (tâche 7.2) le montre avant le staging. Correction dans le calcul (bug du port) ou dans Next, jamais un écart toléré.
- [La base live change entre la répétition et le cutover] → le cutover refait l'essai à blanc sur la copie du jour, puis l'import ; une nouvelle valeur hors bornes échoue avant toute écriture visible.
- [Copie de `app.db` sans son WAL] → `VACUUM INTO`, comme `backups/pull-live-db.sh` (D14).
- [Photo convertie différente de l'originale] → seules les photos qui ne sont pas en WebP le sont ; la base réelle n'en a aucune.
- [`pdo_sqlite` retiré d'une future image FrankenPHP] → tâche 7.1 le vérifie sur l'image construite ; le mainteneur peut aussi lancer la commande depuis une copie locale et restaurer un dump (procédure de restauration de `docs/deployment.md`).
- [Données privées] → fixture inventée ; la sauvegarde réelle ne sort pas de `backups/` et de la base Sail ; aucune sortie de la commande ne part dans le journal.
- [`--force` vide une base de production par erreur] → question qui nomme les tables et leurs comptes, refus sans terminal, et `backup` a un dump de moins de 26 h.

## Migration Plan

1. Pull request de code, après l'archivage de 8, 9 et 11 : aucune migration, aucune variable nouvelle. Le déploiement ne change rien tant que la commande n'est pas lancée.
2. Répétition sur le staging, puis cutover de production : tâches du mainteneur (D14, « Workflow follow-up » de `tasks.md`).
3. Retour arrière : avant le cutover, rien ; après, rebasculer le domaine sur Next (volume gardé 30 jours).

## Open Questions

Aucune.
