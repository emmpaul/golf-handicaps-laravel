# Spec Delta

## ADDED Requirements

### Requirement: Attentes des actions de la recherche
Sur la page Parcours, le système SHALL afficher l'indicateur d'attente « Confirmation de la rating… » à côté du parcours dont une proposition est confirmée ou un tee retenu, et « Relance de la recherche… » à côté du parcours dont la recherche est relancée ou un candidat choisi, et désactiver les boutons de ce parcours jusqu'à la réponse. Les autres parcours restent utilisables.

#### Scenario: Confirmation en cours
- **WHEN** l'utilisateur touche « Confirmer » pour « BETHPAGE BLACK / Red » et que la réponse n'est pas arrivée
- **THEN** la ligne de ce parcours affiche « Confirmation de la rating… » et ses boutons sont désactivés

#### Scenario: Relance en cours
- **WHEN** l'utilisateur touche « Relancer la recherche » pour « PAYNES VALLEY / Blue » et que la réponse n'est pas arrivée
- **THEN** la ligne de ce parcours affiche « Relance de la recherche… », ses boutons sont désactivés, et ceux de « BETHPAGE BLACK / Red » restent actifs

### Requirement: Recherche en cours signalée
Sur la page Parcours, un parcours en « Recherche en cours » SHALL montrer l'indicateur d'attente à côté de son statut, et seulement lui.

#### Scenario: Ligne en recherche
- **GIVEN** « BETHPAGE BLACK / Red » en « Recherche en cours » et « PAYNES VALLEY / Blue » « Confirmé »
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** l'indicateur d'attente s'affiche sur la ligne de « BETHPAGE BLACK / Red » et pas sur celle de « PAYNES VALLEY / Blue »
