# Spec Delta

## MODIFIED Requirements

### Requirement: Liste des rounds
La page Rounds SHALL lister les rounds du plus récent au plus ancien par date de jeu, puis par date d'enregistrement. Chaque ligne montre la date, le parcours, les tees, les joueurs avec leur brut dans l'ordre de la carte, et le ou les gagnants nets, et mène à la page du round. Des gagnants ex æquo sont tous nommés ; un round où personne n'a d'index montre « — ». La page donne un lien « Parcours » vers la page Parcours. Sans round, la page affiche « Aucun round enregistré. ».

#### Scenario: Ordre de la liste
- **GIVEN** des rounds joués le 2026-10-10 et le 2026-10-17
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** le round du 2026-10-17 apparaît en premier

#### Scenario: Ligne de la liste
- **GIVEN** le round de référence
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne montre « 17 octobre 2026 », « BETHPAGE BLACK », « Red », « Gab 72 » et « Manu 88 », sans « · » entre le parcours et les tees

#### Scenario: Gagnants nets ex æquo
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,0 avant la carte `IMG_3064`, sans rating
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne de la carte `IMG_3064` montre « Gab, Manu » comme gagnants nets

#### Scenario: Personne n'a d'index
- **GIVEN** la carte `IMG_3064`, premier round de Gab et de Manu
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne montre « — » comme gagnant net

#### Scenario: Lien vers les parcours
- **WHEN** l'utilisateur touche « Parcours » sur `/rounds`
- **THEN** la page Parcours s'ouvre

#### Scenario: Aucun round
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la page affiche « Aucun round enregistré. » et le lien « Saisie manuelle »

## ADDED Requirements

### Requirement: Classement net d'un round
Pour chaque joueur d'un round, dans l'ordre de la carte, la page du round SHALL afficher le brut, le handicap de jeu, le net et le rang net. Le net vaut brut moins handicap de jeu. Le rang est un rang de compétition : des nets égaux partagent le même rang, et le rang suivant saute les places partagées.

#### Scenario: Rang après des ex æquo
- **GIVEN** quatre joueurs dont les nets valent 80, 70, 59 et 70
- **WHEN** l'utilisateur ouvre le round
- **THEN** leurs rangs sont 4, 2, 1 et 2

#### Scenario: Joueur sans index
- **WHEN** un joueur joue son tout premier round
- **THEN** son handicap de jeu et son net s'affichent « — » avec la mention « pas encore d'index », et il n'est pas classé en net

### Requirement: Handicap de jeu d'un round
Le handicap de jeu d'un joueur sur un round SHALL être son handicap de parcours : index du joueur juste avant ce round × slope / 113 + (course rating − par total du round), arrondi à l'entier le plus proche (0,5 vers le haut), avec la rating et le slope du parcours et des tees du round (spec `course-ratings`). Sans rating, c'est l'index d'avant le round arrondi à l'entier.

#### Scenario: Classement net à deux
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,4 juste avant un round sans rating
- **WHEN** Gab y joue 72 et Manu 88
- **THEN** Gab a un handicap de jeu de 1 et un net de 71, Manu de 17 et un net de 71, et les deux sont premiers ex æquo

#### Scenario: Classement net avec rating
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,4 juste avant un round sur « BETHPAGE BLACK / Red » à 71,4 / 144, par 71
- **WHEN** Gab y joue 72 et Manu 88
- **THEN** Gab a un handicap de jeu de 2 et un net de 70, Manu de 23 et un net de 65, Manu est premier et Gab deuxième

#### Scenario: Index d'avant le round
- **GIVEN** Gab à 1,0 et Manu à 17,0 après un round le 2026-10-10, et la carte `IMG_3064` le 2026-10-17
- **WHEN** l'utilisateur ouvre la carte `IMG_3064`
- **THEN** les handicaps de jeu sont 1 et 17, calculés depuis l'index d'avant ce round et non depuis celui d'après

#### Scenario: Joueur plus
- **GIVEN** un joueur avec un index de −1,6 juste avant un round sans rating
- **WHEN** il y joue 70
- **THEN** son handicap de jeu vaut −2 et son net 72

### Requirement: Classement lisible sur téléphone
Le classement net d'un round SHALL montrer toutes ses colonnes (joueur, brut, handicap de jeu, net, rang) sans en couper aucune sur un écran de 320 px, et la page MUST NOT défiler horizontalement. Le nom du ou des gagnants nets est marqué du rouge « sous le par ».

#### Scenario: Classement à 320 px
- **WHEN** l'utilisateur ouvre à 320 px un round de Manu et Sean où les deux ont un index
- **THEN** les en-têtes « Brut », « Net » et « Rang » et leurs valeurs sont entièrement visibles sans défilement horizontal

### Requirement: Rating affichée sur le round
La page d'un round SHALL afficher la course rating et le slope utilisés pour ses différentiels et son classement net, en deux éléments distincts sans « · », ou « Sans rating (par 71, slope 113) » avec le par total du round. Cette ligne MUST mener à la page Parcours.

#### Scenario: Round avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** l'utilisateur ouvre la carte `IMG_3064`
- **THEN** la page affiche « Rating 71,4 » et « Slope 144 », sans « · », et cette ligne mène à la page Parcours

#### Scenario: Round sans rating
- **WHEN** l'utilisateur ouvre un round de par 72 sur un parcours sans rating
- **THEN** la page affiche « Sans rating (par 72, slope 113) » et cette ligne mène à la page Parcours

## REMOVED Requirements

### Requirement: Bruts d'un round
**Reason**: Le classement net d'un round montre le brut de chaque joueur dans l'ordre de la carte, avec son handicap de jeu, son net et son rang.
**Migration**: Voir « Classement net d'un round » et « Classement lisible sur téléphone ».
