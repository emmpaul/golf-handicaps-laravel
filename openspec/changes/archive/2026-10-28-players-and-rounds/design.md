# Design

## Context

- Aucun modèle applicatif : `app/Domain` ne contient que `LocalTime`, `database/migrations` ne crée que les tables du framework, `DatabaseSeeder` est vide. Le test d'architecture impose `HasUuids` à tout modèle de `App\Models` (ADR 0002).
- `routes/web.php` sert `rounds.index` et `players.index` par la page `section-placeholder`, avec `{path?}` pour les sous-adresses. `RequirePasscode` est dans le groupe `web` : toute route ajoutée exige la session (ADR 0007), et `tests/Feature/ProtectedRoutesTest.php` compare les routes ouvertes à une liste littérale.
- Le disque `local` de `config/filesystems.php` a `serve => true` : Laravel déclare `storage/{path}` (routes ouvertes de la liste littérale, protégées par URL signée). Le volume `storage` de la stack est monté sur `storage/app` pour `web` et `worker`, pas pour `migrate`.
- Le flash Inertia `toast` (`{ type, message }`) et son affichage existent ; aucun code serveur ne l'émet. ARCHITECTURE.md prévoit `App\Domain\DomainException` et son rendu commun avec la première Action qui lève une erreur métier.
- Primitives déjà portées : `calendar`, `popover`, `field`, `input`, `native-select`, `alert-dialog`, `alert`, `table`, `pending`, `ScoreMark`, `ScrollFrame`. Pas encore de `lib/format.ts`.
- Référence Next (`/Users/epaul/www/z_perso/golf-handicaps`) :
    - schéma : `src/db/schema.ts` ;
    - contrôles : `src/scorecard/checks.ts`, `src/scorecard/form-schema.ts` ;
    - écritures : `src/server/rounds/{save,edit,courses,http}.ts`, `src/server/players/{players,aliases,seed}.ts`, `src/app/(app)/players/actions.ts` ;
    - pages : `src/app/(app)/rounds/**`, `src/app/(app)/players/page.tsx` ;
    - composants : `src/components/round-editor/*` (sans `autosave.ts`), `src/components/rounds/*`, `src/components/players/*` ;
    - formatage : `src/lib/format.ts` ;
    - données : `src/server/rounds/seed.ts` (round de référence), `tests/fixtures/scorecards/gspro-img3064.jpg`.

## Goals / Non-Goals

**Goals**

- Les requirements des six deltas, sans paquet nouveau.
- Un modèle de données que `handicap-index` (6), `scorecard-ai-import` (8) et `sqlite-import-command` (10) étendent sans le changer : `player_round_stats` s'y rattache par `round_id` et `player_id`, `imports` par `round_id`, l'importeur remappe chaque table de Next une à une.
- Les mêmes contrôles de cohérence dans l'éditeur (à chaque frappe) et au serveur (avant toute écriture).

**Non-Goals**

- Recalcul d'index, mise en file de la recherche SGT, lien avec un import : les Actions d'écriture laissent la place d'un appel, sans le préparer d'avance.
- Contrôles de sommes OUT / IN / TOT : ils arrivent avec les totaux lus, en 8.

## Decisions

### D1. Six tables en UUID, invariants en contraintes PostgreSQL

| Table | Colonnes | Contraintes |
| --- | --- | --- |
| `players` | `id`, `name`, timestamps | index unique sur `lower(name)` |
| `player_aliases` | `id`, `player_id`, `alias`, timestamps | FK `players` en cascade ; index unique sur `lower(alias)` ; index `player_id` |
| `rounds` | `id`, `course_name`, `tees`, `played_on` (`date`), `photo_path` (nullable), timestamps | index `(played_on, created_at)` |
| `round_holes` | `id`, `round_id`, `hole`, `par`, `stroke_index`, `distance` (nullable) | FK en cascade ; uniques `(round_id, hole)` et `(round_id, stroke_index)` ; `CHECK` `hole` 1–18, `par` 3–5, `stroke_index` 1–18, `distance` 1–999 |
| `round_players` | `id`, `round_id`, `player_id`, `position` | FK `rounds` en cascade, FK `players` en `restrict` ; uniques `(round_id, player_id)` et `(round_id, position)` ; `CHECK` `position` 0–3 ; index `player_id` |
| `scores` | `id`, `round_id`, `player_id`, `hole`, `strokes` | FK `rounds` en cascade ; FK composite `(round_id, player_id)` vers `round_players` en cascade ; unique `(round_id, player_id, hole)` ; `CHECK` `hole` 1–18, `strokes` 1–15 |

