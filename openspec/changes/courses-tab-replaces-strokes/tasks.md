# Tasks

## 1. Préalables

- [ ] 1.1 Vérifier dans ROADMAP.md que 7, 9 et 11 sont faits, relire proposal, design et les quatre deltas, puis ajouter l'entrée 16 `courses-tab-replaces-strokes` (dépend de 7, 9 et 11, statut « en cours », une phrase de résumé) ; vérifier que l'entrée s'affiche après la 15.

## 2. Section courante (`lib/sections.ts`)

- [ ] 2.1 RED : dans `resources/js/lib/sections.test.ts`, ajouter les cas `inSection('/strokes', '/handicaps') === true`, `inSection('/courses', '/rounds') === false`, `inSection('/courses/rating', '/courses') === true` et `inSection('/coursesx', '/courses') === false` ; `make test-frontend FILES=resources/js/lib/sections.test.ts` échoue sur les deux premiers. Mutation qui les rend rouges : garder `'/rounds': ['/courses']` ou oublier `'/handicaps': ['/strokes']`.
- [ ] 2.2 GREEN : `SECTION_PREFIXES` devient `{ '/import': ['/imports'], '/handicaps': ['/strokes'] }` et son commentaire suit (design D2) ; le test de 2.1 passe.

## 3. Barre d'onglets (`app-nav.tsx`)

- [ ] 3.1 Mesurer dans Chrome la largeur du libellé « Parcours » (Geist 600, 13 px) sur la page lancée par `make dev`, et noter la valeur entière en px.
- [ ] 3.2 RED : dans `resources/js/components/app-nav.test.tsx`, remplacer « Coups » / `/strokes` par « Parcours » / `/courses` dans les listes littérales (onglets et adresses, ordre au clavier, section courante, `aria-current` sur `/courses` pour « Parcours » et non « Rounds », sur `/strokes` pour « Handicaps »), ajouter « Parcours » à `LABEL_WIDTHS` avec la mesure de 3.1 et le cas de largeur à 320 px sur `/courses`, et adapter les listes préchargées (`/courses` au lieu de `/strokes`) ; ajouter l'assertion qu'aucun lien de la barre ne vise `/strokes`. `make test-frontend FILES=resources/js/components/app-nav.test.tsx` échoue.
- [ ] 3.3 GREEN : `TABS` devient Rounds, Handicaps, Parcours (`courses.index`, icône `Flag`), Joueurs ; retirer l'import de `@/routes/strokes` ; le test de 3.2 passe, sans changer les classes de compactage (design D1). Si la somme à 320 px dépasse 288 px, s'arrêter et le signaler au lieu de resserrer les classes.

## 4. Pages

- [ ] 4.1 RED puis GREEN, page Parcours : dans `resources/js/tests/pages/courses.test.tsx`, attendre « Golf » dans la rangée du haut et aucun lien de retour « Rounds » ; puis `PageTopRow` sans `back` dans `resources/js/pages/courses/index.tsx` et retrait de l'import de `roundsIndex` devenu inutile. Mutation : remettre `back` → rouge.
- [ ] 4.2 RED puis GREEN, page Coups : dans `resources/js/tests/pages/strokes.test.tsx`, attendre le lien de retour « Handicaps » vers `/handicaps` et pas de « Golf » ; puis `PageTopRow back={{ href: handicapsIndex.url(), label: 'Handicaps' }}` dans `resources/js/pages/strokes/index.tsx`. Mutation : `PageTopRow` sans `back` → rouge.
- [ ] 4.3 RED puis GREEN, page Handicaps : dans `resources/js/tests/pages/handicaps.test.tsx`, attendre le lien « Coups à donner » d'`href` exactement `/strokes`, avec un joueur choisi et avec la liste vide (« Aucun joueur. ») ; puis le titre et un `Link` `TITLE_LINK` vers `strokes.index()` dans une ligne `flex items-end justify-between` (design D4). Mutations : lien absent sans joueur, ou `?joueur=` ajouté → rouge.
- [ ] 4.4 RED puis GREEN, page Rounds : dans `resources/js/tests/pages/rounds-index.test.tsx`, attendre qu'aucun lien « Parcours » ne s'affiche et que « Saisie manuelle » reste ; puis retirer le lien et l'import de `coursesIndex` de `resources/js/pages/rounds/index.tsx`. Vérifier que `resources/js/tests/pages/rounds-show.test.tsx` garde le lien de la rating vers `/courses` et passe.

## 5. Documentation et changement 15

- [ ] 5.1 Mettre à jour DESIGN.md (barre : « quatre onglets (Rounds, Handicaps, Parcours, Joueurs) », section Coups : sous-page de Handicaps) et ARCHITECTURE.md si une ligne nomme l'onglet Coups ; `grep -n "Coups" DESIGN.md ARCHITECTURE.md` ne montre plus d'onglet Coups.
- [ ] 5.2 Dans `openspec/changes/offline-pwa/specs/offline-mode/spec.md`, reformuler le scénario « Coups à rendre hors ligne » : « il ouvre l'application en mode avion et ouvre `/strokes` » au lieu de « touche l'onglet des coups », sans autre changement ; `openspec validate offline-pwa` passe.
- [ ] 5.3 Ajouter sous `## [Unreleased]` de CHANGELOG.md, en anglais, `### Changed` : « Navigation bar: the « Parcours » tab (`/courses`) replaces « Coups »; the strokes page stays at `/strokes`, opened from the « Coups à donner » link of the Handicaps page, with a « Handicaps » back link. » et « Rounds page: the « Parcours » link next to the title is removed, the tab replaces it. »

## 6. Vérification

- [ ] 6.1 `make test-frontend` puis `make check` passent ; corriger les échecs sans affaiblir de règle.
- [ ] 6.2 Passe navigateur : `make up`, `make dev`, `make browser`, puis le skill `browser-use` sur l'app à 390 et 320 px : barre « Rounds, Handicaps, Parcours, Joueurs » + Importer, libellé « Parcours » entier à 320 px, `/courses` avec « Golf » et l'onglet Parcours courant, lien « Coups à donner » de `/handicaps` vers `/strokes` avec le retour « Handicaps » et l'onglet Handicaps courant, plus de lien « Parcours » sur `/rounds`, lien de rating d'un round vers `/courses`, aucun défilement horizontal ; lire la console. Captures dans `reports/captures/courses-tab-replaces-strokes/`. Finir par `make dev-stop`.
- [ ] 6.3 Relire chaque scénario des quatre deltas et noter, dans le compte rendu, le test ou la capture qui le prouve ; mettre à jour le statut de l'entrée 16 de ROADMAP.md.

## Workflow follow-up

- Archiver le changement une fois les critères d'acceptation vérifiés et `make check` vert.
