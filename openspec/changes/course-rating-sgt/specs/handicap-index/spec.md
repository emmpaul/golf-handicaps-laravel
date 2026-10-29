# Spec Delta

## MODIFIED Requirements

### Requirement: Différentiel d'un round
Le système SHALL calculer le différentiel d'un joueur sur un round : (113 / slope) × (score ajusté − course rating), arrondi au dixième le plus proche, 0,05 arrondi vers le haut. La course rating et le slope sont ceux confirmés pour le parcours et les tees du round (spec `course-ratings`). Sans rating confirmée, la course rating vaut le par total du round et le slope 113.

#### Scenario: Différentiels sans rating
- **GIVEN** « BETHPAGE BLACK / Red » sans rating
- **WHEN** la carte `IMG_3064` (par 71) est enregistrée
- **THEN** le différentiel de Gab vaut 1,0 et celui de Manu 17,0

#### Scenario: Différentiels avec rating
- **GIVEN** « BETHPAGE BLACK / Red » avec une rating de 71,4 et un slope de 144
- **WHEN** la carte `IMG_3064` est enregistrée
- **THEN** le différentiel de Gab vaut 0,5 et celui de Manu 13,0

#### Scenario: Round sous le par
- **GIVEN** « BETHPAGE BLACK / Red » sans rating
- **WHEN** Gab joue la carte `IMG_3064` avec 3 au trou 1 au lieu de 5
- **THEN** son différentiel vaut −1,0

#### Scenario: Proposition en attente
- **GIVEN** « BETHPAGE BLACK / Red » avec la proposition 71,4 / 144, non confirmée
- **WHEN** la carte `IMG_3064` est enregistrée
- **THEN** le différentiel de Gab vaut 1,0 et celui de Manu 17,0

### Requirement: Recalcul complet
Après tout ajout, correction ou suppression de round, et après toute saisie, confirmation, choix d'un tee, correction ou retrait de la rating d'un parcours, le système SHALL recalculer les différentiels et tout l'historique d'index des joueurs concernés, y compris pour les rounds postérieurs à la date modifiée, avant de répondre. Un joueur retiré d'un round corrigé MUST être recalculé lui aussi. Une écriture refusée MUST NOT changer aucun historique.

#### Scenario: Round ajouté dans le passé
- **GIVEN** Gab avec un round le 2026-10-10 (différentiel 17,0) et un le 2026-10-17 (12,0)
- **WHEN** l'utilisateur enregistre un round de Gab daté du 2026-10-01 avec un différentiel de 20,0
- **THEN** l'index de Gab après le round du 2026-10-10 vaut 18,5 et après celui du 2026-10-17 vaut 10,0

#### Scenario: Score corrigé
- **GIVEN** Manu avec 88 brut le 2026-10-10 et la carte `IMG_3064` le 2026-10-17, sans rating
- **WHEN** l'utilisateur corrige le trou 1 de Manu de 7 à 6 sur la carte `IMG_3064`
- **THEN** le différentiel de Manu sur ce round vaut 16,0 et son index après ce round 16,5

#### Scenario: Joueur remplacé
- **GIVEN** Manu avec 88 brut le 2026-10-10 et la carte `IMG_3064` le 2026-10-17
- **WHEN** l'utilisateur associe la ligne de Manu de la carte `IMG_3064` à Sean
- **THEN** Manu n'a plus que son round du 2026-10-10, avec un index après de 17,0, et Sean a un round avec un différentiel de 17,0

#### Scenario: Round supprimé
- **GIVEN** Gab avec des rounds le 2026-10-01 (20,0), le 2026-10-10 (17,0) et le 2026-10-17 (12,0)
- **WHEN** l'utilisateur supprime le round du 2026-10-01
- **THEN** l'index de Gab après le round du 2026-10-10 vaut 17,0 et après celui du 2026-10-17 vaut 14,5

#### Scenario: Rating saisie après coup
- **GIVEN** Manu avec la carte `IMG_3064` sur « BETHPAGE BLACK / Red » sans rating, différentiel 17,0
- **WHEN** l'utilisateur saisit 71,4 et 144 pour « BETHPAGE BLACK / Red »
- **THEN** le différentiel de Manu sur ce round vaut 13,0, et ses index après ce round et après les suivants sont recalculés

#### Scenario: Proposition confirmée
- **GIVEN** Manu avec 88 brut le 2026-10-10 sur un autre parcours de par 71 sans rating (différentiel 17,0) et la carte `IMG_3064` le 2026-10-17 sur « BETHPAGE BLACK / Red », avec la proposition 71,4 / 144
- **WHEN** l'utilisateur confirme la proposition
- **THEN** le différentiel de Manu sur la carte `IMG_3064` vaut 13,0 et son index après ce round 15,0

#### Scenario: Rating retirée
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144 et la carte `IMG_3064` enregistrée, différentiels de Gab 0,5 et de Manu 13,0
- **WHEN** l'utilisateur retire la rating de « BETHPAGE BLACK / Red »
- **THEN** le différentiel de Gab sur ce round vaut 1,0 et celui de Manu 17,0, et leurs index après ce round et après les suivants sont recalculés

#### Scenario: Correction refusée
- **GIVEN** la carte `IMG_3064` enregistrée
- **WHEN** l'utilisateur la corrige avec un par 6 et l'enregistrement est refusé
- **THEN** l'historique de Gab et de Manu reste inchangé
