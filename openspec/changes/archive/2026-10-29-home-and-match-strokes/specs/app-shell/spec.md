# Spec Delta

## MODIFIED Requirements

### Requirement: Adresses des sections
Les liens de section SHALL viser `/import` (Importer), `/rounds` (Rounds), `/handicaps` (Handicaps), `/strokes` (Coups) et `/players` (Joueurs), dans cet ordre. Chaque section MUST servir sa propre page ; aucune adresse ne sert plus de page « Section à venir », et une sous-adresse qu'aucune page ne sert répond 404.

#### Scenario: Section sans page
- **GIVEN** les cinq sections ont leur page
- **WHEN** l'utilisateur ouvre `/handicaps` puis `/strokes`
- **THEN** le système répond 200 avec la page Handicaps puis la page Coups, et aucune des deux réponses ne contient « Section à venir »

#### Scenario: Sous-adresse d'une section sans page
- **WHEN** l'utilisateur ouvre `/handicaps/0190a6b2-7c3e-7000-8000-000000000001`, puis `/strokes/xyz`
- **THEN** le système répond 404 les deux fois

#### Scenario: Section qui a sa page
- **WHEN** l'utilisateur ouvre `/import`, `/rounds`, `/handicaps`, `/strokes` puis `/players`
- **THEN** chacune répond 200 avec la page de sa section, et aucune réponse ne contient « Section à venir »

#### Scenario: Sous-adresse inconnue de l'import
- **WHEN** l'utilisateur ouvre `/import/xyz`
- **THEN** le système répond 404
