# Design

L'interface suit « Golf handicap app redesign », le design commandé par le mainteneur : une maquette de téléphone de 390 × 844 px (`Golf App.dc.html`) qui dessine les neuf écrans, la barre d'onglets, le toast et la feuille. Interface en français, thème sombre seul. Les décisions sont dans l'ADR 0013 (refonte visuelle) ; l'ADR 0006 garde les primitives base-ui possédées, le garde-fou `theme-sources` et le toast par le flash d'Inertia.

## Direction

Une app de téléphone sombre et typographique : des chiffres en très grand, peu de traits, une barre d'onglets flottante et un bouton d'import toujours à portée de pouce.

- Fond presque noir, trois surfaces un cran plus claires, encre presque blanche. Aucune image : les chiffres en Geist Mono font la page.
- Chaque couleur vive porte un sens : ambre pour ce qui attend l'utilisateur (Importer, imports à vérifier, premier net, provisoire, case vide ou en erreur), rouge pour le sous le par et la suppression, cyan pour la lecture par l'IA, vert pour la rating confirmée. Les joueurs ont leur couleur, toujours accompagnée de leur nom.
- Listes en lignes séparées par des filets de 1 px sous un libellé de section, pas en cartes. Les tuiles et les feuilles sont arrondies, une seule ombre pour ce qui flotte.
- Une seule colonne de 390 px à toute largeur : le design ne dessine pas d'écran large.

## Tokens

`resources/css/tokens.css` est la source de vérité. Son bloc `@theme static` vide `--color-*`, `--text-*`, `--radius-*` et `--shadow-*`, puis déclare les tokens du design : une classe Tailwind par défaut comme `text-gray-500` ne produit aucun style. Aucun bloc `prefers-color-scheme` : `:root { color-scheme: dark }` dans `app.css`.

| Token                   | OKLCH                                                                                               | Hex             | Rôle                                                                                           |
| ----------------------- | --------------------------------------------------------------------------------------------------- | --------------- | ---------------------------------------------------------------------------------------------- |
| `bg`                    | `0.13 0.005 260`                                                                                    | `#060709`       | fond, `html`, `body`, bande de zone sûre, `theme-color`                                        |
| `surface-1`             | `0.17 0.006 260`                                                                                    | `#0E0F12`       | champs, listes, sous-totaux, feuille, tuiles                                                   |
| `surface-2`             | `0.2 0.006 260`                                                                                     | `#141619`       | barre d'onglets, survol de tuile, fond de photo                                                |
| `surface-3`             | `0.22 0.006 260`                                                                                    | `#191B1D`       | case de coups, pastille neutre, bouton d'enregistrement bloqué                                 |
| `rule`                  | `0.27 0.008 260`                                                                                    | `#24272A`       | filet sous un libellé de section, bordure à contour, contour de champ (exception de contraste) |
| `rule-soft`             | `0.21 0.006 260`                                                                                    | `#17181B`       | filet entre deux lignes                                                                        |
| `bar`                   | `0.3 0.008 260`                                                                                     | `#2B2E32`       | barre d'un différentiel qui ne compte pas                                                      |
| `ink`                   | `0.97 0.003 260`                                                                                    | `#F4F5F7`       | texte, bouton principal, onglet courant, toast, contour de focus                               |
| `ink-2`                 | `0.68 0.01 260`                                                                                     | `#95989F`       | texte secondaire, libellés, métas, indications de champ                                        |
| `ink-3`                 | `0.5 0.01 260`                                                                                      | `#606369`       | contrôle désactivé et décor, jamais de texte lisible                                           |
| `ink-4`                 | `0.4 0.01 260`                                                                                      | `#45484D`       | point d'un trou sans coup, bordure de case décochée (exception), segment « Double bogey + »    |
| `ink-soft`              | `0.88 0.005 260`                                                                                    | `#D5D7DB`       | lignes de règle et texte des cas particuliers de la page « Calcul de l'index »                 |
| `over`                  | `0.6 0.01 260`                                                                                      | `#7D8086`       | marques bogey et double bogey                                                                  |
| `accent`                | `0.78 0.16 60`                                                                                      | `#FF9C3B`       | Importer, à vérifier, « 1ER », provisoire, problèmes, case vide ou en erreur                   |
| `signal`                | `0.7 0.17 25`                                                                                       | `#F66D67`       | sous le par, « Supprimer », message d'erreur                                                   |
| `info`                  | `0.8 0.12 200`                                                                                      | `#43D5DC`       | lecture en cours, repère « Carte lue par l’IA »                                                |
| `ok`                    | `0.8 0.14 145`                                                                                      | `#81D584`       | rating confirmée, moyenne bonne                                                                |
| `on-signal`             | `#FFFFFF`                                                                                           | `#FFFFFF`       | texte du bouton rouge « Supprimer » (exception)                                                |
| `player-1` à `player-6` | `0.72 0.24 145`, `0.62 0.22 260`, `0.75 0.19 55`, `0.72 0.17 200`, `0.58 0.26 300`, `0.85 0.19 105` | hors gamut sRGB | couleurs de joueur                                                                             |

