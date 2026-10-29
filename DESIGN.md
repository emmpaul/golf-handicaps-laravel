# Design

Le port reprend l'interface de l'application Next.js `golf-handicaps` : mêmes valeurs OKLCH, mêmes polices, mêmes classes dans les primitives. Interface en français, composée d'abord pour le téléphone. Les choix d'architecture du thème sont dans l'ADR 0006.

## Direction

L'application est le tableau des scores du groupe : papier bleuté froid, encre bleu-nuit dense, un seul rouge, celui du score sous le par. Le golf a déjà son code de couleurs (rouge sous le par, encre au-dessus), et les chiffres en grand sont la seule image.

- Un seul rouge, et il porte un sens : sous le par, birdie, vainqueur net. Jamais le fond d'un bouton. Les erreurs sont rouille, pour que le rouge ne veuille jamais dire « erreur ».
- Pas de coins arrondis ni d'ombres : `--radius-card: 0`, `--shadow-card: none`.
- Les listes (rounds, joueurs, parcours) sont des lignes de tableau avant d'être des cartes. Jamais de carte dans une carte.

## Tokens

`resources/css/tokens.css` est la source de vérité : tokens clairs dans `@theme static`, couleurs sombres dans le bloc `@media (prefers-color-scheme: dark)`. `resources/css/app.css` pointe les variables shadcn (`--background`, `--primary`, `--destructive`…) vers ces tokens ; le mode sombre ne change que `tokens.css`.

| Token             | Clair     | Sombre    | Rôle                                                                        |
| ----------------- | --------- | --------- | --------------------------------------------------------------------------- |
| `--color-paper`   | `#F4F7FB` | `#0A111F` | fond de page, en-tête, `html` et `body`, `theme-color`                      |
| `--color-paper-2` | `#E8EEF3` |           | survol de ligne, `muted`, `popover`, voile des dialogues                    |
| `--color-paper-3` | `#D9E1E9` |           | ligne sélectionnée, `::selection`, `accent` de shadcn                       |
| `--color-ink`     | `#0F1D3E` | `#EAEFF4` | texte, action principale, filet de 2 px, onglet courant                     |
| `--color-ink-2`   | `#46566E` | `#A9B9CA` | texte secondaire, marque « au-dessus du par » (`--bogey`)                   |
| `--color-ink-3`   | `#5B6777` |           | texte indicatif, contour des champs                                         |
| `--color-rule`    | `#CAD2DA` |           | filet de 1 px                                                               |
| `--color-signal`  | `#CB1E21` | `#F86B65` | sous le par (`--birdie`), jamais un fond de bouton                          |
| `--color-error`   | `#9B540C` | `#EB9854` | `destructive` et erreurs                                                    |
| `--color-ai`      | `#007D8D` |           | repère « Carte lue par l’IA, vérifie chaque case » de l'écran de correction |
| `--color-focus`   | `#0B66D6` |           | contour de focus de 2 px                                                    |

Une case sombre vide n'a pas de valeur hex fixée par la spec ; sa valeur OKLCH est dans `tokens.css`. Les valeurs hex de la spec sont des littéraux dans `resources/js/tests/theme-contrast.test.ts`, qui vérifie aussi les contrastes : 4,5:1 pour le texte courant et secondaire sur chaque fond et pour le repère de lecture par l’IA (`--color-ai`), 3:1 pour le contour de champ, le contour de focus et la marque « au-dessus du par », dans les deux modes.

La palette est fermée : `--color-*`, `--text-*`, `--radius-*` et `--shadow-*` sont vidés avant les déclarations du projet. Une classe Tailwind par défaut comme `text-gray-500` ne produit aucun style. `--text-base` et les interlignes de `text-xs` à `text-3xl` sont redéclarés pour garder les valeurs calculées de Next.

Les espacements (`--space-*`), durées (`--dur-micro` 120 ms, `--dur-short`, `--dur-long`), courbes (`--ease-*`) et z-index (`--z-*`) restent dans `:root` de `tokens.css`. Les zones sûres et hauteurs du cadre (`--safe-top`, `--safe-bottom`, `--top-chrome`, `--tabbar-h`, `--bottom-chrome`) sont dans `:root` d'`app.css`.

## Polices

