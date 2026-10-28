# Spec Delta

## ADDED Requirements

### Requirement: Index et rating de démonstration
Le jeu de démonstration SHALL saisir la rating 71,4 et le slope 144 de « BETHPAGE BLACK / Red » et laisser ses autres parcours sans rating. Chaque joueur de chaque round de démonstration MUST avoir son historique d'index calculé, comme après une saisie dans l'application. `make fresh` n'écrit aucune rating.

#### Scenario: Démonstration calculée
- **WHEN** on lance `make demo`
- **THEN** chaque joueur de chaque round a son différentiel, son index d'avant et son index d'après, et la page Joueurs montre un index pour chaque joueur qui a au moins un round

#### Scenario: Parcours avec et sans rating
- **WHEN** on lance `make demo` puis qu'on ouvre `/courses`
- **THEN** « BETHPAGE BLACK / Red » affiche « 71,4 / 144 » et les autres parcours « sans rating »

#### Scenario: Base minimale
- **WHEN** on lance `make fresh`
- **THEN** la base ne contient aucune rating ni aucun historique d'index

#### Scenario: Fabriques des nouveaux modèles
- **WHEN** un test fabrique une rating et une ligne d'historique
- **THEN** chacune est valide en base sans autre préparation que sa fabrique
