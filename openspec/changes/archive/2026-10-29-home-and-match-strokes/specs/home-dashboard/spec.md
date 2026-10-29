# Spec Delta

## Purpose

Faire de la page d'accueil un point de départ utile au téléphone : lancer un import en un geste et voir d'un coup d'œil où en sont les index, le dernier round et les imports à vérifier.

## ADDED Requirements

### Requirement: Accueil protégé
La page d'accueil SHALL exiger la session du code d'accès, comme toute page de l'application : sans session, elle redirige vers `/login` et n'affiche aucune donnée du groupe.

#### Scenario: Sans session
- **GIVEN** aucune session ouverte
- **WHEN** le navigateur demande `/`
- **THEN** le système redirige vers `/login`, et la réponse ne contient ni nom de joueur, ni index, ni parcours

### Requirement: Action d'import en tête
La page d'accueil SHALL commencer, sous le titre « Handicaps golf », par une action principale « Importer une carte » qui mène à l'écran d'import. Elle MUST NOT reprendre la liste des cinq sections de la navigation.

#### Scenario: Importer depuis l'accueil
- **WHEN** l'utilisateur touche « Importer une carte » sur l'accueil
- **THEN** l'écran d'import `/import` s'ouvre

#### Scenario: Plus de liste de sections
- **WHEN** l'utilisateur ouvre l'accueil sur un écran de 390 px
- **THEN** le contenu de la page ne contient ni liste « Sections » ni le texte « Le tableau du groupe arrive bientôt. »

### Requirement: Index des joueurs
La page d'accueil SHALL afficher chaque joueur, par nom sans tenir compte de la casse, avec son index actuel et l'écart signé avec son index d'avant son dernier round (« −0,3 », « +0,1 », « = » à écart nul). Un index provisoire MUST porter la mention « provisoire ». Un joueur sans round MUST afficher « pas encore d'index » sans écart ; un joueur à un seul round n'a pas d'écart. Chaque joueur mène à la page Handicaps, sur ce joueur.

#### Scenario: Index en hausse
- **GIVEN** Manu avait un index de 15,5 avant son dernier round et de 15,8 après
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Manu affiche « Manu », « 15,8 » et « +0,3 »

#### Scenario: Index en baisse
- **GIVEN** Gab avait un index de 1,3 avant son dernier round et de 1,0 après
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Gab affiche « 1,0 » et « −0,3 », avec le signe moins typographique « − »

#### Scenario: Index inchangé
- **GIVEN** l'index de Gab est 0,5 avant et après son dernier round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Gab affiche « 0,5 » et « = »

#### Scenario: Dernier round par date de jeu
- **GIVEN** Gab avec des rounds le 2026-10-01 (index après 17,0), le 2026-10-10 (15,5) et le 2026-10-17 (15,8), le round du 2026-10-01 enregistré en dernier
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Gab affiche « 15,8 » et « +0,3 »

#### Scenario: Joueur sans round
- **GIVEN** Sean n'a encore aucun round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Sean affiche « Sean » et « pas encore d'index », sans écart

#### Scenario: Premier round
- **GIVEN** Sean n'a joué qu'un round, avec un différentiel de 17,0
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Sean affiche « 17,0 » et « provisoire », sans écart

#### Scenario: Index provisoire
- **GIVEN** Sean a joué 2 rounds
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** son index s'affiche avec la mention « provisoire »

#### Scenario: Vers la page Handicaps
- **WHEN** l'utilisateur touche la ligne de Manu
- **THEN** la page Handicaps s'ouvre avec Manu choisi

### Requirement: Dernier round
La page d'accueil SHALL afficher le round le plus récent par date de jeu, puis par date d'enregistrement : parcours, tees, date, score brut de chaque joueur dans l'ordre de la carte et gagnant net, avec un lien vers la page du round. Le parcours et les tees MUST être deux éléments distincts, sans point médian entre eux. Sans round, elle MUST afficher « Aucun round enregistré » à la place.

#### Scenario: Dernier round affiché
- **GIVEN** le round le plus récent est BETHPAGE BLACK / Red du 17 octobre 2026, Gab 72 et Manu 88, Gab seul gagnant net
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** l'accueil affiche « BETHPAGE BLACK », « Tees Red », « 17 octobre 2026 », « Gab 72 », « Manu 88 » et « Gagnant net : Gab », sans « · », et le bloc mène à la page de ce round

#### Scenario: Même jour
- **GIVEN** deux rounds joués le 2026-10-17, celui de PAYNES VALLEY enregistré après celui de BETHPAGE BLACK
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bloc montre PAYNES VALLEY

#### Scenario: Gagnants ex æquo
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,0 avant la carte `IMG_3064`, sans rating, dernier round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bloc affiche « Gagnant net : Gab, Manu »

#### Scenario: Personne n'a d'index
- **GIVEN** la carte `IMG_3064`, premier round de Gab et de Manu, est le seul round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bloc affiche « Gab 72 » et « Manu 88 » et aucune ligne « Gagnant net »

#### Scenario: Base vide
- **GIVEN** aucun round n'est enregistré
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** l'accueil affiche « Aucun round enregistré »

### Requirement: Imports à vérifier
Quand des imports lus attendent une vérification (« Prêt à vérifier », sans round enregistré), la page d'accueil SHALL l'indiquer avec leur nombre et un lien vers l'écran de correction du plus récent par date d'envoi. Sans import à vérifier, ce bloc MUST NOT s'afficher. Les imports « En attente », « Lecture en cours » ou « Échec » ne comptent pas.

#### Scenario: Un import prêt
- **GIVEN** un import « Prêt à vérifier » sans round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** l'accueil affiche « 1 import à vérifier » avec un lien vers l'écran de correction de cet import

#### Scenario: Plusieurs imports prêts
- **GIVEN** deux imports « Prêt à vérifier » sans round, envoyés le 2026-10-16 et le 2026-10-17, un import « Prêt à vérifier » déjà enregistré en round, un « Lecture en cours » et un « Échec »
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** l'accueil affiche « 2 imports à vérifier » avec un lien vers l'écran de correction de l'import du 2026-10-17

#### Scenario: Import ancien
- **GIVEN** un import « Prêt à vérifier » sans round, puis 21 imports plus récents en « Échec »
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** l'accueil affiche « 1 import à vérifier », même si l'écran d'import ne liste que les 20 plus récents

#### Scenario: Rien à vérifier
- **GIVEN** tous les imports prêts ont leur round enregistré
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** l'accueil n'affiche aucun bloc « à vérifier »