- Les fonds à 8, 15 et 22 % et les bordures à 50 et 60 % s'écrivent avec les modificateurs d'opacité (`bg-accent/15`), jamais en nouveau token.
- `--color-scrim` (`rgba(0, 0, 0, 0.6)`) est le voile de la feuille ; `--shadow-float` (`0 10px 30px rgba(0, 0, 0, 0.5)`) est la seule ombre, portée par la barre d'onglets, le bouton Importer et le toast.
- Rayons nommés par leur valeur : `rounded-pill` (999 px), `rounded-22` (coins du haut de la feuille), `rounded-14` (bouton collé en bas, groupe de tuiles), `rounded-12`, `rounded-10` (champ), `rounded-9`, `rounded-8` (sous-total, case de l'éditeur), `rounded-6` (case de coups), `rounded-4`, `rounded-3`, `rounded-2` (pastille de joueur).
- `app.css` pointe les variables shadcn des primitives sur ces tokens : `--background` → `bg`, `--foreground` et `--primary` → `ink`, `--primary-foreground` → `bg`, `--muted` → `surface-1`, `--muted-foreground` → `ink-2`, `--border` et `--input` → `rule`, `--destructive` → `signal`, `--destructive-foreground` → `on-signal`, `--ring` → `ink`.
- `:root` de `tokens.css` porte les durées (`--dur-*`), les marges (`--gutter` et `--gutter-nav`, 16 px chacune), la colonne (`--column` 390 px) et les z-index (`--z-*`) ; `:root` d'`app.css` les zones sûres (`--safe-top`, `--safe-bottom`).
- `cn` étend `tailwind-merge` avec les styles `--text-*`, les rayons et l'ombre de `tokens.css` : sans cela, `cn('text-bg', 'text-label')` retirait la couleur. `lib/utils.test.ts` échoue si un style manque.

## Échelle typographique

Chaque texte prend un style `text-*` de l'échelle, qui fixe taille, interlignage, approche et graisse ; la famille vient de `font-sans` (Geist) ou `font-mono` (Geist Mono). Aucun autre style de texte.

| Classe                                   | Famille   | Valeur                                  | Usage                                                                   |
| ---------------------------------------- | --------- | --------------------------------------- | ----------------------------------------------------------------------- |
| `text-display-xl`                        | Mono 500  | 88 / 1 / −0,06 em                       | index des pages Handicaps et « Calcul de l'index », « — » sans index    |
| `text-display-l`                         | Mono 500  | 48 / 0,9 / −0,04 em                     | coups reçus                                                             |
| `text-display`                           | Mono 500  | 44 / 1 / −0,04 em                       | index de l'accueil, net d'un round                                      |
| `text-kpi`                               | Mono 500  | 30 / 1 / −0,04 em                       | valeurs du bloc STATS                                                   |
| `text-title`                             | Geist 600 | 28 / 1,05 / −0,03 em                    | titre de page                                                           |
| `text-num-36`                            | Mono 500  | 36 / 1 / −0,04 em                       | différentiel de l'étape 02 du calcul de l'index                         |
| `text-num-l`                             | Mono 500  | 22 / 1                                  | jour d'un round, index de la page Joueurs, moyenne par par              |
| `text-name-l`, `text-name-l-course`      | Geist 600 | 22 / 1 / −0,01 em ; 22 / 1,1 / −0,02 em | nom du classement d'un round ; parcours du dernier round                |
| `text-name-m`, `text-name-m-17`          | Geist 600 | 20 / 1 / −0,01 em ; 17 / 1              | nom de la page Joueurs ; nom de l'accueil, parcours de la page Parcours |
| `text-num-m`, `-18`, `-17`, `-16`, `-15` | Mono 500  | 24, 18, 17, 16, 15 / 1                  | net du dernier round, rang, numéro de trou, case, brut                  |
| `text-num-20`                            | Mono 500  | 20 / 1 / −0,03 em                       | différentiels de la liste et cases d'ajustement du calcul de l'index    |
| `text-body-l`                            | Geist 500 | 16 / 1                                  | champs et listes déroulantes                                            |
| `text-label`, `-14`, `-13`               | Geist 600 | 15, 14, 13 / 1                          | boutons et noms de ligne ; onglets de joueur ; libellé d'onglet         |
| `text-prose`                             | Geist 400 | 15 / 1,45                               | lignes de règle du calcul de l'index                                    |
| `text-body`, `-13`, `-12`                | Geist 400 | 14, 13, 12 / 1,5                        | messages vides ; texte courant ; explications                           |
| `text-link`, `-13`                       | Geist 500 | 14, 13 / 1                              | liens de rangée ; liens d'action                                        |
| `text-meta`, `-12`, `text-meta-round`    | Mono 500  | 13, 12 / 1 ; 12 / 1,4                   | index d'un joueur, métas ; méta d'un round                              |
| `text-section`                           | Mono 500  | 11 / 1 / 0,08 em, capitales             | libellés de section                                                     |
| `text-caption`, `-9`                     | Mono 500  | 10 / 1 / 0,08 em, capitales ; 9 / 1     | libellés de colonne ; numéro de trou des coups, date des barres         |

Les capitales passent par `uppercase` : le DOM garde la casse d'origine, et les tests cherchent ce texte. Quand le design change le mot lui-même (« 1ER NET », « À VÉRIFIER », « ENREGISTRÉ »), le DOM porte le nouveau mot.

## Polices

- Geist (400, 500, 600) pour le texte, les titres, les noms et les boutons ; Geist Mono (500, 600) pour les chiffres, les libellés de section et de colonne, les métas. Tout nombre affiché en colonne est en Geist Mono, pour que « 1 » et « 8 » aient la même largeur.
- Servies par les deux `local()` de `vite.config.ts` depuis les paquets `@fontsource` verrouillés, variables `--font-geist` et `--font-geist-mono`. Un seul fichier par graisse : le WOFF2 du sous-ensemble `latin`, qui couvre le français (é, è, à, ç, œ). Aucun WOFF, aucun `latin-ext` : un caractère hors du sous-ensemble s'affiche dans la police de repli, sans téléchargement. La page précharge ces cinq fichiers et rien d'autre ; `tests/Feature/FontLoadingTest.php` le vérifie sur le build.
- Avant l'arrivée de Geist, le texte s'affiche en « Geist fallback » et « Geist Mono fallback » : Arial aux métriques ajustées (`size-adjust`, `ascent-override`, `descent-override`), écrites dans `resources/css/app.css` pour que la page ne saute pas au changement de police.
- Aucune page ne charge de ressource d'un autre domaine, Google Fonts compris ; `tests/Feature/PageAssetsTest.php` le vérifie.
- Sentient, présente dans l'archive du design, n'est pas livrée : le design final ne s'en sert pas, et sa licence interdit de la publier dans un dépôt public.

## Cadre et mesures

- Colonne : `main`, la barre d'onglets et le bouton Importer tiennent dans `max-w-(--column)` (390 px) centrée ; en dessous, la page occupe l'écran. Le fond de `body` reste `bg` à toute largeur.
- Marges latérales de 16 px (`px-(--gutter)`) sur toutes les pages, connexion comprise, alignées sur la barre flottante (`--gutter` = `--gutter-nav`). Les deux jetons restent distincts pour que la barre puisse évoluer seule.
- Bande de zone sûre : un élément fixe (`data-slot="safe-area-band"`) de `--safe-top` de haut, fond `bg`, au-dessus du contenu. Pas d'en-tête collé.
- Rangée du haut (`PageTopRow`), 44 px, qui défile avec la page : « Golf » à gauche (lien vers l'accueil, sauf sur l'accueil) ou un lien de retour (chevron de 18 px, `text-link` gris), les actions à droite. Haut à `max(44px, safe-top)` pour une rangée à retour ou à actions, `max(54px, safe-top)` pour « Golf » seul ou suivi d'une valeur : sans zone sûre, la mesure du design ; avec une zone sûre plus haute, la rangée commence à son bas, sans marge ajoutée.
- Bas de page : `pb-[calc(108px+var(--safe-bottom))]`, pour que la ligne de version reste au-dessus de la barre flottante.
- Barre d'onglets (`AppNav`, `NavLink`) : `nav` fixe à 28 px au-dessus du bas de la zone sûre, pilule de 60 px de haut, fond `surface-2`, filet `rule`, quatre onglets (Rounds, Handicaps, Coups, Joueurs) dont seul le courant montre son libellé, puis le bouton rond Importer de 60 px, ambre, avec la pastille du nombre d'imports à vérifier. Le libellé est toujours dans le DOM et le lien garde son `aria-label`. `aria-current="page"` sur la section courante, sur Importer pour `/import`.
- Écrans de l'éditeur (nouveau round, modification, vérification d'un import) : la page déclare `layout = { chrome: 'editor' }`, le layout ne rend ni barre d'onglets ni Importer ; le lien de retour et `EditorSaveBar` en tiennent lieu.
- Cibles tactiles de 44 px au moins ; champs et listes déroulantes de 48 px à texte de 16 px (iOS ne zoome pas au focus) ; boutons de liste 44 px ; bouton principal collé en bas 56 px ; boutons de la feuille 52 px.

