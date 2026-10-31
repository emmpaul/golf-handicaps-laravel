## MODIFIED Requirements

### Requirement: Fermeture de session
La page d'accueil SHALL offrir un bouton « Se déconnecter » qui ferme la session de ce navigateur, efface les pages gardées sur l'appareil pour la lecture hors ligne et affiche `/login`. Les photos en attente d'envoi MUST rester sur l'appareil. Les sessions ouvertes sur d'autres appareils MUST rester valides.

#### Scenario: Déconnexion
- **GIVEN** l'utilisateur a ouvert une session
- **WHEN** il appuie sur « Se déconnecter » sur la page d'accueil
- **THEN** le système affiche `/login`, et une ouverture suivante de l'accueil redirige vers `/login`

#### Scenario: Pages gardées effacées
- **GIVEN** l'utilisateur a visité `/rounds` en ligne
- **WHEN** il appuie sur « Se déconnecter » puis ouvre `/rounds` hors ligne
- **THEN** l'écran « Hors ligne » s'affiche, sans aucun round

#### Scenario: Photos en attente gardées
- **GIVEN** une photo est en attente d'envoi
- **WHEN** l'utilisateur appuie sur « Se déconnecter »
- **THEN** la photo reste en attente d'envoi

#### Scenario: Écriture après déconnexion
- **GIVEN** l'utilisateur s'est déconnecté
- **WHEN** le navigateur envoie une écriture vers une route protégée en demandant du JSON
- **THEN** le système répond 401 et n'exécute rien

#### Scenario: Autre appareil
- **GIVEN** une session est ouverte sur le téléphone et une autre sur l'ordinateur
- **WHEN** l'utilisateur se déconnecte sur le téléphone
- **THEN** l'ordinateur affiche toujours l'accueil sans redemander le code
