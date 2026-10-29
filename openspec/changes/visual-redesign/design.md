# Design

## Context

- Le thème vit dans `resources/css/tokens.css` (palette fermée sous `@theme static`, couleurs sombres sous `prefers-color-scheme`), `app.css` (variables shadcn, zones sûres, `.scroll-frame`) et `base.css` (mouvement réduit). Les polices sont servies par deux `fontsource()` de `vite.config.ts`. Les primitives base-ui de `resources/js/components/ui/` sont possédées par le projet (ADR 0006).
- Garde-fous existants : `theme-sources.test.ts` (aucune couleur en dur, aucune transition globale), `theme-contrast.test.ts` (littéraux hex de la spec et contrastes), `tabular-figures.test.ts`, `reduced-motion.test.ts`, et côté Pest `DocumentHeadTest`, `WebManifestTest`, `AppIconsTest`, `PageAssetsTest`.
- La source du design est `Golf App.dc.html` (voir proposition, « Source du design »). Ses valeurs sont en ligne ; ce document les range en tokens. Les captures `ref/*-light.png` montrent l'ancienne interface et ne servent pas de cible.
- Ce changement s'applique après l'archivage de 6, 7, 8 et 9, fait le 2026-10-29. Les deltas ont été réconciliés avec leurs specs archivées par la tâche 1.2 (D12) ; les arbitrages sont listés dans « Décisions par défaut (orchestrateur) ».

## Goals / Non-Goals

**Goals :**
- Chaque valeur du design (couleur, police, taille, marge, rayon, ombre, durée, courbe) devient un token ou une classe dérivée d'un token, nommée une fois.
- Les pages à 390 px reproduisent le HTML au pixel près, vérifié par la passe navigateur contre des captures de la cible.
- Les garde-fous existants restent verts et sont réécrits avec les nouveaux littéraux.

**Non-Goals :**
- Aucune route ni Action ne change, et le calcul de l'index non plus. S'ajoutent quatre lectures : le compte d'imports partagé (D7), le rang de couleur des joueurs (D6), les statistiques du bloc STATS (D13) et le nombre de rounds par parcours de la page Parcours (D14).
- Pas de thème clair (décision Q1), pas de Sentient (décision Q2).

## Decisions

### D1. Tokens

`tokens.css` vide toujours `--color-*`, `--text-*`, `--radius-*`, `--shadow-*`, puis déclare :

| Token | OKLCH | Hex (sRGB) | Rôle |
| --- | --- | --- | --- |
| `--color-bg` | `0.13 0.005 260` | `#060709` | fond, `html`, `body`, bande de zone sûre, `theme-color` |
| `--color-surface-1` | `0.17 0.006 260` | `#0E0F12` | champs, listes, sous-totaux, feuille, tuiles, sélecteur |
| `--color-surface-2` | `0.2 0.006 260` | `#141619` | barre d'onglets, pastille d'alias, survol de tuile, fond de photo |
| `--color-surface-3` | `0.22 0.006 260` | `#191B1D` | case de coups, pastille neutre, bouton d'enregistrement bloqué |
| `--color-rule` | `0.27 0.008 260` | `#24272A` | filet sous un libellé de section, bordure de bouton à contour, pointillés, **contour de champ** (exception de contraste, D9), bordure des cases par et index (DO23) |
| `--color-rule-soft` | `0.21 0.006 260` | `#17181B` | filet entre deux lignes |
| `--color-bar` | `0.3 0.008 260` | `#2B2E32` | barre de différentiel qui ne compte pas |
| `--color-ink` | `0.97 0.003 260` | `#F4F5F7` | texte, bouton principal, onglet courant, toast |
| `--color-ink-2` | `0.68 0.01 260` | `#95989F` | texte secondaire, libellés, métas |
| `--color-ink-3` | `0.5 0.01 260` | `#606369` | contrôle désactivé (bouton bloqué), décor ; jamais de texte lisible (DO15) |
| `--color-ink-4` | `0.4 0.01 260` | `#45484D` | « · » d'un trou sans coup, **bordure de case décochée** (exception de contraste, D9), segment « Double bogey + » |
| `--color-over` | `0.6 0.01 260` | `#7D8086` | marques bogey et double bogey |
| `--color-accent` | `0.78 0.16 60` | `#FF9C3B` | bouton Importer, imports à vérifier, « 1ER », provisoire, problèmes, case vide |
| `--color-signal` | `0.7 0.17 25` | `#F66D67` | sous le par, « Supprimer », erreur |
| `--color-info` | `0.8 0.12 200` | `#43D5DC` | lecture en cours, repère IA |
| `--color-ok` | `0.8 0.14 145` | `#81D584` | rating confirmée, écart par par jusqu'à 0,3 |
| `--color-on-signal` | `#FFFFFF` | `#FFFFFF` | texte du bouton rouge « Supprimer » (exception de contraste, D9) |
| `--color-player-1` à `-6` | `0.72 0.24 145`, `0.62 0.22 260`, `0.75 0.19 55`, `0.72 0.17 200`, `0.58 0.26 300`, `0.85 0.19 105` | hors gamut sRGB pour la plupart | couleurs de joueur |

Les fonds à 8, 15, 22 % et les bordures à 50 et 60 % sont écrits avec les modificateurs d'opacité de Tailwind (`bg-accent/15`), pas en nouveaux tokens. Le voile de la feuille (`rgba(0,0,0,.6)`) et l'ombre deviennent `--color-scrim` et `--shadow-float`.

Les hex sont calculés par la conversion OKLCH → sRGB de CSS Color 4 et arrondis ; ce sont les littéraux de `theme-contrast.test.ts`. Les couleurs de joueur sont hors gamut sRGB : leur valeur testée reste la chaîne OKLCH.

`app.css` repointe les variables shadcn : `--background` → `bg`, `--foreground` → `ink`, `--primary` → `ink`, `--primary-foreground` → `bg`, `--muted` → `surface-1`, `--muted-foreground` → `ink-2`, `--border` → `rule`, `--input` → `rule`, `--destructive` → `signal`, `--destructive-foreground` → `on-signal`, `--ring` → `ink`. Le bloc `prefers-color-scheme` disparaît ; `:root { color-scheme: dark }`.

