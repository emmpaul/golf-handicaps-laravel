# Spec Delta

## MODIFIED Requirements

### Requirement: Liste des joueurs
La page Joueurs SHALL lister tous les joueurs par nom, sans tenir compte de la casse, chacun avec sa pastille de couleur, son index à droite et, sous « ALIAS », ses alias par ordre alphabétique en pastilles qui portent chacune un bouton de retrait. Un joueur sans alias SHALL afficher « Aucun alias » après « ALIAS ».

#### Scenario: Joueurs de développement
- **GIVEN** les joueurs Manu, gab et Sean
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la liste montre gab, Manu puis Sean, et Manu a la pastille d'alias « MANU » avec le bouton « Retirer l’alias MANU de Manu » (nom accessible actuel, gardé)

#### Scenario: Joueur sans alias
- **GIVEN** le joueur Alex, ajouté sur la page Joueurs
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la ligne d'Alex affiche « ALIAS » puis « Aucun alias »

### Requirement: Index sur la page Joueurs
Sur la page Joueurs, chaque joueur SHALL afficher à droite son index actuel, celui d'après son dernier round par date de jeu puis par date d'enregistrement (spec `handicap-index`), au-dessus de son état : « 10,0 » et « INDEX », « 14,5 » et « PROVISOIRE » pour un index provisoire, ou « — » et « PAS D'INDEX » sans round.

#### Scenario: Index établi
- **GIVEN** Gab avec trois rounds de différentiels 17,0, 12,0 et 20,0
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la ligne de Gab affiche « 10,0 » et « INDEX »

#### Scenario: Index provisoire
- **GIVEN** Manu avec deux rounds de différentiels 17,0 et 12,0
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la ligne de Manu affiche « 14,5 » et « PROVISOIRE »

#### Scenario: Joueur sans round
- **GIVEN** Sean sans round
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la ligne de Sean affiche « — » et « PAS D'INDEX »

#### Scenario: Round ajouté avant les autres
- **GIVEN** Gab avec des rounds le 2026-10-10 (17,0) et le 2026-10-17 (12,0)
- **WHEN** l'utilisateur enregistre un round de Gab le 2026-10-01 (20,0) puis ouvre `/players`
- **THEN** la ligne de Gab affiche « 10,0 », l'index d'après le round du 2026-10-17

## ADDED Requirements

### Requirement: Mise en page de la page Joueurs
À 390 px, la page Joueurs SHALL suivre le design : titre « Joueurs », puis 20 px dessous le champ « Nouveau joueur » et le bouton plein « Ajouter » côte à côte (48 px), un filet 24 px dessous, puis une ligne par joueur avec 16 px de marge verticale : pastille de 10 px, nom en Geist 600, 20 px, et à droite l'index en Geist Mono 500, 22 px, au-dessus de son état en Geist Mono 500, 10 px, gris.

#### Scenario: Pastille d'alias
- **WHEN** la ligne de Manu s'affiche à 390 px
- **THEN** la pastille « MANU » fait 32 px de haut, fond `oklch(0.2 0.006 260)`, Geist Mono 500, 12 px, et son bouton de retrait offre 24 px de cercle, icône × de 12 px grise

### Requirement: Modification repliée d'un joueur
Sur la page Joueurs, le renommage et l'ajout d'alias d'un joueur SHALL être repliés derrière « Modifier », à droite de ses alias ; un seul joueur est ouvert à la fois. Ouvert, le joueur montre le champ du nom et « Renommer », le champ « Nouvel alias de {nom} » et « Ajouter l'alias » (boutons de 130 px à contour), puis « Les alias servent à reconnaître le joueur sur les photos de carte. », et « Modifier » devient « Fermer ».

#### Scenario: Ouverture
- **WHEN** l'utilisateur touche « Modifier » sur la ligne de Sean
- **THEN** la ligne de Sean montre le champ pré-rempli « Sean », « Renommer », le champ « Nouvel alias de Sean » et « Ajouter l'alias », et « Modifier » devient « Fermer »

#### Scenario: Un seul joueur ouvert
- **GIVEN** la ligne de Sean est ouverte
- **WHEN** l'utilisateur touche « Modifier » sur la ligne de Gab
- **THEN** la ligne de Gab s'ouvre et celle de Sean se referme

#### Scenario: Erreur gardée ouverte
- **GIVEN** la ligne de Manu est ouverte
- **WHEN** l'utilisateur renomme Manu en « GAB »
- **THEN** la ligne reste ouverte et affiche « Ce joueur existe déjà » sous le champ du nom
