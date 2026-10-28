# Spec Delta

## ADDED Requirements

### Requirement: Attentes de l'éditeur de round
Dans l'éditeur de round, le système SHALL afficher l'indicateur d'attente pendant l'enregistrement du round (« Enregistrement du round… ») et pendant la création d'un joueur depuis une ligne (« Création du joueur… »). Le bouton d'enregistrement MUST rester désactivé, et l'indicateur affiché, jusqu'à l'ouverture de la page suivante quand l'enregistrement réussit.

#### Scenario: Enregistrement d'un round
- **WHEN** l'utilisateur clique sur « Enregistrer » et que la réponse du serveur n'est pas arrivée
- **THEN** l'écran affiche « Enregistrement du round… » et le bouton reste désactivé

#### Scenario: Création d'un joueur depuis une ligne
- **WHEN** l'utilisateur crée le joueur « Julien » depuis la ligne 1 et que la création n'est pas terminée
- **THEN** l'écran affiche « Création du joueur… » à côté de la ligne 1

### Requirement: Attentes des joueurs et des rounds
Le système SHALL afficher l'indicateur d'attente pendant l'ajout ou le renommage d'un joueur (« Enregistrement du joueur… ») et pendant la suppression d'un round (« Suppression du round… »), et désactiver le bouton qui l'a déclenchée jusqu'à la réponse.

#### Scenario: Ajout d'un joueur
- **WHEN** l'utilisateur ajoute le joueur « Alex » et que la réponse n'est pas arrivée
- **THEN** la page Joueurs affiche « Enregistrement du joueur… » et le bouton d'ajout est désactivé

#### Scenario: Suppression d'un round
- **WHEN** l'utilisateur confirme la suppression d'un round et que la réponse n'est pas arrivée
- **THEN** la boîte de confirmation affiche « Suppression du round… »
