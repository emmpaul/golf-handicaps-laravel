# Spec Delta

## ADDED Requirements

### Requirement: Attentes de l'envoi d'une photo
Sur l'écran d'import, le système SHALL afficher l'indicateur d'attente pendant la préparation de la photo choisie dans le navigateur (« Préparation de la photo… ») puis pendant son envoi au serveur (« Envoi de la photo… »). Les actions d'envoi MUST rester désactivées jusqu'à la fin de l'envoi.

#### Scenario: Photo lourde choisie
- **WHEN** l'utilisateur choisit une photo et que sa préparation n'est pas terminée
- **THEN** l'écran affiche « Préparation de la photo… » et « Choisir une photo » est désactivé

#### Scenario: Envoi en cours
- **WHEN** la photo est prête et que l'envoi au serveur n'est pas terminé
- **THEN** l'écran affiche « Envoi de la photo… », annoncé une fois par le lecteur d'écran

#### Scenario: Fin de l'envoi
- **WHEN** l'envoi de la photo se termine
- **THEN** l'indicateur disparaît et le message « Lecture en file d'attente. Tu peux envoyer la photo suivante. » s'affiche

### Requirement: Imports en attente de lecture
Dans la liste des imports, chaque import « En attente » ou « Lecture en cours » SHALL afficher le motif animé à côté de son état. Les imports « Prêt à vérifier » et « Échec » MUST NOT l'afficher. Le libellé de l'état reste le texte annoncé.

#### Scenario: Lecture en cours
- **WHEN** la liste des imports contient un import « Lecture en cours » et un import « Prêt à vérifier »
- **THEN** seul l'import « Lecture en cours » affiche le motif animé

#### Scenario: Lecture terminée
- **WHEN** un import affiché « En attente » passe à « Prêt à vérifier » au rafraîchissement de la liste
- **THEN** son motif animé disparaît

### Requirement: Attentes de la suppression d'un import
Le système SHALL afficher l'indicateur d'attente « Suppression de l’import… » dans la boîte de confirmation pendant la suppression d'un import, et désactiver ses boutons jusqu'à la réponse.

#### Scenario: Suppression en cours
- **WHEN** l'utilisateur confirme la suppression d'un import et que la réponse n'est pas arrivée
- **THEN** la boîte de confirmation affiche « Suppression de l’import… »
