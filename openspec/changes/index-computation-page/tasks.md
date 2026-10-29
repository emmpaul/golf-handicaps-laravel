# Tasks

Chaque tâche de code suit RED, GREEN, REFACTOR : le test d'abord, rouge pour la raison qu'il nomme, puis le code. Valeurs attendues en littéraux de la spec.

## 1. Règle de l'index exposée par `Whs`

- [ ] 1.1 Test unitaire Pest de `Whs::retainedAndAdjustment` : `[1, 0]` pour 1, `[2, 0]` pour 2, `[1, -20]` pour 3, `[1, -10]` pour 4, `[1, 0]` pour 5, `[2, -10]` pour 6, `[2, 0]` pour 8, `[3, 0]` pour 9, `[8, 0]` pour 20 (mutation : retenus de 6 à 1 → rouge). Rendre publiques `whsRule` (renommée `retainedAndAdjustment`, provisoire compris) et `roundHalfUp` sans changer `computeIndex` ; `make test-backend FILTER=Whs` vert, tests de `RecomputePlayerStats` verts.

## 2. Query `PlayerIndexComputation`

- [ ] 2.1 Tests Pest (`tests/Feature/Handicaps/PlayerIndexComputationTest.php`), avec les fabriques et la carte `IMG_3064` des tests existants : rounds du plus récent au plus ancien ; 21 rounds → 20 sans le plus ancien ; `parTotal` 71, `ratingT`/`slope` `null` sans rating, `714`/`144` confirmée, `null` pour une rating seulement proposée ; `gross`/`adjusted` 91/89 avec 10 au trou 1 pour Manu. Mutations nommées : tri inversé, `limit(21)`, jointure sans filtre de rating confirmée.
- [ ] 2.2 Tests de `cappedHole` : trou 1 (10, par 4) ; `null` pour Gab sur `IMG_3064` ; round le plus récent d'abord (trou 1 plutôt que trou 5 d'un round antérieur) ; plus grand dépassement (trou 2 à 10 plutôt que trou 1 à 9). Mutation : tri par numéro de trou seul → rouge.
- [ ] 2.3 Tests de `meanT` et `adjustmentT` sur les scénarios de l'étape 05 : 6 rounds → 130 et −10 ; 3 rounds → 120 et −20 ; 5 rounds → 120 et 0 ; 2 rounds → 145 et 0 ; plafond 63,0/70,0/65,0 → 630 et −20 ; joueur sans round → `null` et 0.
- [ ] 2.4 Implémenter la query (design D2, jointure de D3) ; test de cohérence de D3 : pour chaque round, `Whs::differentialTenths` recalculé avec la rating renvoyée égale `differentialT` enregistré. `make test-backend FILES=tests/Feature/Handicaps/PlayerIndexComputationTest.php` vert.

## 3. Route et contrôleur

- [ ] 3.1 Tests Pest (`tests/Feature/Handicaps/IndexComputationPageTest.php`) : `GET /handicaps/calcul` → 200, composant `handicaps/computation`, Gab choisi ; `?joueur=<Manu>` ; identifiant inconnu et `?joueur=12` → 200 sur Gab ; base sans joueur → `computation` `null` ; sans session → redirection `/login` sans nom ni score dans la réponse ; `/handicaps/<uuid>` toujours 404. Ajouter la route à `ProtectedRoutesTest`.
- [ ] 3.2 Ajouter la route `handicaps.computation` et `HandicapController::computation` (design D1, D4), choix du joueur extrait et partagé avec `index` ; régénérer Wayfinder ; `make test-backend FILES=tests/Feature/Handicaps` vert, `HandicapsPageTest` compris.

## 4. Tokens du design

- [ ] 4.1 Étendre `theme-tokens.test.ts` (littéraux `oklch(0.88 0.005 260)`, 15 px / 1,45 / 400, 36 px / −0,04 em, 20 px / −0,03 em), `theme-contrast.test.ts` (`ink-soft` sur `bg` et `surface-1`, avec son hex) et `utils.test.ts` (`cn` garde `text-prose`, `text-num-36`, `text-num-20` avec une couleur) ; rouges, puis ajouter les tokens à `resources/css/tokens.css` et à `cn` (design D7). `make test-frontend` vert.
- [ ] 4.2 Mettre à jour `DESIGN.md` : tableau des tokens (`ink-soft`), échelle typographique (trois styles), composants Handicaps (`computation/*`, `ComputationLink`), écarts de D8.

## 5. Lien sur les quatre pages

