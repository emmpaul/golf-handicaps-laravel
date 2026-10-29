# match-strokes Specification

## Purpose
Avant une partie, dire à chaque joueur combien de coups il reçoit et sur quels trous, à partir des index actuels, de la rating et des index de trou d'un parcours déjà connu.

## Requirements

### Requirement: Page Coups protégée et sans écriture
La page Coups SHALL exiger la session du code d'accès : sans session, `/strokes` redirige vers `/login`. Préparer une partie MUST NOT écrire quoi que ce soit ni envoyer de requête : le choix des joueurs, du parcours et du mode reste dans la page et se perd en la quittant.

#### Scenario: Sans session
- **GIVEN** aucune session ouverte
- **WHEN** le navigateur demande `/strokes`
- **THEN** le système redirige vers `/login`, et la réponse ne contient ni nom de joueur ni parcours

#### Scenario: Aucune requête au choix
- **WHEN** l'utilisateur coche Gab et Manu, choisit un parcours puis passe en mode « Complet »
- **THEN** les coups s'affichent sans aucune requête au serveur, et la base ne change pas

#### Scenario: Sous-adresse inconnue
- **WHEN** l'utilisateur ouvre `/strokes/xyz`
- **THEN** le système répond 404

### Requirement: Préparation d'une partie
Le système SHALL permettre de choisir 2 à 4 joueurs qui ont un index, provisoire ou non, et un parcours connu (nom et tees), parmi les joueurs du groupe par nom et les parcours connus par nom puis tees. La liste des parcours MUST s'ouvrir sur le premier parcours connu, comme le design. Quatre joueurs choisis, les autres ne se cochent plus. Tant que moins de deux joueurs avec un index sont choisis, la page affiche « Choisissez au moins 2 joueurs avec un index. » à la place des coups.

#### Scenario: Sélection valide
- **WHEN** l'utilisateur choisit Gab, Manu et « BETHPAGE BLACK / Red »
- **THEN** le système affiche les coups reçus par Gab et par Manu

#### Scenario: Parcours choisi à l'ouverture
- **GIVEN** les parcours « BETHPAGE BLACK / Red » et « PAYNES VALLEY / Blue »
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** la liste des parcours montre « BETHPAGE BLACK / Red »

#### Scenario: Ligne de joueur
- **GIVEN** Manu avec un index de 17,4
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** la ligne de Manu affiche « Manu » et « Index 17,4 »

#### Scenario: Cinquième joueur
- **GIVEN** cinq joueurs avec un index, dont quatre cochés
- **WHEN** l'utilisateur essaie de cocher le cinquième
- **THEN** sa case reste décochée ; décocher l'un des quatre la rend de nouveau cochable

#### Scenario: Un seul joueur
- **WHEN** l'utilisateur coche Gab seul
- **THEN** la page affiche « Choisissez au moins 2 joueurs avec un index. » et aucun coup

#### Scenario: Aucun parcours connu
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** la page affiche « Aucun parcours : enregistrez d'abord un round. » à la place de la liste des parcours

### Requirement: Joueur sans index dans la préparation
Sur la page Coups, un joueur sans index MUST être affiché grisé sans pouvoir être choisi, avec « pas encore d'index ».

#### Scenario: Joueur sans index
- **GIVEN** Sean n'a encore aucun round
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** Sean apparaît grisé avec « pas encore d'index » et ne peut pas être coché

#### Scenario: Index provisoire
- **GIVEN** Sean n'a joué qu'un round
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** Sean peut être coché


### Requirement: Modes de calcul des coups
Le système SHALL proposer deux modes. En mode « Écart », le mode par défaut, le joueur au plus petit handicap de jeu reçoit 0 coup et chaque autre joueur reçoit la différence entre son handicap de jeu et ce minimum. En mode « Complet », chaque joueur reçoit son handicap de jeu entier.

#### Scenario: Mode Écart
- **GIVEN** Gab a un index de 1,0 et Manu de 17,4, sur un parcours sans rating
- **WHEN** l'utilisateur les choisit sans changer de mode
- **THEN** Gab reçoit 0 coup et Manu en reçoit 16

#### Scenario: Mode Complet
- **GIVEN** Gab a un index de 1,0 et Manu de 17,4, sur un parcours sans rating
- **WHEN** l'utilisateur passe en mode « Complet »
- **THEN** Gab reçoit 1 coup et Manu en reçoit 17

#### Scenario: Mode Écart avec rating
- **GIVEN** Gab a un index de 1,0 et Manu de 17,4 sur « BETHPAGE BLACK / Red » (rating 71,4, slope 144, par 71)
- **WHEN** l'utilisateur les choisit en mode « Écart »
- **THEN** Gab reçoit 0 coup et Manu en reçoit 21

