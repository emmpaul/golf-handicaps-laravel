# Spec Delta

## MODIFIED Requirements

### Requirement: Migrations avant le web et le worker
La stack SHALL appliquer les migrations en attente par un service dédié, une seule fois par déploiement, avant de démarrer le web et le worker. Le même service SHALL ensuite créer les joueurs initiaux (spec `players`), puis recalculer l'historique d'index de tous les joueurs (spec `handicap-index`). Si les migrations, la création des joueurs initiaux ou le recalcul échouent, le web et le worker MUST NOT démarrer sur la nouvelle image.

#### Scenario: Déploiement avec une migration en attente
- **GIVEN** la nouvelle image apporte une migration que la base n'a pas
- **WHEN** l'opérateur redéploie la stack
- **THEN** le service `migrate` applique la migration et s'arrête avec le code 0, puis `web` et `worker` démarrent

#### Scenario: Migration en échec
- **GIVEN** une migration de la nouvelle image échoue
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec un code non nul, et ni `web` ni `worker` ne démarrent sur la nouvelle image

#### Scenario: Base à jour
- **GIVEN** la base a déjà toutes les migrations de l'image, des joueurs et l'historique d'index de leurs rounds
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec le code 0, sans changer ni les tables ni les joueurs ni l'historique d'index, puis `web` et `worker` démarrent

#### Scenario: Joueurs initiaux en double
- **GIVEN** une base sans joueur et `INITIAL_PLAYERS=Gab,gab` dans l'environnement de la stack
- **WHEN** l'opérateur lance la stack
- **THEN** `migrate` s'arrête avec un code non nul et un message qui nomme `INITIAL_PLAYERS`, et ni `web` ni `worker` ne démarrent

#### Scenario: Rounds enregistrés avant l'index
- **GIVEN** une base qui contient la carte `IMG_3064` et aucun historique d'index
- **WHEN** l'opérateur redéploie la stack sur l'image qui apporte l'index
- **THEN** `migrate` affiche « 2 joueurs recalculés. » et s'arrête avec le code 0, Gab a un différentiel de 1,0 et Manu de 17,0 sur ce round, puis `web` et `worker` démarrent

#### Scenario: Première mise en route sur une base vide
- **GIVEN** une base vide
- **WHEN** l'opérateur lance la stack pour la première fois
- **THEN** `migrate` applique les migrations, crée les joueurs initiaux, affiche « 0 joueur recalculé. » et s'arrête avec le code 0, puis `web` et `worker` démarrent