## Composants

Primitives sous `resources/js/components/ui/`, possédées par le projet (ADR 0006), restylées sans changer leur API :

- `button` : `default` plein encre, `outline` à contour `rule`, `ghost`, `destructive` plein rouge à texte `on-signal` ; hauteurs 44, 48, 52 et 56 px.
- `input`, `native-select` : 48 px, `surface-1`, contour `rule`, rayon 10 px. En erreur (`aria-invalid`), contour `accent/60` sur fond `accent/8`, et le libellé d'un `Field` invalide passe en `accent` ; le message sous le champ reste `signal`.
- `badge` : pastilles d'état en Geist Mono 10 ou 12 px, capitales, rayon 999 px, texte de la couleur de l'état sur cette couleur à 15 % ; état neutre en `ink-2` sur `surface-3`.
- `alert-dialog` : une feuille du bas, bâtie sur le `Dialog` de base-ui avec `role="alertdialog"` pour que toucher le voile la ferme. Fond `surface-1`, filet du haut `rule`, coins du haut de 22 px, titre en Geist 600 20 px, bouton d'action et « Annuler » de 52 px.
- `toaster` : sonner en pilule centrée à 52 px sous la zone sûre, 2,2 s. Confirmation claire avec coche dans un `role="status"` ; erreur en pilule rouge sans coche dans un `role="alert"`. Toucher la pilule la ferme.
- `pending` : région `role="status"` toujours montée ; pendant l'attente, l'orbe de `thinking-orbs` en variante sombre et un texte en `text-meta-12` `ink-2` qui finit par « … ».
- `checkbox`, `calendar`, `popover`, `field`, `label`, `alert`, `table`, `separator` aux surfaces et à l'échelle du design.

