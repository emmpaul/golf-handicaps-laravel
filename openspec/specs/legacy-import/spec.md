# legacy-import Specification

## Purpose
Reprendre une fois, sans perte ni écart d'index, les données de la base SQLite de l'application Next dans la base PostgreSQL du port Laravel, avec leurs photos, au moment de la bascule.

## Requirements

### Requirement: Commande d'import
Le système SHALL fournir la commande `php artisan golf:import-sqlite <base> [--uploads=<dossier>] [--force] [--dry-run]`, qui lit la base SQLite de Next donnée en argument et les photos du dossier `--uploads`. La commande est réservée à l'opérateur du serveur : aucune page ni route ne la déclenche. Elle sort en 0 quand l'import (ou l'essai à blanc) réussit, en 1 sinon, avec un message en français.

#### Scenario: Base absente
- **WHEN** l'opérateur lance `golf:import-sqlite /tmp/absente.db`
- **THEN** la commande affiche « Base SQLite introuvable : /tmp/absente.db », sort en 1 et n'écrit rien

#### Scenario: Aucune route
- **WHEN** la liste des routes de l'application est lue
- **THEN** aucune route ne lance l'import

### Requirement: Base source en lecture seule
La commande SHALL ouvrir la base SQLite en lecture seule et MUST NOT la modifier ni créer de fichier à côté d'elle, qu'elle réussisse, échoue ou fasse un essai à blanc. Le dossier `--uploads` est lu sans être modifié.

#### Scenario: Fichier inchangé
- **GIVEN** une base SQLite valide et son empreinte SHA-256
- **WHEN** l'import réussit, puis un second import échoue sur une base cible non vide
- **THEN** l'empreinte du fichier est la même qu'avant le premier import

#### Scenario: Base en WAL
- **GIVEN** une base SQLite au niveau `0004` dont le journal est en mode WAL (octets 18 et 19 de l'en-tête à 2), sans fichier `-wal` ni `-shm`
- **WHEN** l'opérateur lance l'import
- **THEN** la commande affiche « Base en WAL : copier par VACUUM INTO (docs/deployment.md, Bascule depuis Next). », sort en 1, n'écrit rien, et aucun fichier `-wal` ni `-shm` n'apparaît à côté de la base

### Requirement: Niveau de schéma vérifié
La commande SHALL refuser une base dont le schéma n'est pas celui de la migration Drizzle `0004_course_ratings` de Next : chaque table reprise doit exister avec chacune de ses colonnes. Le refus nomme les tables ou colonnes manquantes et arrive avant toute écriture. La table `jobs`, seulement comptée dans le rapport, doit exister ; les tables internes de SQLite et de Drizzle ne sont pas contrôlées.

#### Scenario: Base d'avant les ratings
- **GIVEN** une base SQLite créée par les migrations `0000` à `0003` seulement
- **WHEN** l'opérateur lance l'import
- **THEN** la commande affiche « Base SQLite au mauvais niveau : migration Drizzle 0004 attendue. » suivi de « Table manquante : course_ratings », sort en 1 et n'écrit rien

#### Scenario: Colonne manquante
- **GIVEN** une base au niveau `0004` dont la table `imports` n'a pas de colonne `correction`
- **WHEN** l'opérateur lance l'import
- **THEN** la commande nomme « Colonne manquante : imports.correction », sort en 1 et n'écrit rien

### Requirement: Refus sur une base cible non vide
La commande SHALL refuser l'import si l'une des tables `players`, `player_aliases`, `rounds`, `round_holes`, `round_players`, `scores`, `player_round_stats`, `course_ratings`, `imports` ou `ai_usage` contient une ligne. Le refus nomme chaque table non vide avec son nombre de lignes, rappelle `--force` et n'écrit rien.

#### Scenario: Joueurs initiaux déjà créés
- **GIVEN** une base cible qui a seulement les trois joueurs créés par `INITIAL_PLAYERS`
- **WHEN** l'opérateur lance l'import sans `--force`
- **THEN** la commande affiche « Tables non vides : players (3), player_aliases (3). Relancer avec --force pour les vider. », sort en 1 et les trois joueurs restent

### Requirement: Vidage confirmé avec --force
Avec `--force`, la commande SHALL demander une confirmation interactive qui nomme les tables non vides, puis les vider dans la transaction de l'import. Une réponse négative, ou l'absence de terminal interactif, arrête la commande sans rien écrire. Les photos des lignes vidées ne sont effacées qu'après le commit d'un import réel.

#### Scenario: Confirmation acceptée
- **GIVEN** une base cible avec les trois joueurs initiaux
- **WHEN** l'opérateur lance l'import avec `--force` et répond oui à « Vider players (3), player_aliases (3) avant l'import ? »
- **THEN** les trois joueurs initiaux disparaissent, les joueurs de la base SQLite les remplacent et la commande sort en 0

#### Scenario: Confirmation refusée
- **WHEN** l'opérateur répond non à la confirmation
- **THEN** la commande affiche « Import abandonné. », sort en 1 et les tables gardent leurs lignes

#### Scenario: Sans terminal
- **WHEN** la commande est lancée avec `--force` et `--no-interaction`
- **THEN** la confirmation est refusée, la commande affiche « Import abandonné. » et sort en 1

#### Scenario: Import qui échoue après le vidage
- **GIVEN** une base cible non vide et une base SQLite dont une photo manque
- **WHEN** l'opérateur lance l'import avec `--force` et confirme
- **THEN** l'import échoue et les lignes et les photos d'avant sont toutes encore là

### Requirement: Import en une seule transaction
La commande SHALL écrire toutes les tables dans une seule transaction PostgreSQL. Une erreur, à n'importe quelle étape, annule toutes les écritures et efface les photos déjà copiées par cet import ; le message nomme l'étape et, quand elle existe, la table et l'identifiant SQLite de la ligne en cause.

#### Scenario: Ligne refusée par une contrainte
- **GIVEN** une base SQLite dont le round 2 a un par 6 au trou 7
- **WHEN** l'opérateur lance l'import
- **THEN** la commande nomme la table `round_holes` et le round SQLite 2, sort en 1, aucune table cible n'a de ligne et le disque `photos` n'a aucun fichier nouveau

### Requirement: Essai à blanc
Avec `--dry-run`, la commande SHALL faire tout le travail de l'import, lecture des photos et comparaison des statistiques comprises, afficher le rapport, puis tout annuler. Elle n'écrit aucun fichier et ne met aucun job en file. Elle sort en 0 si l'import aurait réussi, en 1 avec le même message qu'un import réel sinon.

#### Scenario: Essai réussi
- **GIVEN** une base SQLite valide et une base cible vide
- **WHEN** l'opérateur lance l'import avec `--dry-run`
- **THEN** le rapport complet s'affiche, suivi de « Essai à blanc : import annulé, rien n'a été écrit. », la commande sort en 0, les tables cibles et le disque `photos` restent vides et la file `jobs` aussi

#### Scenario: Essai qui échoue
- **GIVEN** une base SQLite dont une photo manque
- **WHEN** l'opérateur lance l'import avec `--dry-run`
- **THEN** la commande liste la photo manquante et sort en 1

### Requirement: Identifiants remappés dans l'ordre de Next
La commande SHALL donner un nouvel UUID à chaque ligne reprise et remapper chaque clé étrangère. Les UUID d'une table sont attribués dans l'ordre croissant des identifiants SQLite, de sorte que deux lignes de même date de création gardent l'ordre qu'elles avaient dans Next. Les anciens identifiants ne sont gardés nulle part.

#### Scenario: Clés remappées
- **GIVEN** une base SQLite avec le round 1 de Gab et Manu et 36 scores
- **WHEN** l'import réussit
- **THEN** les 36 scores, les 2 lignes de `round_players` et les 18 trous pointent vers l'UUID du round importé, et chaque score vers l'UUID de son joueur importé

#### Scenario: Joueurs créés dans la même milliseconde
- **GIVEN** une base SQLite où Manu (id 2) et Sean (id 3) ont la même date de création, après Gab (id 1)
- **WHEN** l'import réussit et la liste des joueurs est lue par ordre de création
- **THEN** l'ordre est Gab, Manu, Sean, et les couleurs de joueur sont la première, la deuxième et la troisième de la palette

### Requirement: Dates converties en UTC
La commande SHALL convertir chaque date de création SQLite (millisecondes depuis l'époque Unix) en instant UTC à la seconde, millisecondes tronquées. Une date de jeu `AAAA-MM-JJ` est reprise telle quelle. Une table Next sans date de mise à jour reçoit la date de création ; `player_aliases`, sans date dans Next, reçoit l'instant de l'import ; `course_ratings`, qui n'a que sa date de mise à jour, la reprend pour les deux dates.

#### Scenario: Instant d'un round
- **GIVEN** un round SQLite créé à 1792251843742 ms et joué le 2026-10-17
- **WHEN** l'import réussit
- **THEN** le round importé a `created_at` et `updated_at` à 2026-10-17 15:44:03 UTC et `played_on` au 2026-10-17

### Requirement: Tables reprises
La commande SHALL reprendre `players`, `player_aliases`, `rounds`, `round_holes`, `round_players`, `scores`, `course_ratings`, `imports` et `ai_usage`, une ligne cible par ligne source, chaque colonne de même nom à l'identique sauf les règles d'identifiants, de dates, de photos, d'imports et de distances de cette spec. La table `jobs` de Next est ignorée.

#### Scenario: Comptes identiques
- **GIVEN** une base SQLite de 3 joueurs, 7 alias, 2 rounds, 36 trous, 4 lignes de round, 72 scores, 2 ratings, 2 imports, 3 lignes `ai_usage` et 5 jobs
- **WHEN** l'import réussit
- **THEN** la base cible a 3 joueurs, 7 alias, 2 rounds, 36 trous, 4 lignes de round, 72 scores, 2 ratings, 2 imports, 3 lignes `ai_usage`, et la file `jobs` ne contient aucun job venu de Next

#### Scenario: Consommation IA du mois
- **GIVEN** une ligne `ai_usage` SQLite du mois `2026-10`, modèle `claude-opus-5-5`, 3 000 tokens en entrée, 1 200 en sortie, 36 000 micro-dollars
- **WHEN** l'import réussit
- **THEN** la ligne importée a les mêmes mois, modèle, tokens et coût, et le budget IA du mois d'octobre 2026 compte ces 36 000 micro-dollars

### Requirement: Distance hors bornes vidée
Une distance de trou SQLite hors de 1 à 999 SHALL être importée vide, et le rapport compte ces distances. Toute autre valeur que la base cible refuse fait échouer l'import (requirement « Import en une seule transaction »).

#### Scenario: Distance 0
- **GIVEN** un round SQLite dont le trou 4 a une distance 0
- **WHEN** l'import réussit
- **THEN** le trou 4 du round importé n'a pas de distance, les autres trous gardent la leur et le rapport affiche « Distances hors de 1 à 999 vidées : 1 »

### Requirement: Statistiques recalculées et comparées
La commande SHALL recalculer `player_round_stats` par le calcul d'index du port, sans copier la table SQLite, puis comparer chaque ligne à celle de même round et même joueur dans SQLite : brut, ajusté, différentiel, index d'avant, index d'après, « compte dans l'index actuel ». Un écart, ou une ligne présente d'un seul côté, fait échouer l'import ; la commande liste chaque différence.

#### Scenario: Statistiques identiques
- **GIVEN** une base SQLite avec la carte `IMG_3064` sans rating, dont les statistiques SQLite sont Gab (72, 72, 10, aucun, 10, oui) et Manu (88, 88, 170, aucun, 170, oui)
- **WHEN** l'import réussit
- **THEN** les deux lignes recalculées ont ces valeurs et le rapport affiche « Statistiques identiques : 2 / 2 »

#### Scenario: Écart
- **GIVEN** la même base, où le différentiel SQLite de Manu vaut 171
- **WHEN** l'opérateur lance l'import
- **THEN** la commande affiche « Statistiques différentes de la base SQLite : » puis une ligne « BETHPAGE BLACK / Red, 2026-10-17, Manu : differential_t SQLite 171, recalculé 170 », sort en 1 et n'écrit rien

### Requirement: Photos copiées
La commande SHALL copier chaque photo référencée par un round ou un import (`uploads/<nom>`) depuis le dossier `--uploads` vers le stockage des photos de l'application, une seule fois quand le round et son import la partagent. Une photo déjà en WebP est copiée octet pour octet ; une autre est convertie en WebP. Le round et son import importés pointent vers la même photo.

#### Scenario: Photo partagée
- **GIVEN** un round SQLite et son import qui référencent tous deux `uploads/3f2a.webp`
- **WHEN** l'import réussit
- **THEN** une seule photo est copiée, identique à l'octet près à l'originale, le round et l'import importés la servent tous deux et le rapport affiche « Photos copiées : 1 »

#### Scenario: Photo en JPEG
- **GIVEN** un round SQLite dont la photo `uploads/ancienne.jpg` est un JPEG
- **WHEN** l'import réussit
- **THEN** la photo du round importé est un WebP

### Requirement: Photo manquante ou chemin refusé
Avant toute écriture, la commande SHALL vérifier que chaque photo référencée existe dans le dossier `--uploads` et que son chemin est de la forme `uploads/<nom de fichier>`. Sinon l'import échoue et la commande liste tous les chemins en cause, pas seulement le premier. Une base qui référence des photos sans `--uploads` est refusée.

#### Scenario: Photos manquantes
- **GIVEN** une base SQLite dont deux rounds référencent `uploads/a.webp` et `uploads/b.webp`, absents du dossier
- **WHEN** l'opérateur lance l'import
- **THEN** la commande affiche « Photos manquantes : » suivi des deux chemins, sort en 1 et n'écrit rien

#### Scenario: Chemin hors du dossier
- **GIVEN** un import SQLite dont la photo est `uploads/../app.db`
- **WHEN** l'opérateur lance l'import
- **THEN** la commande affiche « Chemin de photo refusé : uploads/../app.db », sort en 1 et ne lit aucun fichier hors du dossier `--uploads`

#### Scenario: Option oubliée
- **GIVEN** une base SQLite dont 9 rounds ont une photo
- **WHEN** l'opérateur lance l'import sans `--uploads`
- **THEN** la commande affiche « Option --uploads requise : 9 photos référencées. » et sort en 1

### Requirement: Imports repris
La commande SHALL reprendre chaque import avec son brouillon, sa date et son round. Un import `queued` ou `reading` devient « Échec » avec la raison « Import interrompu par la migration ». Un import en échec garde sa note IA comme raison, ou « Panneau introuvable sur la photo » sans note. Les coins, leur provenance et les cases complétées par l'IA sont abandonnés.

#### Scenario: Import interrompu
- **GIVEN** un import SQLite au statut `reading`
- **WHEN** l'import réussit
- **THEN** l'import importé est « Échec » avec « Import interrompu par la migration », et la relance de sa lecture est proposée sur la page Importer

#### Scenario: Import déjà enregistré
- **GIVEN** un import SQLite `ready` relié au round 1
- **WHEN** l'import réussit et l'utilisateur ouvre cet import
- **THEN** il arrive sur la page du round importé

### Requirement: Correction reprise ou abandonnée
La correction enregistrée d'un import encore à vérifier SHALL être traduite dans la forme du port, joueurs remappés ; elle s'ouvre à la reprise de la vérification. La correction d'un import déjà enregistré en round est abandonnée. Une correction illisible est abandonnée, l'import s'ouvre alors sur son brouillon. Le rapport compte les corrections abandonnées.

#### Scenario: Vérification reprise
- **GIVEN** un import SQLite `ready` sans round, dont la correction enregistrée associe la ligne 1 à Gab et met 6 au trou 3
- **WHEN** l'import réussit et l'utilisateur ouvre cet import
- **THEN** l'écran de vérification montre la ligne 1 associée à Gab et 6 au trou 3

#### Scenario: Correction d'un import enregistré
- **GIVEN** deux imports SQLite reliés à un round, chacun avec une correction
- **WHEN** l'import réussit
- **THEN** les deux imports importés n'ont pas de correction et le rapport affiche « Corrections abandonnées : 2 »

### Requirement: Recherches de rating remises en file
La commande SHALL reprendre chaque ligne `course_ratings` avec son statut, sa rating, sa source, son parcours SGT, ses candidats, ses tees lus, son tee proposé et sa note. Après le commit d'un import réel, chaque ligne « Recherche en cours » est remise en file de recherche ; les autres ne le sont pas. L'essai à blanc ne met rien en file.

#### Scenario: Recherche interrompue
- **GIVEN** une base SQLite avec une rating « Recherche en cours » et une « Parcours SGT à choisir » avec deux candidats
- **WHEN** l'import réussit
- **THEN** une seule recherche de rating est en file, pour la première ligne, la seconde garde ses deux candidats sur la page Parcours et le rapport affiche « Recherches de rating remises en file : 1 »

#### Scenario: Rating confirmée
- **GIVEN** une rating SQLite « Confirmé » 71,4 / 144, source `sgt`, sur le parcours du round importé
- **WHEN** l'import réussit
- **THEN** la page Parcours montre 71,4 / 144 « Confirmé » et les statistiques recalculées utilisent cette rating

### Requirement: Rapport d'import
À la fin d'un import réussi ou d'un essai à blanc, la commande SHALL afficher, pour chaque table reprise, le nombre de lignes SQLite et le nombre de lignes écrites, puis les photos copiées, les statistiques identiques sur le total comparé, les distances vidées, les corrections abandonnées, les recherches remises en file et les jobs Next ignorés. Le rapport ne contient aucune donnée de joueur.

#### Scenario: Import réel
- **WHEN** un import réel réussit
- **THEN** le rapport se termine par « Import terminé. » et la commande sort en 0

### Requirement: Rien dans le journal de l'application
La commande MUST NOT écrire de ligne de données (nom, alias, score, brouillon) dans le journal de l'application, ni en succès ni en échec ; ses messages vont seulement à la console de l'opérateur.

#### Scenario: Échec sur un écart
- **GIVEN** une base SQLite dont une statistique diffère
- **WHEN** l'import échoue
- **THEN** le journal de l'application ne contient ni le nom du joueur ni le parcours en cause
