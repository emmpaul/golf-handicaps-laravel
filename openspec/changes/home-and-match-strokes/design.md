# Design

## Context

- `routes/web.php` : `/` sert `home` par `Route::inertia` (page vide « Le tableau du groupe arrive bientôt. ») ; `/handicaps/{path?}` et `/strokes/{path?}` servent `section-placeholder` sous les noms `handicaps.index` et `strokes.index`, que la navigation lit par Wayfinder. Après 8, `/import` a sa page et seul ce repli reste (testé par `SectionPlaceholderTest`).
- Lectures existantes (6) : `PlayerOverview` (index d'après le dernier round par `distinct on`, provisoire sous 3 rounds, alias) ; `RoundList` (tous les rounds, bruts dans l'ordre de la carte, gagnants nets par `NetRanking`, cinq requêtes) ; `CourseOverview` (parcours connus avec par et rating, une requête, trous chargés) ; `KnownCourses` (pars, index et distances du dernier round de chaque paire) ; `Whs::courseHandicap` et `Whs::playingHandicap` (PHP, testés par `CourseHandicapTest`).
- `player_round_stats` garde, par joueur et par round, `index_before_t`, `index_after_t`, `differential_t` et `counts_in_current` : l'écart d'un joueur est `index_after_t − index_before_t` de son dernier round, sans autre calcul.
- `imports` (8, design D1) : `status`, `round_id` nullable unique, `created_at` indexé ; route `imports.show` vers l'écran de correction.
- `resources/js/lib/format.ts` : `formatTenths`, `formatIndex`, `formatPlayedOn`, `NO_VALUE`, `NO_INDEX`. `components/handicaps/computation-help` : l'explication repliée.
- Référence Next (`/Users/epaul/www/z_perso/golf-handicaps`) : `src/app/(app)/page.tsx`, `src/server/home/summary.ts` et `tests/server/home/summary.test.ts` ; `src/app/(app)/handicaps/page.tsx` ; `src/app/(app)/strokes/page.tsx`, `src/components/strokes/{strokes-planner,strokes-grid}.tsx`, `src/domain/strokes.ts` et `tests/domain/strokes.test.ts` ; `src/lib/format.ts` (`formatSignedTenths`).
- `visual-redesign` (11) recompose ensuite ces trois pages ; ses deltas ajoutent des exigences de mise en page sous d'autres noms que ceux de ce changement, et sa tâche 1.2 réconcilie les contradictions.

## Goals / Non-Goals

**Goals**

- Les mêmes nombres que Next sur l'accueil, la page Handicaps et la page Coups, pour les mêmes rounds et les mêmes ratings.
- Des lectures bornées : un nombre de requêtes fixe par page, quel que soit le nombre de rounds ou de joueurs.
- Le comportement dans le thème actuel, avec les primitives existantes, sans travail de style que 11 jettera.

**Non-Goals**

- Graphique, tendance fléchée, statistiques de jeu, couleur de joueur (11).
- Tout stockage nouveau : aucune table, colonne ni modèle.
- Mise en cache des lectures : quelques centaines de rounds au plus.

## Decisions

### D1. Index courant et écart : une Query partagée

`App\Domain\Handicaps\Queries\PlayerStandings::get()` rend, par joueur, trié par `lower(name)` puis `id` : `id`, `name`, `indexT`, `provisional`, `deltaT` (`index_after_t − index_before_t` du dernier round par date de jeu, puis `created_at`, puis `id` ; `null` sans round ou au premier round). Une requête : la même sous-requête `distinct on` que `PlayerOverview`, étendue de `index_before_t`.

Pour ne pas dupliquer cette sous-requête (jscpd à seuil 0), elle sort de `PlayerOverview` dans une méthode publique de `PlayerStandings` (`currentStats(): Collection`) que `PlayerOverview` appelle ; la page Joueurs ne change pas de props.

- *Alternative* : ajouter `deltaT` aux props de `PlayerOverview`. Rejetée : la page Joueurs recevrait une valeur qu'elle n'affiche pas, et l'accueil, la page Handicaps et la page Coups chargeraient les alias pour rien.

### D2. Dernier round : `RoundList` bornée

