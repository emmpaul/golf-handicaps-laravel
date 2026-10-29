## MODIFIED Requirements

### Requirement: Photo et explication sur la page d'un round
La page d'un round SHALL garder, sous la légende, la photo de la carte quand elle existe (rayon 12 px, proportions 16/10, fond `oklch(0.2 0.006 260)`) puis le lien « Comment l’index est calculé » vers la page « Calcul de l'index » (spec `handicap-index`), que le design ne dessine pas.

#### Scenario: Round avec photo
- **GIVEN** le round de référence de la base de démonstration, qui a une photo
- **WHEN** l'utilisateur ouvre le round à 390 px
- **THEN** l'image « Carte du round » s'affiche sous la légende, sur toute la largeur moins 20 px de chaque côté, aux coins de 12 px

#### Scenario: Lien vers le calcul
- **WHEN** l'utilisateur ouvre le round de référence et touche « Comment l’index est calculé »
- **THEN** la page `/handicaps/calcul` s'ouvre