Composants du cadre et du thème sous `resources/js/components/` :

- `PageTopRow`, `SectionLabel` (libellé de section en `text-section` `ink-2`, 10 px au-dessus d'un filet `rule`, valeur à droite dans le même style), `AppNav`, `NavLink`, `AppVersion`, `LogoutButton`.
- `PlayerSwatch` : pastille de 6, 8 ou 10 px, rayon 2 ou 3 px, cachée aux lecteurs d'écran. Le rang `colorSlot` (0 à 5) vient du serveur (`Player::withColorSlot()`, rang de création modulo 6, rien en base) ; `PLAYER_BG` et `PLAYER_TEXT` l'associent à `player-1` … `player-6`.
- `scores/score-mark` : double cercle pour un eagle ou mieux, cercle pour un birdie (chiffre et trait en `signal`), carré pour un bogey, double carré pour un double bogey ou plus (trait `over`, chiffre en encre), rien au par. Annonces « 3, eagle », « 3, birdie », « 5, bogey », « 7, double bogey ou plus », « 4 ».
- `BrandMark` : le dessin du drapeau, repris par `favicon.svg` et les icônes ; plus affiché dans l'interface, où « Golf » s'écrit en texte.

Composants des pages :

- Rounds : `rounds/net-leaderboard` (classement net, « 1ER » en pastille ambre), `rounds/round-scorecard` (une rangée par trou, scores marqués par `ScoreMark`), `rounds/delete-round` (feuille de suppression).
- Éditeur (`round-editor/*`) : `round-editor` tient l'état et compte les problèmes ; `score-grid` (une rangée par trou, cases de 44 px en Geist Mono 16 px, OUT, IN et TOT calculés, case en erreur en ambre) ; `line-players` ; `date-field` ; `distances-block` (bloc « Distances » replié, `details` natif) ; `editor-save-bar` (bouton « Enregistrer » collé en bas, bloqué en `surface-3` / `ink-3` tant qu'il reste des problèmes) ; `autosave` sur l'écran d'un import.
- Handicaps : `player-tabs` (onglets de joueur), `differential-bars` (barres CSS, la barre qui compte dans la couleur du joueur, celle qui ne compte pas en `bar`), `scoring-stats` (bloc STATS), `round-history`, `ComputationLink` (`computation-help.tsx`, lien « Comment l’index est calculé » vers `/handicaps/calcul` en `text-link-13` gris, souligné au survol, 44 px de haut, avec `?joueur=` depuis la page Handicaps ; le fichier exporte aussi `RULE_LINES`, les sept lignes de règle, seule source de leur texte).
- Calcul de l'index (`handicaps/computation/*`) : `step-heading` (libellé d'étape en `text-section`, numéro à la couleur du joueur, libellé en `ink-2`, filet `rule`) ; `adjusted-score-step` (dernier trou plafonné, coups barrés, « MAX » et par + 4, puis « DERNIER ROUND » en `text-kpi`) ; `differential-step` (formule en `text-num-m-17`, différentiel en `text-num-36` à la couleur du joueur) ; `retained-step` (escalier de 20 marches, la marche du joueur à sa couleur, puis la liste des 20 derniers rounds, différentiels en `text-num-20`) ; `adjustment-step` (quatre cases, différences en `text-num-20`, celle du joueur bordée à sa couleur) ; `index-step` (moyenne et ajustement en `text-num-m-17`, index en `text-display-xl`) ; `special-cases` (pastilles « PROVISOIRE » et « SANS RATING », lignes en `ink-soft`). Les lignes de règle sont en `text-prose` `ink-soft`.
- Coups : `strokes/strokes-planner`, `segmented-control` (« Écart » / « Complet »), `stroke-hole-grid` (cases de coups, point `ink-4` d'un trou sans coup, caché aux lecteurs d'écran).
- Import : `import-tile`, `import-upload`, `import-list`, `delete-import`, `decode-photo`, `unreadable-photo`.
- Parcours : `courses/lookup-status`, `sgt-outcome`, `course-action`, `manual-rating-form`.
- `players/inline-form` : un champ et un bouton, le refus du serveur sous le champ relié par `aria-describedby`.

