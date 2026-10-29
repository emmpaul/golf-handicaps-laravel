# home-dashboard Specification

## Purpose
Faire de la page d'accueil un point de départ utile au téléphone : lancer un import en un geste et voir d'un coup d'œil où en sont les index, le dernier round et les imports à vérifier.

## Requirements

### Requirement: Accueil protégé
La page d'accueil SHALL exiger la session du code d'accès, comme toute page de l'application : sans session, elle redirige vers `/login` et n'affiche aucune donnée du groupe.

#### Scenario: Sans session
- **GIVEN** aucune session ouverte
- **WHEN** le navigateur demande `/`
- **THEN** le système redirige vers `/login`, et la réponse ne contient ni nom de joueur, ni index, ni parcours

### Requirement: Action d'import en tête
La page d'accueil SHALL offrir l'import en un geste par le bouton rond « Importer » de la barre flottante (spec `app-shell`, « Bouton Importer »), toujours affiché sur l'accueil. Elle commence par la rangée du haut « Golf » et « Se déconnecter », sans titre de page ni bouton d'import propre, et MUST NOT reprendre la liste des sections de la navigation.

#### Scenario: Importer depuis l'accueil
- **WHEN** l'utilisateur touche le bouton « Importer » sur l'accueil
- **THEN** l'écran d'import `/import` s'ouvre

#### Scenario: Plus de liste de sections
- **WHEN** l'utilisateur ouvre l'accueil sur un écran de 390 px
- **THEN** le contenu de la page ne contient ni liste « Sections » ni le texte « Le tableau du groupe arrive bientôt. »

### Requirement: Index des joueurs
Sous « INDEX », la page d'accueil SHALL lister les joueurs par index actuel croissant, puis par nom sans tenir compte de la casse à index égal, les joueurs sans index à la fin par nom. Chaque ligne montre le rang (« – » sans index), la pastille et le nom, une ligne d'état et l'index à droite (« — » sans index). Chaque ligne mène à la page Handicaps, sur ce joueur.

#### Scenario: Index égaux
- **GIVEN** Manu et gab tous deux à 10,0
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** gab est au-dessus de Manu

#### Scenario: Vers la page Handicaps
- **WHEN** l'utilisateur touche la ligne de Manu
- **THEN** la page Handicaps s'ouvre avec Manu choisi

### Requirement: Ligne d'état d'un joueur
Sur l'accueil, la ligne d'état de chaque joueur SHALL joindre par « · » la tendance, écart entre l'index d'après le dernier round du joueur et celui d'après l'avant-dernier (« ▼ 1,5 » en baisse, « ▲ 0,3 » en hausse, « = STABLE » sans écart, absente avant le deuxième round), puis « PROVISOIRE » pour un index provisoire ou le nombre de rounds du joueur (« 8 ROUNDS ») ; un joueur sans round affiche « PAS ENCORE D'INDEX ». Le dernier round se prend par date de jeu, puis par date d'enregistrement.

#### Scenario: Lignes d'état
- **GIVEN** Gab à 2,5 sur 8 rounds dont le dernier l'a fait baisser de 1,5, Manu à 16,5 stable sur 8 rounds, Sean à 19,0 provisoire sur un seul round, Alex sans round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** les lignes sont, dans l'ordre, « 1 », Gab, « ▼ 1,5 · 8 ROUNDS », « 2,5 » ; « 2 », Manu, « = STABLE · 8 ROUNDS », « 16,5 » ; « 3 », Sean, « PROVISOIRE », « 19,0 » ; « – », Alex, « PAS ENCORE D'INDEX », « — »

#### Scenario: Index en hausse
- **GIVEN** Manu avait un index de 15,5 avant son dernier round et de 15,8 après
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Manu affiche « Manu », « 15,8 » et une ligne d'état qui commence par « ▲ 0,3 »

#### Scenario: Index en baisse
- **GIVEN** Gab avait un index de 1,3 avant son dernier round et de 1,0 après
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Gab affiche « 1,0 » et une ligne d'état qui commence par « ▼ 0,3 »

#### Scenario: Index inchangé
- **GIVEN** l'index de Gab est 0,5 avant et après son dernier round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Gab affiche « 0,5 » et une ligne d'état qui commence par « = STABLE »

#### Scenario: Dernier round par date de jeu
- **GIVEN** Gab avec des rounds le 2026-10-01 (index après 17,0), le 2026-10-10 (15,5) et le 2026-10-17 (15,8), le round du 2026-10-01 enregistré en dernier
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Gab affiche « 15,8 » et « ▲ 0,3 · 3 ROUNDS »

#### Scenario: Index provisoire
- **GIVEN** Sean a joué 2 rounds, de différentiels 17,0 puis 12,0
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Sean affiche « 14,5 » et « ▼ 2,5 · PROVISOIRE »

#### Scenario: Joueur sans round
- **GIVEN** Sean n'a encore aucun round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Sean affiche le rang « – », « Sean », « PAS ENCORE D'INDEX » et « — », sans tendance, après les joueurs qui ont un index

#### Scenario: Premier round
- **GIVEN** Sean n'a joué qu'un round, avec un différentiel de 17,0
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la ligne de Sean affiche « 17,0 » et « PROVISOIRE », sans tendance


### Requirement: Dernier round
La page d'accueil SHALL afficher le round le plus récent par date de jeu, puis par date d'enregistrement, sous « DERNIER ROUND » et sa date abrégée (« 17 OCT. ») : le parcours suivi de « · » et des tees, puis une ligne par joueur rangée par rang net croissant, des ex æquo dans l'ordre de la carte (rang, pastille et nom, « {brut} − {handicap de jeu} », net), puis « Net · » et le résultat. Le bloc mène à la page du round. Sans round, le bloc « DERNIER ROUND » n'apparaît pas.

