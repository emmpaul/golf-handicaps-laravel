# deployment Specification

## Purpose
Faire tourner l'application sur un serveur Docker sous Dokploy, à partir d'une image publiée et de variables d'environnement : démarrage ordonné, état de santé visible, données conservées et sauvegardées, retour arrière possible.

## Requirements

### Requirement: Image unique sans secret
Le système SHALL être livré comme une seule image qui sert le web, le worker et les migrations selon la commande lancée. L'image MUST contenir les assets compilés et MUST NOT contenir de secret, de fichier `.env`, de dépendance de développement ni de serveur Vite. Elle tourne sous un utilisateur non root.

#### Scenario: Image inspectée
- **WHEN** l'opérateur liste les fichiers de l'image publiée
- **THEN** il trouve le manifeste Vite compilé, et ni `.env`, ni `node_modules`, ni `public/hot`, ni le paquet `pestphp/pest` sous `vendor`

#### Scenario: Utilisateur du conteneur
- **WHEN** l'opérateur lance `id -u` dans le conteneur `web`
- **THEN** la commande affiche un identifiant différent de `0`

### Requirement: Configuration par l'environnement
Le système SHALL lire toute sa configuration de production dans les variables d'environnement du conteneur, au démarrage. Changer une variable puis redémarrer les conteneurs MUST suffire à la prendre en compte, sans reconstruire l'image.

#### Scenario: Code d'accès changé
- **GIVEN** la stack tourne avec `APP_PASSCODE=birdie-2026`
- **WHEN** l'opérateur passe `APP_PASSCODE` à `eagle-2027` dans Dokploy et redéploie
- **THEN** `/login` accepte `eagle-2027` et refuse `birdie-2026`

#### Scenario: Code d'accès absent
- **GIVEN** `APP_PASSCODE` n'est pas défini dans l'environnement de la stack
- **WHEN** l'opérateur lance la stack
- **THEN** le service `web` n'atteint jamais l'état sain, puisque `/up` répond 500

### Requirement: Commit inscrit dans l'image
Une image construite par la CI SHALL connaître le sha complet du commit dont elle vient. Une image construite à la main sans ce sha répond `commit: null` sur la route de version.

#### Scenario: Image de la CI
- **GIVEN** la CI a construit l'image depuis le commit `4794e1b0c0ffee00000000000000000000000000`
- **WHEN** un client demande `GET /api/version` au service `web`
- **THEN** `commit` vaut `4794e1b0c0ffee00000000000000000000000000`

#### Scenario: Image construite à la main
- **GIVEN** l'opérateur a construit l'image sans fournir de sha
- **WHEN** un client demande `GET /api/version`
- **THEN** `commit` vaut `null`

### Requirement: Migrations avant le web et le worker
La stack SHALL appliquer les migrations en attente par un service dédié, une seule fois par déploiement, avant de démarrer le web et le worker. Si les migrations échouent, le web et le worker MUST NOT démarrer sur la nouvelle image.

#### Scenario: Déploiement avec une migration en attente
- **GIVEN** la nouvelle image apporte une migration que la base n'a pas
- **WHEN** l'opérateur redéploie la stack
- **THEN** le service `migrate` applique la migration et s'arrête avec le code 0, puis `web` et `worker` démarrent

#### Scenario: Migration en échec
- **GIVEN** une migration de la nouvelle image échoue
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec un code non nul, et ni `web` ni `worker` ne démarrent sur la nouvelle image

#### Scenario: Base à jour
- **GIVEN** la base a déjà toutes les migrations de l'image
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec le code 0 sans rien changer, puis `web` et `worker` démarrent

### Requirement: Web sain seulement quand il répond
Le service `web` SHALL être déclaré sain seulement quand `/up` répond 200 depuis le conteneur. Une stack dont la configuration est valide MUST atteindre l'état sain sous `docker compose up --wait`.

#### Scenario: Démarrage normal
- **GIVEN** une configuration valide
- **WHEN** l'opérateur lance `docker compose up --wait`
- **THEN** la commande rend la main avec `pgsql`, `web`, `worker` et `backup` sains et `migrate` terminé avec le code 0

#### Scenario: Base injoignable au démarrage
- **GIVEN** `DB_PASSWORD` est faux
- **WHEN** l'opérateur lance la stack
- **THEN** `migrate` échoue et `web` ne démarre pas

### Requirement: Données conservées entre les déploiements
La base PostgreSQL et les fichiers de l'application (photos à venir) SHALL vivre dans des volumes nommés. Redéployer, changer d'image ou recréer les conteneurs MUST NOT perdre de donnée. Le web et le worker voient les mêmes fichiers.

