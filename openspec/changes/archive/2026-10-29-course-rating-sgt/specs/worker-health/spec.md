# Spec Delta

## ADDED Requirements

### Requirement: Recherche de rating bornée dans le temps
Une recherche de rating SHALL abandonner chaque requête vers Simulator Golf Tour qui n'a pas répondu en 20 secondes, et toute la recherche au bout de 120 secondes. Un Simulator Golf Tour qui ne répond plus MUST NOT faire passer le worker en malade ni bloquer les autres jobs de la file.

#### Scenario: Simulator Golf Tour muet
- **GIVEN** Simulator Golf Tour accepte les connexions mais ne répond jamais
- **WHEN** le worker traite la recherche de « BETHPAGE BLACK / Red »
- **THEN** chaque tentative s'arrête au bout de 20 secondes, le parcours finit en « Recherche échouée » avec « SGT injoignable », et le service `worker` reste sain

#### Scenario: Import derrière une recherche lente
- **GIVEN** une recherche de rating vers un Simulator Golf Tour muet, puis un import de photo, dans la file
- **WHEN** le worker traite la file
- **THEN** l'import est lu après la fin de la première tentative de la recherche, sans attendre plus de 20 secondes de requête vers SGT
