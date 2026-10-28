# loading-feedback Specification

## Purpose
Montrer à l'utilisateur, de la même façon partout dans l'application, qu'une action est en cours et qu'il doit attendre, sans gêner la saisie ni les personnes sensibles au mouvement.

## Requirements

### Requirement: Indicateur d'attente
Pendant toute attente déclenchée par l'utilisateur ou suivie à l'écran, le système SHALL afficher un indicateur composé d'un motif animé et d'un texte court qui dit ce qui se passe et finit par « … ». Le texte MUST être annoncé aux lecteurs d'écran sans interrompre la saisie ; le motif MUST être ignoré par eux. L'indicateur SHALL disparaître dès la fin de l'attente.

#### Scenario: Attente en cours
- **WHEN** une action affiche l'attente « Enregistrement du joueur… »
- **THEN** l'écran montre le motif animé et le texte « Enregistrement du joueur… », et le lecteur d'écran annonce ce texte une fois, poliment, sans lire le motif

#### Scenario: Fin de l'attente
- **WHEN** l'attente se termine
- **THEN** le motif et le texte disparaissent sans laisser d'espace vide dans la page

#### Scenario: Attente annoncée dès son début
- **WHEN** une attente commence dans une zone de la page qui n'affichait rien
- **THEN** le lecteur d'écran annonce le texte, parce que la région annoncée existait déjà avant l'attente

### Requirement: Indicateur dans les deux thèmes
Le motif de l'indicateur SHALL suivre le thème de l'appareil : clair sur fond clair, sombre sur fond sombre.

#### Scenario: Mode sombre
- **WHEN** l'indicateur s'affiche sur un téléphone en mode sombre
- **THEN** le motif s'affiche dans sa variante sombre, lisible sur le fond `#0A111F`

### Requirement: Mouvement réduit
Quand le système d'exploitation ou le navigateur demande de réduire les animations, le motif de l'indicateur MUST rester immobile. Le texte de l'indicateur MUST rester affiché et annoncé.

#### Scenario: Réduire les animations activé
- **WHEN** l'option « réduire les animations » est active et qu'une attente commence
- **THEN** l'écran affiche le texte de l'attente avec un motif immobile

### Requirement: Attente de l'ouverture de session
Pendant l'envoi du code d'accès, la page de saisie SHALL afficher l'indicateur d'attente avec le texte « Connexion… », et le bouton « Entrer » MUST être désactivé jusqu'à la réponse.

#### Scenario: Ouverture de session en cours
- **WHEN** l'utilisateur saisit le code et appuie sur « Entrer » et que la réponse n'est pas arrivée
- **THEN** l'écran affiche « Connexion… » et le bouton « Entrer » est désactivé

#### Scenario: Réponse arrivée
- **WHEN** la réponse à un code faux arrive
- **THEN** « Connexion… » disparaît, « Code incorrect » s'affiche et le bouton « Entrer » est de nouveau actif
