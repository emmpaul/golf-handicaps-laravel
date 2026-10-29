## ADDED Requirements

### Requirement: Sections préchargées
Dès qu'une page de l'application est affichée, le système SHALL précharger les pages des quatre onglets et du bouton Importer, sauf la page courante. Toucher un onglet dont la page est préchargée MUST afficher cette page sans attendre le serveur, puis la mettre à jour en arrière-plan si la copie a plus de 30 secondes. Une copie de plus de 5 minutes MUST NOT être affichée : la page est redemandée.

#### Scenario: Onglet touché après le préchargement
- **GIVEN** l'accueil est affiché depuis 2 secondes
- **WHEN** l'utilisateur touche l'onglet « Rounds »
- **THEN** la page Rounds s'affiche sans nouvelle requête vers `/rounds`

#### Scenario: Copie ancienne mais utilisable
- **GIVEN** la page Rounds a été préchargée il y a 2 minutes, et un round a été enregistré depuis sur un autre téléphone
- **WHEN** l'utilisateur touche « Rounds »
- **THEN** la liste préchargée s'affiche aussitôt, puis la liste se met à jour avec le nouveau round sans action de l'utilisateur

#### Scenario: Copie expirée
- **GIVEN** la page Rounds a été préchargée il y a 6 minutes
- **WHEN** l'utilisateur touche « Rounds »
- **THEN** le système redemande `/rounds` et affiche la réponse du serveur

#### Scenario: Pas de préchargement de la page courante
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** aucune requête de préchargement ne vise `/players`

### Requirement: Liens de liste préchargés
Les liens qui ouvrent un round depuis la liste des rounds, l'accueil ou l'historique d'un joueur, et ceux qui ouvrent un import depuis la liste des imports, SHALL précharger leur page quand le doigt se pose ou que le pointeur s'arrête dessus, avec la même règle de fraîcheur que les sections.

#### Scenario: Round ouvert depuis la liste
- **GIVEN** la page Rounds est affichée
- **WHEN** l'utilisateur pose le doigt sur un round puis le relâche
- **THEN** la requête de la page du round part au contact, avant le relâchement, et la page s'affiche avec ce round

### Requirement: Données à jour après une écriture
Après toute écriture réussie depuis l'application (round, joueur, alias, rating, import, correction, ouverture ou fermeture de session), le système SHALL oublier toutes les pages préchargées. La page suivante MUST refléter l'écriture.

#### Scenario: Round supprimé
- **GIVEN** les pages Rounds et Handicaps sont préchargées
- **WHEN** l'utilisateur supprime un round puis touche « Handicaps »
- **THEN** l'historique et l'index affichés ne comptent plus ce round

#### Scenario: Correction enregistrée automatiquement
- **GIVEN** la page Import est préchargée, et l'utilisateur corrige un score sur l'écran de correction
- **WHEN** la sauvegarde automatique réussit puis l'utilisateur touche « Importer »
- **THEN** la page Import est redemandée au serveur

#### Scenario: Écriture refusée
- **GIVEN** la page Joueurs est préchargée
- **WHEN** l'ajout d'un joueur est refusé pour un nom déjà pris
- **THEN** les pages préchargées restent utilisables

## MODIFIED Requirements

### Requirement: Installation sur l'écran d'accueil
Le système SHALL fournir un manifest d'application (nom « Handicaps golf », nom court « Golf », affichage `standalone`, adresse de départ `/`, langue `fr`) et une icône d'application propre, de 180 px pour iOS, et de 192 et 512 px pour le manifest. Ouverte depuis l'écran d'accueil, l'application MUST s'afficher sans barre de navigateur.

#### Scenario: Manifest servi
- **WHEN** le navigateur charge le manifest référencé par la page
- **THEN** il obtient `name` égal à « Handicaps golf », `short_name` à « Golf », `display` à `standalone`, `start_url` à `/` et des icônes PNG de 192 et 512 px

#### Scenario: Icône iOS
- **WHEN** Safari iOS cherche l'icône d'écran d'accueil de la page
- **THEN** il trouve une image PNG de 180 × 180 px

#### Scenario: Manifest et icônes sans session
- **WHEN** un client sans cookie demande le manifest ou une icône
- **THEN** il reçoit le fichier, sans redirection et sans en-tête `Set-Cookie`

#### Scenario: Icône à l'adresse par défaut
- **WHEN** un client sans cookie demande `/favicon.ico`
- **THEN** il reçoit une icône avec le statut 200, sans redirection vers `/login`, sans en-tête `Set-Cookie` et sans session créée en base
