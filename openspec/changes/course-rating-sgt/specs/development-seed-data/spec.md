# Spec Delta

## MODIFIED Requirements

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
