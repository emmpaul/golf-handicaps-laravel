# Design

## Context

- `players-and-rounds` (entrée 5) pose six tables en UUID (`players`, `player_aliases`, `rounds`, `round_holes`, `round_players`, `scores`), avec une FK composite `scores (round_id, player_id)` vers `round_players` et l'unicité `(round_id, player_id)` sur `round_players`. Son design D1 prévoit que `player_round_stats` s'y rattache par `round_id` et `player_id` ; ses Actions `CreateRound`, `UpdateRound` (suppression puis réécriture des trous et des lignes) et `DeleteRound` ouvrent leur transaction et « laissent la place d'un appel » de recalcul.
- Lectures existantes : `RoundList` (quatre requêtes quel que soit le nombre de rounds, bornées par un test), `RoundDetail`, `KnownCourses` (clé `lower(trim(course_name)), lower(trim(tees))`, trous du round le plus récent), `PlayerOverview`. La page d'un round montre les bruts par le composant `rounds/round-gross`.
- `resources/js/lib/sections.ts` rattache déjà `/courses` à la section Rounds (spec `app-shell`) ; aucune route `/courses` n'existe.
- `App\Domain\DomainException` et son rendu commun (flash `toast`, ou 422 `{ "error" }` en JSON) existent.
- Référence Next (`/Users/epaul/www/z_perso/golf-handicaps`) :
    - calcul : `src/domain/handicap.ts` (ajusté, différentiel, index), `src/domain/strokes.ts` (`playingHandicap`, `courseHandicap` ; `allocateStrokes` et `matchStrokes` sont pour l'entrée 7) ;
    - recalcul : `src/server/rounds/stats.ts`, appels dans `save.ts` et `edit.ts` ;
    - classement : `src/server/rounds/leaderboard.ts`, `list.ts` (gagnants nets), `detail.ts` ;
    - ratings : `src/server/courses/ratings.ts` (`ratingFor`), `manage.ts` (`saveManualRating`, `writeConfirmed`, `playersOnCourse`, `courseRatingOverview`) ; tout ce qui touche SGT reste pour l'entrée 9 ;
    - schéma : `src/db/schema.ts` (`playerRoundStats`, `courseRatings`) ;
    - interface : `src/components/rounds/net-leaderboard.tsx`, `src/app/(app)/rounds/page.tsx` (colonne « Gagnant net », lien « Parcours »), `src/app/(app)/rounds/[id]/page.tsx` (ligne de rating), `src/app/(app)/courses/page.tsx` (sans les colonnes SGT), `src/components/courses/manual-rating-form.tsx`, `src/app/(app)/players/page.tsx` (ligne « Index »), `src/lib/format.ts` (`formatTenths`, `formatIndex`, `NO_VALUE`, `NO_INDEX`) ;
    - tests à porter en Pest avec les mêmes valeurs : `tests/domain/handicap.test.ts`, `tests/domain/handicap-index.test.ts`, `tests/domain/strokes.test.ts` (`playingHandicap`, `courseHandicap`), `tests/server/rounds/{stats,history,leaderboard,save,edit}.test.ts`, `tests/server/courses/{ratings,manage}.test.ts` (cas de saisie manuelle).
- Le changement `add-course-rating` de Next n'est pas archivé (sa tâche 7.2, vérification sur la prod, reste ouverte), mais ses formules sont dans `src/domain` et testées : ce sont elles qui sont portées.

## Goals / Non-Goals

**Goals**

- Les mêmes nombres que Next, au dixième près, pour les mêmes rounds et les mêmes ratings : c'est ce que l'import SQLite (10) vérifiera ligne à ligne.
- Un calcul pur, sans base ni framework, testé par les valeurs de Next ; la base ne fait que lire et écrire.
- `player_round_stats` toujours juste après une écriture réussie, et inchangée après une écriture refusée.

**Non-Goals**

- Tout ce qui touche SGT, l'IA ou le worker : aucune colonne de statut, de proposition ni de candidat n'est créée d'avance ; l'entrée 9 les ajoute par sa migration.
- Graphiques, page Handicaps, accueil, page Coups (7).
- Cache des index : quelques centaines de rounds au plus.

## Decisions

### D1. Calcul pur dans `App\Domain\Handicaps\Whs`

- Port un pour un de `handicap.ts` et de `strokes.ts` (sans la répartition des coups) : `adjustedHoleScore`, `adjustedScore`, `differentialTenths(strokes, pars, CourseRating)`, `computeIndex(list<RoundDifferential>): HandicapIndex { indexT, provisional, counts }`, `playingHandicap(indexT)`, `courseHandicap(indexT, ?RatedCourse)`. Valeurs `final readonly` : `CourseRating { ratingT, slope }`, `RatedCourse { ratingT, slope, par }`, `RoundDifferential { playedOn, createdAt, differentialT }`.
- `App\Domain\Handicaps\NetRanking::rank(list<NetEntry>, ?RatedCourse)` : port de `rankNet` (rang de compétition, joueur sans index ni net ni rang).
- Une règle d'architecture Pest : `Whs`, `NetRanking` et leurs valeurs n'utilisent ni `Illuminate`, ni `App\Models`. Le calcul se teste sans base.
- Tri de `computeIndex` : date de jeu, puis `created_at`, puis `id` comme Next ; tri des différentiels par `usort`, stable depuis PHP 8 comme `Array.prototype.sort` : à différentiels égaux, les mêmes rounds sont marqués que dans Next.

### D2. Dixièmes entiers, arrondi de Next

- Différentiels et index en dixièmes entiers (`differential_t`, `index_before_t`, `index_after_t`, `rating_t`), comme Next. La seule division flottante est celle du différentiel, de la moyenne et du handicap de parcours, suivie de `(int) floor($x + 0.5)`, la formule de Next (`Math.floor(x + 0.5)`) : 0,05 vers le haut, y compris pour un négatif (−1,15 → −1,1).
- Le front reçoit les dixièmes et formate (`formatTenths(174)` → « 17,4 », `formatIndex`), comme Next.

**Alternative écartée** : colonnes `numeric(4,1)` et calcul en `bcmath`. Plus lisible en base, mais l'arrondi diffère de Next sur les demi-dixièmes et l'import (10) devrait convertir pour comparer.

### D3. Deux tables

| Table | Colonnes | Contraintes |
| --- | --- | --- |
| `player_round_stats` | `id`, `round_id`, `player_id`, `gross`, `adjusted`, `differential_t`, `index_before_t` (nullable), `index_after_t`, `counts_in_current` (booléen, faux par défaut) ; sans timestamps | FK `rounds` en cascade ; FK `players` en `restrict` ; FK composite `(round_id, player_id)` vers `round_players` en cascade ; unique `(round_id, player_id)` ; index `player_id` ; `CHECK` `index_before_t` et `index_after_t` ≤ 540 |
| `course_ratings` | `id`, `course_name`, `tees`, `rating_t`, `slope`, timestamps | index unique sur `(lower(trim(course_name)), lower(trim(tees)))` en `DB::statement` ; `CHECK` `rating_t` 400–900, `slope` 55–155 |

- `player_round_stats` garde l'`id` UUID de la règle ADR 0002 (l'arch test l'exige), là où Next a une clé composite ; l'unicité `(round_id, player_id)` tient le même rôle. La FK composite vers `round_players` efface les statistiques d'un joueur retiré d'un round, même si un recalcul était oublié.
- `course_ratings` : `rating_t` et `slope` non nuls, car une ligne n'existe ici qu'après une saisie. L'entrée 9 rendra ces colonnes nullables et ajoutera statut, parcours SGT et tees lus, comme Next (`rating_t` null tant que rien n'est confirmé).
- Une ligne de rating dont plus aucun round n'utilise la paire reste en base, invisible sur `/courses` ; elle resservira si un round revient sur ce parcours. Next fait de même.

