# Proposal

## Why

L'application Next tourne encore en production, avec sa base SQLite : les joueurs, leurs alias, chaque round et ses scores, l'historique d'index, les recherches de rating, les imports de photos et la consommation IA du mois. Le port Laravel ne remplace Next qu'une fois ces données reprises sans perte ni écart d'index. Ce changement livre la commande d'import, stricte et vérifiée, puis prépare la bascule (cutover) que le mainteneur fera à la main. Il correspond à l'entrée 10 de `ROADMAP.md` ; son contrat est la section « Commande d'import SQLite » de `docs/PORT_TO_LARAVEL.md`, corrigée là où la base réelle le demande (design, « Écarts au contrat »).

Dépendances : l'entrée 10 dépend de 9 (`course-rating-sgt`) et de 11 (`visual-redesign`), toutes deux proposées et pas encore implémentées ; 9 suppose aussi 8 (`scorecard-ai-import`), en cours d'implémentation sur la branche `feat/scorecard-ai-import`. La commande écrit dans les tables `imports` et `ai_usage` de 8, dans les colonnes de recherche de `course_ratings` de 9, et réutilise la conversion WebP de 8, le recalcul de 6 et le job de recherche de 9. 11 fixe la couleur d'un joueur par son ordre de création : l'import doit le garder. **L'apply attend que 8, 9 et 11 soient fusionnés et archivés** (tâche 1.1).

## What Changes

- **Commande `php artisan golf:import-sqlite {db} {--uploads=} {--force} {--dry-run}`**, lancée une fois au cutover, et avant sur une copie locale et sur le staging.
- **Lecture seule de la base SQLite** : connexion `legacy` déclarée à la volée, fichier ouvert en lecture seule ; le fichier n'est jamais modifié. Une base qui n'est pas au niveau de la migration Drizzle `0004_course_ratings` (tables ou colonnes manquantes) est refusée avant toute écriture.
- **Base cible vide, ou vidée avec `--force`** : la commande refuse si une table métier contient une ligne et nomme chaque table avec son compte. `--force` les vide dans la même transaction, après une confirmation interactive ; sans terminal, la confirmation échoue et rien n'est écrit.
- **Une seule transaction PostgreSQL** : toute erreur annule tout, photos copiées comprises. `--dry-run` fait tout le travail, comparaison des statistiques comprise, puis annule et affiche le rapport, sans écrire aucun fichier.
- **Correspondance des tables** : `players`, `player_aliases`, `rounds`, `round_holes`, `round_players`, `scores`, `course_ratings`, `imports`, `ai_usage` reprises avec de nouveaux UUID et leurs clés étrangères remappées ; dates en millisecondes converties en instants UTC ; `jobs` ignorée.
- **Ordre gardé** : les UUID sont attribués dans l'ordre des identifiants SQLite. Deux joueurs créés dans la même seconde (cas réel) gardent donc leur ordre de Next, et avec lui leur couleur (entrée 11) ; deux rounds du même jour gardent leur ordre dans l'historique d'index.
- **`player_round_stats` recalculée, puis comparée ligne à ligne** à la table SQLite : brut, ajusté, différentiel, index d'avant et d'après, « compte dans l'index ». Un écart, ou une ligne de trop ou de moins, fait échouer l'import et liste les différences.
- **Photos** : chaque `photo_path` (`uploads/<nom>.webp`) est copié sur le disque `photos` sous `imports/<nom>.webp`, chemin partagé par le round et son import comme dans 8 ; une photo qui n'est pas en WebP est convertie. Une photo absente fait échouer l'import, et la commande liste tous les chemins manquants avant d'écrire quoi que ce soit.
- **Imports** : statuts, brouillon et round repris ; `corners`, `corners_source` et `ai_cells` abandonnés ; un import `queued` ou `reading` devient « Échec » avec « Import interrompu par la migration » ; la raison d'un échec Next devient la raison affichée. La correction d'un import déjà enregistré en round est abandonnée ; celle d'un import encore à vérifier est traduite dans la forme de 8.
- **Recherches de rating** : `course_ratings` copiée avec toutes ses colonnes ; les lignes « Recherche en cours » sont remises en file après le commit.
- **`ai_usage` copiée** telle quelle : le budget du mois en cours reste juste.
- **Données hors des bornes du port** : une distance de trou hors de 1 à 999 (cas réel : une distance 0) devient vide et le rapport la compte ; toute autre valeur refusée par une contrainte de la base fait échouer l'import en nommant la table et la ligne SQLite.
- **Rapport** : lignes par table (SQLite → PostgreSQL), photos copiées, statistiques identiques, distances vidées, corrections abandonnées, recherches remises en file, puis « Import terminé. » ou le message de l'essai à blanc.
- **Tests Pest sur une fixture construite à la main** : une base `app.db` créée à chaque test par les cinq migrations Drizzle de Next (fichiers SQL copiés dans `tests/fixtures/`, schéma seul), remplie de quelques rounds dont les statistiques sont connues en littéraux.
- **Vérification locale sur la sauvegarde réelle** de la base Next (privée, hors de git) : essai à blanc, import réel dans la base Sail, rapport lu, comptes et statistiques identiques, passe navigateur.
- **Procédure de cutover** dans `docs/deployment.md` (copie cohérente de la base en WAL, `--force` sur la base qui a déjà les joueurs initiaux, bascule du domaine, retour arrière) et ADR « Import unique de la base SQLite de Next ».

