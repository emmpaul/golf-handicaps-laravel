# Proposal

## Why

L'application Laravel n'a encore aucune interface. Elle sert la page d'accueil du kit (`welcome.tsx`, couleurs en dur, police Instrument Sans chargée chez Bunny), sans thème, sans navigation ni mode sombre. Chaque page métier à venir (joueurs, rounds, import, handicaps) a besoin du même cadre : la palette scoreboard claire et sombre de l'application Next.js, ses polices, ses primitives, la navigation au doigt sur téléphone et l'indicateur d'attente. Ce changement correspond à l'entrée 2 de `ROADMAP.md`. Sa seule dépendance, l'entrée 1 (`project-skeleton-and-quality-gates`), est faite et archivée.

Il reprend aussi trois points laissés par l'entrée 1 : la ligne de version dans l'interface (capacité `app-version`), le toast par lequel le rendu Inertia de `DomainException` passera (ADR 0001), et Testing Library avec le `setupFiles` de Vitest, qui arrivent avec les premiers tests de composants.

## What Changes

- **Thème scoreboard** : tokens clairs et sombres de l'application Next.js (papier bleuté, encre bleu-nuit, rouge « sous le par »), palette fermée de la couche 3 du squelette (`--color-*`, `--text-*`, `--radius-*` et `--shadow-*` vidés), variables shadcn pointées vers les tokens. Le mode sombre suit le système, sans réglage : le reste de `$appearance` disparaît de `app.blade.php`.
- **Polices auto-hébergées** : Sofia Sans Condensed pour les titres et les chiffres, IBM Plex Sans pour le texte, livrées par les paquets `fontsource` au lieu de Bunny, qui transmet l'adresse IP de chaque visiteur.
- **Primitives `components/ui`** sur base-ui, restylées aux tokens : les dix qu'une page Next importe (`alert-dialog`, `alert`, `badge`, `button`, `calendar`, `field`, `input`, `native-select`, `popover`, `table`) et les deux que `field` importe (`label`, `separator`), plus `pending` (indicateur d'attente) et le toast. Les composants du thème `BrandMark`, `ScoreMark`, `ScrollFrame` et `PageTitle` viennent aussi.
- **Shell de l'application** : en-tête collé avec la marque et « Golf », navigation des cinq sections (en haut à partir de 768 px, barre d'onglets en bas en dessous), section courante marquée, zones sûres, ligne de version en bas de page, toast monté une fois.
- **Pages d'attente** : `/import`, `/rounds`, `/handicaps`, `/strokes` et `/players` servent une page « Section à venir » dans le shell, que le changement de chaque section remplace. L'accueil `/` remplace `welcome.tsx`.
- **Installation sur l'écran d'accueil** : `manifest.webmanifest` statique, icônes PNG de 180, 192 et 512 px dessinées par une commande artisan avec GD et versionnées, balises `theme-color` claire et sombre, `viewport-fit=cover`.
- **Ligne de version** : « v0.1.0 · 4794e1b », lue dans `config('app.version')` et `config('app.commit')` par une donnée partagée Inertia. Elle pointe vers la release GitHub, sauf en `0.0.0`.
- **Toast** : sonner, stylé aux tokens. Il affiche la donnée flash Inertia `toast` (`{ type: 'error' | 'success', message }`). Aucun code serveur ne l'émet encore : `DomainException` et son rendu arrivent avec `players-and-rounds`.
- **Outillage de test frontend** : `@testing-library/react`, `@testing-library/user-event`, `@testing-library/jest-dom`, `resources/js/tests/setup.ts` déclaré dans `setupFiles`. Les tests Vitest de contraste, de cohérence des couleurs de marque et de composants sont repris de Next.
- **ADR 0006** (système de design), `DESIGN.md` complété, `ARCHITECTURE.md` mis à jour.
- Dépendances ajoutées par PNPM dans le conteneur (`vendor/bin/sail pnpm add`), jamais par npm.

### Hors périmètre

- Code d'accès, `/login`, déconnexion : `app-access`. Le shell n'a ni bouton de déconnexion ni état de session.
- Tout modèle, toute règle métier, `App\Domain\DomainException` et son rendu dans `bootstrap/app.php` (`players-and-rounds`).
- Contenu des pages de section, tableau de bord d'accueil, graphiques recharts (`home-and-match-strokes`).
- Primitives que Next n'importe nulle part (`card`, `checkbox`, `toggle`, `toggle-group`) : retirées.
- Page de réglage de l'apparence, cookie `appearance`, middleware `HandleAppearance`.
- Tests navigateur versionnés (décision 8 du port) : les scénarios de mise en page se vérifient par la passe `browser-use` de fin d'apply.

## Requirements des specs Next

Chaque requirement des specs `visual-theme`, `app-shell` et `loading-feedback` de l'application Next.js est soit porté en delta, soit retiré de ce changement, avec sa raison.