- Sofia Sans Condensed (`--font-display`, classe `font-heading`) en 400, 600 et 800 : titres, noms, index, scores. Les titres de page sont en 800.
- IBM Plex Sans (`--font-body`, classe `font-sans`) en 400, 500 et 600, 16 px : texte, libellés, boutons.
- Les deux viennent des paquets `@fontsource`, déclarés par `fontsource()` dans `vite.config.ts` et servis depuis `/build/assets`. Aucune page ne charge de ressource d'un autre domaine ; `tests/Feature/PageAssetsTest.php` le vérifie.
- Les nombres en colonne prennent la classe `num` (ou `tabular-nums`) pour des chiffres à chasse fixe.

## Composants

Primitives sous `resources/js/components/ui/`, copiées de Next sur base-ui et possédées par le projet : `alert-dialog`, `alert`, `badge`, `button`, `calendar`, `field`, `input`, `label`, `native-select`, `popover`, `separator`, `table`. S'y ajoutent `pending` (indicateur d'attente) et `toaster` (messages éphémères). `components.json` documente la provenance ; la CLI shadcn ne régénère rien.

Composants du thème sous `resources/js/components/` :

- `BrandMark` : le drapeau de l'application en SVG, aux couleurs des tokens, décoratif (`aria-hidden`).
- `PageTitle` : titre de page en `font-heading` 800, `text-2xl`, `text-3xl` à partir de 768 px.
- `ScoreMark` : cercle `--birdie` sous le par, carré `--bogey` au-dessus, rien au par ; « 3, birdie » et « 5, bogey » pour les lecteurs d'écran.
- `ScrollFrame` : cadre défilant à partir de 768 px, fondu au bord de chaque côté où il reste du contenu (règles `.scroll-frame` d'`app.css`).
- `AppNav` et `NavLink` : en-tête collé avec la marque et « Golf », liens des cinq sections en haut à partir de 768 px, barre d'onglets fixée en bas en dessous. La section courante porte `aria-current="page"` ; sur la barre d'onglets, une pastille d'encre (`data-slot="tab-pill"`) passe derrière son icône.
- `AppVersion` : ligne de version en bas de page, liée à la release GitHub sauf en `0.0.0`.

Composants des joueurs et des rounds, portés de Next avec les mêmes classes (design D9 de `players-and-rounds`) :

- `players/inline-form` : un champ et un bouton, le refus du serveur sous le champ (« Ce joueur existe déjà »), relié par `aria-describedby` ; un sac d'erreurs par formulaire.
- `rounds/round-scorecard` : carte de score d'un round enregistré, 18 trous sur une rangée à partir de 768 px, deux blocs (aller, retour) en dessous, scores marqués par `ScoreMark`.
- `rounds/delete-round` : suppression confirmée par `AlertDialog`, « Suppression du round… » jusqu'à la page suivante.
- `round-editor/*` : l'éditeur partagé par la saisie et la correction. `round-editor` tient l'état local et compte les problèmes sur son bouton ; `score-grid` est la grille (une colonne par trou à partir de 768 px, une ligne par trou en dessous, mêmes noms accessibles « Ligne 1 trou 9 ») avec OUT, IN et TOT calculés en lecture seule ; `line-players` choisit ou crée le joueur de chaque ligne ; `date-field` ouvre le calendrier (lundi en premier, clavier et doigt) ; `problem-list` liste les messages, les identiques regroupés ; `form-state` porte l'état et le pré-remplissage d'un parcours connu. Une case en erreur prend `aria-invalid`, donc le contour rouille.
- `resources/js/lib/format.ts` : `formatPlayedOn('2026-10-17')` donne « 17 octobre 2026 », lu comme jour civil, sans passer par UTC.

Composants de l'import des cartes, portés de Next avec les mêmes classes (design D7 et D8 de `scorecard-ai-import`) :

- `import/import-upload` : envoi d'une photo. Sur écran tactile (`pointer: coarse`), « Prendre une photo » en bouton plein ouvre l'appareil photo arrière, « Choisir une photo » reste dessous, sans libellé entre les deux ; sans pointeur grossier, seul « Choisir une photo ». Chaque bouton est un libellé autour d'un champ fichier masqué (`sr-only`) nommé « Photo de la carte », qui garde le focus clavier. `Pending` dit « Préparation de la photo… » puis « Envoi de la photo… », les boutons désactivés. Sans clé d'IA, le message « Lecture des photos désactivée : aucune clé d’IA n’est configurée. » et « Saisie manuelle » remplacent l'envoi.
- `import/decode-photo` : décode la photo dans le navigateur (`createImageBitmap`) et la réencode en JPEG de 3000 px au plus sur le grand côté.
- `import/unreadable-photo` : « Format de photo non lisible, exporte-la en JPEG » et le lien « Saisie manuelle », quand le navigateur ne sait pas décoder le fichier.
- `import/import-list` : les 20 derniers imports, date en heure de Montréal, état en `Badge`, raison d'un échec, actions par état (« Vérifier », « Supprimer », « Voir le round », « Relancer la lecture » seulement avec une clé). L'orbe de `PendingOrb` tourne sur un import en attente ou en lecture seulement, sans `role="status"` : le badge change déjà à chaque rafraîchissement (toutes les 2 s tant qu'un import attend). La relance dit « Relance de la lecture… » sur sa ligne et désactive ses seules actions.
- `import/delete-import` : suppression confirmée par `AlertDialog`, « Suppression de l’import… » jusqu'à la page suivante ; « Annuler » n'envoie rien.
- `round-editor/*` gagne, sur l'écran de correction d'un import seulement (prop `importContext`) : le repère « Carte lue par l’IA, vérifie chaque case » en `text-ai`, en tête, quand la carte vient d'une lecture ; la photo à côté de la grille ; la raison d'un échec en `Alert` ; « lu « GAB » » sous le libellé de chaque ligne lue (`line-players`) ; les cases OUT, IN et TOT modifiables d'une ligne lue ; le surlignage des cases vides de la lecture à l'ouverture ; l'indicateur d'enregistrement de l'autosave (`autosave.ts`) dans un `role="status"`, « Enregistrement… » avec l'orbe, puis « Enregistré » ou « Modifications non enregistrées » en couleur d'erreur. `rounds/create` et `rounds/edit` n'en passent rien et ne changent pas.

Composants de l'index et des ratings, portés de Next avec les mêmes classes (design D7 de `handicap-index`) :

- `rounds/net-leaderboard` : classement net d'un round (« Classement net »), colonnes Joueur, Brut, Hcp de jeu, Net et Rang, en-tête sur le bandeau d'encre (`bg-band`, `text-band-ink`). Chiffres en `font-heading`, alignés à droite, cellules resserrées sous 768 px, où « Hcp de jeu » devient « Hcp ». Un joueur sans index montre « — » en handicap, net et rang, et la mention « pas encore d'index » qui passe à la ligne sous son nom plutôt que d'élargir la table : tout tient à 320 px. Le gagnant net (rang 1, ex æquo compris) est en `text-signal`, son net en gras. Il remplace `rounds/round-gross`.
- `courses/manual-rating-form` : saisie de la rating et du slope d'une ligne de `/courses`, deux champs étroits nommés « Rating de BETHPAGE BLACK / Red » et « Slope de BETHPAGE BLACK / Red », pré-remplis quand la ligne a une rating, et le bouton « Enregistrer ». Un `useForm` par ligne : pendant l'envoi, `Pending` dit « Enregistrement de la rating… » sur cette ligne et seul son bouton est désactivé ; le refus du serveur (« Rating entre 40,0 et 90,0 », « Slope entre 55 et 155 ») reste sous le champ de sa ligne, relié par `aria-describedby`. Sur une ligne qui a une rating, un bouton secondaire « Retirer la rating » (nom accessible « Retirer la rating de <parcours> / <tees> ») l'efface sans confirmation, par la même instance de formulaire : « Retrait de la rating… » et les deux boutons de la ligne désactivés jusqu'à la réponse.
- `handicaps/computation-help` : « Comment l’index est calculé », un `<details>` fermé par défaut dont le `<summary>` s'ouvre au doigt et au clavier. Il déplie les sept lignes fixes de la spec `handicap-index` (score ajusté, différentiel, index, différentiels retenus, ajustement, index provisoire, parcours sans rating), en texte qui passe à la ligne, sans aucune valeur calculée. Placé sous la ligne de rating d'un round, sous la liste de `/players` et sous celle de `/courses`.
- `resources/js/lib/format.ts` gagne `formatTenths(174)` « 17,4 » et `formatTenths(-30)` « -3,0 », `formatIndex(145, true)` « 14,5 (provisoire) », et les constantes `NO_VALUE` (« — ») et `NO_INDEX` (« pas encore d'index »).

Indicateur d'attente : `Pending` monte toujours sa région `role="status"`, vide et `sr-only` au repos. Pendant l'attente, elle montre l'orbe de `thinking-orbs` (`aria-hidden`, `theme="auto"`, figée sous mouvement réduit) et un texte court qui finit par « … ».

Message éphémère : sonner, aux couleurs des tokens, coins droits, bouton de fermeture « Fermer le message ». Une erreur s'affiche en couleur destructive dans un `role="alert"`, une confirmation dans un `role="status"`.

## Contrôles

- Principal : fond d'encre, texte papier, coins droits.
- Secondaire (`outline`) : contour d'encre de 1 px ; destructif : contour et texte rouille.
- Hauteur de 44 px sous 768 px (`h-11`), 32 px à partir de 768 px (`md:h-8`).
- Champs et listes déroulantes : contour `ink-3` de 1 px, encre au survol, texte de 16 px sous 768 px (`text-base`) pour qu'iOS ne zoome pas au focus. `aria-invalid` passe le contour en rouille.
- Désactivé : opacité 0,55, `cursor: not-allowed`.
- Focus : contour `--color-focus` de 2 px sur `:focus-visible`, qui apparaît dans la même image, sans transition sur `outline`.
- Boîte de confirmation : `AlertDialog`, `max-w-xs` (20 rem) pour tenir à 320 px, sur un voile opaque `paper-2`.

## Mouvement

- Les transitions nomment leurs propriétés (`transition-[color,background-color,border-color]`) à `--dur-micro`. Jamais `transition-all`, jamais `outline`, jamais largeur, hauteur ou position.
- Dialogues et popovers : fondu et zoom depuis 95 %. Sous mouvement réduit, ils s'ouvrent sans animation (`motion-reduce:animate-none`).
- `base.css` ramène toute animation et toute transition à 0,01 ms sous `prefers-reduced-motion: reduce`.

## Règles d'écran

- Téléphone d'abord : composer à 390 px, vérifier à 320 px, puis élargir. Sous 768 px, rien ne défile de côté, ni la page ni un cadre.
- En-tête collé et opaque, du même `paper` que `html` et `body`, sans `backdrop-filter` : Safari iOS y prend la teinte de sa barre d'état. La rangée de la marque commence 8 px sous la zone sûre.
- Contenu de `main` limité à `max-w-7xl`. Son bas laisse la place de la barre d'onglets et de la barre d'accueil (`--bottom-chrome`).
- Mode sombre par le système seul (`prefers-color-scheme`), sans réglage ni cookie. Deux `theme-color` dans `app.blade.php` : `#F4F7FB` en clair, `#0A111F` en sombre.
- Apostrophes typographiques dans les textes de l'interface.

## Garde-fous

`resources/js/tests/theme-sources.test.ts` parcourt les fichiers `.ts` et `.tsx` de `resources/js`, primitives comprises, hors code généré par Wayfinder et hors tests. Il échoue en nommant le fichier qui écrit une couleur en dur (`#rrggbb` entre guillemets, `[#`, `oklch(`, `rgb(`, `hsl(`) ou une transition globale (`transition-all`, `transition: all`). Seule exception : la barre de progression d'Inertia dans `app.tsx`, en `#0F1D3E`, parce qu'elle ne lit pas les variables CSS.

## Icônes et manifest

- `vendor/bin/sail artisan app:icons` dessine avec GD le drapeau de `BrandMark` (fond `#0F1D3E`, green `#46566E`, mât `#F4F7FB`, fanion `#CB1E21`) et écrit `public/icons/icon-192.png`, `public/icons/icon-512.png` et `public/apple-touch-icon.png` (180 px). Les PNG sont versionnés ; relancer la commande quand la palette change, avec les constantes de `GenerateAppIcons`.
- `public/favicon.svg` reprend le même dessin en couleurs fixes de la palette claire.
- `public/manifest.webmanifest` : « Handicaps golf », nom court « Golf », `standalone`, `theme_color` et `background_color` `#F4F7FB`.
- Un token de couleur modifié se reporte aussi dans ces fichiers et dans les `theme-color` d'`app.blade.php`. `tests/Feature/AppIconsTest.php`, `WebManifestTest.php` et `DocumentHeadTest.php` comparent aux littéraux de la spec.
