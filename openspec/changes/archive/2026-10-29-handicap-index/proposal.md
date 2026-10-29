# Proposal

## Why

Après `players-and-rounds`, l'application garde les rounds mais ne calcule rien : la page d'un round montre les bruts, la liste des rounds n'a pas de gagnant net, la page Joueurs n'affiche aucun index. Or l'index de chaque joueur et le classement net d'un round sont la raison d'être de l'application, et l'accueil (7), la recherche SGT (9) et l'import SQLite (10) les supposent. Ce changement correspond à l'entrée 6 de `ROADMAP.md`.

Dépendances : l'entrée 5 (`players-and-rounds`) doit être fusionnée et archivée avant l'apply : ce changement modifie ses capacités `rounds`, `players` et `development-seed-data`, et appelle le recalcul depuis ses Actions `CreateRound`, `UpdateRound` et `DeleteRound`. L'entrée 4 reste « en cours » (staging, première `/release`) ; ce changement n'en dépend pas pour son code. Le mainteneur confirme avant l'apply que l'entrée 5 est archivée.

## What Changes

- **Calcul WHS** porté de `src/domain/handicap.ts` et `src/domain/strokes.ts` de Next : score ajusté (trou plafonné à par + 4), différentiel `113 / slope × (ajusté − course rating)`, index sur les 20 derniers rounds par la table WHS, index provisoire sous 3 rounds, arrondi au dixième et plafond 54,0, handicap de jeu (handicap de parcours). Les tests du domaine de Next sont portés en Pest avec les mêmes valeurs attendues, en littéraux.
- **Table dérivée `player_round_stats`** : pour chaque joueur d'un round, brut, ajusté, différentiel, index juste avant et juste après le round, et marque « compte dans l'index actuel ». Mêmes colonnes et mêmes unités (dixièmes entiers) que Next, pour que l'import SQLite (10) compare ligne à ligne.
- **Recalcul complet** de l'historique des joueurs concernés, dans la transaction de chaque écriture : création, correction, suppression d'un round, saisie, correction ou retrait d'une rating. Commande `golf:recompute-stats`, idempotente, lancée par le service `migrate` à chaque déploiement après les joueurs initiaux : une base qui a déjà des rounds (staging) est calculée sans étape à la main.
- **Ratings saisies à la main** : table `course_ratings`, une ligne par parcours et tees (sans casse), course rating à une décimale de 40,0 à 90,0, slope entier de 55 à 155. Page `/courses` : la liste des parcours connus avec leur par, leur rating et leur slope ou « sans rating », la saisie ou la correction de chacun, et « Retirer la rating » pour revenir à « sans rating » (par et slope 113). Sans rating, le calcul se replie sur le par et le slope 113 et redonne exactement les valeurs du calcul sans rating.
- **Classement net d'un round** sur sa page, à la place des bruts : brut, handicap de jeu, net, rang (rang de compétition, ex æquo partagés), « pas encore d'index » pour un premier round. Lisible à 320 px. La page du round montre la rating et le slope utilisés ou « Sans rating (par 71, slope 113) », avec un lien vers `/courses`.
- **Liste des rounds** : colonne « Gagnant net » et lien « Parcours » vers `/courses`.
- **Page Joueurs** : l'index actuel de chaque joueur (« Index 10,0 », « Index 14,5 (provisoire) », « Index — »).
- **Explication du calcul** : sur la page Joueurs, la page d'un round et `/courses`, un bloc replié « Comment l’index est calculé » donne en sept lignes le score ajusté, le différentiel, l'index, le nombre de différentiels retenus et l'ajustement selon le nombre de rounds, l'index provisoire et le repli sans rating.
- **Données de démonstration** : la rating de BETHPAGE BLACK / Red (71,4 / 144) saisie par le seeder ; les statistiques de chaque round de démonstration sont calculées. Fabriques et états des deux modèles nouveaux.
- **ADR 0010** : calcul WHS en dixièmes entiers, table dérivée recalculée dans la transaction d'écriture, rating appliquée à tout l'historique d'un parcours.