#### Scenario: Mode Complet avec rating
- **GIVEN** Gab a un index de 1,0 et Manu de 17,4 sur « BETHPAGE BLACK / Red » (rating 71,4, slope 144, par 71)
- **WHEN** l'utilisateur les choisit en mode « Complet »
- **THEN** Gab reçoit 2 coups et Manu en reçoit 23

#### Scenario: Trois joueurs en Écart
- **GIVEN** Sean à 12,5, Gab à 1,0 et Manu à 17,4, sur un parcours sans rating
- **WHEN** l'utilisateur les choisit en mode « Écart »
- **THEN** Sean reçoit 12 coups, Gab 0 et Manu 16

### Requirement: Handicap de jeu sur le parcours choisi
Le handicap de jeu d'un joueur sur la page Coups SHALL être le handicap de parcours de son index actuel : index × slope / 113 + (course rating − par total du parcours), arrondi à l'entier le plus proche (0,5 vers le haut), avec la rating saisie du parcours et des tees choisis, comme sur la page d'un round (spec `rounds`, « Handicap de jeu d'un round »). Sans rating, c'est l'index actuel arrondi à l'entier.

#### Scenario: Avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144, par 71
- **WHEN** Gab à 1,0, Manu à 17,4 et un joueur à −2,0 y sont choisis en mode « Complet »
- **THEN** leurs handicaps de jeu valent 2, 23 et −2

#### Scenario: Sans rating
- **WHEN** Gab à 1,0, Manu à 17,4 et Sean à 12,5 sont choisis en mode « Complet » sur un parcours sans rating
- **THEN** leurs handicaps de jeu valent 1, 17 et 13

### Requirement: Répartition des coups par trou
Pour S coups reçus, le système SHALL donner à chaque trou ⌊S / 18⌋ coups, plus 1 coup sur chaque trou dont l'index est inférieur ou égal à S modulo 18. Les index de trou sont ceux du round le plus récent sur ce parcours et ces tees (spec `rounds`, « Parcours connus »). Il SHALL afficher, par joueur, le total de coups et le nombre de coups sur chaque trou.

#### Scenario: 16 coups sur BETHPAGE BLACK / Red
- **GIVEN** BETHPAGE BLACK / Red a les index 8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15
- **WHEN** Manu reçoit 16 coups sur ce parcours
- **THEN** Manu reçoit 1 coup sur tous les trous sauf les trous 3 et 14

#### Scenario: Un seul coup
- **WHEN** Gab reçoit 1 coup sur BETHPAGE BLACK / Red
- **THEN** son coup tombe sur le trou 15 (index 1)

#### Scenario: Plus de 18 coups
- **WHEN** un joueur reçoit 20 coups sur BETHPAGE BLACK / Red
- **THEN** il reçoit 1 coup sur chaque trou et 2 coups sur les trous 15 et 4 (index 1 et 2)

#### Scenario: 36 coups
- **WHEN** un joueur reçoit 36 coups sur BETHPAGE BLACK / Red
- **THEN** il reçoit 2 coups sur chacun des 18 trous

### Requirement: Handicap de jeu négatif
En mode « Complet », un joueur au handicap de jeu négatif −S SHALL rendre 1 coup sur chacun des S trous aux index les plus élevés (18, puis 17, etc.). Son bloc le montre en coups rendus : « rend {S} coups » (« rend 1 coup » pour un seul) à côté de son nom, et « −1 » dans la case de chaque trou concerné.

#### Scenario: Joueur plus 2
- **GIVEN** un joueur à −1,6 et Gab à 1,0, sur BETHPAGE BLACK / Red sans rating
- **WHEN** l'utilisateur les choisit en mode « Complet »
- **THEN** le bloc du premier dit « rend 2 coups », avec « −1 » sur le trou 3 (index 18) et sur le trou 14 (index 17), et celui de Gab « reçoit 1 coup »

#### Scenario: Joueur plus en Écart
- **GIVEN** un joueur à −1,6 et Gab à 1,0, sur un parcours sans rating
- **WHEN** l'utilisateur les choisit en mode « Écart »
- **THEN** le premier ne reçoit aucun coup et Gab en reçoit 3

### Requirement: Total des coups d'un joueur
Pour chaque joueur choisi, le bloc de résultat de la page Coups SHALL donner son nom puis son total : « reçoit {n} coups », « reçoit 1 coup », ou « ne reçoit aucun coup », et le nombre de coups en grand. Un joueur qui ne reçoit aucun coup MUST NOT avoir de grille.

#### Scenario: Pluriel
- **WHEN** Manu reçoit 16 coups
- **THEN** son bloc affiche « Manu », « reçoit 16 coups » et « 16 »

