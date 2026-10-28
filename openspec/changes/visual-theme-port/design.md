# Design

## Context

État hérité de `project-skeleton-and-quality-gates` (voir `proposal.md`, « Why ») :

- Front du kit nettoyé : `resources/js/app.tsx` (`createInertiaApp` sans layout), `pages/welcome.tsx` (couleurs en dur), `lib/utils.ts` (`cn`), un seul test Vitest (`tests/timezone.test.ts`). Pas de `components/`, de `components.json`, de primitives, de mode sombre ni de middleware `HandleAppearance` ; `app.blade.php` lit pourtant encore `$appearance`.
- `resources/css/app.css` n'importe que Tailwind et déclare `--font-sans` (Instrument Sans, chargée par `bunny()` dans `vite.config.ts`).
- `vite.config.ts` : `test` sous `jsdom` et `TZ=America/Montreal`, sans `setupFiles` ; `lint` et `fmt` excluent déjà `resources/js/components/ui/*`, `.jscpd.json` aussi.
- `HandleInertiaRequests` partage `name`. `config('app.version')` (lu dans `version.txt`) et `config('app.commit')` existent ; `VersionController` valide le commit par une expression inline.
- `public/` contient les icônes du kit (`favicon.ico`, `favicon.svg`, `apple-touch-icon.png`).
- GD et Imagick sont présents dans l'image Sail.

Référence de comportement : `src/app/{tokens,base,globals}.css`, `layout.tsx`, `brand-colors.ts`, `app-glyph.tsx`, `manifest.ts`, `src/components/{app-nav,nav-link,app-version,brand-mark,page-title,scroll-frame}.tsx`, `src/components/scores/score-mark.tsx`, `src/components/ui/*` et `tests/app/{palette,theme-contrast,brand-colors}.test.ts` du dépôt Next `golf-handicaps`. `docs/PORT_TO_LARAVEL.md` prime sur la couche 3 du squelette ; les décisions du mainteneur sont dans `proposal.md`.

## Goals / Non-Goals

**Goals:**