### Reporté à d'autres changements

| Sujet | Changement | Raison |
| --- | --- | --- |
| Page Handicaps : courbe de l'index, liste des différentiels et marque « Compte », résumé et écart, un joueur à la fois sur téléphone | `home-and-match-strokes` (7) | La page et ses graphiques recharts sont l'entrée 7. Ce changement stocke tout ce qu'elle lit. |
| Index des joueurs sur l'accueil, dernier round | `home-and-match-strokes` (7) | Tableau de bord. |
| Coups rendus en match, répartition par trou, rating du parcours choisi sur la page Coups et son lien vers `/courses` | `home-and-match-strokes` (7) | La page Coups n'existe pas encore ; le handicap de parcours qu'elle utilisera est porté ici. |
| Recherche sur Simulator Golf Tour, proposition lue par l'IA, statuts de recherche, choix d'un candidat ou d'un tee, confirmation, relance, liens Carte SGT et The Course View, balayage des parcours existants | `course-rating-sgt` (9) | Il faut le worker, l'IA et son budget (8). Ici, une rating saisie à la main est confirmée par sa saisie même. |
| Comparaison des statistiques recalculées aux valeurs SQLite | `sqlite-import-command` (10) | La table et ses unités sont faites pour cette comparaison. |

### Hors périmètre

- Règles WHS absentes de Next : allocation à 95 %, net double bogey, PCC, caps, rounds de 9 trous.
- Rating figée à la date d'un round : une rating s'applique à tous les rounds du parcours, passés et futurs, comme dans Next.
- Fusion de tees proches (« Blue » et « Blue 6212yd ») : deux parcours distincts.

## Requirements des specs Next

Référence : `openspec/specs/{handicap-index,rounds}` de Next et le changement `add-course-rating` de Next (non archivé, implémenté dans `src/domain` et `src/server/courses`), qui modifie `handicap-index`, `rounds` et `match-strokes` et crée `course-rating`.