- Chaque table a sa clé `id` UUID v7 et son modèle (`Player`, `PlayerAlias`, `Round`, `RoundHole`, `RoundPlayer`, `Score`) : la règle « chaque table applicative en UUID » reste sans exception, et l'arch test la garde déjà.
- `scores` garde `round_id` et `player_id`, comme Next : les requêtes du calcul d'index (6) et l'importeur (10) lisent les mêmes colonnes. La FK composite vers `round_players` empêche un score d'un joueur qui n'est pas du round ; elle demande une contrainte unique `(round_id, player_id)` sur `round_players`, qui existe déjà.
- Les index uniques sur `lower(...)` s'écrivent en `DB::statement` dans la migration ; Blueprint ne les exprime pas.
- Les `CHECK` sont une seconde ligne : les contrôles de D2 refusent avant, avec un message. Une `QueryException` sur un `CHECK` est un bug, pas une erreur métier.

**Alternative écartée** : 18 scores dans une colonne `smallint[]` de `round_players`. Moins de lignes, mais l'importeur, les requêtes WHS et les contraintes par trou deviennent plus durs ; le volume (quelques centaines de rounds) ne justifie rien.

### D2. La carte d'un round est une valeur du domaine, contrôlée deux fois

- `App\Domain\Rounds\RoundCard` : objet immuable (parcours, tees, date `Y-m-d`, 18 trous `{par, strokeIndex, distance}`, lignes `{playerId, strokes[18]}`), chaque case pouvant être `null`. Construit depuis les données validées du Form Request.
- `App\Domain\Rounds\RoundCardChecks::problems(RoundCard): list<Problem>` : `Problem { message, cells }`, avec les clés de case de Next (`courseName`, `par:3`, `strokeIndex:7`, `player0:2`, `player1:player`…) et les messages littéraux de la spec (« Case vide », « Par 6 hors de 3 à 5 », « Index 1 en double », « Ligne sans joueur », « Joueur en double », « Aucune ligne de joueur », « 4 joueurs au plus »). `problemCountLabel()` donne « 1 problème à corriger ».
- `resources/js/lib/round-checks.ts` : le port de `src/scorecard/checks.ts`, sans `sumProblems` ni `checkDraft`, pour l'éditeur.
- **Une table de cas partagée** : `tests/fixtures/round-checks.json` liste des cartes et, pour chacune, les problèmes attendus écrits à la main. Un test Pest et un test Vitest la parcourent tous deux. Un écart entre PHP et TypeScript fait rougir l'un des deux. Chaque test a aussi ses cas propres en littéraux.

**Alternative écartée** : contrôles seulement au serveur, par Precognition à chaque frappe. Un aller-retour par case sur le téléphone au simulateur, et l'éditeur ne marche plus le temps d'une coupure. Le double code est petit (moins de 200 lignes) et la table partagée le tient aligné.

### D3. Écritures : Form Request pour la forme, Action pour les invariants

