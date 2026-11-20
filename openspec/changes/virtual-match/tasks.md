# Tasks

## 1. Préalables

- [x] 1.1 Relire proposal, design et les trois deltas. Dans `ROADMAP.md`, ajouter sous « Usage quotidien » l'entrée 25 `virtual-match` (dépend du classement net d'un round et des ratings, archivés ; statut « en cours » ; une phrase de résumé), et sous l'entrée 21 la ligne « Un tournoi pourrait accepter des rounds de jours différents, en partant de la partie virtuelle (25). ». Vérifier que l'entrée 25 s'affiche après la 24.

## 2. Rang de compétition (`NetRanking`, design D2)

- [x] 2.1 RED : dans `tests/Unit/Handicaps/NetRankingTest.php`, ajouter les cas de `NetRanking::competitionRanks()` : `[80, 70, 59, 70]` → `[4, 2, 1, 2]`, `[70, null, 70]` → `[1, null, 1]`, `[]` → `[]`. `make test-backend FILES=tests/Unit/Handicaps/NetRankingTest.php` échoue (la méthode n'existe pas). Mutation qui rend le premier cas rouge une fois en place : un rang dense (`[3, 2, 1, 2]`).
- [x] 2.2 GREEN puis REFACTOR : écrire `competitionRanks()` et faire calculer le rang de `rank()` par elle. Le test de 2.1 et les tests existants de `NetRanking` et de `RoundLeaderboard` passent sans modification.

## 3. Lecture de la partie virtuelle (`VirtualMatch`, design D3)

- [x] 3.1 RED : `tests/Feature/Handicaps/VirtualMatchTest.php` construit par les fabriques les rounds du scénario « Trois joueurs, deux jours » (BETHPAGE BLACK / Red à 71,4 / 144, Gab à 1,0 seul le 2026-11-18 avec 74, Manu à 17,4 et Sean à 12,5 le 2026-11-19 avec 88 et 84). Il attend en littéraux : rangs 1, 2, 3 pour Manu (23, 65), Sean (16, 68), Gab (2, 72) ; la liste d'ajout du plus récent au plus ancien avec « seul » / partenaires ; l'ex æquo dans l'ordre du choix ; le joueur sans index après les classés, avec `net` et `rank` à `null`. Mutation nommée : l'index actuel au lieu de `index_before_t` donne 72 → autre net pour Gab dans le cas « Index changé depuis ».
- [x] 3.2 RED : dans le même fichier, les filtres. Un choix vers un round supprimé, vers d'autres tees (« BETHPAGE BLACK / Blue ») ou vers un joueur absent du round est ignoré. Un deuxième choix du même joueur est ignoré. La casse et les espaces de « Bethpage Black / red » sont acceptés. Un round au par 5 au trou 1 donne `holesDiffer = true` et les pars du round le plus récent, des cartes identiques `false`. Sur un parcours sans rating, un round au par 72 et un au par 71 gardent chacun le handicap de jeu de la page de leur round (index arrondi). Mutation nommée : un `$ratingT` commun tiré du round le plus récent décale d'un coup le handicap de l'autre round.
- [x] 3.3 GREEN : écrire la valeur `App\Domain\Handicaps\Pick` et la Query `App\Domain\Handicaps\Queries\VirtualMatch` (trois requêtes, rating lue une fois, par de chaque round). Les tests de 3.1 et 3.2 passent. Ajouter un cas qui compte les requêtes (`DB::getQueryLog`) pour cinq joueurs-rounds : 3 requêtes, pas de N+1.
- [ ] 3.4 Vérifier que `tests/Arch/ArchitectureTest.php` passe (`make arch-test`) : la Query n'utilise ni `App\Http` ni Inertia.

## 4. Suggestion (`VirtualMatchSuggestion`, design D3)

- [ ] 4.1 RED : `tests/Feature/Rounds/VirtualMatchSuggestionTest.php` couvre en littéraux les scénarios de la spec :
    - ami seul la veille ;
    - plusieurs joueurs, triés par date ;
    - round le plus proche (le 2026-11-21 plutôt que le 2026-11-14 pour un round du 2026-11-19), puis le plus récent à égalité d'écart (le 2026-11-21 plutôt que le 2026-11-17) ;
    - hors de la fenêtre (2026-11-11), avec la borne de 7 jours incluse (2026-11-12) ;
    - joueur déjà sur la carte ;
    - autres tees.

  Mutation nommée : `< 7` au lieu de `<= 7` rend rouge le cas du 2026-11-12.
- [ ] 4.2 GREEN : écrire la Query (une requête, `distinct on (player_id)`). Les tests de 4.1 passent.

## 5. Route et contrôleur (design D4, D6)

- [ ] 5.1 RED : `tests/Feature/Rounds/RoundComparePageTest.php` couvre :
    - sans session, `GET /rounds/compare` redirige vers `/login` sans nom dans la réponse ;
    - `/rounds/compare/xyz` répond 404 ;
    - sans paramètre, la page `rounds/compare` reçoit le premier parcours connu et aucun choix ;
    - `picks` mal formés ou d'un autre parcours sont ignorés sans 422 ;
    - l'ordre des `picks` est rendu tel quel ;
    - aucune table ne change, en comptant les lignes avant et après sur toutes les tables du domaine ;
    - une base sans round donne une liste de parcours vide.

  Dans `tests/Feature/Rounds/RoundPagesTest.php`, la prop `virtualMatch` porte les suggérés et `picks` (joueurs de la carte puis suggérés).
- [ ] 5.2 GREEN : ajouter `CompareRoundsRequest`, `RoundController::compare`, la route nommée `rounds.compare` dans `routes/web.php` et la prop `virtualMatch` dans `RoundController::show`. Lancer `make wayfinder`. Les tests de 5.1 et `tests/Feature/ProtectedRoutesTest.php` passent, sans route ouverte de plus.

## 6. Front : textes et composants (design D7)

- [ ] 6.1 RED puis GREEN : `resources/js/lib/virtual-match.test.ts` pour `winnerSummary()`, avec les cas suivants. Mutation nommée : le pluriel sans test d'égalité à 1 donne « 1 coups ».

  | Classement | Texte attendu |
  | --- | --- |
  | Manu 65, Sean 68 | « 65 net · 3 coups devant Sean » |
  | Manu 65, Sean 66 | « 1 coup devant Sean » |
  | Manu 65, Sean 68, Gab 68 | « 3 coups devant Sean et Gab » |
  | Gab et Manu à 70 | « ÉGALITÉ », « Gab et Manu », « 70 net » |
  | moins de 2 classés | `null` |

- [ ] 6.2 RED puis GREEN : `net-leaderboard.tsx` et `round-scorecard.tsx` gagnent la date facultative, et la carte défile dans son cadre au-delà de la largeur. Leurs tests existants passent sans changement, et un nouveau cas vérifie la date sous « 88 − 23 » et sous « MANU ».
- [ ] 6.3 RED puis GREEN : `resources/js/components/rounds/virtual-match-picks.test.tsx`. Une pastille porte le nom accessible « Retirer Gab, 18 nov. 2026 », et la toucher appelle `router.get` sans ce choix. « + Ajouter » ouvre la liste, une ligne « déjà choisi » est désactivée, toucher une ligne ajoute à la fin et ferme la liste, fermer n'ajoute rien.
- [ ] 6.4 RED puis GREEN : `resources/js/components/rounds/virtual-match-suggestion.test.tsx` vérifie « Gab (18 nov.) a joué ce parcours. », « Gab (18 nov.) et Sean (20 nov.) ont joué ce parcours. », le lien « Voir la partie virtuelle » vers `/rounds/compare?…picks=…`, aucun bouton « Ignorer », et sans suggéré le lien « Comparer avec d'autres rounds ».

## 7. Front : pages

- [ ] 7.1 RED puis GREEN : `resources/js/tests/pages/rounds-compare.test.tsx` vérifie, avec des props littérales :
    - le lien de retour « Rounds » ;
    - la liste des parcours et la ligne de rating vers `/courses` ;
    - « Choisissez au moins 2 joueurs. » sans encadré ni bouton de carte quand moins de deux joueurs sont choisis ;
    - l'encadré « AURAIT GAGNÉ » ;
    - le classement avec dates ;
    - « Voir la carte combinée » qui déplie la carte sans requête, puis « Masquer la carte combinée » ;
    - la note « Les pars ou les index de trou diffèrent entre ces rounds. » quand `holesDiffer` ;
    - un changement de parcours qui visite l'adresse sans `picks` ;
    - « Aucun parcours : enregistrez d'abord un round. ».

  Écrire `resources/js/pages/rounds/compare.tsx` avec les tokens seulement.
- [ ] 7.2 RED puis GREEN : dans `resources/js/tests/pages/rounds-index.test.tsx`, le lien « Comparer » vers `/rounds/compare` précède « Saisie manuelle », et aucun lien « Parcours » n'apparaît. Dans `rounds-show.test.tsx`, l'encadré de suggestion ou le lien de comparaison se place entre le classement et la carte. Modifier `rounds/index.tsx` et `rounds/show.tsx`.

## 8. Démonstration (design D8)

- [ ] 8.1 RED : dans `tests/Feature/Seeders/DemoDatabaseSeederTest.php`, attendre les deux rounds « PAYNES VALLEY / Blue » du 2026-10-24 (Gab seul) et du 2026-10-25 (Manu et Sean) avec leurs bruts littéraux, la suggestion de Gab sur le second, et une partie virtuelle de trois joueurs classés. Réécrire à la main les littéraux que ces rounds changent : 27 rounds, dernier round, deux premières lignes de l'historique de Manu, `PlayerStandings`. Ne rien recalculer par le code testé. Le test échoue.
- [ ] 8.2 GREEN : ajouter ces deux rounds à scores fixes dans `DemoRoundsSeeder`, après le round de référence. Le test de 8.1 passe, ainsi que le scénario « Jeu déterministe », qui lance le seeder deux fois.

## 9. Documentation

- [ ] 9.1 `ARCHITECTURE.md` :
    - dans « Joueurs et rounds », ajouter `VirtualMatchSuggestion` aux Queries de `Rounds` ;
    - dans « Lectures », une ligne sur `VirtualMatch` (net par joueur-round sur le par de son round, `competitionRanks()`, choix dans l'adresse, aucune écriture).

  `grep -n "VirtualMatch" ARCHITECTURE.md` montre les deux lignes.
- [ ] 9.2 `CHANGELOG.md`, sous `## [Unreleased]` :
    - `### Added` : « Virtual match page (`/rounds/compare`): pick player rounds of one course and tees played on different days and see who would have won on net, without saving anything. »
    - `### Added` : « Round page: suggests players who played the same course and tees within 7 days, with a link to the virtual match. »
    - `### Added` : « Demo data: a solo round and a two-player round the next day on PAYNES VALLEY / Blue. »
    - `### Changed` : « Rounds page: a « Comparer » link next to « Saisie manuelle ». »

## 10. Vérification

- [ ] 10.1 `make format`, `make lint`, `make test-backend`, `make test-frontend`, puis `make check` passent. Après un échec, lire `reports/check/<étape>.log` et corriger sans affaiblir de règle.
- [ ] 10.2 Passe navigateur : `make up`, `make demo`, `make dev`, `make browser`, puis le skill `browser-use` sur `http://localhost`, à 390 et 320 px. Vérifier :
    - le round PAYNES VALLEY du 2026-10-25 et son encadré de suggestion de Gab ;
    - le lien vers la partie virtuelle à trois et l'encadré du gagnant ;
    - le retrait et l'ajout d'une pastille, avec l'adresse mise à jour ;
    - la carte combinée dépliée puis repliée ;
    - le lien « Comparer » de `/rounds` ;
    - aucun défilement horizontal de la page.

  Lire la console, ranger les captures dans `reports/captures/virtual-match/` et finir par `make dev-stop`.
- [ ] 10.3 Relire chaque scénario des trois deltas et noter, dans le compte rendu, le test ou la capture qui le prouve. Mettre à jour le statut de l'entrée 25 de `ROADMAP.md`.

## Workflow follow-up

- Archiver le changement une fois les critères d'acceptation vérifiés et `make check` vert, dans une pull request d'archivage séparée.
