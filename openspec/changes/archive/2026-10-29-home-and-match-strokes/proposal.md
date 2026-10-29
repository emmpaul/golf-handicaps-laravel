# Proposal

## Why

Après `handicap-index` (6), chaque joueur a un index et chaque round un classement net, mais l'accueil dit encore « Le tableau du groupe arrive bientôt. », et les sections Handicaps et Coups servent « Section à venir ». Le groupe ne voit ni l'index de chacun d'un coup d'œil, ni son évolution, ni les coups à rendre avant un match, alors que c'est ce qu'il ouvre l'application pour savoir. Ce changement correspond à l'entrée 7 de `ROADMAP.md`.

Dépendances : l'entrée 7 dépend de 6 (`handicap-index`, archivée : `player_round_stats`, `course_ratings`, `Whs::courseHandicap`) et de 8 (`scorecard-ai-import`, proposée, non implémentée), dont elle lit la table `imports` pour le bloc « Imports à vérifier » de l'accueil. **L'apply commence après l'archivage de 8** (tâche 1.1), même si les pages Handicaps et Coups n'en dépendent pas (décision par défaut DP1 de `design.md`). L'entrée 11 (`visual-redesign`, proposée) vient après et recompose ces trois pages : ce changement livre le comportement dans le thème actuel, sans investir dans un style que 11 remplacera.

## What Changes

- **Accueil `/`** (port de `home-dashboard` de Next) : titre « Handicaps golf », action principale « Importer une carte » vers `/import`, index de chaque joueur avec son écart signé au round précédent (« +0,3 », « −0,3 », « = ») et la mention « provisoire », dernier round joué (parcours, tees, date, brut de chaque joueur, gagnant net) avec un lien vers sa page, bloc « N imports à vérifier » vers la vérification du plus récent, bouton de déconnexion gardé.
- **Page Handicaps `/handicaps`** (port de la page Handicaps de Next) : sélecteur des joueurs, un joueur à la fois, joueur choisi dans l'adresse (`?joueur=<uuid>`) ; en tête, l'index actuel en grand, l'écart signé et « provisoire » ; puis la liste de ses rounds du plus récent au plus ancien : date, marque « Compte » sur la même ligne, parcours et tees, différentiel, index après, chaque ligne vers la page du round. Explication repliée « Comment l’index est calculé » sur cette page aussi.
- **Page Coups `/strokes`** (port de `match-strokes` de Next, version `add-course-rating`) : 2 à 4 joueurs qui ont un index, un parcours connu, mode « Écart » (défaut) ou « Complet », handicap de jeu = handicap de parcours avec la rating saisie du parcours (repli par / 113), répartition des coups par trou, grille de 18 cases, coups rendus pour un handicap négatif, rating du parcours choisi avec un lien vers `/courses`. Calcul dans le navigateur, sans aller-retour serveur.
- **Plus de page « Section à venir »** : avec 8 et ce changement, les cinq sections ont leur page ; la page et ses routes de repli disparaissent, une sous-adresse inconnue de `/handicaps` ou `/strokes` répond 404.
- **Pas de recharts ni de courbe de l'index** (décision par défaut DP3) : la courbe de Next n'est pas portée ; l'index après chaque round se lit dans la liste, et le graphique des différentiels arrive avec 11, en CSS. Aucune dépendance ajoutée.
- **Lectures** : index courant et écart de chaque joueur, historique d'un joueur, dernier round seul (sans charger toute la liste), compte des imports prêts sans round, parcours connus avec leurs index de trou et leur rating ; nombre de requêtes borné par des tests.
- **Données** : aucun modèle, aucune table, aucune migration. Le jeu de démonstration existant (25 rounds, rating de BETHPAGE BLACK / Red, imports de 8) remplit les trois pages.

### Reporté à d'autres changements

| Sujet | Changement | Raison |
| --- | --- | --- |
| Graphique des différentiels (barres), tendance « ▼ 0,4 », bloc STATS, onglets en pilule, classement de l'accueil trié par index, dernier round en net, compte d'imports sur le bouton Importer | `visual-redesign` (11) | Le design les définit ; ses deltas `home-dashboard`, `match-strokes` et `handicap-index` s'ajoutent à ceux-ci (sa tâche 1.2 les réconcilie). |
| Rating « confirmée » contre « proposée » pour le handicap de parcours de la page Coups | `course-rating-sgt` (9) | Sans SGT, une rating saisie est confirmée par sa saisie (comme en 6) ; 9 ajoute les statuts et filtre. |
| Comparaison des pages avec la prod Next au cutover | `sqlite-import-command` (10) | Répétition sur staging (`docs/PORT_TO_LARAVEL.md`, Cutover). |

### Hors périmètre

- Mise en page sur deux colonnes à partir de 768 px et sections de tous les joueurs à la fois sur ordinateur (Next) : 11 garde la colonne du téléphone à toute largeur (sa décision Q3) ; ce changement montre un joueur à la fois à toute largeur (DP4).
- Match play trou par trou, scores en cours de partie, enregistrement d'une partie préparée : la page Coups ne stocke rien, comme dans Next.
- Allocation à 95 % ou autre règle WHS de handicap de jeu absente de Next.

## Requirements des specs Next

Référence : `openspec/specs/{home-dashboard,match-strokes,handicap-index}` de Next, le changement `add-course-rating` de Next (non archivé, implémenté) pour `match-strokes`, et le code `src/app/(app)/{page,handicaps/page,strokes/page}.tsx`, `src/components/strokes/*`, `src/domain/strokes.ts`, `src/server/home/summary.ts`.