#### Scenario: Redéploiement
- **GIVEN** la base contient des sessions et un fichier a été écrit dans le stockage de l'application
- **WHEN** l'opérateur redéploie la stack sur une nouvelle image
- **THEN** les sessions sont toujours ouvertes et le fichier est toujours lisible

#### Scenario: Fichier écrit par le worker
- **WHEN** le worker écrit un fichier dans le stockage de l'application
- **THEN** le web lit ce fichier au même chemin

### Requirement: Sauvegarde quotidienne de la base
La stack SHALL sauvegarder la base entière au démarrage du service de sauvegarde puis toutes les 24 heures, dans un volume dédié, et garder les sauvegardes des 14 derniers jours. Une sauvegarde plus ancienne MUST être supprimée.

#### Scenario: Premier démarrage
- **WHEN** la stack démarre pour la première fois
- **THEN** le volume de sauvegarde contient un fichier de sauvegarde daté du jour dans les minutes qui suivent

#### Scenario: Rotation
- **GIVEN** le volume contient une sauvegarde vieille de 15 jours
- **WHEN** la sauvegarde du jour se termine
- **THEN** la sauvegarde de 15 jours est supprimée et celles des 14 derniers jours restent

### Requirement: Sauvegarde en retard visible
Le service de sauvegarde SHALL être déclaré malade quand la plus récente sauvegarde réussie a plus de 26 heures, ou quand il n'y en a aucune après son délai de démarrage. Une sauvegarde interrompue MUST NOT compter comme réussie.

#### Scenario: Sauvegarde du jour faite
- **GIVEN** la dernière sauvegarde réussie date de 3 heures
- **WHEN** l'orchestrateur lance le healthcheck de `backup`
- **THEN** le service est sain

#### Scenario: Sauvegardes arrêtées
- **GIVEN** la dernière sauvegarde réussie date de 27 heures
- **WHEN** l'orchestrateur lance le healthcheck de `backup`
- **THEN** le service est malade

#### Scenario: Dump interrompu
- **GIVEN** `pg_dump` échoue au milieu de la sauvegarde
- **WHEN** l'opérateur liste le volume de sauvegarde
- **THEN** aucun fichier partiel n'y porte le nom d'une sauvegarde réussie

### Requirement: Restauration documentée et éprouvée
`docs/deployment.md` SHALL décrire la restauration d'une sauvegarde dans une base vide, commande par commande. Cette procédure MUST avoir été suivie une fois sur le staging avant l'archivage du changement, et la doc garde la date de cet essai.

#### Scenario: Restauration sur staging
- **GIVEN** le staging a une sauvegarde du jour et une session ouverte
- **WHEN** l'opérateur suit la procédure de restauration de `docs/deployment.md` dans une base vide
- **THEN** l'application redémarre saine et la session ouverte avant la sauvegarde est toujours valide

### Requirement: Retour à l'image précédente
L'opérateur SHALL pouvoir revenir à l'image précédente en changeant seulement le tag d'image de la stack puis en redéployant. `docs/deployment.md` MUST dire quand ce retour demande en plus de restaurer une sauvegarde (migration irréversible).

#### Scenario: Retour sans migration
- **GIVEN** la stack tourne sur `v0.2.0`, qui n'apporte aucune migration par rapport à `v0.1.0`
- **WHEN** l'opérateur remet le tag `v0.1.0` et redéploie
- **THEN** `GET /api/version` répond `"version": "0.1.0"` et les données sont intactes

### Requirement: Journaux sur la sortie du conteneur
En production, le web, le worker et les migrations SHALL écrire leurs journaux sur la sortie d'erreur du conteneur, lisibles par `docker compose logs` et Dokploy, sans fichier de journal qui grossit dans le conteneur. Un journal MUST NOT contenir le code d'accès ni l'identifiant d'une session. Une erreur de requête SQL MUST être journalisée avec sa requête, sans les valeurs liées.

#### Scenario: Erreur côté serveur
- **WHEN** une requête provoque une exception sur le service `web`
- **THEN** l'exception apparaît dans `docker compose logs web` et aucun fichier n'est écrit sous `storage/logs`

#### Scenario: Code faux saisi
- **WHEN** un visiteur envoie le code `birdie-2025` sur `/login`
- **THEN** aucun journal de la stack ne contient `birdie-2025`

#### Scenario: Base arrêtée pendant une session
- **GIVEN** le service `pgsql` est arrêté
- **WHEN** une requête qui porte un témoin de session arrive sur `web`
- **THEN** l'erreur journalisée nomme la requête `select * from "sessions" where "id" = ?` sans sa valeur liée, et aucun journal de la stack ne contient l'identifiant de cette session
