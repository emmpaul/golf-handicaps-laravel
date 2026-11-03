## ADDED Requirements

### Requirement: Application gardée en mémoire par le web
Le service `web` SHALL garder l'application chargée en mémoire entre les requêtes, au lieu de la reconstruire à chaque requête. Il MUST relancer ses processus après un nombre fixé de requêtes, et un processus tombé MUST être remplacé sans que le service cesse de répondre.

#### Scenario: Requêtes servies par un processus déjà chargé
- **GIVEN** la mesure de référence de `/up` dans `docs/performance.md`, prise avant ce changement depuis le même poste
- **WHEN** l'opérateur relance la mesure contre la production après le déploiement
- **THEN** la médiane de `/up` est plus basse que celle de la référence

#### Scenario: Processus tombé
- **GIVEN** une requête fait tomber le processus qui la sert
- **WHEN** l'opérateur demande `/up` aussitôt après
- **THEN** `/up` répond 200 et le service `web` reste sain

#### Scenario: Nouvelle image
- **WHEN** l'opérateur déploie une nouvelle image
- **THEN** les pages servent les assets et le code de la nouvelle version, sans redémarrage manuel de plus

### Requirement: Aucun état d'une requête dans la suivante
Une requête servie par le web SHALL voir seulement sa propre session, ses propres messages flash, ses propres erreurs de validation et sa propre ouverture de session par le code. Ces états MUST NOT passer à une requête suivante d'un autre navigateur servie par le même processus.

#### Scenario: Session d'un autre navigateur
- **GIVEN** le navigateur A a ouvert une session, le navigateur B n'a aucun témoin
- **WHEN** B demande `/rounds` juste après une requête de A servie par le même processus
- **THEN** B est redirigé vers `/login`

#### Scenario: Message flash
- **GIVEN** le navigateur A vient d'enregistrer un round et reçoit « Round enregistré. »
- **WHEN** le navigateur B, avec sa propre session, ouvre `/rounds` sur le même processus
- **THEN** B ne voit aucun message « Round enregistré. »

#### Scenario: Erreurs de validation
- **GIVEN** le navigateur A a envoyé un joueur sans nom et reçu une erreur sur le champ du nom
- **WHEN** le navigateur B ouvre `/players` sur le même processus
- **THEN** la page de B ne porte aucune erreur de validation

#### Scenario: Code d'accès changé
- **GIVEN** `APP_PASSCODE` a changé et l'opérateur a redéployé
- **WHEN** un navigateur dont la session a été ouverte avec l'ancien code demande `/`
- **THEN** il est redirigé vers `/login`, comme avant ce changement