Écarts assumés à l'écran « Calcul de l'index » de l'archive :

- Rounds non retenus de la liste : l'archive les passe à l'opacité 0,42, ce qui met le texte sous 4,5:1. Ils prennent `ink-2`, sans pastille ; un round retenu garde `ink` et la pastille de 8 px du joueur, au nom accessible « Retenu ».
- Pastille « SANS RATING » : la variante neutre de `badge` (`ink-2` sur `surface-3`), au lieu du fond `rule` de l'archive.
- Case d'ajustement appliquée : `aria-current="true"` et un texte masqué « appliqué », en plus de la bordure à la couleur du joueur.
- Mois abrégés (« OCT », « AOÛT », « SEPT ») écrits tels quels dans le DOM, comme les mots que le design change ; le nom du parcours passe par `uppercase`.
- Texte des cas particuliers en `text-body` (14 / 1,5) au lieu de 14 / 1,45 ; onglets de joueur non choisis en gris, comme sur la page Handicaps, alors que l'archive les dessine en encre.

## Mouvement

Les animations du design sont toutes reprises. Courbes `--ease-design` (`cubic-bezier(0.2, 0.8, 0.2, 1)`) et `--ease-pop` (`cubic-bezier(0.3, 1.6, 0.5, 1)`) ; durées `--dur-press` 150 ms, `--dur-fade` 250 ms, `--dur-swap` 300 ms, `--dur-nav` 350 ms, `--dur-sheet` 400 ms, `--dur-enter` 420 ms, `--dur-bar` 600 ms.