- [ ] 5.1 Réécrire `computation-help.test.tsx` pour `ComputationLink` : lien « Comment l’index est calculé » vers `/handicaps/calcul`, avec `?joueur=<id>` quand le joueur est donné ; aucune des sept lignes rendue. Exporter `RULE_LINES` et tester son contenu exact contre les sept littéraux de « Texte exact des lignes », dans l'ordre des étapes. Mutation : ligne réordonnée ou `?joueur` perdu → rouge.
- [ ] 5.2 Remplacer `ComputationHelp` par `ComputationLink` dans `players/index`, `rounds/show`, `courses/index` et `handicaps/index` (joueur choisi passé sur Handicaps) ; mettre à jour leurs tests de page (`players`, `rounds-show`, `courses`, `handicaps`) : le lien présent, les lignes absentes. `make test-frontend` vert.
- [ ] 5.3 Paramétrer `PlayerTabs` (`hrefFor`, `only`, design D5) ; le test de la page Handicaps garde ses liens `?joueur=` et son `only`.

## 6. Page « Calcul de l'index »

- [ ] 6.1 Test Vitest de la page (`resources/js/tests/pages/handicaps-computation.test.tsx`) pour l'en-tête : retour « Handicaps » vers `/handicaps?joueur=<id>`, titre, onglets vers `/handicaps/calcul?joueur=`, « Chaque étape avec les rounds de Gab. », « Aucun joueur. » sans onglets ; sept lignes dans l'ordre de « Texte exact des lignes ». Puis la page `handicaps/computation.tsx` et `step-heading`.
- [ ] 6.2 Étape 01 : tests « Trou plafonné » (« TROU 1 · P4 », « 10 » barré, « 8 », « 89 / 91 ») et « Aucun trou plafonné » ; puis `adjusted-score-step`.
- [ ] 6.3 Étape 02 : tests sans rating (« 113 », « 113 », « 72 », « 71 », « BETHPAGE BLACK · 17 OCT », « 1,0 ») et avec rating (« 144 », « 88 », « 71,4 », « 13,0 ») ; puis `differential-step`.
- [ ] 6.4 Étape 03 : tests de l'escalier (marche 3 → « 1 » et « 3 → 1 RETENU » ; « 6 → 2 RETENUS » ; « 2 → 2 RETENUS » ; « 20 → 8 RETENUS ») et de la constante des 20 marches contre la ligne de règle ; tests de la liste (« 20 DERNIERS ROUNDS · 6 », ordre, « Retenu » sur 12,0 et 14,0 seulement, « 72 ajusté · par 71 / 113 », « 72 ajusté · 71,4 / 144 ») ; puis `retained-step`.
- [ ] 6.5 Étape 04 : tests (seule « 3 ROUNDS » appliquée pour 3 rounds, seule « AUTRES » pour 5 et pour 2) ; puis `adjustment-step`.
- [ ] 6.6 Étape 05 : tests des cinq scénarios (« Moyenne des 2 meilleurs (12,0 · 14,0) », « 13,0 », « −1,0 », « INDEX GAB », « 12,0 » ; « Meilleur différentiel » ; « aucun » ; « Moyenne des 2 différentiels (12,0 · 17,0) » ; « 54,0 ») ; puis `index-step`.
- [ ] 6.7 Cas particuliers et joueur sans round : tests « Ex. BETHPAGE BLACK → rating 71 · slope 113 », absence de « Ex. » tous parcours notés, Sean sans round (lignes, phrase, aucune case ni marche en couleur, ni « DERNIER ROUND », ni « 20 DERNIERS ROUNDS », ni « INDEX SEAN ») ; puis `special-cases` et le cas sans round.
- [ ] 6.8 Test de la barre d'onglets : Handicaps porte `aria-current="page"` sur `/handicaps/calcul`. `make test-frontend` vert, `theme-sources`, `tabular-figures` et `reduced-motion` compris.

## 7. Seeder, journal, feuille de route

- [ ] 7.1 Vérifier que la base de démonstration donne un exemple complet : un joueur avec un trou plafonné, un round sans rating et un avec rating ; compléter le seeder de démonstration sinon, et `make test-backend FILES=tests/Feature/Seeders` vert.
- [ ] 7.2 `CHANGELOG.md`, sous `## [Unreleased]` : `### Added` la page du calcul de l'index, `### Changed` le lien à la place de l'explication repliée. `ROADMAP.md` : entrée 12 `index-computation-page`, dépend de 6, 9 et 11.

## 8. Vérification d'ensemble

- [ ] 8.1 `make format`, `make lint`, puis `make check` vert ; lire `reports/check/<étape>.log` en cas d'échec.
- [ ] 8.2 Passe navigateur : `make up`, `make dev`, `make browser`, skill `browser-use` sur `http://localhost/handicaps/calcul` à 390 et 320 px, pour chaque joueur de la démonstration ; lien depuis les quatre pages ; console sans erreur ; captures sous `reports/captures/index-computation-page/` ; `make dev-stop`.
- [ ] 8.3 Relire chaque scénario des deltas `handicap-index` et `rounds` et noter le test ou la capture qui le prouve.

## Workflow follow-up

- Archiver le changement une fois la PR de code fusionnée et les critères vérifiés.