### `visual-theme`

| Requirement Next | Sort | Raison ou adaptation |
| --- | --- | --- |
| Thème sombre selon le système | Porté | Scénarios ramenés aux pages qui existent (accueil, page d'attente). |
| Marque d'app dans l'en-tête | Porté | — |
| Marques de score | Porté | Vérifié sur la case de score elle-même ; les pages qui l'affichent arrivent avec `players-and-rounds`. |
| Chiffres alignés | Porté | — |
| Palette sur mesure | Porté | Le scénario « Plus de vert Fairway » devient les valeurs littérales de la palette (ce dépôt n'a jamais eu Fairway). Le linter Hallmark n'existe pas ici : la palette fermée et un test qui parcourt `resources/js` le remplacent. |
| Mouvement des contrôles | Porté | Le scénario sur la suppression d'un round devient une boîte de dialogue quelconque ; « Pas de transition globale » est vérifié par le même test de parcours. |
| Focus visible au clavier | Porté | — |
| Couleurs du manifest | Porté | — |

### `app-shell`

| Requirement Next | Sort | Raison ou adaptation |
| --- | --- | --- |
| Navigation entre sections sur téléphone | Porté | — |
| Section courante indiquée | Porté | La sous-page d'un round devient une adresse sous `/rounds/`, servie par la page d'attente. |
| Installation sur l'écran d'accueil | Porté | — |
| Zones sûres en plein écran | Porté | Le scénario « Barre d'enregistrement sur iPhone » est retiré d'ici et reporté à `scorecard-ai-import`, qui crée l'écran de correction. |
| Pas de défilement horizontal de la page | Porté | Les scénarios propres à une page (calendrier de la date de jeu, marque « Compte », liste de toutes les pages) sont retirés d'ici. Chaque changement qui crée une de ces pages ajoute le sien. |
| Cibles tactiles et texte des champs sur téléphone | Porté | Vérifié sur les primitives (bouton, champ, liste déroulante, lien d'action). Les scénarios de l'écran de correction et de la liste des imports sont reportés à `scorecard-ai-import`, celui de Joueurs à `players-and-rounds`. |
| Contenu défilant signalé | Porté | Vérifié sur un cadre défilant générique ; la grille de correction et la carte de score arriveront avec leurs pages. |
| Section courante visible sur la barre d'onglets | Porté | — |
| En-tête net sous la barre d'état | Porté | — |

### `loading-feedback`

| Requirement Next | Sort | Raison ou adaptation |
| --- | --- | --- |
| Indicateur d'attente | Porté | Scénarios génériques ; les libellés de l'import arrivent avec leurs écrans. |
| Mouvement réduit | Porté | — |
| Attentes de l'import d'une photo | Retiré | « Préparation de la photo… » et « Redressement de la photo… » disparaissent avec l'OCR, le cadrage et `/recadrer` (décisions 2 et 3 du port). « Envoi de la photo… » est reporté à `scorecard-ai-import`. |
| Imports en attente de lecture | Retiré | Reporté à `scorecard-ai-import`, qui crée la liste des imports et les statuts `queued` / `reading`. |
| Attentes de l'écran de correction | Retiré | Reporté à `scorecard-ai-import` (écran de correction, création d'un joueur depuis une ligne). « Enregistrement du round… » de la saisie manuelle arrive avec `players-and-rounds`. |
| Autres attentes de l'app | Retiré | « Connexion… » est reporté à `app-access` ; « Enregistrement du joueur… » et « Suppression du round… » à `players-and-rounds`. |

### Requirements ajoutés ou découpés

- `visual-theme` « Contraste du texte » : les règles de contraste de « Thème sombre selon le système » et de « Palette sur mesure » sont réunies dans un seul requirement, qui couvre les deux thèmes et ajoute le seuil de 3:1 des marques non textuelles, déjà vérifié par le test de contraste de Next.
- `visual-theme` « Polices auto-hébergées » : couche 3 du squelette, aucune police chez un tiers.
- `app-shell` « Adresses des sections » : décision 4 ci-dessous (pages d'attente).
- `app-shell` « Message éphémère » : le toast hérité de l'ADR 0001 (décision 6).
- `loading-feedback` « Indicateur dans les deux thèmes » : décision 8.
- `app-version` « Version affichée dans l'interface » : héritage de l'entrée 1 (décision 7).

## Capabilities

### New Capabilities

- `visual-theme` : palette scoreboard claire et sombre, polices, marque, marques de score, chiffres alignés, mouvement, focus, couleurs du manifest.
- `app-shell` : cadre commun des pages (navigation, section courante, installation, zones sûres, largeur, cibles tactiles, cadres défilants, en-tête opaque, toast).
- `loading-feedback` : indicateur d'attente commun et son comportement en mouvement réduit.

### Modified Capabilities

- `app-version` : ajout de la ligne de version affichée dans l'interface.

## Décisions du mainteneur (2026-10-27)

Les règles laissées ouvertes par les specs Next, le squelette et `docs/PORT_TO_LARAVEL.md` ont été posées au mainteneur avant l'écriture des artefacts. Chaque réponse retient la recommandation proposée.

1. **Mode sombre** : il suit le système seul (`prefers-color-scheme`), comme Next, sans réglage dans l'application. Pas de `HandleAppearance`, de cookie `appearance` ni de page de réglage. Cet écart au squelette est consigné dans l'ADR 0006.
2. **Primitives** : les dix primitives qu'une page Next importe sont portées maintenant, avec `ScoreMark` et `ScrollFrame`. La question posée comptait six primitives jamais importées ; `field` importe en fait `label` et `separator`, qui viennent donc avec lui. Les quatre autres (`card`, `checkbox`, `toggle`, `toggle-group`) sont retirées.
3. **Attentes propres à des pages** : les requirements de `loading-feedback` qui visent des écrans à venir sont retirés de ce changement et reportés, chacun nommément, au changement qui crée l'écran. Ceux que l'abandon de l'OCR rend sans objet sont retirés.
4. **Sections sans page** : les cinq liens existent dès ce changement, et chaque adresse sert une page « Section à venir » dans le shell jusqu'à ce que le changement de la section la remplace.
5. **Icônes d'écran d'accueil** : des PNG de 180, 192 et 512 px, dessinés par une commande artisan avec GD aux couleurs de la palette, versionnés dans `public/` et servis par le serveur web sans session.
6. **Toast** : sonner, stylé aux tokens, monté dans le shell, alimenté par la donnée flash Inertia `toast` (`{ type: 'error' | 'success', message }`), que le serveur émettra par `Inertia::flash`.
7. **Ligne de version** : le lien vers `https://github.com/emmpaul/golf-handicaps-laravel/releases/tag/vX.Y.Z` existe pour toute version sauf `0.0.0`, qui s'affiche en texte seul. Le commit court s'ajoute quand il est connu.
8. **Indicateur d'attente** : le paquet `thinking-orbs` de Next, en thème `auto`, pour que l'orbe suive le mode sombre (Next la figeait en clair).

Choix tranchés par `docs/PORT_TO_LARAVEL.md`, qui prime sur le squelette : primitives sur base-ui (décision 7 du port) au lieu de Radix.

## Impact

- Fichiers nouveaux : `resources/css/tokens.css`, `resources/css/base.css`, `resources/js/components/` (shell, marque, version, primitives `ui/`), `resources/js/layouts/app-layout.tsx`, `resources/js/pages/home.tsx`, `resources/js/pages/section-placeholder.tsx`, `resources/js/lib/`, `resources/js/tests/setup.ts` et les tests Vitest, `app/Console/Commands/` (commande des icônes), `app/Support/AppBuild.php`, `public/manifest.webmanifest`, `public/icons/`, tests Pest, `docs/adr/0006-systeme-de-design.md`.
- Fichiers supprimés : `resources/js/pages/welcome.tsx`, `public/favicon.ico` du kit. `public/favicon.svg` et `public/apple-touch-icon.png` sont remplacés par les icônes de la marque.
- Fichiers modifiés : `resources/css/app.css`, `resources/js/app.tsx`, `resources/views/app.blade.php`, `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/VersionController.php`, `.env.example` (`APP_NAME`), `vite.config.ts`, `package.json`, `pnpm-lock.yaml`, `components.json` (nouveau ou ajusté), `tests/Feature/HomePageTest.php`, `DESIGN.md`, `ARCHITECTURE.md`, `docs/adr/README.md`, `CHANGELOG.md` (dans la PR de code).
- Dépendances PNPM : `@base-ui/react`, `class-variance-authority`, `lucide-react`, `sonner`, `thinking-orbs`, `tw-animate-css`, `react-day-picker`, `date-fns`, paquets `@fontsource` des deux polices, `@testing-library/react`, `@testing-library/user-event`, `@testing-library/jest-dom`.
- Routes : `GET /import`, `/rounds`, `/handicaps`, `/strokes`, `/players` et leurs sous-adresses servent la page d'attente. Aucune n'expose de donnée.
- Domaines : aucun.
- Vie privée : les polices ne se chargent plus chez un tiers ; la page ne fait plus aucune requête hors du domaine. La ligne de version ne révèle que la version et le commit, déjà publics.
- Accès : toutes les pages restent ouvertes jusqu'à `app-access`, comme aujourd'hui. Le manifest et les icônes devront rester lisibles sans session ; comme fichiers statiques, ils ne passent pas par le middleware.
- Retour arrière : revenir sur le commit de fusion. Aucune donnée ni migration en jeu.
