# Spec Delta

## Purpose

Calculer avant un match les coups que chaque joueur reçoit sur un parcours et leur répartition par trou, et les montrer sur la page Coups.

## ADDED Requirements

### Requirement: Mise en page de la page Coups
À 390 px, la page Coups SHALL suivre le design : titre « Coups à donner », « JOUEURS · 2 À 4 » et une ligne par joueur, « PARCOURS » et sa liste, la ligne de rating, « MODE » et son sélecteur à deux positions avec son explication, puis un bloc de résultat par joueur choisi. Cette exigence est réconciliée par la tâche 1.2 avec celles que l'entrée 7 écrit pour cette page.

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
- **THEN** la page montre, dans un cadre en pointillés aux coins de 12 px, « Choisissez au moins 2 joueurs avec un index. »

### Requirement: Bloc de résultat des coups
Chaque joueur choisi SHALL avoir un bloc sous un filet : pastille et nom, « reçoit {n} coups », « reçoit 1 coup » ou « ne reçoit aucun coup » en Geist 400, 13 px, gris, le nombre à droite en Geist Mono 500, 48 px, puis, s'il reçoit des coups, une grille de 9 colonnes et 2 rangées de cases de 44 px, une par trou.

#### Scenario: Joueur qui reçoit des coups
- **GIVEN** Manu reçoit 16 coups sur un parcours dont les index 17 et 18 sont aux trous 3 et 14
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** le bloc de Manu dit « reçoit 16 coups » et « 16 » ; chaque case porte son numéro de trou en Geist Mono 500, 9 px, gris ; les cases des trous qui reçoivent un coup ont le fond `oklch(0.22 0.006 260)` et « 1 » en Geist Mono 600, 15 px, à la couleur de Manu ; les trous 3 et 14 ont un fond transparent et « · » en `oklch(0.4 0.01 260)`

#### Scenario: Joueur scratch
- **WHEN** Gab est le meilleur joueur en mode Écart
- **THEN** son bloc dit « ne reçoit aucun coup » et « 0 », sans grille
