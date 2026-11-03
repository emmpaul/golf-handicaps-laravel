## MODIFIED Requirements

### Requirement: Attentes de l'envoi d'une photo
Sur l'écran d'import, le système SHALL afficher l'indicateur d'attente pendant la préparation de la photo choisie dans le navigateur (« Préparation de la photo… ») puis, avec du réseau, pendant son envoi au serveur (« Envoi de la photo… »). Les actions d'envoi MUST rester désactivées jusqu'à la fin de la préparation et de l'envoi. Sans réseau, l'indicateur MUST disparaître dès que la photo est gardée sur l'appareil, sans afficher « Envoi de la photo… ».

#### Scenario: Photo lourde choisie
- **WHEN** l'utilisateur choisit une photo et que sa préparation n'est pas terminée
- **THEN** l'écran affiche « Préparation de la photo… » et « Choisir une photo » est désactivé

#### Scenario: Envoi en cours
- **WHEN** la photo est prête, que l'appareil a du réseau et que l'envoi au serveur n'est pas terminé
- **THEN** l'écran affiche « Envoi de la photo… », annoncé une fois par le lecteur d'écran

#### Scenario: Fin de l'envoi
- **WHEN** l'envoi de la photo se termine
- **THEN** l'indicateur disparaît et le message « Lecture en file d'attente. Tu peux envoyer la photo suivante. » s'affiche

#### Scenario: Photo gardée hors ligne
- **WHEN** l'appareil est hors ligne et que la préparation de la photo se termine
- **THEN** l'indicateur disparaît, « Envoi de la photo… » ne s'affiche pas, et les tuiles « Prendre une photo » et « Choisir une photo » redeviennent actives

## ADDED Requirements

### Requirement: Envoi des photos en attente signalé
Pendant l'envoi des photos en attente au retour du réseau, la section « EN ATTENTE D'ENVOI » SHALL afficher l'indicateur d'attente « Envoi de N photos… » (« Envoi d'1 photo… » pour une seule), annoncé une fois par le lecteur d'écran. « Retirer » MUST être désactivé sur la photo en cours d'envoi.

#### Scenario: Deux photos à envoyer
- **GIVEN** deux photos sont en attente d'envoi sur l'écran Importer
- **WHEN** le réseau revient et que le premier envoi n'est pas terminé
- **THEN** la section affiche « Envoi de 2 photos… » et « Retirer » est désactivé sur la plus ancienne
