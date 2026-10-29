# Spec Delta

## ADDED Requirements

### Requirement: Attente de la saisie d'une rating
Sur la page Parcours, le système SHALL afficher l'indicateur d'attente « Enregistrement de la rating… » à côté du parcours dont la rating est envoyée, et désactiver son bouton « Enregistrer » jusqu'à la réponse. Les autres parcours restent utilisables.

#### Scenario: Rating en cours d'enregistrement
- **WHEN** l'utilisateur enregistre 71,4 / 144 pour « BETHPAGE BLACK / Red » et que la réponse n'est pas arrivée
- **THEN** la ligne de ce parcours affiche « Enregistrement de la rating… » et son bouton « Enregistrer » est désactivé

#### Scenario: Autre parcours
- **WHEN** la rating de « BETHPAGE BLACK / Red » est en cours d'enregistrement
- **THEN** le bouton « Enregistrer » de « PAYNES VALLEY / Blue » reste actif et sa ligne n'affiche aucun indicateur

### Requirement: Attente du retrait d'une rating
Sur la page Parcours, le système SHALL afficher l'indicateur d'attente « Retrait de la rating… » à côté du parcours dont la rating est retirée, et désactiver ses boutons « Enregistrer » et « Retirer la rating » jusqu'à la réponse. Les autres parcours restent utilisables.

#### Scenario: Retrait en cours
- **WHEN** l'utilisateur retire la rating de « BETHPAGE BLACK / Red » et que la réponse n'est pas arrivée
- **THEN** la ligne de ce parcours affiche « Retrait de la rating… », et ses boutons « Enregistrer » et « Retirer la rating » sont désactivés