- Entrée de page : `main` passe d'une opacité 0 à 1 (320 ms `ease`) et de 10 px plus bas à sa place (420 ms, courbe), à chaque changement de chemin, pas à chaque requête (`useEntered` et `pageEnterStyle` de `lib/motion.ts`, deux `requestAnimationFrame`). Toucher l'onglet de la section courante remonte la page par un défilement doux sans rejouer l'entrée.
- Appui : ligne de liste à l'opacité 0,6 ; onglet à l'échelle 0,94, onglet de joueur 0,96, Importer 0,92, tuile 0,97, « Enregistrer » 0,98.
- Onglets : fond, couleur et remplissage en 350 ms ; le libellé s'ouvre de 0 à 90 px de largeur maximale et de 0 à 8 px de marge en 350 ms, son opacité en 250 ms.
- Handicaps : l'index s'échange en deux temps (`useSwap`, 140 ms) ; les barres partent de 4 px et grandissent en 600 ms, les segments de répartition partent de 0 en 700 ms, de nouveau à chaque joueur.
- Case à cocher, sélecteur de mode, tuile au survol, bouton « Enregistrer » (fond et texte en 300 ms `ease`), toast (16 px et échelle 0,96, 400 ms), feuille (voile en 300 ms, translation du bas en 400 ms).
- Chaque transition nomme ses propriétés ; jamais `transition-all`, jamais `outline`. Hauteur, largeur, largeur maximale, marge et remplissage ne s'animent que là où le design les anime.
- Mouvement réduit : `base.css` ramène animations et transitions à 0,01 ms sous `prefers-reduced-motion: reduce`, et `lib/motion.ts` donne l'état final dès la première image, sans transition en ligne.

## Contraste