`RoundList::get(?int $limit = null)` : avec une limite, la requête des rounds prend `limit`, et les requêtes des bruts et des statistiques se restreignent aux `round_id` retenus (`whereIn`). L'accueil appelle `get(1)` et prend le premier élément ou `null`. La page Rounds garde `get()` et ses cinq requêtes. Les gagnants nets viennent du même code que la liste des rounds : l'accueil et `/rounds` ne peuvent pas diverger.

- *Alternative* : une Query `LatestRound` à part. Rejetée : elle recopierait le calcul des gagnants nets.

### D3. Imports à vérifier

`App\Domain\Imports\Queries\PendingImports::get(): array{count: int, latestId: string|null}` : une requête sur `imports` où `status = 'ready'` et `round_id is null`, `count(*)` et l'`id` du plus récent par `created_at desc, id desc` (fenêtre ou deux agrégats dans la même requête). Pas de borne aux 20 imports de l'écran d'import (scénario « Import ancien »). 11 réutilise cette Query pour le compteur du bouton Importer (son D7).

### D4. Page Handicaps

- `HandicapController@index(Request)` : lit `joueur` dans la query string ; si ce n'est pas un UUID connu parmi `PlayerStandings`, prend le premier joueur. Props : `players` (`PlayerStandings`), `selectedPlayerId` (`string|null`, `null` sans joueur), `history` (rounds du joueur choisi, `[]` sans joueur).
- `App\Domain\Handicaps\Queries\PlayerHandicapHistory::get(string $playerId)` : une requête sur `player_round_stats` jointe à `rounds`, du plus récent au plus ancien (`played_on desc, created_at desc, id desc`) : `roundId`, `playedOn`, `courseName`, `tees`, `differentialT`, `indexAfterT`, `countsInCurrent`. Seul le joueur choisi est chargé : un joueur à la fois (DP4).
- Le sélecteur est une suite de `Link` Wayfinder `handicaps.index({ query: { joueur } })`, `aria-current="page"` sur le joueur choisi, visite avec `preserveScroll` et `only: ['selectedPlayerId', 'history']` : `players` ne se relit pas. Un identifiant mal formé ne fait aucune requête de plus (contrôle `Str::isUuid` avant la recherche).
- Le résumé lit le joueur choisi dans `players` (index, écart, provisoire) : pas de calcul côté client.

### D5. Page Coups : calcul dans le navigateur

