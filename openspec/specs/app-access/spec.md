# app-access Specification

## Purpose
Réserver l'application au groupe d'amis par un code d'accès partagé unique et une session de navigateur, sans comptes individuels.

## Requirements

### Requirement: Accès protégé par code partagé
Le système MUST refuser toute page et toute action de lecture ou d'écriture tant que le visiteur n'a pas ouvert de session avec le code d'accès configuré sur le serveur. Seules la page de saisie du code, la route de version, le contrôle de santé `/up` et les fichiers statiques (manifest, icônes, assets compilés), qui ne contiennent aucune donnée du groupe, restent accessibles sans session.

#### Scenario: Visiteur sans session
- **WHEN** un visiteur sans session ouvre la page d'accueil
- **THEN** le système le redirige vers `/login`

#### Scenario: Page de section sans session
- **WHEN** un visiteur sans session ouvre `/rounds/0190a6b2-7c3e-7000-8000-000000000001`
- **THEN** le système le redirige vers `/login`

#### Scenario: Adresse inconnue sans session
- **WHEN** un visiteur sans session ouvre `/icon-admin`
- **THEN** le système le redirige vers `/login`

#### Scenario: Adresse inconnue avec session
- **GIVEN** le visiteur a ouvert une session
- **WHEN** il ouvre `/icon-admin`
- **THEN** le système répond 404

#### Scenario: Route de version sans session
- **WHEN** un client sans session demande `GET /api/version`
- **THEN** le système répond 200, sans redirection

#### Scenario: Route proche de la route de version
- **WHEN** un client sans session demande `GET /api/version/x` ou `GET /api/versions`
- **THEN** le système répond 404 sans aucune donnée du groupe

#### Scenario: Contrôle de santé sans session
- **WHEN** un client sans session demande `GET /up`
- **THEN** le système répond 200

#### Scenario: Toute nouvelle route protégée
- **WHEN** une route applicative est ajoutée hors de `/login`, `/api/version` et `/up`
- **THEN** un test échoue tant que cette route ne demande pas la session du code

### Requirement: Écriture refusée sans session
Une requête d'écriture (`POST`, `PUT`, `PATCH`, `DELETE`) sans session valide MUST être rejetée sans rien enregistrer. Un client qui n'est pas Inertia SHALL recevoir 401 avec `{ "error": "Session requise" }` ; une visite Inertia SHALL être renvoyée vers `/login`.

#### Scenario: Écriture d'un client JSON
- **WHEN** un client sans session envoie `POST /logout` en demandant du JSON
- **THEN** le système répond 401 avec `{ "error": "Session requise" }`

#### Scenario: Écriture d'une visite Inertia
- **WHEN** une visite Inertia sans session envoie une écriture vers une route protégée
- **THEN** le système n'exécute pas l'écriture et le navigateur affiche `/login`

#### Scenario: Jeton CSRF périmé après déconnexion
- **GIVEN** le navigateur s'est déconnecté et garde le jeton CSRF de son ancienne session
- **WHEN** il envoie une écriture vers une route protégée en demandant du JSON
- **THEN** le système répond 401, pas 419

### Requirement: Page de saisie du code
La page `/login` SHALL afficher, hors du cadre commun et sans navigation, la marque, le titre « Handicaps golf », un champ masqué « Code d'accès », le bouton « Entrer » et la ligne de version en bas de page. Un visiteur qui a déjà une session valide et ouvre `/login` SHALL être renvoyé à l'accueil.

#### Scenario: Page vue sans session
- **WHEN** un visiteur sans session ouvre `/login`
- **THEN** il voit le titre « Handicaps golf », le champ « Code d'accès » de type mot de passe et le bouton « Entrer », sans les liens de section

#### Scenario: Visiteur déjà connecté
- **GIVEN** le visiteur a ouvert une session
- **WHEN** il ouvre `/login`
- **THEN** le système le redirige vers l'accueil

### Requirement: Ouverture de session
Quand le code saisi est exact, le système SHALL ouvrir une session dans le navigateur avec un nouvel identifiant de session, puis afficher l'accueil. Quand le code est faux ou vide, le système SHALL afficher « Code incorrect » sous le champ et n'ouvrir aucune session. Le code saisi MUST NOT être écrit dans un journal, dans la session ni dans une réponse.

#### Scenario: Code correct
- **GIVEN** le code configuré est `birdie-2026`
- **WHEN** le visiteur saisit `birdie-2026` et appuie sur « Entrer »
- **THEN** le système ouvre une session et affiche l'accueil

#### Scenario: Nouvel identifiant de session
- **GIVEN** le navigateur a un identifiant de session avant de saisir le code
- **WHEN** il saisit le bon code
- **THEN** l'identifiant de session après connexion est différent