| Spec Next | Requirement | Sort | Raison ou adaptation |
| --- | --- | --- | --- |
| `home-dashboard` | Action d'import en tête | Porté | — |
| `home-dashboard` | Index des joueurs | Porté | Un premier round n'a pas d'écart (code de Next, scénario ajouté). |
| `home-dashboard` | Dernier round | Porté | Gagnants ex æquo tous nommés, aucune ligne de gagnant quand personne n'avait d'index (règle de `rounds` de 6). |
| `home-dashboard` | Imports à vérifier | Porté | Lit la table `imports` de 8 ; libellés « 1 import à vérifier », « 2 imports à vérifier ». |
| `match-strokes` | Préparation d'une partie | Porté | Au plus 4 joueurs : un cinquième ne se coche pas (code de Next). Sans parcours connu, message (ajouté). |
| `match-strokes` | Modes de calcul des coups | Porté | Version `add-course-rating` : handicap de parcours ; « confirmée » devient « saisie » (comme 6). |
| `match-strokes` | Répartition des coups par trou | Porté | — |
| `match-strokes` | Handicap de jeu négatif | Porté | Libellé « rend 2 coups » et case « −1 » (code de Next). |
| `match-strokes` | Grille des coups par trou | Porté | — |
| `match-strokes` | Joueur sans coup | Porté | — |
| `match-strokes` (`add-course-rating`) | Rating du parcours choisi | Porté | Libellés de la page d'un round de 6 : « Rating 71,4 » et « Slope 144 », ou « Sans rating (par 71, slope 113) » (DP6). |
| `handicap-index` | Historique de l'index (affichage) | Porté en partie | La liste des différentiels et la marque « Compte » sont portées ; la courbe ne l'est pas (DP3). Le stockage est déjà porté par 6. |
| `handicap-index` | Un joueur à la fois sur téléphone | Porté, adapté | Un joueur à la fois à toute largeur (DP4). |
| `handicap-index` | Résumé de l'index d'un joueur | Porté | — |
| `handicap-index` | Marque « Compte » sur la ligne du round | Porté | — |
| — | Parcours et lien vers le round sur chaque ligne de l'historique | Ajouté | DP5 : donnée que 11 affiche aussi ; le lien sert à retrouver un round depuis son différentiel. |
| — | Explication du calcul sur la page Handicaps | Ajouté | Le sous-titre de Next (« différentiel = score ajusté − par ») est faux depuis la rating ; l'explication de 6 le remplace (DP7). |
| `app-shell` | Adresses des sections | Modifié | Plus aucune section sans page : « Section à venir » disparaît. |

## Capabilities

### New Capabilities

- `home-dashboard` : l'accueil, son action d'import, les index du groupe, le dernier round et les imports à vérifier.
- `match-strokes` : la préparation d'une partie, le calcul et la répartition des coups reçus ou rendus, la grille par trou, la rating du parcours choisi.

### Modified Capabilities

- `handicap-index` : la page Handicaps (sélecteur, résumé de l'index, liste des rounds et marque « Compte ») ; l'explication du calcul gagne la page Handicaps.
- `app-shell` : la page « Section à venir » disparaît, toutes les sections ayant leur page.

`app-shell` est aussi modifiée par 8 (« Section qui a sa page » pour Importer) : ce delta suppose 8 archivé.

## Impact

- **Code** : Queries `PlayerStandings` et `PlayerHandicapHistory` (`app/Domain/Handicaps/Queries`), `PendingImports` (`app/Domain/Imports/Queries`), `RoundList` (dernier round seul), `CourseOverview` (index de trou), `HomeController`, `HandicapController`, `StrokesController`, pages `home`, `handicaps/index`, `strokes/index`, composants `handicaps/*` et `strokes/*`, `lib/strokes.ts` et `lib/format.ts`, routes `home`, `handicaps.index`, `strokes.index`. Suppression de `section-placeholder.tsx` et de `SectionPlaceholderTest`.
- **Base** : aucune table, aucune colonne, aucune migration.
- **Dépendances** : aucune. recharts n'est pas ajouté.
- **Confidentialité** : les trois pages sont derrière le code d'accès comme toute page ; elles n'affichent que les données du groupe déjà visibles ailleurs (index, rounds, imports). Aucune requête sortante, rien de stocké : la préparation d'une partie reste dans le navigateur.
- **Retour arrière** : annuler la pull request de code ; aucune donnée à reprendre.

## Documents touchés

| Document | Changement |
| --- | --- |
| `ROADMAP.md` | Entrée 7 « proposé » (cette PR), puis « fait » à l'archivage. |
| `docs/PORT_TO_LARAVEL.md` | Section « 7. home-and-match-strokes » : « page des handicaps (graphiques recharts) » devient « page des handicaps (liste des différentiels ; le graphique arrive avec 11) » ; décision 7 annotée : recharts n'est pas ajouté par 7 (pull request de code). |
| `ARCHITECTURE.md` | Section « Frontend » : `/`, `/handicaps` et `/strokes` ont leur page, plus de `section-placeholder` ; section « Index et ratings » : lectures de l'accueil et de la page Handicaps, calcul des coups dans le navigateur et son jeu de cas partagé (pull request de code). |
| `CHANGELOG.md` | Lignes `Added` et `Removed` sous `## [Unreleased]` (pull request de code). |
