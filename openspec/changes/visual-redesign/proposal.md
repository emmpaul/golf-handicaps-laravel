# Proposal

## Why

Le port reprend l'interface de Next (décision 7 de `docs/PORT_TO_LARAVEL.md`, thème scoreboard clair et sombre, ADR 0006). Le mainteneur a fait dessiner une nouvelle interface, « Golf handicap app redesign » : une app de téléphone sombre, typographique, avec une barre d'onglets flottante, un bouton d'import toujours à portée de pouce et des chiffres en très grand. Ce changement l'implémente au pixel près sur toutes les pages, une fois qu'elles existent toutes, avant la reprise des données de Next (`sqlite-import-command`), pour que le groupe découvre la nouvelle interface au cutover et pas deux fois.

Entrée de `ROADMAP.md` : **11. `visual-redesign`**, placée avant l'entrée 10. Dépendances : 6 (`handicap-index`), 8 (`scorecard-ai-import`), 7 (`home-and-match-strokes`) et 9 (`course-rating-sgt`) archivées. Au moment de cette proposition, 6 et 8 sont proposées, 7 et 9 ne le sont pas encore : l'apply ne commence pas avant leur archivage (tâche 1.1).

## Source du design

Archive `Golf handicap app redesign.zip` du mainteneur, lue sans rien exécuter (`support.js` et le HTML n'ont pas été ouverts dans un navigateur) :

- `Golf App.dc.html` : **la cible**. Une maquette de téléphone de 390 × 844 px qui contient les neuf écrans (Accueil, Importer, Correction, Rounds, Round, Handicaps, Coups, Joueurs, Parcours), la barre d'onglets, le toast et la feuille de suppression. Toutes les valeurs sont en ligne (couleurs OKLCH, `font:` abrégés, marges en px) ; il n'y a ni variables CSS ni second thème. La logique de démonstration en JavaScript précise les libellés, les formats et les états.
- `ref/*-light.png` : **pas la cible**. Ce sont des captures de l'application actuelle (Next v0.7.0, thème vert clair, barre d'onglets à cinq sections, cartes arrondies) fournies au designer comme point de départ. Elles ne serviront pas de référence de comparaison (voir « Vérification »).
- `uploads/draw-*.png`, `uploads/pasted-*.png` : annotations et états intermédiaires du designer (une variante crème à empattements avec un bouton jaune, une variante à onglets en haut). Ils ne sont pas retenus ; le HTML est la version finale.
- `uploads/Sentient_Complete/` : la famille Sentient (Indian Type Foundry, Fontshare), en OTF, TTF et WEB. **Le HTML final ne la nomme nulle part** : ses 157 déclarations de police sont en Geist et Geist Mono (chargées depuis Google Fonts), et sa vignette `.thumbnail` le confirme. Seule une variante intermédiaire abandonnée (`uploads/pasted-1793221323810-0.png`, fond crème, chiffres à empattements) semble l'employer. Le mainteneur a confirmé : Sentient n'est pas livrée.
- `assets/scorecard.jpg` : la photo de carte de la maquette, utile seulement comme donnée de démonstration de l'écran Importer.

### Licence des polices

- **Sentient**, ITF Free Font License 2.0 (`License/FFL.txt`) : l'auto-hébergement est permis pour ses propres sites et applications, `@font-face` compris (section 01). Mais la section 02 interdit de rendre les fichiers disponibles à d'autres, « including … repository … publicly accessible servers », et interdit tout sous-ensemble ou conversion de format ; la section 08 prévoit la résiliation immédiate en cas de redistribution. Le dépôt `emmpaul/golf-handicaps-laravel` est **public** sur GitHub, et l'image GHCR embarque `public/build`. **Versionner les woff2 de Sentient dans ce dépôt public les rendrait téléchargeables par tous : c'est une redistribution que la licence interdit.** Comme le design final ne s'en sert pas, ce changement ne l'embarque pas. Si elle revient un jour, il faudra un dépôt et une image privés, ou une livraison au build hors du dépôt, et des fichiers servis tels quels (pas de `fontsource`, de sous-ensemble ni de conversion).
- **Geist et Geist Mono** (Vercel), SIL Open Font License 1.1 : redistribution et auto-hébergement permis. Elles sont servies comme aujourd'hui par `fontsource()` depuis les paquets `@fontsource` verrouillés, sans requête vers Google Fonts.