- `StrokesController@index` : props `players` (`PlayerStandings`, réduits à `id`, `name`, `indexT`) et `courses` (`CourseOverview`). `CourseOverview` gagne `strokeIndexes` (les index des trous du dernier round de la paire, déjà chargés par `with('holes')`) ; la page Parcours reçoit ce champ sans l'afficher, et `CoursesPageTest` passe à la nouvelle forme littérale.
- `resources/js/lib/strokes.ts` porte `playingHandicap`, `courseHandicap`, `allocateStrokes` et `matchStrokes` de `src/domain/strokes.ts`, en entiers (dixièmes), même arrondi `Math.floor(x + 0.5)`. Le composant `strokes/strokes-planner` garde l'état (joueurs cochés, parcours, mode) en `useState`, comme Next.
- Deux implémentations du handicap de parcours (PHP pour le classement net, TypeScript ici) : un jeu de cas écrit à la main, `tests/fixtures/course-handicap.json` (index, rating, slope, par, attendu), est lu par `tests/Unit/Handicaps/CourseHandicapTest.php` et par `resources/js/lib/strokes.test.ts`. Une divergence de formule fait échouer l'un des deux. Même procédé que `tests/fixtures/round-checks.json` (5, 8).
- *Alternative* : calcul serveur par une visite Inertia à chaque case cochée (état dans l'adresse). Rejetée : un aller-retour par geste sur un téléphone au simulateur, pour un calcul de quelques entiers ; et la page Coups ne stocke rien.
- *Alternative* : envoyer depuis PHP le handicap de parcours de chaque joueur sur chaque parcours. Rejetée : joueurs × parcours valeurs pour en utiliser quelques-unes, et le mode et la répartition resteraient côté client de toute façon.

### D6. Routes

| Méthode | Adresse | Nom | Action |
| --- | --- | --- | --- |
| GET | `/` | `home` | `HomeController` (invocable) |
| GET | `/handicaps` | `handicaps.index` | `HandicapController@index` |
| GET | `/strokes` | `strokes.index` | `StrokesController@index` |

Les noms ne changent pas : `AppNav` et ses tests lisent les mêmes fonctions Wayfinder. Les routes de repli `/{section}/{path?}`, la page `section-placeholder.tsx` et `SectionPlaceholderTest` sont supprimées ; une sous-adresse tombe dans le `fallback` existant (404). `HomeController` rend `home` avec `players` (`PlayerStandings`), `latestRound` (D2) et `pendingImports` (D3).

### D7. Interface dans le thème actuel

- `lib/format.ts` gagne `formatSignedTenths` (« = », « +0,3 », « −0,3 » avec U+2212), porté de Next, et `formatShortPlayedOn` (« 17 oct. 2026 », `Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })` sur `isoToLocalDate`).
- Pages `home`, `handicaps/index`, `strokes/index` et composants `handicaps/player-picker`, `handicaps/round-history`, `strokes/strokes-planner`, `strokes/strokes-grid` : le balisage de Next, porté sur les primitives et tokens existants (`Table`, `Badge`, `Field`, `NativeSelect`, `bg-band`, `text-signal`). Pas de nouvelle primitive, pas de nouveau token : 11 réécrit ces pages.
- `components/handicaps/computation-help` s'ajoute sous la liste de `handicaps/index`.
- La grille des coups : `grid-cols-9`, cases `role="img"` avec `aria-label` « Trou 3 : aucun coup », « Trou 15 : 1 coup », « Trou 15 : 2 coups », et « Trou 3 : rend 1 coup » pour une case à −1 ; tient à 320 px comme dans Next.

### D8. Requêtes bornées

Chaque page a son test de nombre de requêtes (`DB::listen`), avec 10 puis 30 rounds sur 3 joueurs : le même nombre les deux fois, comparé à un littéral relevé au premier passage vert et écrit dans le test. Attendus indicatifs : accueil, `PlayerStandings` 1, `RoundList::get(1)` 5, `PendingImports` 1 ; page Handicaps, `PlayerStandings` 1, historique 1 ; page Coups, `PlayerStandings` 1, `CourseOverview` 2 (rounds, trous). Les requêtes de session et du middleware sont comptées à part ou exclues de la même façon que `RoundPagesTest`.

### D9. Pas d'ADR

Aucune décision d'architecture nouvelle : des Queries et des contrôleurs minces selon l'ADR 0001, des valeurs en dixièmes selon l'ADR 0010, un jeu de cas partagé PHP / TypeScript déjà employé par 5. La décision de ne pas ajouter recharts (DP3) annote la décision 7 de `docs/PORT_TO_LARAVEL.md` ; 11 retire la dépendance par sa propre ADR.

## Décisions par défaut (orchestrateur, nuit du 2026-10-28)

Le mainteneur dormait pendant la proposition. Chaque question ouverte a été tranchée par l'option recommandée ci-dessous ; chacune peut être contestée avant l'apply, et le changement est alors mis à jour par `/opsx:update`.

| # | Question | Décision | Alternative écartée |
| --- | --- | --- | --- |
| DP1 | 8 n'est pas implémenté ; seul le bloc « Imports à vérifier » lit sa table | L'apply attend l'archivage de 8 (tâche 1.1), pour livrer l'entrée entière en une pull request, comme `ROADMAP.md` l'ordonne (8 avant 7). | Livrer d'abord Handicaps et Coups et ajouter le bloc après 8 : deux PR pour une entrée, et un accueil incomplet entre les deux. |
| DP2 | Ordre des joueurs de l'accueil | Par nom, comme Next et la page Joueurs. | Par index croissant (design de 11) : 11 le spécifie et le fera. |
| DP3 | Courbe de l'index et recharts (décision 7 de `docs/PORT_TO_LARAVEL.md`) | Ni courbe ni recharts : l'index après chaque round se lit dans la liste ; le graphique des différentiels de 11, en CSS, remplace la courbe de Next. | Porter la courbe recharts : une dépendance et un composant que 11 retire aussitôt, avant même le cutover, seule date où le groupe voit le port. |
| DP4 | Un joueur à la fois sous 768 px, tous au-delà (Next) | Un joueur à la fois à toute largeur, joueur choisi dans `?joueur=`. | Garder les deux mises en page de Next : 11 garde la colonne du téléphone à toute largeur (sa décision Q3) et supprimerait la seconde. |
| DP5 | Ligne de l'historique : date seule (Next) | Date, marque « Compte », parcours et tees, différentiel, index après, et lien vers le round. | Date seule : 11 affiche le parcours sur cette ligne et mène au round ; la donnée coûte une jointure déjà faite. |
| DP6 | Libellé de la rating sur la page Coups | Celui de la page d'un round (6) : « Rating 71,4 », « Slope 144 », « Sans rating (par 71, slope 113) ». | « sans rating » de Next : un second libellé pour la même information dans l'application. |
| DP7 | Sous-titre de la page Handicaps de Next (« différentiel = score ajusté − par ») | Remplacé par l'explication repliée de 6. | Le porter : il est faux dès qu'une rating est saisie. |
| DP8 | Calcul des coups : serveur ou navigateur, état dans l'adresse ou non | Navigateur, état en mémoire de la page, comme Next ; jeu de cas partagé PHP / TypeScript (D5). | Serveur avec état dans l'adresse : un aller-retour par case cochée. |
| DP9 | Page « Section à venir » une fois les cinq sections servies | Supprimée avec ses routes ; une sous-adresse inconnue de `/handicaps` ou `/strokes` répond 404, comme `/import/xyz` après 8. | La garder pour une section future : aucune n'est prévue, et la spec garderait une clause morte. |
| DP10 | Cible du lien « N imports à vérifier » | L'écran de correction de l'import prêt le plus récent, comme Next. | `/import` (design de 11) : 11 le spécifie et le fera. |
| DP11 | Textes sans équivalent dans Next | « Aucun parcours : enregistrez d'abord un round. » (texte de `/courses`) sur la page Coups sans parcours ; « Aucun joueur. » sur la page Handicaps sans joueur. Les autres textes sont ceux de Next, tutoiement compris (« Choisis 2 à 4 joueurs… »). | Harmoniser le tutoiement de toute l'application : hors périmètre, à trancher par le mainteneur pour 11. |

## Risks / Trade-offs

- [Le handicap de parcours diverge entre PHP et TypeScript] → jeu de cas commun lu par Pest et Vitest (D5) ; une mutation de la formule TypeScript seule fait échouer Vitest, une mutation PHP seule fait échouer Pest.
- [L'accueil et `/rounds` montrent des gagnants différents] → même code `RoundList` (D2) ; un test compare les deux props sur la même base.
- [Les index de trou d'un parcours changent d'un round à l'autre (carte corrigée)] → la page Coups prend ceux du round le plus récent de la paire, la même règle que la saisie (`KnownCourses`) ; une rating s'applique de même à toute la paire.
- [Une prop de plus sur la page Parcours (`strokeIndexes`)] → quelques dizaines d'entiers ; évite une seconde requête des trous.
- [11 doit réconcilier des exigences de ce changement avec son design] → les noms d'exigence ne se recouvrent pas (aucun ADDED en double) ; les écarts connus (ordre des joueurs, cible du lien des imports, mise en page) sont listés par DP2, DP4 et DP10 pour sa tâche 1.2.
- [Rating proposée par SGT (9) prise pour une rating saisie] → 9 n'existe pas encore ; quand il ajoutera des statuts, `CourseOverview` filtrera les ratings confirmées pour la page Parcours, la page Coups et le classement net à la fois.

## Migration Plan

1. Pull request de code : aucune migration ; routes, contrôleurs, Queries, pages. Le déploiement par l'image habituelle suffit.
2. Sur le staging : ouvrir `/`, `/handicaps` et `/strokes` après le déploiement, avec les données déjà là.
3. Retour arrière : annuler la pull request de code et redéployer l'image précédente ; aucune donnée n'est écrite par ce changement.

## Open Questions

Aucune. Les questions tranchées sans le mainteneur sont dans « Décisions par défaut ».
