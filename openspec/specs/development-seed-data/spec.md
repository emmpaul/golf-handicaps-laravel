# development-seed-data Specification

## Purpose
Donner aux développeurs et aux agents une base locale remplie de données réalistes et fictives en une commande, sans jamais pouvoir écrire ces données en production.

## Requirements

### Requirement: Base de développement minimale
`make fresh` SHALL recréer la base de développement, appliquer toutes les migrations et n'écrire que les joueurs de développement (Gab, Manu, Sean et leurs alias), sans round.

#### Scenario: Base recréée
- **GIVEN** une base de développement avec des rounds
- **WHEN** on lance `make fresh`
- **THEN** la base contient exactement les joueurs Gab, Manu et Sean et aucun round

### Requirement: Jeu de démonstration
`make demo` SHALL recréer la base de développement et écrire le jeu de démonstration complet : les joueurs de développement, le round de référence `IMG_3064` avec sa photo, et au moins 20 autres rounds répartis sur au moins trois parcours et plusieurs mois, de 1 à 4 joueurs. Deux lancements MUST produire les mêmes parcours, dates et scores.

#### Scenario: Écrans remplis
- **WHEN** on lance `make demo` puis qu'on ouvre `/rounds` et `/players`
- **THEN** la liste montre au moins 21 rounds, dont le round de référence avec sa photo, et la page Joueurs montre Gab, Manu et Sean

#### Scenario: Jeu déterministe
- **WHEN** on lance `make demo` deux fois
- **THEN** les deux bases ont les mêmes rounds (parcours, tees, date, joueurs et scores)

#### Scenario: Rounds cohérents
- **WHEN** on lance `make demo`
- **THEN** chaque round écrit passe les contrôles de cohérence de la saisie manuelle

### Requirement: Démonstration sur une base vide seulement
Le jeu de démonstration MUST refuser de s'écrire sur une base qui contient déjà un joueur ou un round, sans rien écrire.

#### Scenario: Base déjà remplie
- **GIVEN** une base qui contient le joueur Gab
- **WHEN** on lance le seeder de démonstration seul, sans recréer la base
- **THEN** il échoue avec un message qui le dit, et la base ne change pas

### Requirement: Aucune donnée de démonstration en production
Toute écriture de données de développement ou de démonstration MUST échouer quand l'application tourne en production, quel que soit le chemin (seeding par défaut, seeder appelé seul, `migrate --seed`), sans rien écrire. Un test d'architecture MUST échouer quand un seeder ne passe pas par cette garde.

#### Scenario: Seeder lancé en production
- **GIVEN** `APP_ENV=production`
- **WHEN** on lance `php artisan db:seed --force`
- **THEN** la commande échoue avec « Les données de démonstration ne peuvent pas être écrites en production. » et aucun joueur n'est créé

#### Scenario: Seeder sans garde
- **WHEN** un seeder est ajouté sans passer par la garde de production
- **THEN** `make arch-test` échoue en nommant le seeder

### Requirement: Données fictives
Les données de développement et de démonstration MUST NOT contenir de renseignement personnel réel au-delà des prénoms du groupe déjà publics dans le dépôt Next et de la carte de référence `IMG_3064`. Elles MUST être écrites sans accès réseau.

#### Scenario: Démonstration hors ligne
- **GIVEN** une machine sans accès à Internet
- **WHEN** on lance `make demo`
- **THEN** la commande réussit

### Requirement: Fabrique de chaque modèle
Chaque modèle applicatif SHALL avoir une fabrique qui produit un enregistrement valide, avec un état pour chaque variante métier. Un test MUST échouer quand un modèle applicatif n'a pas de fabrique.

#### Scenario: Modèle sans fabrique
- **WHEN** un modèle applicatif est ajouté sans fabrique
- **THEN** `make test-backend` échoue en nommant le modèle

#### Scenario: Round fabriqué
- **WHEN** un test fabrique un round avec deux joueurs
- **THEN** le round a 18 trous, deux joueurs et 36 scores, et passe les contrôles de cohérence

### Requirement: Index et rating de démonstration
Le jeu de démonstration SHALL confirmer la rating 71,4 et le slope 144 de « BETHPAGE BLACK / Red » depuis une proposition SGT, montrer « PAYNES VALLEY / Blue » en « Proposition à confirmer » et « CALA NOVA / White » « Introuvable sur SGT », sans requête réseau ni recherche mise en file. Chaque joueur de chaque round de démonstration MUST avoir son historique d'index calculé, comme après une saisie dans l'application. `make fresh` n'écrit aucune rating.

#### Scenario: Démonstration calculée
- **WHEN** on lance `make demo`
- **THEN** chaque joueur de chaque round a son différentiel, son index d'avant et son index d'après, et la page Joueurs montre un index pour chaque joueur qui a au moins un round

#### Scenario: Parcours avec et sans rating
- **WHEN** on lance `make demo` puis qu'on ouvre `/courses`
- **THEN** « BETHPAGE BLACK / Red » affiche « 71,4 / 144 » et « Confirmé », « PAYNES VALLEY / Blue » « sans rating » et « Proposition à confirmer », « CALA NOVA / White » « sans rating » et « Introuvable sur SGT »

#### Scenario: Aucune recherche en file
- **WHEN** on lance `make demo`
- **THEN** la file ne contient aucune recherche de rating

#### Scenario: Base minimale
- **WHEN** on lance `make fresh`
- **THEN** la base ne contient aucune rating ni aucun historique d'index

#### Scenario: Fabriques des nouveaux modèles
- **WHEN** un test fabrique une rating et une ligne d'historique
- **THEN** chacune est valide en base sans autre préparation que sa fabrique

#### Scenario: Rating dans chaque état
- **WHEN** un test fabrique une rating dans chacun des huit états de la recherche
- **THEN** chacune est valide en base, et seule la rating « Confirmé » a une rating et un slope
