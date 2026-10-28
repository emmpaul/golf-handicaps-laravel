# Spec Delta

## ADDED Requirements

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
