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
La stack SHALL appliquer les migrations en attente par un service dédié, une seule fois par déploiement, avant de démarrer le web et le worker. Le même service SHALL ensuite créer les joueurs initiaux (spec `players`), recalculer l'historique d'index de tous les joueurs (spec `handicap-index`), puis mettre en file la recherche de rating des parcours qui n'en ont pas (spec `course-ratings`). Si l'une de ces étapes échoue, le web et le worker MUST NOT démarrer sur la nouvelle image.

#### Scenario: Déploiement avec une migration en attente
- **GIVEN** la nouvelle image apporte une migration que la base n'a pas
- **WHEN** l'opérateur redéploie la stack
- **THEN** le service `migrate` applique la migration et s'arrête avec le code 0, puis `web` et `worker` démarrent

#### Scenario: Migration en échec
- **GIVEN** une migration de la nouvelle image échoue
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec un code non nul, et ni `web` ni `worker` ne démarrent sur la nouvelle image

#### Scenario: Base à jour
- **GIVEN** la base a déjà toutes les migrations de l'image, des joueurs, l'historique d'index de leurs rounds et une ligne de rating par parcours
- **WHEN** l'opérateur redéploie la stack
- **THEN** `migrate` s'arrête avec le code 0, sans changer ni les tables ni les joueurs ni l'historique d'index ni les ratings, sans mettre de recherche en file, puis `web` et `worker` démarrent

#### Scenario: Joueurs initiaux en double
- **GIVEN** une base sans joueur et `INITIAL_PLAYERS=Gab,gab` dans l'environnement de la stack
- **WHEN** l'opérateur lance la stack
- **THEN** `migrate` s'arrête avec un code non nul et un message qui nomme `INITIAL_PLAYERS`, et ni `web` ni `worker` ne démarrent

#### Scenario: Rounds enregistrés avant l'index
- **GIVEN** une base qui contient la carte `IMG_3064` et aucun historique d'index
- **WHEN** l'opérateur redéploie la stack sur l'image qui apporte l'index
- **THEN** `migrate` affiche « 2 joueurs recalculés. » et s'arrête avec le code 0, Gab a un différentiel de 1,0 et Manu de 17,0 sur ce round, puis `web` et `worker` démarrent

#### Scenario: Rounds enregistrés avant la recherche
- **GIVEN** une base qui contient la carte `IMG_3064` sur « BETHPAGE BLACK / Red » et aucune ligne de rating
- **WHEN** l'opérateur redéploie la stack sur l'image qui apporte la recherche SGT
- **THEN** `migrate` affiche « 1 recherche de rating mise en file. » et s'arrête avec le code 0, puis le worker démarre et traite cette recherche

#### Scenario: Première mise en route sur une base vide
- **GIVEN** une base vide
- **WHEN** l'opérateur lance la stack pour la première fois
- **THEN** `migrate` applique les migrations, crée les joueurs initiaux, affiche « 0 joueur recalculé. » et « 0 recherche de rating mise en file. » et s'arrête avec le code 0, puis `web` et `worker` démarrent

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
La base PostgreSQL et les fichiers de l'application (photos à venir) SHALL vivre dans des volumes nommés. Redéployer, changer d'image ou recréer les conteneurs MUST NOT perdre de donnée. Le web et le worker voient les mêmes fichiers. Le lancement de la stack sur un volume de stockage neuf MUST réussir à chaque essai, quel que soit l'ordre dans lequel les conteneurs sont créés.

#### Scenario: Redéploiement
- **GIVEN** la base contient des sessions et un fichier a été écrit dans le stockage de l'application
- **WHEN** l'opérateur redéploie la stack sur une nouvelle image
- **THEN** les sessions sont toujours ouvertes et le fichier est toujours lisible

#### Scenario: Fichier écrit par le worker
- **WHEN** le worker écrit un fichier dans le stockage de l'application
- **THEN** le web lit ce fichier au même chemin

#### Scenario: Lancement sur un volume de stockage neuf
- **GIVEN** aucun volume de stockage n'existe pour la stack
- **WHEN** l'opérateur lance `docker compose up --wait` cinq fois de suite, en retirant la stack et ses volumes entre deux essais
- **THEN** chaque lancement rend la main avec `pgsql`, `web`, `worker` et `backup` sains et `migrate` terminé avec le code 0, et le stockage de l'application contient les dossiers `public` et `private`, où le web et le worker peuvent écrire