- `StoreRoundRequest` / `UpdateRoundRequest` (une classe de base commune) valident la forme : chaînes de 100 caractères au plus pour parcours et tees, date `Y-m-d`, exactement 18 trous, entiers ou `null`, 1 à 4 lignes, `playerId` UUID existant ou `null`, `force` booléen. Une forme invalide est une 422 de validation ordinaire (requête hors éditeur).
- `CreateRound::handle(RoundCard): Round` et `UpdateRound::handle(Round, RoundCard)` ouvrent leur transaction, rejouent `RoundCardChecks`, et lèvent `IncoherentRoundCard` (message `problemCountLabel`, problèmes joints) avant toute écriture. `UpdateRound` remplace trous, joueurs et scores du round (suppression puis réécriture, comme Next) ; `photo_path` ne change pas.
- `DeleteRound::handle(Round)` supprime le round (cascade) dans la transaction, puis efface la photo par `DB::afterCommit` : un rollback ne perd jamais la photo d'un round qui existe encore.
- Players : `AddPlayer`, `RenamePlayer`, `AssignAlias` (supprime l'alias de même `lower()` où qu'il soit, puis l'insère pour le joueur, en une transaction ; réutilisée par l'import en 8), `RemoveAlias`. `CreateInitialPlayers` (D6).
- Le doublon de nom est d'abord une règle du Form Request (`Rule::unique` sur `lower(name)`, en ignorant le joueur renommé) : message « Ce joueur existe déjà » sous le champ `name`. La contrainte unique reste la garde : l'Action traduit `UniqueConstraintViolationException` en `DuplicatePlayer` (course entre deux onglets).
- Normalisation : `App\Domain\Players\PlayerName::name()` (trim) et `::alias()` (trim puis `mb_strtoupper`).

### D4. Rendu des erreurs métier

