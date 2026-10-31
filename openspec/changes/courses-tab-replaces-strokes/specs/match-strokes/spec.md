## MODIFIED Requirements

### Requirement: Mise en page de la page Coups
À 390 px, la page Coups SHALL suivre le design d'une sous-page de Handicaps : lien de retour « Handicaps » vers `/handicaps` dans la rangée du haut, titre « Coups à donner », « JOUEURS · 2 À 4 » et une ligne par joueur, « PARCOURS » et sa liste, la ligne de rating, « MODE » et son sélecteur à deux positions avec son explication, puis un bloc de résultat par joueur choisi.

#### Scenario: Lien de retour
- **WHEN** l'utilisateur ouvre `/strokes` à 390 px
- **THEN** la rangée du haut montre le lien de retour « Handicaps », sans « Golf », et le toucher ouvre `/handicaps`

#### Scenario: Ligne de joueur
- **GIVEN** Gab avec un index de 1,0, choisi, et Sean sans index
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** la ligne de Gab fait 52 px : case de 22 px aux coins de 6 px, pleine `oklch(0.97 0.003 260)` avec la coche, pastille et « Gab » en Geist 600, 15 px, « Index 1,0 » à droite en Geist Mono 500, 13 px, gris ; la ligne de Sean est à l'opacité 0,45, avec « pas encore d'index », et ne se coche pas

#### Scenario: Ligne de rating
- **WHEN** le parcours choisi n'a pas de rating
- **THEN** la ligne sous la liste dit « sans rating (slope 113) », soulignée, en Geist Mono 500, 12 px, gris, et ouvre la page Parcours

#### Scenario: Sélecteur de mode
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** « Écart » et « Complet » se partagent un cadre de 4 px de remplissage, fond `oklch(0.17 0.006 260)`, coins de 12 px ; la position choisie porte un curseur `oklch(0.97 0.003 260)` aux coins de 9 px, texte `oklch(0.13 0.005 260)` ; dessous, « Le meilleur joueur joue scratch, les autres reçoivent la différence. » en mode Écart, « Chaque joueur reçoit son handicap de jeu complet. » en mode Complet

#### Scenario: Pas assez de joueurs
- **WHEN** moins de deux joueurs avec un index sont choisis
- **THEN** « Choisissez au moins 2 joueurs avec un index. » s'affiche dans un cadre en pointillés `oklch(0.27 0.008 260)` aux coins de 12 px, Geist 400, 14 px, gris