#### Scenario: Redéploiement sur un volume de stockage existant
- **GIVEN** le volume de stockage contient le fichier `public/keep.txt` écrit par la version précédente, et pas le fichier `.gitignore` livré avec l'image
- **WHEN** l'opérateur redéploie la stack sur une nouvelle image
- **THEN** `public/keep.txt` est toujours lisible avec le même contenu, et aucun fichier de l'image n'est ajouté au volume

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

### Requirement: Photos de carte acceptées en production
L'image de production SHALL accepter l'envoi d'une photo de carte jusqu'à 20 Mo et la convertir en WebP, sans réglage à faire dans Dokploy. Une photo plus lourde MUST être refusée avec un message qui donne la limite, sans erreur 500.

#### Scenario: Photo de téléphone
- **GIVEN** la stack de production lancée sur l'image construite par la CI
- **WHEN** l'utilisateur envoie une photo JPEG de 8 Mo
- **THEN** l'import est créé et sa photo est servie en `image/webp`

#### Scenario: Photo trop lourde
- **WHEN** l'utilisateur envoie un fichier de 25 Mo
- **THEN** le système refuse l'envoi avec « La photo dépasse 20 Mo » et aucun import n'est créé

### Requirement: Variables de la lecture par l'IA
La stack SHALL lire dans l'environnement du conteneur la clé d'API (`ANTHROPIC_API_KEY`), le modèle (`AI_MODEL`), le plafond mensuel (`AI_MONTHLY_BUDGET_USD`) et le fuseau du budget (`AI_BUDGET_TIME_ZONE`), pour `web` comme pour `worker`. Ces valeurs MUST NOT être inscrites dans l'image.

#### Scenario: Clé ajoutée après coup
- **GIVEN** la stack tourne sans `ANTHROPIC_API_KEY` et l'écran d'import affiche la lecture désactivée
- **WHEN** l'opérateur ajoute la clé dans Dokploy et redéploie
- **THEN** l'écran d'import propose l'envoi d'une photo et le worker lit les imports

#### Scenario: Image publiée
- **WHEN** on inspecte l'image publiée sur GHCR
- **THEN** elle ne contient aucune clé d'API

### Requirement: Variable de la recherche SGT
La stack SHALL lire `SGT_LOOKUP` dans l'environnement du conteneur, `on` par défaut. Seul le worker MUST faire des requêtes vers Simulator Golf Tour ; le web et les migrations n'en font aucune.

#### Scenario: Recherche coupée par l'opérateur
- **GIVEN** la stack tourne avec `SGT_LOOKUP` absent
- **WHEN** l'opérateur ajoute `SGT_LOOKUP=off` dans Dokploy et redéploie
- **THEN** les recherches suivantes finissent en « Saisie manuelle » avec « Recherche SGT désactivée », sans requête vers SGT

#### Scenario: Déploiement sans sortie réseau du web
- **WHEN** le service `migrate` met en file la recherche de 6 parcours
- **THEN** ni `migrate` ni `web` ne font de requête vers `simulatorgolftour.com`

### Requirement: Bascule depuis l'application Next documentée
`docs/deployment.md` SHALL décrire la bascule de l'application Next vers la stack Laravel, commande par commande : répétition sur le staging, arrêt de Next, copie cohérente de sa base SQLite et de ses photos, import avec `golf:import-sqlite`, lecture du rapport, bascule du domaine, contrôles, retour arrière. La procédure MUST désigner chaque étape que seul le mainteneur exécute.

#### Scenario: Copie d'une base en WAL
- **GIVEN** la base SQLite de Next en mode WAL
- **WHEN** l'opérateur lit l'étape de copie de `docs/deployment.md`
- **THEN** elle copie la base par `VACUUM INTO` et vérifie son intégrité, au lieu de copier le seul fichier `app.db`

#### Scenario: Base qui a déjà les joueurs initiaux
- **GIVEN** la stack Laravel de production, dont le service `migrate` a créé les joueurs de `INITIAL_PLAYERS`
- **WHEN** l'opérateur suit l'étape d'import
- **THEN** la commande documentée passe `--force` dans un terminal interactif (`docker exec -it`) et dit de répondre oui à la confirmation qui nomme `players` et `player_aliases`

#### Scenario: Retour arrière
- **GIVEN** la bascule faite depuis moins de 30 jours
- **WHEN** l'opérateur suit la section de retour arrière
- **THEN** il rebascule le domaine sur Next et redémarre ses applications, dont le volume a été gardé, sans toucher à la stack Laravel
