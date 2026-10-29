# players Specification

## Purpose
Tenir la liste des joueurs du groupe et les alias qui relient un nom lu sur une carte de score au bon joueur.

## Requirements

### Requirement: Joueurs initiaux
Quand le service `migrate` de la stack tourne sur une base sans aucun joueur, le système SHALL créer les joueurs nommés dans `INITIAL_PLAYERS` (noms séparés par des virgules, espaces autour retirés, noms vides ignorés), chacun avec pour alias son nom en majuscules. Variable absente ou vide, le système MUST NOT créer de joueur. Deux noms identiques sans tenir compte de la casse MUST faire échouer `migrate` avec un message qui nomme la variable.

#### Scenario: Base vide
- **GIVEN** une base migrée sans aucun joueur et `INITIAL_PLAYERS=Manu, Gab,Sean`
- **WHEN** le service `migrate` tourne
- **THEN** la liste des joueurs contient exactement Gab, Manu et Sean, avec les alias « GAB », « MANU » et « SEAN »

#### Scenario: Base vide sans variable
- **GIVEN** une base migrée sans aucun joueur et sans `INITIAL_PLAYERS`
- **WHEN** le service `migrate` tourne
- **THEN** la liste des joueurs est vide et l'utilisateur peut ajouter des joueurs sur la page Joueurs

#### Scenario: Noms vides ignorés
- **GIVEN** une base sans aucun joueur et `INITIAL_PLAYERS=Gab,,  ,Sean`
- **WHEN** le service `migrate` tourne
- **THEN** la liste des joueurs contient exactement Gab et Sean

#### Scenario: Noms en double
- **GIVEN** une base sans aucun joueur et `INITIAL_PLAYERS=Gab,gab`
- **WHEN** le service `migrate` tourne
- **THEN** `migrate` s'arrête avec un code non nul et un message qui nomme `INITIAL_PLAYERS`, et aucun joueur n'est créé

#### Scenario: Base existante
- **GIVEN** une base qui contient déjà le joueur Gab et `INITIAL_PLAYERS=Alex,Sam`
- **WHEN** le service `migrate` tourne
- **THEN** le système ne crée et ne modifie aucun joueur, et `migrate` s'arrête avec le code 0

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

### Requirement: Ajout d'un joueur
Le système SHALL permettre d'ajouter un joueur sur la page Joueurs. Le nom est retiré de ses espaces autour, obligatoire, et fait au plus 50 caractères. Deux joueurs MUST NOT avoir le même nom sans tenir compte de la casse.

#### Scenario: Ajout d'un joueur
- **WHEN** l'utilisateur ajoute le joueur « Alex »
- **THEN** « Alex » apparaît dans la liste des joueurs, sans alias et sans round

#### Scenario: Nom en double
- **GIVEN** le joueur Gab existe
- **WHEN** l'utilisateur ajoute un joueur nommé « gab »
- **THEN** le système refuse l'ajout, affiche « Ce joueur existe déjà » et la liste ne change pas

#### Scenario: Nom vide
- **WHEN** l'utilisateur ajoute un joueur dont le nom ne contient que des espaces
- **THEN** le système refuse l'ajout et affiche « Le nom est obligatoire »

### Requirement: Renommage d'un joueur
Le système SHALL permettre de renommer un joueur, avec les mêmes règles de nom que l'ajout. Les rounds du joueur et ses alias ne changent pas.

#### Scenario: Joueur renommé
- **GIVEN** Manu a joué le round de référence
- **WHEN** l'utilisateur renomme Manu en « Emmanuel »
- **THEN** la liste des joueurs montre « Emmanuel » avec l'alias « MANU », et le round de référence montre « Emmanuel »

#### Scenario: Renommage vers un nom pris
- **GIVEN** les joueurs Gab et Manu existent
- **WHEN** l'utilisateur renomme Manu en « GAB »
- **THEN** le système refuse, affiche « Ce joueur existe déjà » et Manu garde son nom

#### Scenario: Changement de casse seulement
- **WHEN** l'utilisateur renomme Manu en « MANU »
- **THEN** le joueur s'appelle désormais « MANU »

### Requirement: Ajout manuel d'un alias
Le système SHALL permettre d'ajouter un alias à un joueur depuis la page Joueurs. L'alias est enregistré en majuscules, sans espaces autour, et fait au plus 50 caractères. Un alias MUST NOT appartenir à deux joueurs sans tenir compte de la casse : l'ajouter à un joueur le retire du joueur qui le portait.

#### Scenario: Nouvel alias
- **WHEN** l'utilisateur ajoute l'alias « seanp » à Sean
- **THEN** les alias de Sean sont « SEAN » et « SEANP »

#### Scenario: Alias déplacé
- **GIVEN** Manu porte l'alias « SEANP »
- **WHEN** l'utilisateur ajoute l'alias « SeanP » à Sean
- **THEN** « SEANP » apparaît dans les alias de Sean et n'apparaît plus dans ceux de Manu

#### Scenario: Alias vide
- **WHEN** l'utilisateur ajoute un alias vide à Sean
- **THEN** le système refuse et affiche « L’alias est obligatoire »