- Texte courant et secondaire : au moins 4,5:1 sur chacun de leurs fonds (page, surfaces 1 à 3, pastilles). Marques non textuelles (focus, marque au-dessus du par) : au moins 3:1 contre le fond.
- Trois exceptions exactement, décision du mainteneur (ADR 0013) : contour de champ `rule` à 1,34:1, bordure de case décochée `ink-4` à 2,18:1, texte blanc sur le bouton rouge à 2,88:1. Aucune autre.
- Hors des paires : `ink-3` seulement pour un contrôle désactivé ou du décor ; `ink-4`, `bar` et `rule-soft` pour du décor masqué aux lecteurs d'écran, un trait sans information, ou une marque dont l'information est aussi écrite (segment « Double bogey + », barre d'un différentiel qui ne compte pas). Une indication de champ prend `ink-2`.
- Focus : contour `ink` de 2 px décalé de 2 px sur `:focus-visible`, dans la même image, sans transition. Le design ne dessine pas de focus ; ce contour est le seul ajout.

## Règles d'écran

- Composer à 390 px, vérifier à 320 px. Dès 320 px, rien ne défile de côté, ni la page ni un cadre, feuille et calendrier ouverts compris ; un nom trop long se coupe par « … » et reste lisible ailleurs ou dans son nom accessible. À 320 px, les colonnes de joueur d'une carte passent de 52 à 44 px.
- Au-delà de 390 px, la même colonne centrée ; aucune navigation en haut.
- Thème sombre quel que soit le réglage de l'appareil, sans réglage ni cookie `appearance`. `app.blade.php` déclare `color-scheme: dark`, une seule `theme-color` `#060709` et la barre d'état `black` de l'application installée.
- La zone sous la barre d'état reste opaque, couleur `bg`, en haut de page comme après défilement.
- Apostrophes typographiques dans les textes de l'interface.

## Garde-fous

- `resources/js/tests/theme-sources.test.ts` parcourt les `.ts` et `.tsx` de `resources/js`, primitives comprises, hors code de Wayfinder et hors tests. Il échoue en nommant le fichier qui écrit une couleur en dur (`#rrggbb` entre guillemets, `[#`, `oklch(`, `rgb(`, `hsl(`) ou une transition globale. Seule exception : la barre de progression d'Inertia dans `app.tsx`, en `#F4F5F7`, qui ne lit pas les variables CSS.
- `theme-tokens.test.ts` compare chaque token de `tokens.css` aux littéraux du design.
- `theme-contrast.test.ts` convertit les OKLCH en sRGB, compare aux hex du tableau ci-dessus, vérifie chaque paire et la liste littérale des trois exceptions avec leurs ratios ; un token de couleur hors des paires, des exceptions et de la liste « hors paires » le fait échouer, comme un texte lisible en `text-ink-3` ou `text-ink-4`.
- `tabular-figures.test.ts`, `reduced-motion.test.ts`, `motion.test.tsx` gardent les chiffres en Geist Mono et le mouvement réduit.

## Icônes et manifest

- `vendor/bin/sail artisan app:icons` dessine avec GD le drapeau sur fond `#060709`, green `#191B1D`, mât `#F4F5F7`, fanion `#F66D67`, et écrit `public/icons/icon-192.png`, `public/icons/icon-512.png` et `public/apple-touch-icon.png` (180 px). Les PNG sont versionnés ; relancer la commande quand la palette change, avec les constantes de `GenerateAppIcons`.
- `public/favicon.svg` reprend le même dessin aux mêmes couleurs.
- `public/manifest.webmanifest` : « Handicaps golf », nom court « Golf », `standalone`, `theme_color` et `background_color` `#060709`.
- Un token de fond ou d'encre modifié se reporte dans ces fichiers et dans `app.blade.php`. `tests/Feature/AppIconsTest.php`, `WebManifestTest.php` et `DocumentHeadTest.php` comparent aux littéraux de la spec.
