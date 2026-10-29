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
Le motif de l'indicateur SHALL s'afficher dans sa variante sombre, lisible sur le fond `oklch(0.13 0.005 260)` et sur les surfaces, quel que soit le réglage de l'appareil (thème sombre seul, spec `visual-theme`). Son texte MUST prendre le style des métas du design : Geist Mono 500, 12 px, gris `oklch(0.68 0.01 260)`.

#### Scenario: Mode sombre
- **WHEN** l'indicateur s'affiche sur un téléphone réglé en mode clair puis en mode sombre
- **THEN** le motif s'affiche les deux fois dans sa variante sombre, et le texte « Enregistrement du joueur… » en Geist Mono 500, 12 px, gris

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

### Requirement: Attente de la saisie d'une rating
Sur la page Parcours, le système SHALL afficher l'indicateur d'attente « Enregistrement de la rating… » à côté du parcours dont la rating est envoyée, et désactiver son bouton « Enregistrer » jusqu'à la réponse. Les autres parcours restent utilisables.

#### Scenario: Rating en cours d'enregistrement
- **WHEN** l'utilisateur enregistre 71,4 / 144 pour « BETHPAGE BLACK / Red » et que la réponse n'est pas arrivée
- **THEN** la ligne de ce parcours affiche « Enregistrement de la rating… » et son bouton « Enregistrer » est désactivé

#### Scenario: Autre parcours
- **WHEN** la rating de « BETHPAGE BLACK / Red » est en cours d'enregistrement
- **THEN** le bouton « Enregistrer » de « PAYNES VALLEY / Blue » reste actif et sa ligne n'affiche aucun indicateur

### Requirement: Attente du retrait d'une rating
Sur la page Parcours, le système SHALL afficher l'indicateur d'attente « Retrait de la rating… » à côté du parcours dont la rating est retirée, et désactiver ses boutons « Enregistrer » et « Retirer la rating » jusqu'à la réponse. Les autres parcours restent utilisables.

#### Scenario: Retrait en cours
- **WHEN** l'utilisateur retire la rating de « BETHPAGE BLACK / Red » et que la réponse n'est pas arrivée
- **THEN** la ligne de ce parcours affiche « Retrait de la rating… », et ses boutons « Enregistrer » et « Retirer la rating » sont désactivés

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

### Requirement: Attente de la relance d'une lecture
Dans la liste des imports, le système SHALL afficher l'indicateur d'attente « Relance de la lecture… » sur la ligne de l'import dont la relance est envoyée, et désactiver ses actions jusqu'à la réponse. Les autres imports restent utilisables.

#### Scenario: Relance en cours
- **WHEN** l'utilisateur touche « Relancer la lecture » sur un import en « Échec » et que la réponse n'est pas arrivée
- **THEN** la ligne de cet import affiche « Relance de la lecture… », et ses actions « Relancer la lecture », « Ouvrir l'écran de correction vide » et « Supprimer » sont désactivées

### Requirement: Attentes des actions de la recherche
Sur la page Parcours, le système SHALL afficher l'indicateur d'attente « Confirmation de la rating… » à côté du parcours dont une proposition est confirmée ou un tee retenu, et « Relance de la recherche… » à côté du parcours dont la recherche est relancée ou un candidat choisi, et désactiver les boutons de ce parcours jusqu'à la réponse. Les autres parcours restent utilisables.

#### Scenario: Confirmation en cours
- **WHEN** l'utilisateur touche « Confirmer » pour « BETHPAGE BLACK / Red » et que la réponse n'est pas arrivée
- **THEN** la ligne de ce parcours affiche « Confirmation de la rating… » et ses boutons sont désactivés

#### Scenario: Relance en cours
- **WHEN** l'utilisateur touche « Relancer la recherche » pour « PAYNES VALLEY / Blue » et que la réponse n'est pas arrivée
- **THEN** la ligne de ce parcours affiche « Relance de la recherche… », ses boutons sont désactivés, et ceux de « BETHPAGE BLACK / Red » restent actifs

### Requirement: Recherche en cours signalée
Sur la page Parcours, un parcours en « Recherche en cours » SHALL montrer l'indicateur d'attente à côté de son statut, et seulement lui.

#### Scenario: Ligne en recherche
- **GIVEN** « BETHPAGE BLACK / Red » en « Recherche en cours » et « PAYNES VALLEY / Blue » « Confirmé »
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** l'indicateur d'attente s'affiche sur la ligne de « BETHPAGE BLACK / Red » et pas sur celle de « PAYNES VALLEY / Blue »
