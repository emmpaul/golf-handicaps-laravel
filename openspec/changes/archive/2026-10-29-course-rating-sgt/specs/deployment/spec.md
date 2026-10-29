# Spec Delta

## MODIFIED Requirements

### Requirement: Migrations avant le web et le worker
La stack SHALL appliquer les migrations en attente par un service dédié, une seule fois par déploiement, avant de démarrer le web et le worker. Le même service SHALL ensuite créer les joueurs initiaux (spec `players`), recalculer l'historique d'index de tous les joueurs (spec `handicap-index`), puis mettre en file la recherche de rating des parcours qui n'en ont pas (spec `course-ratings`). Si l'une de ces étapes échoue, le web et le worker MUST NOT démarrer sur la nouvelle image.

#### Scenario: Déploiement avec une migration en attente
- **GIVEN** la nouvelle image apporte une migration que la base n'a pas
- **WHEN** l'opérateur redéploie la stack
- **THEN** le service `migrate` applique la migration et s'arrête avec le code 0, puis `web` et `worker` démarrent

#### Scenario: Migration en échec
- **GIVEN** une migration de la nouvelle image échoue
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec un code non nul, et ni `web` ni `worker` ne démarrent sur la nouvelle image

#### Scenario: Base à jour
- **GIVEN** la base a déjà toutes les migrations de l'image, des joueurs, l'historique d'index de leurs rounds et une ligne de rating par parcours
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec le code 0, sans changer ni les tables ni les joueurs ni l'historique d'index ni les ratings, sans mettre de recherche en file, puis `web` et `worker` démarrent

#### Scenario: Joueurs initiaux en double
- **GIVEN** une base sans joueur et `INITIAL_PLAYERS=Gab,gab` dans l'environnement de la stack
- **WHEN** l'opérateur lance la stack
- **THEN** `migrate` s'arrête avec un code non nul et un message qui nomme `INITIAL_PLAYERS`, et ni `web` ni `worker` ne démarrent

#### Scenario: Rounds enregistrés avant l'index
- **GIVEN** une base qui contient la carte `IMG_3064` et aucun historique d'index
- **WHEN** l'opérateur redéploie la stack sur l'image qui apporte l'index
- **THEN** `migrate` affiche « 2 joueurs recalculés. » et s'arrête avec le code 0, Gab a un différentiel de 1,0 et Manu de 17,0 sur ce round, puis `web` et `worker` démarrent

#### Scenario: Rounds enregistrés avant la recherche
- **GIVEN** une base qui contient la carte `IMG_3064` sur « BETHPAGE BLACK / Red » et aucune ligne de rating
- **WHEN** l'opérateur redéploie la stack sur l'image qui apporte la recherche SGT
- **THEN** `migrate` affiche « 1 recherche de rating mise en file. » et s'arrête avec le code 0, puis le worker démarre et traite cette recherche

#### Scenario: Première mise en route sur une base vide
- **GIVEN** une base vide
- **WHEN** l'opérateur lance la stack pour la première fois
- **THEN** `migrate` applique les migrations, crée les joueurs initiaux, affiche « 0 joueur recalculé. » et « 0 recherche de rating mise en file. » et s'arrête avec le code 0, puis `web` et `worker` démarrent

## ADDED Requirements

### Requirement: Variable de la recherche SGT
La stack SHALL lire `SGT_LOOKUP` dans l'environnement du conteneur, `on` par défaut. Seul le worker MUST faire des requêtes vers Simulator Golf Tour ; le web et les migrations n'en font aucune.

#### Scenario: Recherche coupée par l'opérateur
- **GIVEN** la stack tourne avec `SGT_LOOKUP` absent
- **WHEN** l'opérateur ajoute `SGT_LOOKUP=off` dans Dokploy et redéploie
- **THEN** les recherches suivantes finissent en « Saisie manuelle » avec « Recherche SGT désactivée », sans requête vers SGT

#### Scenario: Déploiement sans sortie réseau du web
- **WHEN** le service `migrate` met en file la recherche de 6 parcours
- **THEN** ni `migrate` ni `web` ne font de requête vers `simulatorgolftour.com`
