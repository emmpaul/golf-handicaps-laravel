# Design

## Context

- Le thème vit dans `resources/css/tokens.css` (palette fermée sous `@theme static`, couleurs sombres sous `prefers-color-scheme`), `app.css` (variables shadcn, zones sûres, `.scroll-frame`) et `base.css` (mouvement réduit). Les polices sont servies par deux `fontsource()` de `vite.config.ts`. Les primitives base-ui de `resources/js/components/ui/` sont possédées par le projet (ADR 0006).
- Garde-fous existants : `theme-sources.test.ts` (aucune couleur en dur, aucune transition globale), `theme-contrast.test.ts` (littéraux hex de la spec et contrastes), `tabular-figures.test.ts`, `reduced-motion.test.ts`, et côté Pest `DocumentHeadTest`, `WebManifestTest`, `AppIconsTest`, `PageAssetsTest`.
- La source du design est `Golf App.dc.html` (voir proposition, « Source du design »). Ses valeurs sont en ligne ; ce document les range en tokens. Les captures `ref/*-light.png` montrent l'ancienne interface et ne servent pas de cible.
- Ce changement s'applique après l'archivage de 6, 7, 8 et 9. Les specs de 7 et 9 n'existent pas encore ; les exigences de mise en page de leurs pages sont écrites ici en ADDED et réconciliées avant l'apply (D12).

## Goals / Non-Goals

**Goals :**
- Chaque valeur du design (couleur, police, taille, marge, rayon, ombre, durée, courbe) devient un token ou une classe dérivée d'un token, nommée une fois.
- Les pages à 390 px reproduisent le HTML au pixel près, vérifié par la passe navigateur contre des captures de la cible.
- Les garde-fous existants restent verts et sont réécrits avec les nouveaux littéraux.

**Non-Goals :**
- Aucune route ni Action ne change, et le calcul de l'index non plus. S'ajoutent trois lectures : le compte d'imports partagé (D7), le rang de couleur des joueurs (D6) et les statistiques du bloc STATS (D13).
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
| `--color-rule` | `0.27 0.008 260` | `#24272A` | filet sous un libellé de section, bordure de bouton à contour, pointillés, **contour de champ** (exception de contraste, D9) |
| `--color-rule-soft` | `0.21 0.006 260` | `#17181B` | filet entre deux lignes, bordure des cases par et index |
| `--color-bar` | `0.3 0.008 260` | `#2B2E32` | barre de différentiel qui ne compte pas |
| `--color-ink` | `0.97 0.003 260` | `#F4F5F7` | texte, bouton principal, onglet courant, toast |
| `--color-ink-2` | `0.68 0.01 260` | `#95989F` | texte secondaire, libellés, métas |
| `--color-ink-3` | `0.5 0.01 260` | `#606369` | texte indicatif, bouton bloqué |
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

Le badge du bouton Importer est sur toutes les pages : `HandleInertiaRequests` partage `pendingImports` (imports prêts à vérifier sans round), une requête `count` par visite, en prop différée par `Inertia::defer` pour ne pas retarder la page. L'accueil lit la même valeur.

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

Avant le premier code, `/opsx:update` réécrit les deltas sur les specs archivées : chaque ADDED « Mise en page… » qui contredit une exigence archivée de 6, 7, 8 ou 9 (ordre du classement net, gagnant en rouge, disposition d'« Une action principale à l'import », « Nom lu sur chaque ligne », libellés de 7) devient un MODIFIED de cette exigence ; les autres restent ADDED. `openspec validate --strict` doit rester vert. Si 7 ou 9 ont changé une page au point que le design ne s'applique plus, l'apply s'arrête.

### D13. Statistiques du bloc STATS (décision Q8)

Une Query `PlayerScoringStats` sous `App\Domain\Handicaps\Queries`, appelée par le contrôleur de la page Handicaps pour le joueur choisi, fait une seule requête d'agrégats sur `scores` joints à `round_holes` et `round_players` du joueur : nombre de rounds, somme des bruts, meilleur brut, compte de trous par classe d'écart (`score - par` ≤ −2, −1, 0, +1, ≥ +2) et, par par (3, 4, 5), nombre de trous et somme des scores. Le par est celui de `round_holes` du round, pas celui d'un parcours : c'est le par contre lequel la page du round marque les scores, et le seul que le schéma garde par round.

Les arrondis se font en PHP, en entiers, sur la valeur exacte, moitié vers le haut : moyenne au dixième `intdiv(20 × somme + n, 2 × n)` dixièmes, pourcentage `intdiv(200 × k + trous, 2 × trous)`, écart d'un par en dixièmes signés, arrondi de sa valeur absolue de la même façon. La teinte d'un écart se décide avant arrondi, en entiers : vert si `10 × (somme − par × n) ≤ 3 × n`, gris si `≤ 10 × n`, rouge sinon. La Query rend des entiers (dixièmes, pour cent, comptes) et la teinte en énumération (`good`, `neutral`, `bad`) ; le front ne fait que formater « 72,0 », « 78% », « +0,3 » et « −0,7 ».

La prop est différée (`Inertia::defer`) avec le graphique, pour ne pas retarder l'ouverture de la page ; aucun round : `null`, et le bloc n'est pas rendu.

- *Alternative* : stocker les comptes dans `player_round_stats`. Rejetée : la table appartient au calcul de l'index et à la comparaison de l'import SQLite (entrée 10) ; une requête d'agrégats sur au plus quelques centaines de rounds suffit.
- *Alternative* : calculer en TypeScript depuis tous les scores du joueur. Rejetée : envoie toutes les cartes au navigateur pour cinq nombres.

## Risks / Trade-offs

- [Le design ne montre qu'un téléphone de 390 px] → colonne de 390 px à toute largeur (décision Q3) ; la passe vérifie 320 px.
- [Trois paires sous les seuils de contraste (D9)] → décision du mainteneur, consignée dans l'ADR ; le test empêche toute quatrième exception.
- [Pas de captures de la cible dans l'archive] → le mainteneur les fournit (tâche 1.3) ; sans elles, la passe ne compare qu'aux valeurs de la spec et le dit.
- [Couleurs de joueur hors gamut sRGB] → rendu différent selon l'écran (P3 ou sRGB) ; les tests comparent la chaîne OKLCH, pas un hex.
- [Contraste des couleurs de joueur en texte (violet 4,10:1)] → le nom accompagne toujours la couleur ; accepté et noté (E28).
- [Changement large] → tâches en groupes revus l'un après l'autre ; une seule PR de code, comme les autres entrées.
- [Specs de 7 et 9 inconnues] → D12 ; l'apply s'arrête si la réconciliation trouve un conflit de fond.
- [Prop partagée `pendingImports` sur chaque visite] → une requête `count` indexée, différée.

## Migration Plan

Aucune donnée ni migration. Déploiement par l'image habituelle ; les icônes PNG régénérées sont versionnées. Retour arrière : revert de la PR de code et nouvelle image.

## Open Questions

Aucune question ne change la spec : les décisions du mainteneur sont consignées dans la proposition. Reste à vérifier à l'installation le nom exact des paquets `@fontsource` de Geist et Geist Mono.
