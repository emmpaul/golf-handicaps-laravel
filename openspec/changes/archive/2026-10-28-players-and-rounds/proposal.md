# Proposal

## Why

L'application Laravel n'a encore aucune donnée métier : les sections Rounds et Joueurs servent la page « Section à venir ». Tout le reste du port (calcul des index, accueil, import par l'IA, import SQLite) suppose des joueurs, des rounds et leurs scores en base. Ce changement correspond à l'entrée 5 de `ROADMAP.md`.

Dépendances : les entrées 1 à 3 sont faites et archivées. L'entrée 4 (`production-deployment`) est archivée, mais `ROADMAP.md` la garde « en cours » tant que le staging (groupe 8 de ses tâches) et la première `/release` ne sont pas passés. Ce changement n'en dépend pas pour son code ; il touche seulement la commande du service `migrate` (voir plus bas). Le mainteneur confirme avant l'apply que ce reste n'est pas bloquant.

## What Changes

- **Joueurs** : page `/players` avec la liste des joueurs et de leurs alias, ajout, renommage, ajout et retrait d'un alias. Nom unique sans tenir compte de la casse (« Ce joueur existe déjà »), alias unique sans tenir compte de la casse : un alias ajouté à un joueur le retire de l'autre, comme dans Next.
- **Joueurs initiaux** : une commande artisan crée les joueurs de `INITIAL_PLAYERS` sur une base sans joueur. Le service `migrate` de la stack la lance après les migrations ; une liste en double fait échouer `migrate`, donc ni `web` ni `worker` ne démarrent.
- **Rounds** : tables `rounds`, `round_holes`, `round_players`, `scores` en UUID. Liste `/rounds` du plus récent au plus ancien, page d'un round (en-tête, bruts, carte de score, photo s'il en a une), saisie manuelle `/rounds/new`, correction `/rounds/{id}/edit`, suppression confirmée.
- **Éditeur de round** : porté de l'écran de correction de Next, sans photo ni autosave : parcours, tees, date au calendrier, par, index, distance, 1 à 4 lignes de joueur, totaux OUT, IN, TOT calculés, contrôles de cohérence recalculés à chaque frappe et rejoués par le serveur, bouton qui compte les problèmes, pré-remplissage depuis un parcours connu, création d'un joueur depuis une ligne, avertissement de doublon.
- **Photo d'un round** : colonne `photo_path`, photo servie par une route protégée, effacée avec le round. Aucun envoi de photo dans ce changement.
- **Erreurs métier** : première `DomainException` (ARCHITECTURE.md) et son rendu commun en message éphémère.
- **Données de développement** : `DemoSeeder`, `DatabaseSeeder` (les trois joueurs Gab, Manu, Sean), `DemoDatabaseSeeder` (joueurs, round de référence `IMG_3064` avec sa photo, rounds générés), cibles `make fresh` et `make demo`. Fabrique et états pour chaque modèle.
- **ADR 0009** : modèle de données des rounds et place des contrôles de cohérence.

### Reporté à d'autres changements

| Sujet | Changement | Raison |
| --- | --- | --- |
| Classement net, handicap de jeu, gagnant net de la liste, index des joueurs, recalcul après écriture | `handicap-index` (6) | Il faut le calcul WHS et `player_round_stats`. Ce changement affiche les bruts seulement. |
| Reconnaissance d'un nom lu, apprentissage d'un alias à l'enregistrement d'un import | `scorecard-ai-import` (8) | Il n'y a pas encore de nom lu : une ligne saisie à la main choisit son joueur dans la liste. |
| Contrôles « Somme aller ≠ OUT », « OUT + IN ≠ TOT » | `scorecard-ai-import` (8) | À la main, les totaux sont calculés, jamais saisis : ces contrôles ne peuvent pas échouer. |
| Envoi et conversion d'une photo, retrait de l'import à la suppression du round | `scorecard-ai-import` (8) | La table `imports` naît avec l'import. |
| Ratings, slopes, page `/courses`, mise en file de la recherche SGT | `handicap-index` (6), `course-rating-sgt` (9) | — |
| Tableau de bord d'accueil | `home-and-match-strokes` (7) | — |

### Hors périmètre

- Suppression d'un joueur (absente de Next).
- Autosave de l'éditeur (propre à l'import, entrée 8).
- Pagination de la liste des rounds : le groupe en compte quelques centaines au plus.

## Requirements des specs Next