#### Scenario: Singulier
- **WHEN** Gab reçoit 1 coup
- **THEN** son bloc affiche « Gab » et « reçoit 1 coup »

#### Scenario: Le meilleur joueur en mode « Écart »
- **GIVEN** Gab (index 0,6), Manu et Sean choisis en mode « Écart »
- **WHEN** les coups s'affichent
- **THEN** le bloc de Gab affiche « Gab », « ne reçoit aucun coup » et « 0 », et aucune grille

### Requirement: Grille des coups par trou
Pour chaque joueur qui reçoit ou rend des coups, la page Coups SHALL afficher une grille de 18 cases, trous 1 à 9 puis 10 à 18, chaque case portant le numéro du trou et son nombre de coups, et annoncée aux lecteurs d'écran (« Trou 3 : aucun coup », « Trou 15 : 2 coups »). Une case sans coup MUST afficher « · », caché aux lecteurs d'écran ; une case à 2 coups ou plus MUST afficher ce nombre. À 320 px, les deux rangées de 9 cases MUST tenir sans défilement horizontal.

#### Scenario: 16 coups sur BETHPAGE BLACK / Red
- **WHEN** Manu reçoit 16 coups sur BETHPAGE BLACK / Red
- **THEN** sa grille marque « 1 » sur chaque trou sauf les trous 3 et 14, dont les cases montrent « · » et sont annoncées « Trou 3 : aucun coup » et « Trou 14 : aucun coup »

#### Scenario: Plus de 18 coups
- **WHEN** un joueur reçoit 20 coups sur BETHPAGE BLACK / Red
- **THEN** les cases des trous 15 et 4 affichent « 2 » et les 16 autres « 1 »

#### Scenario: Grille à 320 px
- **WHEN** la page Coups montre la grille de Manu sur un écran de 320 px
- **THEN** les trous 1 à 9 et 10 à 18 sont visibles sur deux rangées, sans défilement horizontal

### Requirement: Rating du parcours choisi
La page Coups SHALL afficher, sous la liste des parcours, la course rating et le slope saisis pour le parcours et les tees choisis, « rating 71,4 · slope 144 », ou « sans rating (slope 113) ». Cette ligne MUST mener à la page Parcours. Une rating saisie ou retirée sur `/courses` change les coups à la visite suivante de `/strokes`.

#### Scenario: Parcours avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** l'utilisateur choisit « BETHPAGE BLACK / Red »
- **THEN** la page affiche « rating 71,4 · slope 144 » dans un lien vers `/courses`

#### Scenario: Parcours sans rating
- **GIVEN** « PAYNES VALLEY / Blue », par 72, sans rating
- **WHEN** l'utilisateur choisit « PAYNES VALLEY / Blue »
- **THEN** la page affiche « sans rating (slope 113) » dans un lien vers `/courses`, et les coups se calculent depuis l'index arrondi

#### Scenario: Rating saisie entre deux visites
- **GIVEN** Gab à 1,0 et Manu à 17,4, « BETHPAGE BLACK / Red » sans rating : Manu reçoit 16 coups en mode « Écart »
- **WHEN** l'utilisateur saisit 71,4 et 144 sur `/courses`, puis rouvre `/strokes` avec les mêmes choix
- **THEN** Manu reçoit 21 coups

### Requirement: Mise en page de la page Coups
À 390 px, la page Coups SHALL suivre le design : titre « Coups à donner », « JOUEURS · 2 À 4 » et une ligne par joueur, « PARCOURS » et sa liste, la ligne de rating, « MODE » et son sélecteur à deux positions avec son explication, puis un bloc de résultat par joueur choisi.

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

### Requirement: Bloc de résultat des coups
Chaque joueur choisi SHALL avoir un bloc sous un filet : pastille et nom, son total en Geist 400, 13 px, gris, le nombre à droite en Geist Mono 500, 48 px, puis, s'il reçoit ou rend des coups, une grille de 9 colonnes et 2 rangées de cases de 44 px, une par trou.

#### Scenario: Joueur qui reçoit des coups
- **GIVEN** Manu reçoit 16 coups sur un parcours dont les index 17 et 18 sont aux trous 3 et 14
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** chaque case du bloc de Manu porte son numéro de trou en Geist Mono 500, 9 px, gris ; les cases des trous qui reçoivent un coup ont le fond `oklch(0.22 0.006 260)` et « 1 » en Geist Mono 600, 15 px, à la couleur de Manu ; les trous 3 et 14 ont un fond transparent et « · » en `oklch(0.4 0.01 260)`

#### Scenario: Joueur scratch
- **WHEN** Gab est le meilleur joueur en mode Écart
- **THEN** son bloc dit « ne reçoit aucun coup » et « 0 », sans grille
