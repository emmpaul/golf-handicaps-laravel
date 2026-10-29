# Spec Delta

## ADDED Requirements

### Requirement: Index sur la page Joueurs
Sur la page Joueurs, chaque joueur SHALL afficher son index actuel, celui d'après son dernier round par date de jeu puis par date d'enregistrement (spec `handicap-index`) : « Index 10,0 », « Index 14,5 (provisoire) » pour un index provisoire, ou « Index — » sans round.

#### Scenario: Index établi
- **GIVEN** Gab avec trois rounds de différentiels 17,0, 12,0 et 20,0
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la ligne de Gab affiche « Index 10,0 »

#### Scenario: Index provisoire
- **GIVEN** Manu avec deux rounds de différentiels 17,0 et 12,0
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la ligne de Manu affiche « Index 14,5 (provisoire) »

#### Scenario: Joueur sans round
- **GIVEN** Sean sans round
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la ligne de Sean affiche « Index — »

#### Scenario: Round ajouté avant les autres
- **GIVEN** Gab avec des rounds le 2026-10-10 (17,0) et le 2026-10-17 (12,0)
- **WHEN** l'utilisateur enregistre un round de Gab le 2026-10-01 (20,0) puis ouvre `/players`
- **THEN** la ligne de Gab affiche « Index 10,0 », l'index d'après le round du 2026-10-17
