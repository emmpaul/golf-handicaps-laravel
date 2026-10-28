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

## ADDED Requirements

### Requirement: Mise en page de la page Joueurs
À 390 px, la page Joueurs SHALL suivre le design : titre « Joueurs », puis 20 px dessous le champ « Nouveau joueur » et le bouton plein « Ajouter » côte à côte (48 px), un filet 24 px dessous, puis une ligne par joueur avec 16 px de marge verticale : pastille de 10 px, nom en Geist 600, 20 px, et à droite l'index en Geist Mono 500, 22 px, au-dessus de son état en Geist Mono 500, 10 px, gris.

#### Scenario: Index d'un joueur
- **GIVEN** Gab avec un index de 10,0 sur 3 rounds ou plus, Manu à 14,5 provisoire, Alex sans round
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** Gab montre « 10,0 » et « INDEX », Manu « 14,5 » et « PROVISOIRE », Alex « — » et « PAS D'INDEX »

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