**Alternative écartée** : créer dès maintenant `course_ratings` avec les colonnes SGT nullables. Elle éviterait une migration en 9 mais fige un schéma pour un comportement non spécifié ici.

### D4. Recalcul dans la transaction de l'écriture

- `App\Domain\Handicaps\Actions\RecomputePlayerStats::handle(iterable<string> $playerIds)` : port de `recomputePlayerStats` / `recomputeStats`. Elle ouvre `DB::transaction` (point de sauvegarde si l'appelant en a une), verrouille les joueurs par `lockForUpdate()` dans l'ordre des `id`, puis, par joueur : supprime ses lignes, lit son historique, calcule, insère toutes ses lignes en un `insert`.
- Lecture de l'historique d'un joueur en trois requêtes : ses rounds avec la rating de leur paire (`left join course_ratings` sur `lower(trim(...))`, la même expression que l'index unique et que `KnownCourses`), les pars de ces rounds, ses scores sur ces rounds. Sans ligne de rating : `ratingT = par × 10`, `slope = 113`.
- Appels :
    - `CreateRound` : les joueurs de la carte, après l'écriture ;
    - `UpdateRound` : les joueurs lus avant la suppression des lignes, plus ceux de la carte (« Joueur remplacé ») ;
    - `DeleteRound` : les joueurs lus avant la suppression ;
    - `SaveCourseRating` (D6) : les joueurs qui ont un round sur la paire, sans casse.
- Les contrôles de cohérence de `CreateRound` / `UpdateRound` passent avant toute écriture ; un refus ne touche donc aucune statistique (scénario « Correction refusée »).
- Le verrou sérialise deux écritures qui touchent le même joueur : sans lui, en `READ COMMITTED`, la seconde pourrait réécrire un historique lu avant le commit de la première.

**Alternative écartée** : un job de file après le commit. Les pages afficheraient un index faux jusqu'au passage du worker, et la spec demande le recalcul « avant de répondre ». Le coût (quelques requêtes par joueur, au plus 4 joueurs par round) est négligeable.

### D5. Lectures : classement à la lecture, requêtes bornées

- Le handicap de jeu et le net ne sont pas stockés (comme Next, design `add-course-rating` décision 1) : ils dépendent de la rating, qui peut changer, et se calculent depuis `index_before_t`.
- `App\Domain\Handicaps\Queries\RoundLeaderboard::for(Round)` : les lignes du round dans l'ordre de la carte, avec `gross` et `index_before_t` de `player_round_stats`, et la rating du round ; puis `NetRanking::rank`. `RoundDetail` l'utilise et ajoute `rating: { rated, ratingT, slope }` et le par total.
- `RoundList` gagne `netWinners: list<string>` : une requête de plus (statistiques de tous les rounds jointes à leur rating), le classement se fait en PHP. Le test qui compte les requêtes de `/rounds` avec 10 rounds passe à la nouvelle valeur littérale.
- `PlayerOverview` gagne `indexT` (index d'après le dernier round, par date de jeu, `created_at`, `id`) et `provisional` (moins de 3 rounds), en une requête (`distinct on (player_id)` plus un `count`).

### D6. Saisie d'une rating

- Route `PUT /courses/rating` (`courses.rating.update`, `CourseRatingController`), corps `{ courseName, tees, rating, slope }`. Pas d'`id` dans l'adresse : un parcours sans rating n'a pas de ligne.
- `SaveCourseRatingRequest` : `courseName` et `tees` obligatoires, 100 caractères au plus ; `rating` doit correspondre à `^\d{2}([.,]\d)?$` (après `trim`) et donner 400 à 900 dixièmes, sinon « Rating entre 40,0 et 90,0 » sous `rating` ; `slope` doit correspondre à `^\d+$` et valoir 55 à 155, sinon « Slope entre 55 et 155 » sous `slope`. La conversion « 71,4 » → 714 est `App\Domain\Courses\RatingInput::tenths()`, pure et testée seule (port de `parseRatingT`).
- `App\Domain\Courses\Actions\SaveCourseRating::handle(string $courseName, string $tees, int $ratingT, int $slope)` : transaction ; si aucun round n'a cette paire (sans casse), `UnknownCourse` (`DomainException`, « Parcours introuvable ») ; sinon `insert … on conflict ((lower(trim(course_name))), (lower(trim(tees)))) do update` (PostgreSQL infère l'index d'expression), puis `RecomputePlayerStats` pour les joueurs de la paire. Deux saisies simultanées du même parcours ne lèvent donc jamais de violation d'unicité.
- Le contrôleur revient en arrière avec le toast « Rating enregistrée. » (même flash que « Round enregistré. »).
- `App\Domain\Courses\Queries\CourseOverview` : `KnownCourses` plus la rating de chaque paire (une requête de plus), le par total des trous du round le plus récent. `GET /courses` (`courses.index`, `CourseController@index`) sert la page `courses/index`.

### D7. Frontend

- `lib/format.ts` gagne `formatTenths`, `formatIndex`, `NO_VALUE` (« — ») et `NO_INDEX` (« pas encore d'index »), portés de Next.
- `rounds/net-leaderboard` porté de Next, mêmes classes : en-tête sur le bandeau d'encre, « Hcp de jeu » abrégé « Hcp » sous 768 px, mention « pas encore d'index » qui passe à la ligne, gagnant net en `text-signal`. Il remplace `rounds/round-gross`, supprimé avec son test.
- `rounds/show` : ligne de rating (lien Wayfinder `courses.index`) ; `rounds/index` : colonne « Gagnant net » et lien « Parcours » à côté de « Saisie manuelle » ; `players/index` : « Index … » sous le nom.
- `courses/index` porté de la page Next sans la colonne SGT (variante `plain` des colonnes) ; `courses/manual-rating-form` : un `useForm` par ligne, envoi `put` sur l'action Wayfinder avec `preserveScroll`, `processing` qui affiche « Enregistrement de la rating… » et désactive le seul bouton de la ligne. Les erreurs d'une ligne restent sur sa propre instance de formulaire.

### D8. Commande `golf:recompute-stats`

- Appelle `RecomputePlayerStats` pour tous les joueurs qui ont au moins un round, dans une transaction, et affiche « N joueurs recalculés. ». Idempotente.
- Elle sert une fois sur le staging (rounds écrits avant ce déploiement) et resservira à l'import (10). Elle n'est pas dans la commande du service `migrate` : en production, aucune donnée n'existe avant l'import.

### D9. Données de démonstration et fabriques

- `DemoDatabaseSeeder` : après `DemoRoundsSeeder` (dont les rounds passent par `CreateRound`, donc sont calculés), `DemoCourseRatingsSeeder` appelle `SaveCourseRating('BETHPAGE BLACK', 'Red', 714, 144)`. PAYNES VALLEY / Blue et CALA NOVA / White restent sans rating, pour montrer les deux cas.
- `CourseRatingFactory` (états `bethpageRed()` 714 / 144, `bounds()` 400 / 55) et `PlayerRoundStatFactory` (crée son round et sa ligne par `RoundFactory::withPlayers()` ; états `firstRound()` sans index d'avant, `counting()`). Ces fabriques ne recalculent rien : elles servent aux tests de lecture. `FactoryDiscoveryTest` passe à la liste littérale des huit modèles.

### D10. ADR 0010

« Calcul de l'index en dixièmes entiers et table dérivée » : D1 (calcul pur), D2 (unités et arrondi de Next), D4 (recalcul synchrone sous verrou), D3 pour la rating appliquée à tout l'historique d'une paire. Ajoutée à `docs/adr/README.md` ; `ARCHITECTURE.md` gagne une section « Index et ratings ».

## Risks / Trade-offs

- [Écart d'arrondi PHP / JavaScript sur un demi-dixième] → même formule `floor(x + 0.5)` ; les cas de Next sont portés en littéraux, dont l'arrondi 13,25 → 13,3, l'index négatif et la rating 70,0 / 130 (17,38 → 1,7 ; 156,46 → 15,6).
- [`lower()` de PostgreSQL et `mb_strtolower` de PHP divergent sur un caractère accentué] → toute comparaison de paire se fait en SQL avec la même expression, jamais en PHP.
- [Recalcul oublié dans une écriture future] → la FK composite efface les lignes orphelines ; les tests des trois Actions et de `SaveCourseRating` lisent `player_round_stats` après l'écriture. L'import (10) et la commande D8 recalculent tout.
- [Changement de rating qui modifie l'historique d'un coup] → voulu (comme Next) ; la saisie est un acte explicite, parcours par parcours.
- [Verrou de joueurs dans une transaction longue] → au plus 4 joueurs par round ; une saisie de rating verrouille les joueurs d'un parcours, quelques-uns.

## Migration Plan

1. Pull request de code : deux migrations, aucune table existante modifiée. `migrate` les applique au déploiement.
2. Sur le staging, si des rounds existent déjà : `php artisan golf:recompute-stats` dans le conteneur `web`, puis vérifier `/players` et un round. Ajouter cette étape à `docs/deployment.md`.
3. Retour arrière : redéployer l'image précédente puis `php artisan migrate:rollback --step=2`. Les tables de `players-and-rounds` sont intactes ; les ratings saisies sur le staging sont perdues.

## Open Questions

- Faut-il pouvoir retirer une rating saisie (retour à « sans rating ») ? Next ne le permet pas et ce changement non plus ; un retrait s'ajouterait à `SaveCourseRating` sans changer le schéma.
- Le mainteneur confirme-t-il que les formules de `add-course-rating` (non archivé dans Next) sont bien la référence, plutôt que la spec principale `handicap-index` de Next (slope fixé à 113) ? Sans rating saisie, les deux donnent les mêmes nombres.
- `golf:recompute-stats` doit-elle tourner à chaque lancement du service `migrate` (idempotente, quelques secondes) plutôt qu'à la main sur le staging ? Le choix ne change que la commande de `migrate` et `docs/deployment.md`.