### Tâches du mainteneur, hors de ce changement

Rien ici ne déploie. Après la fusion du code, le mainteneur fait à la main : la répétition sur le staging (copie de la base et des photos de production, import, comparaison à l'œil avec Next), puis le cutover de production, puis l'archivage du dépôt Next après 30 jours. `tasks.md` les liste sous « Workflow follow-up ».

### Hors périmètre

- Retrait de la commande après le cutover : elle reste, sans coût, tant que le dépôt Next n'est pas archivé.
- Import incrémental ou répété sur une base qui a reçu de nouvelles données Laravel : la commande ne fusionne jamais, elle remplit une base vide ou vidée.
- Redirection des anciennes adresses (`/rounds/12`) : elles cassent, ce qui est accepté (décision 6 du port).
- Copie de la file `jobs` de Next : elle est vidée avant le cutover.

## Requirements des specs Next

Next n'a pas de commande d'import : la référence est son schéma Drizzle (`src/db/schema.ts`, migrations `drizzle/0000` à `0004`) et le contrat de `docs/PORT_TO_LARAVEL.md`. Aucun requirement Next n'est porté ; le comportement des données reprises est celui des specs déjà portées par 5 à 9.

## Capabilities

### New Capabilities

- `legacy-import` : commande d'import de la base SQLite de Next, refus et confirmation, transaction unique et essai à blanc, correspondance des tables et des identifiants, photos, comparaison des statistiques, remise en file des recherches, rapport.

### Modified Capabilities

- `deployment` : procédure de bascule depuis l'application Next documentée dans `docs/deployment.md`.

## Impact

- **Code** : commande `golf:import-sqlite` (`app/Console/Commands`), domaine `app/Domain/LegacyImport` (lecture du schéma Next, correspondance des identifiants, écritures, photos, comparaison, rapport), règle d'architecture qui le réserve à la commande, `tests/fixtures/legacy-sqlite/` (migrations Drizzle et constructeur de fixture), `docs/deployment.md`, `docs/PORT_TO_LARAVEL.md` (contrat corrigé), `ARCHITECTURE.md`, ADR.
- **Base** : aucune migration, aucun modèle nouveau (pas de fabrique ni de seeder à ajouter). La commande écrit dans les tables existantes.
- **Dépendances** : aucune nouvelle. `pdo_sqlite` est déjà dans l'image de production (relevé le 2026-10-28 sur `golf-handicaps-laravel:local`) et dans l'image Sail ; la tâche 7.1 le vérifie de nouveau.
- **Confidentialité** : la base Next et ses photos contiennent les noms et les scores du groupe. La fixture des tests est inventée ; la sauvegarde réelle reste dans `backups/` (ignoré par git) et dans la base Sail locale, jamais dans le dépôt, une PR ou un journal. La commande n'écrit aucune ligne de données dans le journal Laravel ; seul son rapport, sur la console de l'opérateur, nomme un joueur quand une statistique diffère.
- **Retour arrière** : avant le cutover, rien à annuler (aucune migration ; une base importée par erreur se vide par `--force` ou `make fresh`). Après le cutover, rebasculer le domaine sur Next et redémarrer ses applications, gardées arrêtées 30 jours avec leur volume.
