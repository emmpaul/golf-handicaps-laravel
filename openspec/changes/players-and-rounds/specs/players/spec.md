# Spec Delta

## Purpose

Tenir la liste des joueurs du groupe et les alias qui relient un nom lu sur une carte de score au bon joueur.

## ADDED Requirements

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
La page Joueurs SHALL lister tous les joueurs par nom, sans tenir compte de la casse, chacun avec ses alias par ordre alphabétique. Un joueur sans alias SHALL afficher « Aucun alias ».

#### Scenario: Joueurs de développement
- **GIVEN** les joueurs Manu, gab et Sean
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la liste montre gab, Manu puis Sean, et Manu a l'alias « MANU »

#### Scenario: Joueur sans alias
- **GIVEN** le joueur Alex, ajouté sur la page Joueurs
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la ligne d'Alex affiche « Aucun alias »

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
- **THEN** le système refuse et affiche « L'alias est obligatoire »

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