## What Changes

- **Tokens** : `resources/css/tokens.css` remplace la palette scoreboard par celle du design (fond `oklch(0.13 0.005 260)`, encre `oklch(0.97 0.003 260)`, ambre `oklch(0.78 0.16 60)`, rouge `oklch(0.7 0.17 25)`…), les rayons (999, 22, 14, 12, 10, 9, 8, 6, 4 px), l'ombre unique `0 10px 30px rgba(0,0,0,.5)`, l'échelle typographique et les courbes de mouvement. Palette toujours fermée. **Thème sombre seul** (décision Q1) : le design ne définit aucun thème clair.
- **Polices** : Geist (400, 500, 600) pour le texte et les titres, Geist Mono (500, 600) pour les chiffres, libellés de section et méta, comme le HTML. Sentient n'est pas livrée (décision du mainteneur). Sofia Sans Condensed et IBM Plex Sans sont retirées.
- **Animations** : toutes celles du design sont reprises, à la demande du mainteneur (entrée de page, appui, onglets, index et barres de la page Handicaps, cases à cocher, sélecteur de mode, toast, feuille). La règle actuelle « jamais largeur, hauteur ou position » est levée pour ce que le design anime.
- **Cadre** : barre flottante à quatre onglets (Rounds, Handicaps, Coups, Joueurs), libellé visible sur l'onglet courant seulement, et bouton rond Importer à droite avec le nombre d'imports à vérifier. L'accueil s'ouvre par « Golf » en haut de page. Plus d'en-tête collé ni de marque dessinée. Toast en pilule en haut de l'écran. Feuille de confirmation montant du bas.
- **Pages** : Accueil, Importer, Correction (éditeur partagé avec la saisie manuelle et la modification), Rounds, Round, Handicaps, Coups, Joueurs, Parcours recomposés selon le design, valeurs à 390 px spécifiées.
- **Marques de score** : eagle ou mieux en double cercle, double bogey ou plus en double carré, en plus du cercle (birdie) et du carré (bogey).
- **Couleur de joueur** : chaque joueur a une couleur de la palette de six du design, selon son ordre de création, sans colonne en base (décision Q11).
- **Bloc STATS de la page Handicaps** (décision Q8) : moyenne des bruts, meilleur brut, part des trous joués au par ou mieux, répartition des trous d'eagle ou mieux à double bogey ou plus, moyenne par trou sur les par 3, 4 et 5. Fonctionnalité nouvelle, calculée à la lecture sur tous les rounds du joueur, sans table ni colonne.
- **Graphique des différentiels** en barres CSS à la place de la courbe recharts de la page Handicaps.
- **Documentation** : `DESIGN.md` réécrit, ADR « Refonte visuelle » qui remplace l'ADR 0006 pour la palette, les polices, le mode sombre et la marque, décision 7 de `docs/PORT_TO_LARAVEL.md` annotée, `.ai/guidelines/golf-handicaps.md` (section Interface) et le `context` d'`openspec/config.yaml` mis à jour, `AGENTS.md` régénéré par `boost:update`.

### Ce que le changement remplace dans les documents

