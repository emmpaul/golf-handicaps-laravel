# Spec Delta

## MODIFIED Requirements

### Requirement: Migrations avant le web et le worker
La stack SHALL appliquer les migrations en attente par un service dédié, une seule fois par déploiement, avant de démarrer le web et le worker. Le même service SHALL ensuite créer les joueurs initiaux (spec `players`). Si les migrations ou la création des joueurs initiaux échouent, le web et le worker MUST NOT démarrer sur la nouvelle image.

#### Scenario: Déploiement avec une migration en attente
- **GIVEN** la nouvelle image apporte une migration que la base n'a pas
- **WHEN** l'opérateur redéploie la stack
- **THEN** le service `migrate` applique la migration et s'arrête avec le code 0, puis `web` et `worker` démarrent

#### Scenario: Migration en échec
- **GIVEN** une migration de la nouvelle image échoue
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec un code non nul, et ni `web` ni `worker` ne démarrent sur la nouvelle image

#### Scenario: Base à jour
- **GIVEN** la base a déjà toutes les migrations de l'image et des joueurs
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec le code 0 sans rien changer, puis `web` et `worker` démarrent

#### Scenario: Joueurs initiaux en double
- **GIVEN** une base sans joueur et `INITIAL_PLAYERS=Gab,gab` dans l'environnement de la stack
- **WHEN** l'opérateur lance la stack
- **THEN** `migrate` s'arrête avec un code non nul et un message qui nomme `INITIAL_PLAYERS`, et ni `web` ni `worker` ne démarrent
