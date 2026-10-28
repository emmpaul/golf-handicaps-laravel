## ADDED Requirements

### Requirement: Attente de l'ouverture de session
Pendant l'envoi du code d'accès, la page de saisie SHALL afficher l'indicateur d'attente avec le texte « Connexion… », et le bouton « Entrer » MUST être désactivé jusqu'à la réponse.

#### Scenario: Ouverture de session en cours
- **WHEN** l'utilisateur saisit le code et appuie sur « Entrer » et que la réponse n'est pas arrivée
- **THEN** l'écran affiche « Connexion… » et le bouton « Entrer » est désactivé

#### Scenario: Réponse arrivée
- **WHEN** la réponse à un code faux arrive
- **THEN** « Connexion… » disparaît, « Code incorrect » s'affiche et le bouton « Entrer » est de nouveau actif