| Document | Passage | Ce qui change |
| --- | --- | --- |
| `docs/PORT_TO_LARAVEL.md` | Décision 7 « Port de l'UI actuelle : thème scoreboard, tokens, composants base-ui/shadcn, recharts » | Remplacée pour le thème, les tokens et recharts par l'entrée 11 et sa nouvelle ADR ; les primitives base-ui restent. Le tableau garde la ligne, annotée « remplacée par `visual-redesign` (ADR NNNN) ». Décision 14 : « 10 changements » devient 11. |
| `.ai/guidelines/golf-handicaps.md` | Section « Interface » : « Le port reprend le thème scoreboard de l'application Next.js » | Réécrite : l'interface suit `DESIGN.md` (design « Golf handicap app redesign »), palette fermée, sombre seul. `AGENTS.md` est régénéré par `make setup` ou `vendor/bin/sail artisan boost:update`, jamais édité à la main. |
| `openspec/config.yaml` | `context` : « port the existing scoreboard theme rather than the stock shadcn/ui appearance » | Pointe vers `DESIGN.md` et le nouveau design. |
| `DESIGN.md` | Tout le fichier | Réécrit : direction, tokens, polices, composants, mouvement, règles d'écran du nouveau design. |
| `docs/adr/0006-systeme-de-design.md` | Palette scoreboard, mode sombre par le système, polices, icônes | Passe à « Remplacée en partie par NNNN » ; la décision des primitives possédées, du garde-fou `theme-sources` et du toast par flash reste. |