| Spec Next | Requirement | Sort | Raison ou adaptation |
| --- | --- | --- | --- |
| `players` | Joueurs initiaux | Porté | « Au premier démarrage » devient « au lancement du service `migrate` » ; `pnpm db:reset` devient `make fresh`. |
| `players` | Ajout et renommage de joueurs | Porté | — |
| `players` | Reconnaissance des noms lus sur la carte | Reporté (8) | — |
| `players` | Apprentissage des alias | Reporté (8) | L'ajout manuel d'un alias, présent dans l'interface de Next sans requirement, est spécifié ici. |
| `players` | Retrait d'un alias | Porté | Le scénario « à l'import suivant » se lit sur l'association de la ligne en 8 ; ici, l'alias disparaît de la liste. |
| `rounds` | Contenu d'un round | Porté | — |
| `rounds` | Parcours connus | Porté | — |
| `rounds` | Liste des rounds | Porté en partie | Sans le gagnant net (6). |
| `rounds` | Classement net d'un round | Reporté (6) | La page montre les bruts à la place. |
| `rounds` | Correction d'un round enregistré | Porté | Sans recalcul des index (6). |
| `rounds` | Suppression d'un round | Porté en partie | Photo effacée ici ; retrait de l'import en 8, recalcul en 6. |
| `rounds` | Carte de score d'un round | Porté | — |
| `rounds` | En-tête d'un round | Porté | — |
| `rounds` | Classement lisible sur téléphone | Reporté (6) | Avec le classement. |
| `scorecard-import` | Contrôles de cohérence | Porté en partie | Sans les contrôles de sommes (8). |
| `scorecard-import` | Correction manuelle avant enregistrement | Porté | Sans la photo ni le nom lu. |
| `scorecard-import` | Enregistrement bloqué tant que la carte est incohérente | Porté | — |
| `scorecard-import` | Saisie entièrement manuelle | Porté | — |
| `scorecard-import` | Détection de doublon | Porté | Pour la saisie manuelle et la correction. |
| `scorecard-import` | Choix de la date au calendrier | Porté | — |
| `scorecard-import` | Calendrier utilisable au clavier et au doigt | Porté | — |
| `scorecard-import` | Marques sur le champ de date | Porté en partie | Sans la marque de l'IA (8). |
| `scorecard-import` | Lignes de joueur groupées sur téléphone | Porté | Sans « lu « GAB » ». |
| `scorecard-import` | Saisie manuelle neuve sans alerte | Porté | Sans le cas OCR. |
| `scorecard-import` | Grille de correction sur téléphone | Porté | — |
| `loading-feedback` | Attentes de l'écran de correction | Porté | — |
| `loading-feedback` | Autres attentes de l'app | Porté | « Enregistrement du joueur… », « Suppression du round… ». |

## Capabilities

### New Capabilities

- `players` : joueurs du groupe, alias, joueurs initiaux.
- `rounds` : contenu, liste, page, saisie, correction et suppression d'un round, parcours connus, contrôles de cohérence, doublon, photo d'un round.
- `development-seed-data` : données de développement et de démonstration, garde de production, cibles `fresh` et `demo`.

### Modified Capabilities

- `app-shell` : les sections Rounds et Joueurs ont leur page ; l'exemple de sous-adresse sans page passe à Handicaps.
- `loading-feedback` : attentes de l'éditeur de round, de l'enregistrement d'un joueur et de la suppression d'un round.
- `deployment` : le service `migrate` crée aussi les joueurs initiaux et échoue sur une liste en double.

## Impact

- **Code** : `app/Domain/Players`, `app/Domain/Rounds` (Actions, Queries, exceptions), `app/Domain/DomainException.php` et son rendu dans `bootstrap/app.php`, modèles `Player`, `PlayerAlias`, `Round`, `RoundHole`, `RoundPlayer`, `Score`, contrôleurs et Form Requests, commande `golf:initial-players`, `routes/web.php` (les routes `rounds.index` et `players.index` remplacent le repli), pages `players/index`, `rounds/index`, `rounds/show`, `rounds/create`, `rounds/edit`, composants `round-editor/*`, `rounds/*`, `players/*`.
- **Base** : six tables nouvelles, toutes en UUID avec contraintes. Aucune table existante modifiée.
- **Déploiement** : commande du service `migrate` dans `docker-compose.yml` ; `INITIAL_PLAYERS` dans `.env.example` et `docs/deployment.md`.
- **Dépendances** : aucune nouvelle. La photo de démonstration est convertie une fois en WebP et versionnée sous `database/data/`.
- **Confidentialité** : noms et scores du groupe derrière le code d'accès, comme toute page. La photo est servie par une route protégée, jamais par une adresse publique ; `storage/{path}` ne doit pas la servir. Les données de démonstration reprennent la carte de référence déjà publique dans le dépôt Next, avec des prénoms seulement.
- **Retour arrière** : annuler la pull request de code et `php artisan migrate:rollback` des six migrations. Avant la mise en production réelle (entrée 10), aucune donnée de production n'existe dans ces tables.
