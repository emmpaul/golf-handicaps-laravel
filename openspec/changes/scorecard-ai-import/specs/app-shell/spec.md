# Spec Delta

## MODIFIED Requirements

### Requirement: Adresses des sections
Les liens de section SHALL viser `/import` (Importer), `/rounds` (Rounds), `/handicaps` (Handicaps), `/strokes` (Coups) et `/players` (Joueurs), dans cet ordre. Tant qu'une section n'a pas sa page, son adresse et ses sous-adresses MUST répondre 200 avec une page « Section à venir » dans le cadre commun, sans donnée du groupe.

#### Scenario: Section sans page
- **GIVEN** la section Handicaps n'a pas encore sa page
- **WHEN** l'utilisateur ouvre `/handicaps`
- **THEN** le système répond 200 avec le titre « Handicaps », le texte « Section à venir », la navigation et la ligne de version

#### Scenario: Sous-adresse d'une section sans page
- **GIVEN** la section Handicaps n'a pas encore sa page
- **WHEN** l'utilisateur ouvre `/handicaps/0190a6b2-7c3e-7000-8000-000000000001`
- **THEN** le système répond 200 avec la page « Section à venir » de Handicaps

#### Scenario: Section qui a sa page
- **GIVEN** les sections Importer, Rounds et Joueurs ont leur page
- **WHEN** l'utilisateur ouvre `/import`, `/rounds` puis `/players`
- **THEN** aucune des trois réponses ne contient « Section à venir »

#### Scenario: Sous-adresse inconnue de l'import
- **WHEN** l'utilisateur ouvre `/import/xyz`
- **THEN** le système répond 404