**ADR nécessaire : oui.** Le changement remplace des décisions de l'ADR 0006 (palette, polices, mode sombre selon le système, marque d'en-tête, primitives d'en-tête) et retire une dépendance (recharts). Numéro : le prochain libre à l'apply (0010 est pris par `handicap-index`, 8, 7 et 9 peuvent en prendre d'autres).

### Écarts au comportement des specs

Principe : le comportement des specs existantes ne change pas, sauf quand le design change clairement un libellé, un format ou un enchaînement ; il prime alors, et l'écart est listé. Ce que les specs exigent et que le design ne dessine pas est gardé, dans le style du design. Ce que le design dessine et qu'une décision du port retire n'est pas fait.

| # | Endroit | Spec actuelle | Design | Choix proposé |
| --- | --- | --- | --- | --- |
| E1 | Navigation | Cinq sections avec icône et libellé (Importer en premier) | Quatre onglets, libellé sur l'onglet courant seulement, bouton rond Importer à part | Design. Noms accessibles gardés (`aria-label`). |
| E2 | Accueil | Pas de lien de section ; « Golf » dans l'en-tête collé | « Golf » en haut de page mène à l'accueil | Design. |
| E3 | En-tête | Collé, opaque, marque dessinée + « Golf » | Rangée de page non collée, « Golf » en texte, sans marque | Design. La zone sous la barre d'état prend le fond. |
| E4 | Version | Ligne de version en bas de chaque page | Absente | Gardée (spec `app-version`), en Geist Mono 11 px gris. |
| E5 | Ordinateur | Navigation en haut à partir de 768 px, contrôles de 32 px, cadres défilants | Non dessiné | Décision Q3 : la mise en page du téléphone dans une colonne centrée de 390 px, barre flottante comprise, à toute largeur ; navigation du haut et cadres défilants retirés. |
| E6 | Liste des rounds | « 17 octobre 2026 », pas de « · » entre parcours et tees, « Gagnant net », vide « Aucun round enregistré. » | « 17 » / « OCT », « Bethpage Black · Red », « 1ER NET », compteur « 8 ROUNDS », vide « Aucun round. Importez une carte pour commencer. » | Design. |
| E7 | En-tête d'un round | « Tees Red », « 17 octobre 2026 », « Par 71 » séparés, sans « · » ; « Rating 71,4 » et « Slope 144 » | « RED · 17.10.2026 · PAR 71 · SANS RATING » ou « … · 71,4 / 144 » | Design. |
| E8 | Classement net | Tableau Joueur, Brut, Hcp de jeu, Net, Rang, dans l'ordre de la carte ; gagnant en rouge | Lignes triées par rang, « 72 − 1 », net en 44 px, pastille « 1ER » ambre | Design. Joueur sans index : net « — », non classé (règle de `handicap-index` gardée ; le design calculait net = brut). |
| E9 | Carte de score d'un round | Colonnes par trou, OUT, IN, TOT ; deux blocs sous 768 px | Une rangée par trou « P4 · 8 », sous-totaux ALLER et RETOUR, pas de total | Design, plus une rangée « TOTAL · PAR 71 » gardée pour le TOT. |
| E10 | Marques de score | Cercle sous le par, carré au-dessus | Double cercle eagle ou mieux, cercle birdie, carré bogey, double carré double bogey ou plus | Design. Annonces « 2, eagle » et « 7, double bogey ou plus » ajoutées. |
| E11 | Photo d'un round | Affichée sur la page du round | Absente | Gardée, dans le cadre photo de la Correction. |
| E12 | Suppression d'un round | Boîte de confirmation | Feuille du bas « Supprimer ce round ? », « {parcours} · {date}. Les index de tous les joueurs seront recalculés. », « Supprimer », « Annuler » | Design. « Suppression du round… » gardé. |
| E13 | Explication du calcul | Bloc « Comment l’index est calculé » (Joueurs, Round, Parcours) | Absent ; pied de page « différentiel = score ajusté − par » | Bloc gardé. Le pied du design est faux (l'index utilise rating et slope) : pas repris. |
| E14 | Joueurs | « Index 14,5 (provisoire) », « Index — », « Aucun alias », formulaires visibles | Index à droite et « PROVISOIRE » / « INDEX » / « PAS D'INDEX », pastilles d'alias avec ×, renommage et alias derrière « Modifier » / « Fermer » | Design. « Aucun alias » gardé après « ALIAS ». |
| E15 | Parcours | « Tees Red », « Par 71 », « sans rating », bouton « Retirer la rating » | « Bethpage Black · Red », « PAR 71 », pastille « 71,4 / 144 · CONFIRMÉ » ou « SANS RATING », « 3 rounds », « Relancer la recherche » | Design. « Retirer la rating » gardé en lien texte comme « Relancer la recherche ». |
| E16 | Importer | « Prendre une photo » plein en tête, « Choisir une photo » dessous | Deux tuiles côte à côte, « CAMÉRA » et « PHOTOTHÈQUE » | Design. Sans écran tactile, une seule tuile pleine largeur. |
| E17 | Imports | États « En attente », « Lecture en cours », « Prêt à vérifier », « Échec » | Pastilles « LECTURE… », « À VÉRIFIER » ; titre « Import n°1 » | Pastilles du design pour lecture et prêt ; « En attente » et « Échec » gardés en pastilles. Titre : l'instant d'envoi, « 23 oct. 2026 · 16:58 » (décision Q5 ; un import n'a pas de numéro). |
| E18 | Recadrage | Retiré par la décision 3 du port | Bouton « Recadrer », lien « Recadrer la photo » | **Pas fait.** |
| E19 | Éditeur, titres | « Saisie manuelle », « Modifier le round » | « Nouveau round », « Modifier le round », « Vérifier la carte », lien retour « Rounds » / « Round » / « Importer » | Design. |
| E20 | Éditeur, date | Calendrier en français, lundi en premier | Champ `type=date` natif « DATE DE JEU » | Calendrier gardé, dans un champ du design ; libellé « Date » gardé. |
| E21 | Éditeur, lignes | Numéro sur une rangée, liste et retrait dessous ; « lu « GAB » » sur la rangée du numéro | Une rangée : « L1 », liste, « lu « GAB » », × | Design. |
| E22 | Éditeur, grille | OUT, IN, TOT ; distance modifiable ; totaux lus modifiables | « ALL. » après le trou 9, « TOT. » à la fin, pas de distance, totaux en lecture seule | Rangées du design, plus « RET. » ; totaux lus gardés modifiables. Distance : gardée dans un bloc « Distances » replié sous la grille (décision Q6). |
| E23 | Éditeur, problèmes | Liste des problèmes, bouton qui les compte | Barre collée en bas : point ambre, messages joints par « · », bouton 56 px grisé | Design, avec les messages actuels. |
| E24 | Repère IA, sauvegarde | « Carte lue par l’IA, vérifie chaque case », indicateur d'enregistrement | Absents | Gardés, en cyan `info` et en Geist Mono gris. |
| E25 | Toast | sonner en bas, bouton « Fermer le message », erreur en destructif | Pilule claire en haut avec coche, succès seulement | Design ; erreur en pilule rouge en haut, fermée au toucher (décision Q7). |
| E26 | Contraste | Contour de champ 3:1, texte 4,5:1 | Contour de champ `oklch(0.27 0.008 260)` à 1,34:1, case décochée `oklch(0.4 0.01 260)` à 2,18:1, texte blanc sur le bouton rouge « Supprimer » à 2,88:1 | **Valeurs du design gardées** (décision Q4 : le pixel près prime). Ces trois cas exactement sont des exceptions nommées au requirement de contraste ; tout le reste garde les seuils. Compromis d'accessibilité consigné dans l'ADR. |
| E27 | Focus | Contour `--color-focus` de 2 px | Non dessiné | Contour de 2 px en encre, décalé de 2 px. |
| E28 | Coups, couleurs de joueur | — | Coups par trou écrits dans la couleur du joueur ; violet à 4,10:1 | Design ; le chiffre est doublé du nom du joueur, la couleur n'est pas seule porteuse. |
| E29 | Handicaps | Courbe recharts (à venir en 7) | Barres des différentiels, pastilles de joueur, tendance « ▼ 0,4 », bloc STATS | Design, bloc STATS compris (décision Q8), spécifié comme fonctionnalité dans `handicap-index`. |
| E30 | Accueil | Spec de 7 à venir | Classement INDEX numéroté, dernier round en net, « Gab gagne de 3 coups » | Design ; réconcilié avec la spec de 7 à l'archivage de 7. |
| E31 | Connexion | `/login` au thème actuel | Non dessinée | Recomposée avec les éléments du design (décision Q9). |
| E32 | Icônes, manifest | Fond `#F4F7FB`, drapeau scoreboard | Non dessinés | Décision Q10 : même dessin aux couleurs du design, fond `#060709`, fanion `#F66D67`, mât `#F4F5F7`, green `#191B1D`. |
| E33 | Mouvement | Transitions de couleur à 120 ms ; jamais largeur, hauteur ou position | Entrée de page, libellés d'onglet qui s'ouvrent, barres qui grandissent, index qui glisse, feuille et toast qui glissent | Design, entièrement (demande du mainteneur). Le mouvement réduit du système est respecté. |
| E34 | Résumé de la page Handicaps | — | « 2 rounds · hcp de jeu 1 · moyenne des 2 différentiels. » | « hcp de jeu » retiré : le design y met l'index arrondi, qui n'est pas un handicap de jeu (celui-ci dépend du parcours, de sa rating et de son slope). Décision du mainteneur. |

## Décisions du mainteneur

Les questions ouvertes de la proposition ont été tranchées par le mainteneur le 2026-10-28 ; les specs, le design et les tâches les appliquent.

| # | Sujet | Décision |
| --- | --- | --- |
| Q1 | Mode clair | Sombre seul : `color-scheme: dark`, une seule `theme-color` `#060709`. |
| Q2 | Sentient | Non livrée : le HTML final est en Geist et Geist Mono. Aucun fichier de Sentient dans le dépôt. |
| Q3 | Écrans larges | La mise en page du téléphone dans une colonne centrée de 390 px à toute largeur ; la navigation du haut et le requirement « Contenu défilant signalé » sont retirés. |
| Q4 | Contraste | Les valeurs du design priment pour trois cas exactement : contour de champ `oklch(0.27 0.008 260)` (1,34:1), case décochée `oklch(0.4 0.01 260)` (2,18:1), texte blanc sur le bouton rouge (2,88:1). Compromis d'accessibilité consigné dans l'ADR. |
| Q5 | Titre d'un import | L'instant d'envoi, « 23 oct. 2026 · 16:58 ». |
| Q6 | Distance | Gardée, dans un bloc « Distances » replié sous la grille de l'éditeur. |
| Q7 | Toast d'erreur | Pilule rouge en haut de l'écran, fermée au toucher. |
| Q8 | Bloc STATS | Inclus dans ce changement, comme fonctionnalité (requirements de `handicap-index`, D13). |
| Q9 | Connexion | `/login` recomposée avec les éléments du design. |
| Q10 | Icônes et manifest | Palette du design (D10). |
| Q11 | Couleur de joueur | Ordre de création, palette de six en boucle, calculée et non stockée. |
| — | « hcp de jeu » | Retiré du résumé de la page Handicaps (E34). |

## Vérification

- Pas de test navigateur, E2E ni de régression visuelle versionné (décision 8). Vitest vérifie les valeurs des tokens, les contrastes calculés depuis les OKLCH, les composants (classes, rôles, noms accessibles, ordre) ; Pest vérifie le HTML des pages, `theme-color`, manifest et icônes.
- Passe navigateur de fin d'apply (`browser-use`), à 320, 390 et 1440 px, contre des **captures de la cible**. Les `ref/*-light.png` ne servent pas : elles montrent l'ancienne interface. Les captures de la cible (neuf écrans, 390 × 844, sombre) sont à fournir par le mainteneur depuis l'outil du designer, ou à prendre par le mainteneur en ouvrant lui-même le HTML ; un agent ne l'exécute pas (code non fiable). Elles sont copiées sous `openspec/changes/visual-redesign/reference/` par une tâche, jamais dans `public/`.

## Capabilities

### New Capabilities

Aucune capacité nouvelle.

### Modified Capabilities

- `visual-theme` : palette du design et sombre seul, trois exceptions de contraste nommées (décision Q4), polices Geist, marques de score à quatre niveaux, couleur de joueur, échelle typographique, mouvement, focus, plus de marque dans l'en-tête, couleurs du manifest.
- `app-shell` : barre flottante à quatre onglets et bouton Importer, rangée de page au lieu de l'en-tête collé, colonne unique à toute largeur, toast en haut, feuille de confirmation.
- `loading-feedback` : indicateur sur le fond sombre seul.
- `rounds` : liste, en-tête, carte de score, suppression, lignes de l'éditeur et mise en page de l'éditeur et des pages Rounds et Round.
- `players` : mise en page de la page Joueurs.
- `handicap-index` (créée par 6) : bloc STATS de la page Handicaps (fonctionnalité nouvelle : statistiques, règles de calcul, répartition, moyennes par par), tendance, graphique et historique, mise en page.
- `course-ratings` (créée par 6), `scorecard-import` (créée par 8), `home-dashboard` et `match-strokes` (à créer par 7) : une exigence de mise en page par page. Ces exigences sont écrites ici en ADDED ; la tâche 1.2 les réconcilie avec les specs archivées de 6 à 9 avant l'apply. Elles sont écrites ici en ADDED ; la tâche 1.2 les réconcilie avec les specs archivées de 6 à 9 avant l'apply.

## Impact

- Code : `resources/css/{tokens,app,base}.css`, `vite.config.ts` (`fontsource`), `resources/views/app.blade.php` (`theme-color`, `color-scheme`), `resources/js/components/**` (nav, page, primitives restylées), toutes les pages, `public/{manifest.webmanifest,favicon.svg,icons/*}`, `app/Console/Commands/GenerateAppIcons.php`, tests Vitest et Pest du thème.
- Dépendances : ajout de `@fontsource/geist` et `@fontsource/geist-mono` (OFL 1.1, nom exact vérifié à l'apply) ; retrait de `@fontsource/sofia-sans-condensed`, `@fontsource/ibm-plex-sans` et de `recharts` s'il n'est plus importé.
- Données : aucune migration. La couleur de joueur et le bloc STATS sont calculés à la lecture (une Query de plus, D13).
- Confidentialité : aucune ressource d'un autre domaine (Google Fonts du HTML remplacé par l'auto-hébergement) ; captures de référence avec les seules données de démonstration.
- Retour arrière : revert de la pull request de code ; pas de donnée touchée.
- Taille : grand changement mais d'un seul tenant ; tâches groupées pour une revue par étapes (fondations, cadre, puis page par page), deux pull requests (code, archivage).

## Hors périmètre

- Tout changement de calcul d'index, de route ou de schéma.
- Recadrage de photo (décision 3 du port), thème clair (décision Q1), réglage d'apparence.
- Sentient (décision Q2) : ni fichier dans le dépôt ni `@font-face`.