### Requirement: Retrait d'un alias
Le système SHALL permettre de retirer un alias d'un joueur depuis la page Joueurs, sans confirmation. Un alias retiré MUST NOT servir à associer les lignes des imports suivants. Les rounds déjà enregistrés ne changent pas.

#### Scenario: Alias appris par erreur
- **GIVEN** Manu porte l'alias « SEANP »
- **WHEN** l'utilisateur retire « SEANP » de Manu
- **THEN** « SEANP » n'apparaît plus dans les alias de Manu

#### Scenario: Alias réappris
- **GIVEN** l'utilisateur a retiré l'alias « SEANP » de Manu
- **WHEN** il ajoute l'alias « SEANP » à Sean
- **THEN** « SEANP » apparaît dans les alias de Sean

#### Scenario: Rounds déjà enregistrés
- **GIVEN** Manu a joué le round de référence
- **WHEN** l'utilisateur retire l'alias « MANU » de Manu
- **THEN** le round de référence montre toujours Manu avec 88 brut

### Requirement: Joueurs de la base de développement
Une base de développement recréée SHALL contenir les joueurs Gab, Manu et Sean avec leurs alias en majuscules, quelle que soit la valeur de `INITIAL_PLAYERS`.

#### Scenario: Base locale recréée
- **GIVEN** `INITIAL_PLAYERS=Alex` dans `.env`
- **WHEN** un développeur lance `make fresh`
- **THEN** la base locale contient exactement les joueurs Gab, Manu et Sean

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

### Requirement: Reconnaissance des noms lus sur la carte
À l'ouverture de l'écran de correction d'un import, le système SHALL associer chaque ligne lue au joueur dont le nom ou un alias est identique au nom lu, sans tenir compte de la casse ni des espaces autour. Sans correspondance exacte, il SHALL pré-associer le seul joueur dont le nom ou un alias est à au plus 1 caractère du nom lu ; s'il y en a plusieurs ou aucun, la ligne reste sans joueur.

#### Scenario: Correspondance exacte
- **GIVEN** le joueur Gab, avec l'alias « GAB »
- **WHEN** la carte contient le nom « gab »
- **THEN** la ligne est associée au joueur Gab

#### Scenario: Erreur de lecture d'un caractère
- **GIVEN** le joueur Manu, avec l'alias « MANU »
- **WHEN** la lecture donne « MANV »
- **THEN** la ligne est pré-associée au joueur Manu et l'utilisateur peut changer ce choix

#### Scenario: Deux joueurs proches
- **GIVEN** les joueurs Sam et Sim, sans autre alias que leur nom en majuscules
- **WHEN** la lecture donne « SAM » pour une ligne, puis « SOM » pour une autre
- **THEN** la première ligne est associée à Sam et la seconde n'est associée à personne

#### Scenario: Nom inconnu
- **WHEN** la carte contient « JULIEN » et aucun joueur ni alias ne s'en approche
- **THEN** la ligne n'est associée à personne et l'utilisateur doit choisir un joueur existant ou en créer un avant d'enregistrer

#### Scenario: Alias retiré
- **GIVEN** l'utilisateur a retiré l'alias « SEANP » de Manu, et aucun autre joueur ne le porte ni ne s'en approche
- **WHEN** une ligne « SEANP » est lue à l'import suivant
- **THEN** la ligne n'est associée à personne

### Requirement: Apprentissage des alias
Quand l'utilisateur enregistre le round d'un import, le système SHALL enregistrer comme alias du joueur associé chaque nom lu qui ne désignait pas déjà exactement ce joueur, en majuscules, dans la même écriture que le round. L'alias appris MUST être retiré du joueur qui le portait. Une ligne sans nom lu n'apprend rien.

#### Scenario: Alias mémorisé
- **WHEN** l'utilisateur associe la ligne « SEANP » au joueur Sean et enregistre le round
- **THEN** « SEANP » apparaît dans les alias de Sean, et à l'import suivant une ligne « SEANP » est associée directement à Sean

#### Scenario: Alias déplacé
- **GIVEN** Manu porte l'alias « SEANP » appris par erreur
- **WHEN** l'utilisateur associe la ligne « SEANP » d'un import à Sean et enregistre le round
- **THEN** « SEANP » apparaît dans les alias de Sean et plus dans ceux de Manu

#### Scenario: Correspondance exacte inchangée
- **GIVEN** Gab porte l'alias « GAB »
- **WHEN** l'utilisateur enregistre un import dont la ligne « GAB » est associée à Gab
- **THEN** les alias de Gab ne changent pas

#### Scenario: Pré-association acceptée
- **GIVEN** la ligne « MANV » a été pré-associée à Manu
- **WHEN** l'utilisateur enregistre le round sans changer ce choix
- **THEN** « MANV » apparaît dans les alias de Manu

#### Scenario: Enregistrement refusé
- **WHEN** l'enregistrement d'un import est refusé comme doublon ou carte incohérente
- **THEN** aucun alias n'est appris

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