#### Scenario: Dernier round affiché
- **GIVEN** le round le plus récent est BETHPAGE BLACK / Red du 17 octobre 2026, à 71,4 / 144, par 71, où Gab (index 1,0 avant le round) joue 72 et Manu (index 17,4) 88
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bloc affiche « DERNIER ROUND », « 17 OCT. », « BETHPAGE BLACK » suivi de « · Red », puis la ligne « 1 », Manu, « 88 − 23 », « 65 », la ligne « 2 », Gab, « 72 − 2 », « 70 », et « Net · Manu gagne de 5 coups » ; le bloc mène à la page de ce round

#### Scenario: Même jour
- **GIVEN** deux rounds joués le 2026-10-17, celui de PAYNES VALLEY enregistré après celui de BETHPAGE BLACK
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bloc montre PAYNES VALLEY

#### Scenario: Base vide
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bloc « DERNIER ROUND » n'apparaît pas

### Requirement: Résultat du dernier round
Dans le bloc « DERNIER ROUND », un joueur sans index SHALL venir après les joueurs classés, sans rang, avec « {brut} · pas encore d'index » et « — » comme net. Le résultat dit « {gagnant} gagne de {n} coups » (« de 1 coup » au singulier), l'écart entre le premier et le deuxième net, « Égalité : {noms} » pour des premiers ex æquo, ou « {nom} seul en jeu » quand un seul joueur est classé ; sans joueur classé, il n'y a pas de résultat.

#### Scenario: Vainqueur seul
- **GIVEN** le dernier round, où Gab fait 68 net et Sean, deuxième, 70
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la dernière ligne dit « Net · Gab gagne de 2 coups »

#### Scenario: Écart d'un coup
- **GIVEN** le dernier round, où Gab fait 70 net et Manu 71
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la dernière ligne dit « Net · Gab gagne de 1 coup »

#### Scenario: Gagnants ex æquo
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,0 avant la carte `IMG_3064`, sans rating, dernier round : tous deux 71 net
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la dernière ligne dit « Net · Égalité : Gab, Manu »

#### Scenario: Joueur seul
- **GIVEN** le dernier round n'a qu'un joueur classé en net, Gab
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la dernière ligne dit « Net · Gab seul en jeu »

#### Scenario: Personne n'a d'index
- **GIVEN** la carte `IMG_3064`, premier round de Gab et de Manu, est le seul round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bloc affiche « 72 · pas encore d'index » et « 88 · pas encore d'index », « — » comme nets, et aucune ligne « Net · »


### Requirement: Imports à vérifier
Quand des imports lus attendent une vérification (« Prêt à vérifier », sans round enregistré), la page d'accueil SHALL l'indiquer par une rangée qui donne leur nombre et ouvre l'écran d'import `/import`. Sans import à vérifier, cette rangée MUST NOT s'afficher. Les imports « En attente », « Lecture en cours » ou « Échec » ne comptent pas.

#### Scenario: Un import prêt
- **GIVEN** un import « Prêt à vérifier » sans round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la rangée dit « 1 » et « import à vérifier », et ouvre `/import`

#### Scenario: Plusieurs imports prêts
- **GIVEN** deux imports « Prêt à vérifier » sans round, un import « Prêt à vérifier » déjà enregistré en round, un « Lecture en cours » et un « Échec »
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la rangée dit « 2 » et « imports à vérifier », et ouvre `/import`

#### Scenario: Import ancien
- **GIVEN** un import « Prêt à vérifier » sans round, puis 21 imports plus récents en « Échec »
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la rangée dit « 1 » et « import à vérifier », même si l'écran d'import ne liste que les 20 plus récents

#### Scenario: Rien à vérifier
- **GIVEN** tous les imports prêts ont leur round enregistré
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la rangée des imports n'apparaît pas

### Requirement: Mise en page de l'accueil
À 390 px, l'accueil SHALL suivre le design : rangée « Golf » et « Se déconnecter », rangée des imports à vérifier, classement « INDEX », puis « DERNIER ROUND ».

#### Scenario: Rangée des imports
- **GIVEN** deux imports prêts à vérifier
- **WHEN** l'utilisateur ouvre l'accueil à 390 px
- **THEN** 12 px sous la rangée du haut, une rangée de 44 px montre la pastille « 2 » (Geist Mono 600, 11 px, ambre sur ambre à 15 %), « imports à vérifier » en Geist 500, 14 px, ambre, et « → » à droite

#### Scenario: Ligne du classement
- **WHEN** le classement « INDEX » s'affiche à 390 px
- **THEN** chaque ligne fait 76 px de haut au moins : rang en Geist Mono 500, 13 px, gris, dans 16 px ; pastille de 8 px et nom en Geist 600, 17 px ; ligne d'état en Geist Mono 500, 11 px, gris, en capitales ; index à droite en Geist Mono 500, 44 px

#### Scenario: Ligne du dernier round
- **WHEN** le bloc « DERNIER ROUND » s'affiche à 390 px
- **THEN** le parcours est en Geist 600, 22 px, interlignage 1,1, les tees en graisse 400 grise ; chaque ligne de joueur fait 52 px : rang en Geist Mono 500, 13 px, gris, nom en Geist 600, 15 px, « 72 − 1 » en Geist Mono 500, 12 px, gris, net en Geist Mono 500, 24 px