- *Alternative* : garder les noms `paper`/`ink` du scoreboard. Rejetée : `paper` désignerait un fond presque noir.

### D2. Typographie

`fontsource('Geist', { weights: [400, 500, 600] })` et `fontsource('Geist Mono', { weights: [500, 600] })`, sous-ensembles `latin` et `latin-ext`, variables `--font-geist` et `--font-geist-mono`. `--font-sans` et `--font-mono` les pointent ; `--font-display` et `--font-body` disparaissent. Le HTML charge aussi Geist 700 et Geist Mono 400 sans s'en servir : non repris.

Échelle (taille / interlignage / approche), en tokens `--text-*` :

| Token | Famille, graisse | Valeur | Usage |
| --- | --- | --- | --- |
| `display-xl` | Mono 500 | 88 / 1 / −0,06 em | index de la page Handicaps, « — » sans index |
| `display-l` | Mono 500 | 48 / 0,9 / −0,04 em | coups reçus |
| `display` | Mono 500 | 44 / 1 / −0,04 em | index de l'accueil, net d'un round |
| `kpi` | Mono 500 | 30 / 1 / −0,04 em | valeurs du bloc STATS |
| `title` | Geist 600 | 28 / 1,05 / −0,03 em | titre de page |
| `num-l` | Mono 500 | 22 / 1 | jour d'un round, index de la page Joueurs, moyenne par par |
| `name-l` | Geist 600 | 22 / 1 / −0,01 em ; 22 / 1,1 / −0,02 em | nom du classement d'un round ; parcours du dernier round |
| `name-m` | Geist 600 | 20 / 1 / −0,01 em ; 17 / 1 | nom de la page Joueurs ; nom de l'accueil et parcours de la page Parcours |
| `num-m` | Mono 500 | 24, 18, 17, 16, 15 / 1 | net du dernier round, rang d'un round, numéro de trou, case, score brut d'un round |
| `body-l` | Geist 500 | 16 / 1 | champs et listes |
| `label` | Geist 600 | 15 / 1 ; 14 / 1 ; 13 / 1 | boutons et noms de ligne ; onglets de joueur ; libellé d'onglet |
| `body` | Geist 400 | 14 / 1,5 ; 13 / 1,5 ; 12 / 1,5 | messages vides ; texte courant ; explications |
| `link` | Geist 500 | 14 / 1 ; 13 / 1 | liens de rangée ; liens d'action |
| `meta` | Mono 500 | 13, 12 / 1 (1,4 pour la méta d'un round) | index d'un joueur, métas |
| `section` | Mono 500 | 11 / 1 / 0,08 em, capitales | libellés de section |
| `caption` | Mono 500 | 10 / 1 / 0,08 em, capitales ; 9 / 1 | libellés de colonne ; numéro de trou des coups, date des barres |

Les capitales des libellés passent par `uppercase` : le texte du DOM garde sa casse, les tests cherchent le texte d'origine. Exception : quand le design change le mot lui-même (« 1ER NET », « À VÉRIFIER »), le texte du DOM est le nouveau mot.

### D3. Mesures du cadre

- Marges latérales : 20 px partout (`--gutter: 20px`), 16 px pour la barre flottante.
- Rangée du haut : le design la place à 44 px du haut de la maquette (rangée à actions) ou 54 px (rangée « Golf » seule), sans barre d'état dessinée. On garde ces écarts au-dessus de la zone sûre : `padding-top: max(44px, env(safe-area-inset-top))` et `max(54px, env(safe-area-inset-top) + 10px)`. Sans zone sûre (navigateur de bureau, passe à 390 px), la mesure égale celle du design.
- Bas de page : `padding-bottom: calc(108px + env(safe-area-inset-bottom))` ; barre flottante à `bottom: calc(28px + env(safe-area-inset-bottom))`.
- Colonne : `max-width: 390px; margin-inline: auto` sur `main` et sur le conteneur fixe de la barre (décision Q3). Le fond de `body` reste `bg` à toute largeur ; le cadre de téléphone de la maquette (rayon 28 px, fond `#0a0b0d`) est une présentation, pas repris.
- Bande de zone sûre : un élément fixe de `env(safe-area-inset-top)` de haut, fond `bg`, au-dessus du contenu, remplace l'en-tête collé.

### D4. Navigation

`AppNav` est réécrit : un `nav` fixe qui contient la pilule (quatre `NavLink`) et le lien rond Importer. Le libellé de chaque onglet est toujours dans le DOM (`max-width: 0; opacity: 0` hors de l'onglet courant), et le lien garde `aria-label` : le nom accessible ne dépend pas de l'animation. Icônes lucide déjà installées : `ListOrdered`, `TrendingUp`, `Flag`, `Users`, `Camera`, trait de 2,1 (2,2 pour Importer). La navigation du haut et le `BrandMark` d'en-tête disparaissent ; `BrandMark` reste pour `favicon.svg` et l'icône. Le layout reçoit une prop `chrome: 'nav' | 'editor'` ; les pages de l'éditeur passent `editor` (barre d'enregistrement, pas de navigation).

### D5. Composants

Primitives restylées dans `components/ui/` (même API) : `button` (variantes `default` pleine encre, `outline` à contour `rule`, `ghost`, `destructive` pleine rouge à texte blanc `on-signal` ; tailles 44, 48, 52, 56 px), `input` et `native-select` (48 px, `surface-1`, contour `rule`, rayon 10, chevron de 16 à 18 px), `badge` (pastilles d'état, D2 `caption`/`meta`), `alert-dialog` remplacé dans son rendu par une feuille du bas (même API, `role="alertdialog"`, focus piégé, Échap), `toaster` (pilule du haut), `calendar` et `popover` aux nouvelles surfaces.

Composants nouveaux ou réécrits : `PageTopRow` (rangée du haut, deux variantes), `SectionLabel`, `PlayerSwatch` (pastille de 6, 8 ou 10 px, rayon 2 ou 3 px), `ScoreMark` (quatre niveaux), `RankRow`, `SegmentedControl` (Coups), `PlayerTabs` (Handicaps), `DifferentialBars`, `StrokeHoleGrid`, `ImportTile`, `EditorSaveBar`, `DistancesBlock` (bloc « Distances » replié, `details`/`summary` natif, décision Q6), `ScoringStats` (bloc STATS : valeurs, barre, classes, cases par par). `ScrollFrame` est retiré avec le requirement « Contenu défilant signalé ».

### D6. Couleur de joueur

Le rang de création est calculé en SQL dans les Query qui rendent des joueurs : `(row_number() over (order by created_at, id) - 1) % 6`, sur la table `players` entière (une sous-requête), exposé en `colorSlot` (0 à 5). Le front associe `colorSlot` à `--color-player-{n+1}`. Rien n'est stocké : renommer ne change rien, et un joueur n'est jamais supprimé.

- *Alternative* : colonne `color` en base. Rejetée : migration, fabrique et seeder pour une donnée dérivable.
- *Alternative* : rang calculé côté client. Rejetée : une page ne charge pas toujours tous les joueurs.

### D7. Compte d'imports à vérifier

Le badge du bouton Importer est sur toutes les pages : `HandleInertiaRequests` partage `pendingImports` (imports prêts à vérifier sans round), une requête `count` par visite, lue avec la page (prop ordinaire, DO35) et seulement avec une session ouverte. L'accueil lit la même valeur.

### D8. Mouvement

Les durées et courbes du design deviennent `--ease-design: cubic-bezier(0.2, 0.8, 0.2, 1)`, `--ease-pop: cubic-bezier(0.3, 1.6, 0.5, 1)`, `--dur-press: 150ms`, `--dur-fade: 250ms`, `--dur-swap: 300ms`, `--dur-nav: 350ms`, `--dur-sheet: 400ms`, `--dur-enter: 420ms`, `--dur-bar: 600ms`. L'entrée de page est jouée par le layout à chaque visite Inertia (clé de page), en deux `requestAnimationFrame` comme le prototype. `theme-sources.test.ts` continue d'interdire `transition-all` ; la règle « jamais largeur ni hauteur » de `DESIGN.md` est levée pour les seules propriétés que le design anime (hauteur des barres, largeur maximale et marge du libellé d'onglet, remplissage de l'onglet). `base.css` garde la réduction à 0,01 ms sous `prefers-reduced-motion`.

### D9. Contraste (décision Q4)

Le mainteneur a choisi le pixel près pour trois cas exactement, sous les seuils de la spec : contour de champ `rule` `oklch(0.27 0.008 260)` à 1,34:1 contre le fond, bordure de case décochée `ink-4` `oklch(0.4 0.01 260)` à 2,18:1, texte blanc `on-signal` sur le bouton rouge à 2,88:1. `theme-contrast.test.ts` les déclare en une liste littérale d'exceptions, chacune avec son ratio attendu arrondi au centième (1,34, 2,18, 2,88), et vérifie que toutes les autres paires de texte et de marque passent leur seuil ; une quatrième paire sous le seuil, ou une exception dont le ratio change, fait échouer le test en la nommant. Le compromis (moins de visibilité des champs et des cases pour les personnes malvoyantes, contre la fidélité au design) est consigné dans l'ADR comme décision du mainteneur.

- *Alternative* : réemployer `ink-3` et `bg` pour passer les seuils (3,35:1 et 6,98:1). Écartée par le mainteneur.

### D10. Icônes et manifest (décision Q10)

`GenerateAppIcons` garde le dessin, avec fond `#060709`, green `#191B1D`, mât `#F4F5F7`, fanion `#F66D67`. `manifest.webmanifest` : `theme_color` et `background_color` `#060709`. `app.blade.php` : une seule `theme-color` `#060709`.

### D11. Vérification

- Vitest : `theme-tokens.test.ts` lit `tokens.css` et compare chaque token aux littéraux de D1 et D2 ; `theme-contrast.test.ts` réécrit (conversion OKLCH → sRGB dans le test, littéraux hex de D1, seuils de la spec, liste littérale des trois exceptions de D9 et leurs ratios) ; tests de composants (`ScoreMark` quatre niveaux et annonces, `AppNav` onglets, `aria-current`, libellé de l'onglet courant, badge, `PlayerSwatch` rang → token, `EditorSaveBar`, feuille, toast d'erreur) ; `theme-sources.test.ts` inchangé dans ses règles.
- Pest : `DocumentHeadTest` (une `theme-color` `#060709`, `color-scheme`), `WebManifestTest`, `AppIconsTest` (pixels du fond et du fanion), `PageAssetsTest` (aucun domaine tiers, Google Fonts compris), test de Query pour `colorSlot` (Gab 0, Manu 1, Sean 2, septième joueur 0), test du partage `pendingImports`, test de la Query des statistiques (D13).
- Passe navigateur `browser-use` : chaque écran à 390 × 844 comparé côte à côte aux captures de la cible (`reference/`), puis 320 px (pas de défilement horizontal, quatre joueurs) et 1440 px (colonne centrée). Les mesures clés (marges, hauteurs, tailles calculées) sont relevées par `getComputedStyle` et comparées aux valeurs de la spec.

### D12. Réconciliation avec 6 à 9

Avant le premier code, les deltas sont réécrits sur les specs archivées : chaque ADDED qui contredit une exigence archivée de 6, 7, 8 ou 9 devient un MODIFIED complet de cette exigence, scénarios archivés gardés sous leur nom et adaptés ; les exigences de pure mise en page restent ADDED. `openspec validate --strict` doit rester vert. Si 7 ou 9 ont changé une page au point que le design ne s'applique plus, l'apply s'arrête.

Fait le 2026-10-29 (tâche 1.2, à la main faute de `/opsx:update` dans la session). Aucune page n'a changé au point d'écarter le design. Exigences passées en MODIFIED :

- `app-shell` : « Adresses des sections » (les cinq sections existent, plus de « Section à venir », sous-adresse inconnue en 404).
- `course-ratings` : « Page Parcours » (bloc « · Red », « PAR 71 », pastille, « 3 rounds », quatre onglets) ; la recherche de 9 est ajoutée en ADDED « Recherche SGT dans le bloc d'un parcours », sans rien retirer de ses exigences.
- `handicap-index` : « Résumé de l'index d'un joueur » (absorbe l'ancien ADDED « Tendance de l'index »), « Liste des rounds d'un joueur » (absorbe « Historique de la page Handicaps »), « Marque « Compte » sur la ligne du round » (point de couleur nommé « Compte »).
- `home-dashboard` : « Action d'import en tête », « Index des joueurs » (absorbe « Classement des index de l'accueil »), « Dernier round » (absorbe « Dernier round de l'accueil »), « Imports à vérifier ».
- `match-strokes` : « Préparation d'une partie », « Handicap de jeu négatif », « Total des coups d'un joueur », « Grille des coups par trou », « Rating du parcours choisi ».
- `players` : « Index sur la page Joueurs ».
- `rounds` : « Classement net d'un round » et « Classement lisible sur téléphone » (absorbent « Présentation du classement net »), « Rating affichée sur le round ».
- `scorecard-import` : « Une action principale à l'import », « Repère de lecture par l'IA » (token `--color-info` au lieu de `--color-ai`), « Nom lu sur chaque ligne ».

### D13. Statistiques du bloc STATS (décision Q8)

Une Query `PlayerScoringStats` sous `App\Domain\Handicaps\Queries`, appelée par le contrôleur de la page Handicaps pour le joueur choisi, fait une seule requête d'agrégats sur `scores` joints à `round_holes` et `round_players` du joueur : nombre de rounds, somme des bruts, meilleur brut, compte de trous par classe d'écart (`score - par` ≤ −2, −1, 0, +1, ≥ +2) et, par par (3, 4, 5), nombre de trous et somme des scores. Le par est celui de `round_holes` du round, pas celui d'un parcours : c'est le par contre lequel la page du round marque les scores, et le seul que le schéma garde par round.

Les arrondis se font en PHP, en entiers, sur la valeur exacte, moitié vers le haut : moyenne au dixième `intdiv(20 × somme + n, 2 × n)` dixièmes, pourcentage `intdiv(200 × k + trous, 2 × trous)`, écart d'un par en dixièmes signés égal à la moyenne arrondie moins `10 × par` (DO40), pour que « 2,8 » sur un par 3 se lise « −0,2 ». La teinte d'un écart se décide avant arrondi, en entiers : vert si `10 × (somme − par × n) ≤ 3 × n`, gris si `≤ 10 × n`, rouge sinon. La Query rend des entiers (dixièmes, pour cent, comptes) et la teinte en énumération (`good`, `neutral`, `bad`) ; le front ne fait que formater « 72,0 », « 78% », « +0,3 » et « −0,7 ».

La prop est différée (`Inertia::defer`), seule : le graphique et l'historique viennent avec la page, et le chargement différé ne relit pas l'historique (closure, DO39) ; aucun round : `null`, et le bloc n'est pas rendu (DO37).

- *Alternative* : stocker les comptes dans `player_round_stats`. Rejetée : la table appartient au calcul de l'index et à la comparaison de l'import SQLite (entrée 10) ; une requête d'agrégats sur au plus quelques centaines de rounds suffit.
- *Alternative* : calculer en TypeScript depuis tous les scores du joueur. Rejetée : envoie toutes les cartes au navigateur pour cinq nombres.

### D14. Nombre de rounds par parcours

Le bloc d'un parcours montre « 3 rounds » (design). La Query de la page Parcours ajoute à chaque paire nom et tees le compte de ses rounds, groupé sans tenir compte de la casse ni des espaces autour, comme la rating. Aucune colonne ni route.

## Décisions par défaut (orchestrateur)

Arbitrages pris pendant la tâche 1.2 et l'unité 1, mainteneur absent ; chacun suit le principe de la proposition (le design prime quand il change clairement un libellé, un format ou un enchaînement ; ce que la spec exige et que le design ne dessine pas est gardé dans son style).

| # | Sujet | Arbitrage |
| --- | --- | --- |
| DO1 | Captures de la cible (tâche 1.3) | L'archive ne contient pas les neuf captures sombres, seulement `ref/*-light.png`. Rien n'est copié ; la passe 12.2 compare aux valeurs de la spec et le dit. |
| DO2 | Ordre des joueurs de l'accueil (DP2 de 7) | Par index croissant, puis par nom à index égal, sans index à la fin par nom (design). |
| DO3 | Lien « N imports à vérifier » (DP10 de 7) | Vers `/import` (design), non plus vers l'écran de correction du plus récent. |
| DO4 | Un joueur à la fois (DP4 de 7) | Inchangé : déjà la règle à toute largeur ; les onglets de joueur du design sont ce sélecteur. |
| DO5 | Écart de l'accueil et de la page Handicaps (« −0,3 », « Écart +0,3 » de 7) | Remplacé par la tendance du design (« ▼ 0,3 », « ▲ 0,3 », « = STABLE » / « = stable »), même calcul. Le nom du joueur n'est plus répété dans la section Handicaps (il est sur l'onglet courant) ; « Aucun round pour l’instant. » devient le texte du design. |
| DO6 | Dernier round de l'accueil | Joueurs rangés par net comme la page d'un round ; joueur sans index en fin, « {brut} · pas encore d'index », « — » ; aucun résultat « Net · » sans joueur classé ; bloc absent sans round au lieu de « Aucun round enregistré ». |
| DO7 | Page Coups, parcours | La liste s'ouvre sur le premier parcours connu (le design n'a pas d'état « aucun parcours choisi ») ; message unique « Choisissez au moins 2 joueurs avec un index. » (vouvoiement du design, DP11 de 7). |
| DO8 | Page Coups, rating | Libellés du design, « rating 71,4 · slope 144 » et « sans rating (slope 113) », au lieu de ceux de la page d'un round (DP6 de 7), qui elle-même passe à « 71,4 / 144 » / « SANS RATING » dans sa méta. |
| DO9 | Page Coups, cases sans coup et coups rendus | Case sans coup : « · » caché aux lecteurs d'écran (design), annonce « Trou 3 : aucun coup » gardée. Coups rendus (non dessinés) : « rend {S} coups » et « −1 », dans le style du bloc. |
| DO10 | Page Parcours, recherche SGT de 9 | Gardée entièrement dans le bloc du parcours : pastille de statut (cyan en recherche, ambre pour un choix à faire, rouge en échec, neutre sinon), note, parcours SGT, liens, proposition, tees, candidats, relecture de 2 s. La pastille de rating porte « · CONFIRMÉ » seulement quand le statut est « Confirmé » ; une rating confirmée dont la recherche relancée attend une proposition montre « 71,4 / 144 » seul et la pastille « Proposition à confirmer ». |
| DO11 | Nombre de rounds par parcours | Nouvelle lecture (D14), sans schéma ni route. |
| DO12 | Page de connexion (`app-access`) | Spec inchangée : la tâche 8.4 recompose `/login` avec les éléments du design en gardant marque, titre « Handicaps golf », champ et bouton ; aucun libellé ne change. |
| DO13 | Noms des tokens (D2, D1) | Échelle `--text-*` : un token par variante de D2, la variante principale sous le nom de D2, les autres suffixées de leur taille (`--text-label-14`, `--text-num-m-17`…), plus `--text-name-l-course` (22 / 1,1 / −0,02 em) et `--text-meta-round` (12 / 1,4). Graisses dans `--text-*--font-weight` ; famille par `font-sans` / `font-mono`. Rayons nommés par leur valeur (`--radius-22` … `--radius-2`) et `--radius-pill` (999 px). Courbes `--ease-design`, `--ease-pop` sous `@theme`, durées `--dur-*` dans `:root`. |
| DO14 | Intermédiaire de l'unité 1 | Le remplacement des tokens retire `paper`, `band`, `error`, `ai`, `focus`… : les composants pas encore restylés perdent ces couleurs jusqu'à leurs tâches (3 à 10). `base.css` est repointé sur les nouveaux tokens pour que le fond, le texte et le focus restent définis. `app.css` ne garde que les variables shadcn de D1 : `text-ai`, `border-birdie`, `border-bogey`, `bg-popover`, `bg-secondary` ne produisent plus de style, et `bg-accent` (survol des boutons et des lignes de tableau) prend l'ambre du design, jusqu'aux tâches 4.1, 4.2 et 6.3. Le formateur retrie les classes inconnues de 29 composants (ordre seul). |
| DO15 | Contraste hors des trois exceptions Q4 | Seules les trois exceptions de Q4 existent. `ink-3` (3,35:1) n'est permis que pour un contrôle désactivé (exempté par WCAG 1.4.3) et pour du décor ; tout texte indicatif lisible (indication « ex. 71,4 », méta) prend au moins `ink-2`. `ink-4`, `bar` et `rule-soft` ne servent qu'au décor masqué aux lecteurs d'écran ou aux traits non porteurs d'information (la bordure de case décochée reste l'exception nommée). `theme-contrast.test.ts` liste ces quatre tokens « hors paires » avec leur usage, vérifie que chaque token de couleur est dans une paire, une exception ou cette liste, et échoue si une indication (`placeholder:text-…`) ou un texte hors variante désactivée prend `text-ink-3` / `text-ink-4` dans `resources/js`. Le delta `visual-theme` le dit (« Contraste sur le thème sombre », scénarios « Indication d'un champ » et « Couleurs hors des paires » ; « Fonds et encres » nomme `ink-3` « texte d'un bouton bloqué »). Correction faite : les jours désactivés du calendrier passent à `*:disabled:text-ink-3`. |
| DO16 | Barre de progression d'Inertia | Couleur `#F4F5F7` (encre du thème sombre) au lieu de `#0F1D3E` ; seule couleur en dur hors de `tokens.css`, exception nommée de `theme-sources.test.ts`. |
| DO17 | Intermédiaire de l'unité 2 | Le cadre (3.x) précède la recomposition des pages (5 à 8) : `main` perd son espacement `gap-4` et son haut de 16 px, et aucune page ne rend encore `PageTopRow`, donc « Golf » et les liens de retour n'apparaissent qu'avec leurs tâches de page ; les grilles larges de la carte et de l'éditeur perdent `ScrollFrame` pour une `section` défilante nommée (`overflow-x-auto`), jusqu'à 5.2 et 6.1. `--top-chrome` et `--bottom-chrome` restent pour les barres collées de l'éditeur (retirées en 6.1, 6.2). L'accueil garde sa prop `pendingImports` objet (8.1 la remplace par la prop partagée) : sur l'accueil seul, la pastille d'Importer n'apparaît pas d'ici là. Le bouton à variante `destructive` (déclencheur de « Supprimer » d'un round et d'un import) devient rouge plein jusqu'à 5.3 et 7.1. |
| DO18 | Feuille de confirmation | `alert-dialog` est bâti sur le `Dialog` de base-ui avec `role="alertdialog"` : l'`AlertDialog` de base-ui force `disablePointerDismissal` (relevé dans `vendor`, `useRenderDialogRoot`), alors que le design ferme la feuille au toucher du voile. Même API exportée. |
| DO19 | `cn` et l'échelle du design | `tailwind-merge` est étendu avec les styles `--text-*`, les rayons et l'ombre de `tokens.css` : sans cela `cn('text-bg', 'text-label')` retirait la couleur. `lib/utils.test.ts` lit `tokens.css` et échoue si un style manque. |
| DO20 | Pastille d'Importer et lecteurs d'écran | Le lien garde le nom « Importer » ; la pastille est cachée et un texte masqué « 1 import à vérifier » / « N imports à vérifier » est relié par `aria-describedby`. `pendingImports` n'est partagé qu'avec une session ouverte (`/login` n'en dit rien). |
| DO21 | Couleur de joueur, portée de 4.3 | Le scope `Player::withColorSlot()` (et `Player::colorSlots()` pour les jointures en Query Builder) porte le calcul de D6. 4.3 l'ajoute à `PlayerOverview` (page Joueurs) et à `RoundDetail` / `RoundLeaderboard` (classement et carte d'un round), que le test couvre ; les Query des autres pages (accueil, Rounds, Handicaps, Coups, éditeur) l'ajoutent avec leur tâche de page et leur test. |
| DO22 | Marque doublée d'un texte (amende DO15) | Une marque graphique dont l'information est aussi donnée en texte est un usage permis de `ink-4` et `bar` : le segment « Double bogey + » de la répartition en `ink-4`, dont le compte et la part sont écrits sur sa ligne, et la barre d'un différentiel qui ne compte pas en `bar`, l'historique portant « Compte ». `theme-contrast.test.ts` le dit dans l'usage de ces deux tokens de sa liste « hors paires » ; le delta `visual-theme` l'écrit dans « Couleurs hors des paires de contraste » (scénario « Marque doublée d'un texte »). |
| DO23 | Cases par et index de l'éditeur | Elles prennent le contour `rule`, celui des champs (exception Q4, 1,34:1), et non `rule-soft` du design : ce sont des champs modifiables, pas un trait de décor. |
| DO24 | Indicateur d'attente (delta `loading-feedback`) | Appliqué à l'unité 3 (tâche 6.4) : `ui/pending.tsx` écrit son texte en Geist Mono 500, 12 px, `ink-2` (`text-meta-12`) et dessine l'orbe en variante sombre (`theme="dark"`) quel que soit le réglage de l'appareil. `ui/alert.tsx` et `ui/table.tsx` reprennent des tailles de l'échelle (`text-body-13`, `text-body-12`, titre `text-label-14`) : `text-sm` ne produisait plus aucun style sur l'échelle fermée. |
| DO25 | Champ texte en erreur | Un champ en erreur (`input`, `native-select`) prend le contour ambre des cases de la grille et de la date, `aria-invalid:border-accent/60` sur fond `accent/8`, et le libellé d'un `Field` invalide passe en `accent` ; `signal` reste réservé au destructif et au « sous le par ». Le message d'erreur sous le champ garde `signal` (rôle « erreur » de D1). `controls.test.tsx` échoue si un champ en erreur prend une classe `signal`. |
| DO26 | Explication du calcul | `components/handicaps/computation-help.tsx` (« Comment l’index est calculé ») passe aux tailles de l'échelle : titre en `text-link-13` gris sur 44 px de toucher, lignes en `text-body-12 text-ink-2`, filet `rule` ; `text-sm`, `max-w-2xl` et `text-muted-foreground` ne produisaient plus de style. |
| DO27 | « · » d'un trou sans coup (page Coups) | Proposé par l'unité 4, à valider : D1 donne `ink-4` au « · » et DO15 interdit tout texte `text-ink-4` (garde-fou de `theme-contrast.test.ts`, gardé tel quel). Le « · » est donc dessiné : un point de 3 px en `bg-ink-4`, caché aux lecteurs d'écran, la case gardant son nom « Trou 3 : aucun coup ». Même rendu, aucune exception de contraste de plus. |
| DO28 | Pastille d'un import enregistré (`/import`) | Décision de l'orchestrateur. La spec `scorecard-import` garde l'import enregistré dans la liste (« Liste des imports », scénario « Import enregistré » : « Voir le round », pas « Supprimer ») sans lui donner de libellé, et son état serveur reste « prêt » : la ligne affichait « Prêt à vérifier ». Elle montre la pastille neutre « ENREGISTRÉ » (mot écrit tel quel, comme « À VÉRIFIER ») et garde « Voir le round » vers le round. Scénario « Import enregistré » ajouté à « Ligne d'un import » du delta. |
| DO29 | Mouvement (tâche 9.1) | L'entrée de page est jouée par `main` (`lib/motion.ts`, `useEntered`, deux `requestAnimationFrame`), à chaque changement de chemin, pas de requête : changer de joueur (`?joueur=`) ne la rejoue pas. Les onglets de joueur visitent avec `preserveState` et relisent `scoringStats` avec l'historique (`only`) : sans cela la page se remontait (l'index ne pouvait pas s'échanger sur place) et les statistiques du joueur précédent restaient affichées. L'index s'échange en deux temps (`useSwap`, 140 ms) ; barres et segments repartent de 4 px et de 0 à chaque joueur (composants clés par joueur). Sous mouvement réduit, état final dès la première image, sans transition en ligne. Onglet de la section courante touché sur la page même de la section : `preventDefault` et `scrollTo` doux. Bouton « Enregistrer » : fond et texte en 300 ms `ease`, appui à 0,98 sur la même durée (une seule durée par bouton dans les classes ; 150 ms du design pour l'appui non repris). Toast : l'animation reste celle de sonner (glissement depuis le haut, 400 ms), non vérifiable sans passe navigateur ; écart à relever en 12.2. |
| DO30 | Style de la barre d'état de l'application installée | Décision de l'orchestrateur. `apple-mobile-web-app-status-bar-style` passe de `default` à `black` dans `resources/views/app.blade.php` : `default` donne une barre claire à texte sombre au-dessus de la page sombre ; `black`, une barre noire à texte blanc, sous laquelle la page commence (pas de `black-translucent`, la page ne passe pas sous la barre, spec app-shell « En-tête net sous la barre d'état »). Testé dans `tests/Feature/DocumentHeadTest.php` (mutation : `default`). |
| DO31 | Clés du graphique et des STATS (correction après revue, B1) | `DifferentialBars` et `ScoringStats` sont frères : ils prennent des clés distinctes par joueur (`bars-{id}`, `stats-{id}`). Une même clé `player.id` sur les deux laissait l'ancien graphique à côté du nouveau au changement de joueur (`preserveState`). Testé dans `handicaps.test.tsx` (un seul titre « Différentiels » après Gab → Manu). |
| DO32 | Avertissements de clé de React en test (L18) | `resources/js/tests/key-warnings.ts` retient les `console.error` « Encountered two children with the same key » et « Each child in a list should have a unique "key" prop » ; `setup.ts` fait échouer le test qui les a causés dans son `afterEach` (React en émet une partie hors de l'appel de rendu, en rendu concurrent : un `throw` dans `console.error` ne remontait pas au test). Les autres messages passent tels quels. `console-guard.test.tsx` le vérifie (mutation : garde non installée). La suite entière n'en révélait aucun autre une fois B1 corrigé. |
| DO33 | Focus initial d'une feuille destructive (B3) | `AlertDialogContent` donne par défaut à base-ui un `initialFocus` qui vise `[data-slot="alert-dialog-cancel"]` de la feuille (repli : comportement de base-ui) ; un `initialFocus` passé par l'appelant reste prioritaire. Sans cela base-ui prenait le premier bouton, « Supprimer », et Entrée puis Entrée supprimait le round. Vaut pour la feuille d'un round et celle d'un import. Delta `app-shell` « Feuille de confirmation » : phrase et scénario « Focus initial sur « Annuler » » ajoutés. Testé dans `alert-dialog.test.tsx`, `delete-round.test.tsx`, `import-index.test.tsx` (mutation : focus initial visant l'action). |
| DO34 | Barre d'onglets à 320 px (B2) | Aux mesures du design (écart 10, pilule 7, onglets 12 et courant 14 px de marge, libellé à 8 px de l'icône), la barre fait 340 px avec « Handicaps » ouvert, au-delà des 288 px d'un écran de 320 px moins 2 × 16 px : Importer dépassait de 17 à 36 px. Sous 375 px de large (`max-[375px]:`), l'écart passe à 8, la pilule à 4, l'onglet fermé à 6 px de marge et 32 px de large au moins, l'onglet courant à 10, le libellé à 4 px de l'icône : 276 px avec « Handicaps » (libellés mesurés dans Chrome en Geist 600, 13 px : Rounds 47, Handicaps 66, Coups 40, Joueurs 51 px). La hauteur de 44 px ne change pas (la spec demande 44 px de haut, pas de large). À 375 px et plus, les mesures du design tiennent (340 ≤ 343) et restent. `app-nav.test.tsx` somme les classes rendues et ces largeurs (≤ 288 px à 320 px, 340 px aux mesures du design) ; la passe navigateur mesure le bord droit d'Importer à 304 px (320) et 374 px (390) sur `/rounds`, `/handicaps`, `/strokes`, `/players`, `/courses`, libellé courant entier. |
| DO35 | `pendingImports` lue avec la page (M3, M4) | La prop partagée n'est plus différée : différée, la pastille d'Importer apparaissait après la page (clignotement) et le bloc « à vérifier » de l'accueil décalait la page. Une requête `count` de plus par visite (accueil : 7 requêtes au lieu de 6). Toujours absente sans session ouverte (`/login`, même sur un rechargement partiel qui la demande). Le poll de `/import` recharge `only: ['imports', 'pendingImports']`, pour que la pastille suive la fin d'une lecture. D7 corrigé. Testé dans `PendingImportsShareTest`, `HomePageTest` (mutation : `Inertia::defer` remis) et `import-index.test.tsx` (mutation : `pendingImports` hors de `only`). |
| DO36 | Vérification de session en un seul endroit (L16) | `App\Support\Passcode::opened(Request)` lit l'empreinte de session (`Passcode::SESSION_KEY`) ; `RequirePasscode`, `LoginController::create` et le partage de `pendingImports` l'appellent, et `Passcode::open(Request)` l'écrit à la connexion. `isFingerprint` devient privée. Refactorisation sans changement de comportement, couverte par `AccessTest` et `PendingImportsShareTest`. |
| DO37 | Entrée du bloc STATS (M5) | `ScoringStats` rend `null` ou un sous-composant `StatsBlock`, seul à appeler `useEntered` : l'entrée part du montage du bloc, à l'arrivée de la prop différée. Avant, le crochet tournait pendant que `stats` valait `null` et les segments arrivaient à leur largeur finale, sans animation. Testé dans `scoring-stats.test.tsx` (mutation : `useEntered` remonté dans `ScoringStats`). |
| DO38 | Échange de l'index entre deux mises en page (L13) | L'ancienne valeur n'est gardée pendant les 140 ms que si l'ancien et le nouveau joueur ont la même mise en page : vers un joueur sans index, « — » s'écrit tout de suite (opacité 0 puis entrée), jamais « 10,0 » à côté de « Pas encore d'index » ; vers un joueur avec index depuis « — », sa valeur directement. Testé dans `motion.test.tsx` (mutation : la branche sans index rend la valeur échangée). Les tests d'annulation au démontage (M8 : `cancelAnimationFrame` de la seconde image de `useEntered`, `clearTimeout` de `useSwap` avant 140 ms) et les assertions de classes par jeton exact (M10 : `toHaveClass` dans `home.test.tsx`, `scoring-stats.test.tsx`, `motion.test.tsx`) l'accompagnent. |
| DO39 | Requêtes des pages Parcours et Handicaps (M6, M9) | `CourseOverview` compte les rounds d'une paire par `count(*) over (partition by lower(trim(course_name)), lower(trim(tees)))`, calculé avant que `distinct on` ne garde une ligne par paire, au lieu d'une sous-requête corrélée par ligne ; la page Parcours fait 3 requêtes quel que soit le nombre de parcours (nouveau test, mutation : compte par parcours en PHP). Sur la page Handicaps, `history` devient une closure : le chargement différé des STATS fait 3 requêtes (joueurs et leurs statistiques pour choisir le joueur, 2 ; l'unique requête d'agrégats de `PlayerScoringStats`, 1) au lieu de relire l'historique ; testé à 10 et 30 rounds. Les comptes des pages Coups (5) et Handicaps (4) prennent la requête de `pendingImports` (DO35). |
| DO40 | Écart d'un par (M7, L14) | `deltaT = averageT − 10 × par`, `averageT` étant la moyenne arrondie affichée : une moyenne de 2,75 sur un par 3 s'affiche « 2,8 » et « −0,2 », alors que l'arrondi de l'écart exact (−0,25, moitié vers le haut de sa valeur absolue) donnait « −0,3 », incohérent avec la moyenne. La teinte reste décidée sur les valeurs exactes. Delta `handicap-index` : « Règles de calcul des statistiques » et « Moyennes par par » le disent, scénario « Demi-valeur sous le par » ajouté ; D13 corrigé. Testé dans `PlayerScoringStatsTest` (mutation : écart arrondi depuis la valeur exacte → −3). `PlayerScoringStats::get()` vérifie `null` avant de lire la ligne (L14, sans changement de comportement). |
| DO41 | Index sur `scores(player_id)` (L15) | La migration `2026_10_29_000012_add_player_index_to_scores_table` ajoute l'index ; son `down()` le retire. Les STATS (`PlayerScoringStats`) filtrent les scores d'un joueur, que l'unique `(round_id, player_id, hole)` ne sert pas (il commence par `round_id`). « Migration Plan » corrigé (il disait « aucune migration »). Testé dans `ScoresPlayerIndexTest` (index présent ; `down()` puis `up()` ; mutation : `down()` vide). |
| DO42 | Bloc « Distances » ouvert sur un problème (L11) | `DistancesBlock` est ouvert à son montage si une clé `distance:*` est invalide, et s'ouvre quand une distance devient invalide ; refermé par l'utilisateur, il le reste. Une erreur de la grille seule ne l'ouvre pas. Delta `rounds` : phrase et scénario « Distance en erreur à l'ouverture ». Testé dans `distances-block.test.tsx` (mutations : `open` retiré ; toute clé invalide ouvre le bloc). |
| DO43 | Problèmes de la barre d'enregistrement en liste (L12) | Les messages restants forment une liste `ul` nommée « Problèmes », un `li` par message (identiques regroupés, « Case vide (×2) »), affichée en ligne : le « · » qui les sépare est un décor caché aux lecteurs d'écran, le rendu ne change pas. Le conteneur passe de `p` à `div` (une liste ne peut pas être dans un paragraphe). Testé dans `editor-save-bar.test.tsx` (mutation : messages joints dans un seul `span`). |
| DO44 | Signe moins d'une valeur négative (V4) | `formatTenths` écrit une valeur négative avec le signe moins U+2212 (« −0,5 » pour un index négatif, « −1 » sur une barre de différentiel, « −1,1 » dans l'historique), comme les écarts ; le trait d'union de `toFixed` (hérité de Next.js) disparaît. Testé dans `format.test.ts` (mutation : trait d'union gardé). |
| DO45 | Ligne de version (V3) | `AppVersion` passe de `text-xs text-muted-foreground` (aucun style sur l'échelle fermée) à `font-mono text-section tracking-normal text-ink-2` : Geist Mono 500, 11 px, gris, sans l'approche des libellés de section. Testé dans `app-version.test.tsx` (mutation : `text-xs` remis). |
| DO46 | Étiquettes du graphique à 20 barres (V1) | À 20 barres, une barre fait 11,8 px à 390 px et 8,3 px à 320 px, alors qu'une date « 17/10 » (Geist Mono 9 px) fait 27 px et une valeur « −1,1 » (10 px) 24 px : elles se chevauchaient et la dernière valeur sortait du graphique. Règle : jusqu'à 10 barres, chaque barre porte sa valeur et sa date ; de 11 à 16, une barre sur deux ; au-delà, une sur trois ; toujours en comptant depuis la plus récente, toujours étiquetée. Au-delà de 10 barres, la plus ancienne aligne ses étiquettes sur le bord gauche et la plus récente sur le bord droit (pour ne pas sortir du graphique) ; c'est ce décalage qui impose le pas de 3 au-delà de 16 barres à 320 px (pas de 2 à 20 barres : « 28/10 » aligné à droite chevauchait sa voisine de 8 px, relevé à la passe navigateur). Les étiquettes des autres barres restent dans le DOM en `sr-only`. Delta `handicap-index` : exigence ajoutée « Étiquettes du graphique des différentiels », scénario « Vingt barres sur téléphone ». Testé dans `differential-bars.test.tsx` (mutations : toutes étiquetées, comptées depuis la plus ancienne, pas de 2 jusqu'à 20, seuil à 20, plus récente centrée) et mesuré à 320 et 390 px. |
| DO47 | Carte de score à 4 joueurs à 320 px (V2) | Décision de l'orchestrateur. À 320 px, « RETOUR · PAR 35 » (sur deux colonnes) élargit les colonnes Trou et Par, et les colonnes de score tombaient à 41 px, « MAXIM » coupé sans « … ». Les cellules de score gardent 52 px, 44 px au moins (`min-w-11 max-w-[52px]`) ; la table est dans un cadre `overflow-x-auto` focalisable (`tabIndex=0`, contour au focus clavier) : à 320 px la carte défile dans son cadre, la page jamais ; à 390 px rien ne défile. L'en-tête d'un joueur finit par « … » (`truncate`) s'il ne tient pas ; le nom entier est au classement. Deltas modifiés : `app-shell` « Pas de défilement horizontal de la page » (exception de la carte, scénario « Carte de score à quatre joueurs ») et `rounds` « Carte sur téléphone ». Testé dans `rounds-show.test.tsx` (mutations : `min-w-8`, cadre sans `overflow-x-auto`, en-tête sans `truncate`) et mesuré à la passe navigateur. |
| DO48 | Corrections du design (L17) | D13 disait la prop des STATS « différée avec le graphique » : seule la prop des STATS est différée, le graphique vient avec la page. Le risque « `pendingImports` … différée » et D7 disent maintenant la prop lue avec la page (DO35) ; « Migration Plan » nomme l'index de DO41. |

## Risks / Trade-offs

- [Le design ne montre qu'un téléphone de 390 px] → colonne de 390 px à toute largeur (décision Q3) ; la passe vérifie 320 px.
- [Trois paires sous les seuils de contraste (D9)] → décision du mainteneur, consignée dans l'ADR ; le test empêche toute quatrième exception.
- [Pas de captures de la cible dans l'archive] → confirmé à la tâche 1.3 (DO1) : la passe 12.2 ne compare qu'aux valeurs de la spec et le dit.
- [Couleurs de joueur hors gamut sRGB] → rendu différent selon l'écran (P3 ou sRGB) ; les tests comparent la chaîne OKLCH, pas un hex.
- [Contraste des couleurs de joueur en texte (violet 4,10:1)] → le nom accompagne toujours la couleur ; accepté et noté (E28).
- [Changement large] → tâches en groupes revus l'un après l'autre ; une seule PR de code, comme les autres entrées.
- [Specs de 7 et 9 réconciliées après coup] → D12 et DO2 à DO12 ; aucun conflit de fond trouvé.
- [Prop partagée `pendingImports` sur chaque visite] → une requête `count` sur `imports`, lue avec la page (DO35) ; le poll de `/import` la relit avec la liste.

## Migration Plan

Aucune donnée ne change ; une seule migration, l'index sur `scores(player_id)` (DO41), réversible. Déploiement par l'image habituelle ; les icônes PNG régénérées sont versionnées. Retour arrière : revert de la PR de code et nouvelle image.

## Open Questions

Aucune question ne change la spec : les décisions du mainteneur sont consignées dans la proposition. Les paquets sont `@fontsource/geist` et `@fontsource/geist-mono` (5.3.0, OFL-1.1), vérifiés dans le registre à la tâche 2.2.