| Spec Next | Requirement | Sort | Raison ou adaptation |
| --- | --- | --- | --- |
| `handicap-index` | Score ajusté par trou | Porté | — |
| `handicap-index` | Différentiel d'un round | Porté | Version `add-course-rating` : avec rating et slope, repli par / 113. |
| `handicap-index` | Calcul de l'index | Porté | — |
| `handicap-index` | Index provisoire | Porté | L'affichage « 14,5 (provisoire) » et « — » se voit sur la page Joueurs. |
| `handicap-index` | Arrondi et bornes de l'index | Porté | — |
| `handicap-index` | Historique de l'index | Porté en partie | L'index après chaque round et la marque « compte » sont stockés ; la courbe et la liste des différentiels sont en 7. |
| `handicap-index` | Recalcul complet | Porté | Version `add-course-rating`, avec la saisie d'une rating. |
| `handicap-index` | Un joueur à la fois sur téléphone | Reporté (7) | Page Handicaps. |
| `handicap-index` | Résumé de l'index d'un joueur | Reporté (7) | Page Handicaps. |
| `handicap-index` | Marque « Compte » sur la ligne du round | Reporté (7) | Page Handicaps. |
| `rounds` | Liste des rounds | Porté | Le gagnant net s'ajoute à la liste de `players-and-rounds` ; lien « Parcours » de `add-course-rating`. |
| `rounds` | Classement net d'un round | Porté | Version `add-course-rating` : handicap de parcours. Remplace « Bruts d'un round » de `players-and-rounds`. |
| `rounds` | Classement lisible sur téléphone | Porté | — |
| `rounds` | Correction d'un round enregistré | Porté | Le recalcul est spécifié dans `handicap-index`, « Recalcul complet ». |
| `rounds` | Suppression d'un round | Porté | Idem. |
| `rounds` (`add-course-rating`) | Rating affichée sur le round | Porté | « Rating 71,4 » et « Slope 144 » en deux éléments, sans « · » (règle de `DESIGN.md`, comme l'implémentation de Next). |
| `course-rating` | Rating et slope d'un parcours | Porté | « Confirmée » devient « saisie » : sans SGT, seule la saisie à la main écrit une rating. |
| `course-rating` | Recherche sur Simulator Golf Tour | Reporté (9) | — |
| `course-rating` | Proposition lue sur la carte SGT | Reporté (9) | — |
| `course-rating` | Page Parcours | Porté en partie | Sans état de recherche ni liens SGT (9) ; sans lien depuis la page Coups (7). |
| `course-rating` | Confirmation, choix et saisie manuelle | Porté en partie | Saisie et correction à la main ; confirmation, choix et relance en 9. |
| `course-rating` | Rounds déjà enregistrés | Reporté (9) | Balayage SGT. |
| `match-strokes` | Modes de calcul des coups, Rating du parcours choisi | Reporté (7) | Seul le handicap de parcours est porté ici. |
| `loading-feedback` | Autres attentes de l'app | Porté | « Enregistrement de la rating… » (composant `manual-rating-form` de Next, sans requirement). |
| — | Index sur la page Joueurs | Porté | Affiché par la page Joueurs de Next sans requirement ; spécifié ici. |
| — | Retrait d'une rating | Ajouté | Absent de Next ; demandé par le mainteneur. Aucun changement de schéma. |
| — | Explication du calcul | Ajouté | Demandé par le mainteneur ; texte seul. La page Handicaps reste en 7. |
| — | Recalcul au déploiement (`deployment`) | Ajouté | Le service `migrate` lance aussi `golf:recompute-stats`, idempotente. |

## Capabilities

### New Capabilities

- `handicap-index` : score ajusté, différentiel, index et index provisoire, historique stocké par round, recalcul complet, explication du calcul.
- `course-ratings` : course rating et slope d'un parcours et de ses tees, saisie et retrait à la main, page Parcours. La partie SGT s'y ajoute en 9.

### Modified Capabilities

- `rounds` : classement net et rating sur la page d'un round (à la place des bruts), gagnant net et lien Parcours sur la liste.
- `players` : index actuel de chaque joueur sur la page Joueurs.
- `loading-feedback` : attente de la saisie et du retrait d'une rating.
- `deployment` : le service `migrate` recalcule l'historique d'index à chaque déploiement.
- `development-seed-data` : rating de démonstration et statistiques calculées.

`rounds`, `players` et `development-seed-data` naissent avec `players-and-rounds` ; leurs deltas supposent ce changement archivé.

## Impact

- **Code** : `app/Domain/Handicaps` (calcul pur, recalcul, classement net, requêtes), `app/Domain/Courses` (saisie et retrait d'une rating, page Parcours), appel du recalcul dans `CreateRound`, `UpdateRound`, `DeleteRound`, requêtes `RoundList`, `RoundDetail`, `PlayerOverview` étendues, modèles `PlayerRoundStat` et `CourseRating`, `CourseRatingController` et ses deux Form Requests, commande `golf:recompute-stats` et commande du service `migrate` dans `docker-compose.yml`, routes `courses.index`, `courses.rating.update` et `courses.rating.destroy`, pages `courses/index`, `rounds/index`, `rounds/show`, `players/index`, composants `rounds/net-leaderboard`, `courses/manual-rating-form`, `handicaps/computation-help`, `lib/format.ts`, `docs/deployment.md`.
- **Base** : deux tables nouvelles en UUID, `player_round_stats` (clé étrangère composite vers `round_players`, en cascade) et `course_ratings` (unicité sur `lower(trim(...))`). Aucune table existante modifiée.
- **Dépendances** : aucune nouvelle.
- **Confidentialité** : index et classements derrière le code d'accès, comme toute page. Aucune requête sortante : SGT arrive en 9.
- **Retour arrière** : annuler la pull request de code et `php artisan migrate:rollback --step=2`. Les tables de `players-and-rounds` ne changent pas ; les statistiques se recalculent par `golf:recompute-stats` au prochain démarrage de `migrate`.