`App\Domain\DomainException` (abstraite, message pour l'utilisateur). Rendu unique dans `bootstrap/app.php` : requête qui attend du JSON hors Inertia, 422 `{ "error": message }` ; sinon `back()` avec `Inertia::flash('toast', ['type' => 'error', 'message' => …])`. `IncoherentRoundCard`, `DuplicatePlayer` en sont les premières sous-classes. Les confirmations (« Round enregistré. », « Round supprimé. ») passent par le même flash, en `success`.

### D5. Doublon de round : une Query avant l'Action

`FindDuplicateRound::for(RoundCard, ?Round $except): ?Round` compare parcours et tees en `lower(trim())`, date égale, et la signature triée « joueur:brut » calculée depuis `scores` (pas de `player_round_stats` avant 6). Le contrôleur l'appelle quand `force` est faux ; s'il trouve un round, il revient à l'éditeur avec `Inertia::flash('duplicate', ['roundId' => …])` sans rien écrire. L'éditeur montre l'alerte « Ce round semble déjà enregistré », le lien vers `rounds.show`, et « Enregistrer quand même » renvoie la même carte avec `force: true`. Le doublon n'est pas un invariant (on peut passer outre), d'où sa place hors de l'Action.

### D6. Joueurs initiaux par une commande du service `migrate`

- `config/golf.php` : `initial_players` lit `INITIAL_PLAYERS`. La commande `golf:initial-players` appelle `CreateInitialPlayers::handle(string $raw)`, qui découpe, retire les vides, refuse un doublon de `lower()` par une exception dont le message nomme `INITIAL_PLAYERS`, et n'écrit rien si un joueur existe déjà, le tout en une transaction. Exception : la commande l'affiche et sort en 1.
- `docker-compose.yml` : `migrate` lance `sh -c 'php artisan migrate --force && php artisan golf:initial-players'`. `migrate` en échec garde `web` et `worker` arrêtés, comme une migration en échec. `make image-smoke` le prouve sans changer : la stack de fumée n'a pas `INITIAL_PLAYERS`, la commande ne fait rien et sort en 0.
- `make setup` ne lance pas la commande : le développeur passe par `make fresh` ou `make demo`.

**Alternative écartée** : une migration de données qui lit `INITIAL_PLAYERS`. Elle ne tourne qu'une fois : une base vidée puis remigrée sans rollback ne retrouverait pas ses joueurs, et la spec dit « à chaque lancement de `migrate` sur une base sans joueur ».

### D7. Photos sur un disque `photos` non servi

- Disque `photos` (driver `local`, racine `storage_path('app/photos')`, `serve => false`) : sous le volume `storage`, hors de la racine du disque `local` que sert `storage/{path}`. `photo_path` est relatif à ce disque (`rounds/<uuid>.webp` pour la démonstration ; l'import, en 8, choisit son propre préfixe).
- `GET /rounds/{round}/photo` (`rounds.photo`) : `Storage::disk('photos')->response()` avec `Content-Type: image/webp` et `Cache-Control: private, max-age=86400`. Sans `photo_path` ou sans fichier : 404 `{ "error": "Photo introuvable" }`.
- Aucun envoi dans ce changement ; la démonstration copie `database/data/img3064.webp` (converti une fois depuis la fixture Next, versionné).

### D8. Routes et contrôleurs

| Méthode | Adresse | Nom | Contrôleur |
| --- | --- | --- | --- |
| GET | `/players` | `players.index` | `PlayerController@index` |
| POST | `/players` | `players.store` | `PlayerController@store` |
| PATCH | `/players/{player}` | `players.update` | `PlayerController@update` |
| POST | `/players/{player}/aliases` | `players.aliases.store` | `PlayerAliasController@store` |
| DELETE | `/players/{player}/aliases/{alias}` | `players.aliases.destroy` | `PlayerAliasController@destroy` |
| GET | `/rounds` | `rounds.index` | `RoundController@index` |
| GET | `/rounds/new` | `rounds.create` | `RoundController@create` |
| POST | `/rounds` | `rounds.store` | `RoundController@store` |
| GET | `/rounds/{round}` | `rounds.show` | `RoundController@show` |
| GET | `/rounds/{round}/edit` | `rounds.edit` | `RoundController@edit` |
| PUT | `/rounds/{round}` | `rounds.update` | `RoundController@update` |
| DELETE | `/rounds/{round}` | `rounds.destroy` | `RoundController@destroy` |
| GET | `/rounds/{round}/photo` | `rounds.photo` | `RoundPhotoController` |

- `{round}`, `{player}` et `{alias}` passent par `whereUuid()` : `/rounds/12` répond 404 sans requête SQL. `{alias}` est l'`id` de `player_aliases`, lié au joueur par `scopeBindings()`.
- `/rounds/new` est déclarée avant `/rounds/{round}`. Les deux routes `section-placeholder` de Rounds et Joueurs disparaissent ; `/players/xyz` et `/rounds/xyz` tombent dans la route de repli (404).
- Création d'un joueur depuis une ligne : `router.post(players.store(), { name }, { preserveState: true, preserveScroll: true, only: ['players'], errorBag: 'linePlayer' })`. `PlayerController@store` revient en arrière ; l'éditeur associe à la ligne le joueur des props rechargées dont le nom égale, sans casse, le nom saisi.
- Lectures : `RoundList`, `RoundDetail`, `KnownCourses`, `PlayerOverview` sous `app/Domain/*/Queries`, chargement anticipé (`with`) pour éviter le N+1 ; un test compte les requêtes de `/rounds` avec 10 rounds.

### D9. Frontend

- Pages : `players/index`, `rounds/index`, `rounds/show`, `rounds/create`, `rounds/edit`. `rounds/create` et `rounds/edit` montent le même `RoundEditor`.
- Composants portés de Next, mêmes classes : `round-editor/{round-editor, score-grid, line-players, date-field, form-state, problem-list}`, `rounds/{round-scorecard, delete-round}`, `players/inline-form`, plus `rounds/round-gross` (bruts, à la place du classement net). `form-state` perd `aiCells`, `readTotals` et `readCardTotals` ; les totaux sont toujours calculés.
- L'état de l'éditeur reste local (`useReducer`) ; l'envoi passe par `router.post` / `router.put` sur les actions Wayfinder, avec la carte entière en JSON. L'attente suit `onStart` / `onFinish` et reste affichée quand la visite réussit.
- `resources/js/lib/format.ts` : `formatPlayedOn('2026-10-17')` → « 17 octobre 2026 », lu comme jour civil, sans `Date` UTC (scénario du fuseau à l'ouest).
- La date du jour de la saisie manuelle vient du serveur (`LocalTime::date(now())`), pas du navigateur.

### D10. Données de développement

- `Database\Seeders\DemoSeeder` (abstraite) : garde de production dans `__invoke()` (couche 10 du squelette), sans `asDemoAccount` (pas de comptes). L'arch test « demo seeders refuse to run in production » est ajouté.
- `DatabaseSeeder` → `DevelopmentPlayersSeeder` : Gab, Manu, Sean par `CreateInitialPlayers::handle('Manu,Gab,Sean')`.
- `DemoDatabaseSeeder` : refuse si `players` ou `rounds` a une ligne ; puis `DevelopmentPlayersSeeder`, puis `DemoRoundsSeeder` : le round de référence (valeurs de `src/server/rounds/seed.ts`, photo copiée sur le disque `photos`) et 24 rounds générés par `CreateRound` sur BETHPAGE BLACK / Red, PAYNES VALLEY / Blue et CALA NOVA / White, du 2026-04-01 au 2026-10-15, avec `fake()->seed(2026)`. Les cartes des parcours générés sont des littéraux du seeder.
- Fabriques : `PlayerFactory` (état `withAlias()`), `PlayerAliasFactory`, `RoundFactory` (états `withPlayers(Player ...$players)`, qui crée 18 trous cohérents, les lignes et 18 scores par joueur ; `withPhoto()`), `RoundHoleFactory`, `RoundPlayerFactory`, `ScoreFactory`. Test de découverte : la liste littérale des six modèles et de leurs fabriques.
- `make fresh` : `migrate:fresh --seed`. `make demo` : `migrate:fresh` puis `db:seed --class=DemoDatabaseSeeder`. `DemoRoundsSeeder` vide d'abord le dossier `rounds/` du disque `photos` : la base recréée ne référence plus aucun de ses fichiers.

### D11. ADR 0009

« Modèle des rounds et contrôles de cohérence » : D1 (six tables, contraintes), D2 (valeur du domaine, double contrôle, table de cas partagée), D7 (disque `photos` non servi). Ajoutée à `docs/adr/README.md` ; `ARCHITECTURE.md` gagne une section « Joueurs et rounds ».

## Risks / Trade-offs

- [Contrôles PHP et TypeScript qui divergent] → table de cas partagée `tests/fixtures/round-checks.json`, parcourue par Pest et Vitest.
- [Remplacement complet des lignes à chaque correction : les `id` des trous et scores changent] → aucune table ne les référence ; `player_round_stats` (6) se rattache à `round_id` et `player_id`.
- [Association du joueur créé depuis une ligne par le nom] → le nom est unique sans casse, la correspondance est donc sûre ; un test Vitest couvre la casse différente.
- [Photo effacée après le commit : un échec du disque laisse un fichier orphelin] → accepté ; aucune donnée perdue, le fichier n'est plus servi.
- [`sh -c` dans la commande de `migrate`] → l'entrypoint fait `exec "$@"` ; `set -e` du shell et `&&` gardent le code de sortie. Le scénario « Joueurs initiaux en double » se vérifie sur la stack de fumée locale lors de l'apply.
- [Taille de la photo de démonstration dans le dépôt] → conversion WebP qualité 80, largeur 1600 px, cible sous 400 Ko.

## Migration Plan

1. Pull request de code : six migrations, aucune table existante touchée. `migrate` les applique au déploiement, puis `golf:initial-players` crée les joueurs si `INITIAL_PLAYERS` est défini sur le staging.
2. Retour arrière : redéployer l'image précédente puis `php artisan migrate:rollback --step=6` dans un conteneur de la stack. Aucune donnée de production n'existe dans ces tables avant le cutover (entrée 10).

## Open Questions

Aucune.