#### Scenario: Code incorrect
- **GIVEN** le code configuré est `birdie-2026`
- **WHEN** le visiteur saisit `Birdie-2026`
- **THEN** le système affiche « Code incorrect », le visiteur reste sur `/login` et l'accueil le redirige encore vers `/login`

#### Scenario: Code vide
- **WHEN** le visiteur envoie le formulaire avec un code vide
- **THEN** le système affiche « Code incorrect » et n'ouvre aucune session

### Requirement: Durée de la session
Une session ouverte par le code SHALL rester valable 90 jours après la dernière requête de ce navigateur : chaque requête repart pour 90 jours. Après 90 jours sans requête, la session MUST être refusée.

#### Scenario: Visite régulière
- **GIVEN** une session ouverte il y a 120 jours et dont la dernière requête date de 10 jours
- **WHEN** le navigateur ouvre l'accueil
- **THEN** le système affiche l'accueil

#### Scenario: Session inactive
- **GIVEN** une session dont la dernière requête date de 91 jours
- **WHEN** le navigateur ouvre l'accueil
- **THEN** le système le redirige vers `/login`

### Requirement: Changement du code d'accès
Quand le code configuré sur le serveur change, toute session ouverte avec l'ancien code MUST être refusée, sur tous les appareils. Le nouveau code SHALL ouvrir une session normalement.

#### Scenario: Code changé sur le serveur
- **GIVEN** un navigateur a ouvert une session avec le code `birdie-2026`
- **WHEN** le code configuré devient `eagle-2027` et le navigateur ouvre l'accueil
- **THEN** le système le redirige vers `/login`

#### Scenario: Nouveau code saisi
- **GIVEN** le code configuré est devenu `eagle-2027`
- **WHEN** le visiteur saisit `eagle-2027`
- **THEN** le système ouvre une session et affiche l'accueil

### Requirement: Limite des essais
Le système MUST accepter au plus 10 essais de code par minute et par adresse IP de client. Au-delà, il SHALL afficher « Trop d'essais. Réessaie dans une minute. » sans vérifier le code ni ouvrir de session, même si le code est exact.

#### Scenario: Onzième essai dans la minute
- **GIVEN** une même IP a envoyé 10 codes faux dans la dernière minute
- **WHEN** elle envoie le bon code
- **THEN** le système affiche « Trop d'essais. Réessaie dans une minute. » et n'ouvre aucune session

#### Scenario: Autre IP
- **GIVEN** l'IP `203.0.113.7` a atteint la limite
- **WHEN** l'IP `203.0.113.8` envoie le bon code
- **THEN** le système ouvre une session

#### Scenario: Minute écoulée
- **GIVEN** une IP a atteint la limite
- **WHEN** elle envoie le bon code 61 secondes après son premier essai
- **THEN** le système ouvre une session

### Requirement: Démarrage sans code configuré
Le système MUST refuser de servir toute requête HTTP, `/up` compris, quand aucun code d'accès n'est configuré, au lieu de laisser l'application ouverte. Le message d'erreur MUST nommer la variable `APP_PASSCODE`. Les commandes artisan MUST continuer de tourner sans code.

#### Scenario: Variable absente
- **GIVEN** `APP_PASSCODE` est vide
- **WHEN** un client demande `GET /up`
- **THEN** le système répond 500 et le journal contient un message qui nomme `APP_PASSCODE`

#### Scenario: Commande artisan sans code
- **GIVEN** `APP_PASSCODE` est vide
- **WHEN** on lance `php artisan config:cache`
- **THEN** la commande réussit

### Requirement: Fermeture de session
La page d'accueil SHALL offrir un bouton « Se déconnecter » qui ferme la session de ce navigateur et affiche `/login`. Les sessions ouvertes sur d'autres appareils MUST rester valides.

#### Scenario: Déconnexion
- **GIVEN** l'utilisateur a ouvert une session
- **WHEN** il appuie sur « Se déconnecter » sur la page d'accueil
- **THEN** le système affiche `/login`, et une ouverture suivante de l'accueil redirige vers `/login`

#### Scenario: Écriture après déconnexion
- **GIVEN** l'utilisateur s'est déconnecté
- **WHEN** le navigateur envoie une écriture vers une route protégée en demandant du JSON
- **THEN** le système répond 401 et n'exécute rien

#### Scenario: Autre appareil
- **GIVEN** une session est ouverte sur le téléphone et une autre sur l'ordinateur
- **WHEN** l'utilisateur se déconnecte sur le téléphone
- **THEN** l'ordinateur affiche toujours l'accueil sans redemander le code