- Reprendre l'apparence de Next à l'identique : mêmes valeurs OKLCH, mêmes polices, mêmes classes dans les primitives, adaptées à Inertia.
- Chaque règle de thème vérifiable sans navigateur a un test Vitest ou Pest qui rougit sous une mutation nommée. Les règles de mise en page (largeurs, zones sûres, opacité de l'en-tête) se vérifient à la passe `browser-use`.

**Non-Goals:**

- Aucun écran métier, aucune donnée du groupe dans les props.
- Pas de rendu serveur (SSR) : le kit ne l'active pas et rien ne le demande.
- Pas de CLI shadcn pour générer ou mettre à jour les primitives : elles sont copiées de Next et possédées par le projet.

## Decisions

### D1. Tokens en trois fichiers, palette fermée

- `resources/css/tokens.css` : copie de `tokens.css` de Next. Le bloc `@theme static` commence par `--color-*: initial; --text-*: initial; --radius-*: initial; --shadow-*: initial;` (palette fermée de la couche 3), puis déclare les tokens clairs en OKLCH, l'échelle de texte, les rayons (`--radius-card: 0`, `--radius-pill`), les courbes et durées. Il ajoute `--color-white`, `--color-black`, `--color-transparent` et `--color-current`. Le bloc `@media (prefers-color-scheme: dark) { :root { … } }` redéfinit les couleurs sombres. Les espacements, durées et z-index restent dans `:root`.
- `resources/css/base.css` : copie de `base.css` (focus visible sans transition, `.num`, `.visually-hidden`, `overflow-x: clip` sur `html` et `body`, règle globale de mouvement réduit), importée dans `layer(base)`.
- `resources/css/app.css` : `@import 'tailwindcss'`, `tw-animate-css`, `tokens.css`, `base.css`, `@custom-variant dark (@media (prefers-color-scheme: dark))`, les variantes `data-open` et `data-closed` recopiées de `shadcn/tailwind.css` (seules utilisées par les primitives base-ui), `@theme inline` qui pointe les couleurs shadcn vers leurs variables, puis le bloc `:root` de `globals.css` (variables shadcn → tokens, zones sûres `--safe-top` / `--safe-bottom`, `--top-chrome`, `--tabbar-h`, `--bottom-chrome`, `--birdie`, `--bogey`, `color-scheme`). `@source not '../../docs'`.

Les variables shadcn pointent vers les tokens : en mode sombre, seul le bloc de `tokens.css` change, sans second bloc `.dark`.

Alternatives écartées : la classe `.dark` posée par un cookie (décision 1 du mainteneur) ; garder la palette Tailwind ouverte, comme Next, qui laisse passer un `text-gray-500` muet.

### D2. Mode sombre par le système seul

`@custom-variant dark` vise la media query. `app.blade.php` perd `@class(['dark' => …])` et déclare `<meta name="color-scheme" content="light dark">`. Aucun middleware, cookie, hook ni page de réglage. C'est un écart au squelette (décision 9 : « mode sombre gardé » l'est, le réglage ne l'est pas), consigné dans l'ADR 0006.

### D3. Polices `fontsource`

`bunny('Instrument Sans')` est remplacé par deux `fontsource()` de `laravel-vite-plugin/fonts` :

- `fontsource('Sofia Sans Condensed', { weights: [400, 600, 800], subsets: ['latin', 'latin-ext'], variable: '--font-sofia-sans-condensed' })` ;
- `fontsource('IBM Plex Sans', { weights: [400, 500, 600], subsets: ['latin', 'latin-ext'], variable: '--font-ibm-plex-sans' })`.

Paquets `@fontsource/sofia-sans-condensed` et `@fontsource/ibm-plex-sans` en dépendances de développement. `tokens.css` déclare `--font-display: var(--font-sofia-sans-condensed), 'Arial Narrow', ui-sans-serif, sans-serif` et `--font-body: var(--font-ibm-plex-sans), ui-sans-serif, system-ui, sans-serif`, comme Next. `@fonts` reste dans `app.blade.php`. Les graisses suivent l'usage de Next : 800 pour les titres (`font-extrabold`), 500 et 600 pour le texte.

Alternative écartée : les paquets variables `@fontsource-variable/*` par l'option `package` du plugin. Les graisses fixes suffisent et restent le cas documenté du plugin ; à revoir si une graisse manque.

### D4. Primitives base-ui copiées de Next

`components.json` (style `base-nova`, comme Next, `tsx`, `css: resources/css/app.css`, alias `@/components`, `@/components/ui`, `@/lib/utils`, `@/hooks`, `iconLibrary: lucide`) documente la provenance ; il n'est pas utilisé pour générer.

Les douze fichiers `alert-dialog`, `alert`, `badge`, `button`, `calendar`, `field`, `input`, `label`, `native-select`, `popover`, `separator` et `table` sont copiés de `src/components/ui/` de Next, avec une seule adaptation : `import { cn } from '@/lib/utils'` au lieu du paquet `cn`. Ils gardent leurs classes : hauteur `h-11 md:h-8` des boutons et champs, texte `text-base md:text-sm` des champs, focus `outline-2 outline-ring` sans transition, transitions nommées (`transition-[color,background-color,border-color]`), animations de dialogue par `tw-animate-css`. `field` importe `label` et `separator`, `alert-dialog` et `calendar` importent `button` : ces dépendances sont dans la liste.

Dépendances : `@base-ui/react`, `class-variance-authority`, `lucide-react`, `react-day-picker` et `date-fns` (calendrier), `tw-animate-css`.

Alternative écartée : Radix, que liste le squelette. `docs/PORT_TO_LARAVEL.md` (décision 7) nomme base-ui, et copier les fichiers de Next évite de restyler une autre bibliothèque.

### D5. Composants du thème

Sous `resources/js/components/`, copiés de Next et adaptés à Inertia :

- `brand-mark.tsx` : SVG aux couleurs des tokens (`fill="var(--color-ink)"`…).
- `page-title.tsx`, `scores/score-mark.tsx`, `scroll-frame.tsx` : inchangés hors imports. `ScoreMark` annonce « 3, birdie » et « 5, bogey » par un `sr-only`.
- `app-nav.tsx` : `LINKS` (cinq sections, icônes lucide `Camera`, `ListOrdered`, `TrendingUp`, `Flag`, `Users`), en-tête collé et barre d'onglets du bas. Les liens sont des `<Link>` Inertia dont l'adresse vient des fonctions Wayfinder des routes nommées `import.index`, `rounds.index`, `handicaps.index`, `strokes.index`, `players.index` (D7).
- `nav-link.tsx` : la fonction pure `inSection(path, href)` et la table `SECTION_PREFIXES` (`/import` → `/imports`, `/rounds` → `/courses`) sont exportées depuis `resources/js/lib/sections.ts`. `NavLink` lit le chemin dans `usePage().url` (sans la query string) au lieu de `usePathname`.
- `app-version.tsx` : props `version` et `commit` ; `resources/js/lib/version.ts` exporte `RELEASES_URL = 'https://github.com/emmpaul/golf-handicaps-laravel/releases/tag/'`, `shortCommit()` et `releaseUrl()`. En `0.0.0`, le composant rend un `<span>` au lieu du lien (décision 7).
- `ui/pending.tsx` : voir D9. `ui/toaster.tsx` : voir D8.

### D6. Layout et pages

- `resources/js/layouts/app-layout.tsx` : `AppNav`, `<main>` avec les marges de Next (`pb-[calc(--spacing(4)+var(--bottom-chrome))]`), les enfants, `AppVersion` en dernier enfant, puis `Toaster`. Il lit `app.version` et `app.commit` dans `usePage().props`.
- `app.tsx` pose ce layout par défaut sur chaque page (option `layout` de `createInertiaApp` ou `resolve` qui assigne `page.default.layout`, selon l'API d'Inertia 3 vérifiée à l'apply) et règle la barre de progression Inertia sur `#0F1D3E` : elle ne lit pas les variables CSS. C'est la seule couleur en dur hors de `tokens.css`, nommée dans l'exception du test de D10.
- `pages/home.tsx` remplace `welcome.tsx` : `PageTitle` « Handicaps golf » et une phrase d'attente. `pages/section-placeholder.tsx` : `PageTitle` du nom de la section et « Section à venir ».
- `app.blade.php` : `lang="fr"`, `viewport` avec `viewport-fit=cover`, deux `theme-color` (`#F4F7FB` clair, `#0A111F` sombre), `color-scheme`, `apple-mobile-web-app-capable`, `apple-mobile-web-app-title` « Golf », `apple-mobile-web-app-status-bar-style` `default`, `<link rel="manifest" href="/manifest.webmanifest">`, `apple-touch-icon`, `favicon.svg`. Le titre par défaut devient `config('app.name')`, et `.env.example` passe `APP_NAME="Handicaps golf"`.

### D7. Routes des pages d'attente

Dans `routes/web.php`, cinq routes `Route::inertia('/<section>/{path?}', 'section-placeholder', ['section' => '<Libellé>'])->where('path', '.*')->name('<section>.index')`. Le nom `import.index` (et non `import`) évite un identifiant réservé côté TypeScript, et chaque changement de section reprend le même nom pour sa vraie page : la navigation ne change pas. `/` reste `Route::inertia('/', 'home')->name('home')`.

Alternative écartée : un contrôleur `SectionPlaceholderController`, qui ne fait rien de plus que `Route::inertia`.

### D8. Toast par la donnée flash Inertia

- Contrat : clé flash `toast`, `{ type: 'error' | 'success', message: string }`, déclarée dans `resources/js/types/global.d.ts` (`flashDataType` d'Inertia 3). Côté serveur, `Inertia::flash('toast', ['type' => 'error', 'message' => …])` ; le rendu de `DomainException` (`players-and-rounds`) l'émettra.
- `ui/toaster.tsx` : `<Toaster>` de sonner, stylé par `toastOptions.classNames` aux tokens (fond `bg-popover`, texte `text-foreground`, erreur `text-destructive border-destructive`), rayon 0, position bas sur téléphone au-dessus de `--bottom-chrome` (`offset`), bouton de fermeture. Une erreur rend son message dans un élément `role="alert"`, une confirmation dans un `role="status"`.
- `lib/flash-toast.ts` : `showFlashToast(flash)` appelle `toast.error` ou `toast.success`, rien si la clé manque. Le layout l'abonne à `router.on('flash', …)` dans un effet.
- Pas de test Pest du flash dans ce changement : sans émetteur, il ne vérifierait qu'Inertia. Le test serveur arrive avec le rendu de `DomainException` (`players-and-rounds`) ; ici, Vitest vérifie l'affichage et l'abonnement du layout.

Alternative écartée : une prop partagée lue depuis la session (`session('toast')`), qui réapparaît sur un rechargement partiel et réimplémente le flash d'Inertia.

### D9. Indicateur d'attente

`ui/pending.tsx` reprend `Pending` et `PendingOrb` de Next : région `role="status"` toujours montée, `sr-only` et vide au repos ; pendant l'attente, `ThinkingOrb` (`aria-hidden`, `role="presentation"`, 20 px) et le texte. Changements :

- `theme="auto"` au lieu de `"light"` (décision 8) ;
- `paused` vaut vrai quand `matchMedia('(prefers-reduced-motion: reduce)')` correspond (hook `useReducedMotion` dans `resources/js/hooks/`), parce que l'orbe s'anime en JavaScript et échappe à la règle CSS globale ;
- `ORB_FOR` ne garde que les attentes de ce changement et de l'avenir proche (`send`, `save` → `working`) ; `rectify` disparaît avec l'OCR, `queued` / `reading` et `orbForImport` arrivent avec `scorecard-ai-import`.

### D10. Garde-fous sans Hallmark

`resources/js/tests/theme-sources.test.ts` parcourt les fichiers `.ts` et `.tsx` de `resources/js` (hors `actions/`, `routes/`, `wayfinder/` générés et hors `*.test.*`) et échoue en nommant le fichier qui contient une couleur en dur (`#rgb`, `#rrggbb` entre guillemets, `[#`, `oklch(`, `rgb(`, `hsl(`) ou une transition globale (`transition-all`, `transition: all`). Exception unique et nommée : la couleur de la barre de progression dans `app.tsx`. Les primitives `components/ui` sont incluses : leur exclusion du lint et de jscpd ne vaut pas ici.

Les tests de palette reprennent `tests/app/palette.ts` de Next sous `resources/js/tests/palette.ts` (lecture de `resources/css/tokens.css` et `app.css`, résolution des `var()`, OKLCH → sRGB, contraste WCAG), avec `theme-contrast.test.ts` (paires de texte à 4,5:1 et de marques à 3:1, dans les deux modes) et les valeurs hex littérales de la spec.

### D11. Icônes et manifest

- `app/Console/Commands/GenerateAppIcons.php` (`php artisan app:icons`) dessine avec GD le glyphe de `app-glyph.tsx` (fond d'encre, green ellipse, trou, mât, fanion rectangulaire, en centièmes du côté) et écrit `public/icons/icon-192.png`, `public/icons/icon-512.png` et `public/apple-touch-icon.png` (180 px). Les couleurs sont des constantes de la classe, en hex de la palette claire. Les PNG sont versionnés ; la commande se relance quand la palette change.
- `public/favicon.svg` : le dessin de `BrandMark` en couleurs fixes de la palette claire. `public/favicon.ico` du kit est supprimé.
- `public/manifest.webmanifest` : JSON statique de `manifest.ts` (`name`, `short_name`, `start_url`, `display`, `background_color` et `theme_color` à `#F4F7FB`, `lang: fr`, icônes 192 et 512).
- Fichiers statiques servis par le serveur web avant Laravel : ni session ni cookie, et `app-access` n'a rien à exclure.
- Tests Pest : tailles des trois PNG et couleur de pixels témoins (centre du fanion `#CB1E21`, coin `#0F1D3E`, centre du green `#46566E`) ; contenu du manifest ; balises de `GET /`. Ces tests et les tests de palette Vitest comparent tous aux mêmes littéraux de la spec : un token changé sans les copies fait rougir l'un ou l'autre.

Alternative écartée : une route qui dessine à la demande, qui devrait rester hors du code d'accès et repasse par PHP à chaque demande.

### D12. Version partagée

`App\Support\AppBuild` (finale, statique) expose `version(): string` et `commit(): ?string` (sha de 40 caractères hexadécimaux minuscules, sinon `null`). `VersionController` et `HandleInertiaRequests` l'utilisent : la validation du commit n'existe qu'une fois. `HandleInertiaRequests::share()` ajoute `app: { version, commit }`, typé dans `global.d.ts`.

### D13. Outillage de test

`@testing-library/react`, `@testing-library/user-event` et `@testing-library/jest-dom` en dépendances de développement ; `resources/js/tests/setup.ts` importe `@testing-library/jest-dom/vitest` et appelle `cleanup()` après chaque test ; `vite.config.ts` ajoute `setupFiles: ['resources/js/tests/setup.ts']`. Les tests de composants rendent le composant réel ; seuls `usePage` et `router` d'Inertia sont simulés quand un composant les lit. Un test qui touche une heure locale vérifie l'heure, jamais le nom du fuseau (ICU rapporte `America/Toronto`).

### D14. ADR et documentation

- `docs/adr/0006-systeme-de-design.md` : palette fermée et tokens clairs et sombres, mode sombre par le système seul (écart au squelette), base-ui au lieu de Radix (décision 7 du port), polices `fontsource`, primitives possédées, icônes statiques générées, toast par flash Inertia.
- `DESIGN.md` : direction (tableau d'affichage froid, encre bleu-nuit, un rouge de score), tokens, polices, composants, règles (palette fermée, transitions nommées, focus, cibles tactiles), commande des icônes.
- `ARCHITECTURE.md` : section Frontend (layout, données partagées `app`, flash `toast`, renvoi à l'ADR 0006).

## Risks / Trade-offs

- [jsdom ne calcule ni mise en page ni media query] → les tests vérifient les classes et les attributs produits ; la passe `browser-use` mesure les hauteurs à 390 et 1440 px, les largeurs à 320 px, l'en-tête opaque et les deux thèmes.
- [Le dessin des icônes existe deux fois, en SVG (`BrandMark`, `favicon.svg`) et en GD] → mêmes coordonnées en centièmes, couleurs vérifiées par pixel ; la passe navigateur compare l'en-tête et `/apple-touch-icon.png`.
- [`thinking-orbs` peint ses propres couleurs] → hors tokens par construction, accepté comme dans Next ; la variante sombre suit le système.
- [Primitives copiées sans consommateur jusqu'à `players-and-rounds`] → elles sont exclues du lint et de jscpd mais couvertes par le test de D10 ; `button`, `input` et `native-select` ont un test de leurs classes de taille.
- [Les cinq routes d'attente répondent 200 à toute sous-adresse] → aucune donnée exposée ; chaque changement de section les remplace.
- [Inertia 3 : API exacte du layout par défaut et de l'évènement `flash`] → vérifiée à l'apply avec `search-docs` de Boost ; le contrat (clé `toast`, forme) ne change pas.

## Migration Plan

Aucune donnée ni migration. Déploiement par la fusion. Retour arrière : revenir sur le commit de fusion ; `make build` reconstruit les assets.
