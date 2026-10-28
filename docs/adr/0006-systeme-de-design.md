# 0006. Système de design

## Statut

Acceptée, 2026-10-27 (changement OpenSpec `visual-theme-port`).

## Contexte

L'application Laravel est le port de l'application Next.js `golf-handicaps`, dont le groupe connaît déjà l'interface : thème scoreboard clair et sombre, polices condensées, primitives shadcn sur base-ui, navigation au doigt sur téléphone. Avant ce changement, l'application servait la page d'accueil du kit, avec des couleurs en dur et la police Instrument Sans téléchargée chez Bunny au build.

Le squelette du mainteneur (couche 3) demande une palette fermée et des polices auto-hébergées, garde un mode sombre réglable par cookie et nomme Radix pour les primitives. `docs/PORT_TO_LARAVEL.md` prime sur le squelette et nomme base-ui (décision 7 du port). Next vérifiait ses couleurs par le linter Hallmark, absent ici.

## Alternatives

- **Palette Tailwind ouverte, comme Next** : une classe `text-gray-500` passe sans bruit et produit une couleur hors du thème.
- **Mode sombre réglable** (classe `.dark` posée par un cookie `appearance` et le middleware `HandleAppearance` du kit) : un réglage, un cookie et un middleware de plus, pour un groupe qui n'a jamais demandé autre chose que le mode de son téléphone.
- **Radix, comme le squelette** : il faudrait restyler une autre bibliothèque au lieu de copier les fichiers de Next.
- **Primitives générées et mises à jour par la CLI shadcn** : une mise à jour écraserait les adaptations (hauteurs tactiles, focus sans transition).
- **Polices par `bunny()`** : le plugin de Vite les servait déjà depuis l'application, mais les téléchargeait au build. Une panne de Bunny ou un build hors ligne cassait le build.
- **Icônes dessinées à la demande par une route** : la route devrait rester hors du code d'accès et repasserait par PHP à chaque demande.
- **Message éphémère lu dans une prop partagée de session** : il réapparaît sur un rechargement partiel et réimplémente le flash d'Inertia.

## Décision

- **Tokens et palette fermée.** `resources/css/tokens.css` copie les valeurs OKLCH de Next. Son bloc `@theme static` vide `--color-*`, `--text-*`, `--radius-*` et `--shadow-*`, puis déclare les tokens clairs ; `@media (prefers-color-scheme: dark)` redéfinit les couleurs sombres. `--text-base` et les interlignes de `xs` à `3xl` sont redéclarés pour que les classes des primitives calculent les mêmes valeurs que dans Next. `resources/css/app.css` pointe les variables shadcn vers les tokens, sans second bloc `.dark`.
- **Mode sombre par le système seul.** `@custom-variant dark` vise `prefers-color-scheme`. Aucun cookie `appearance`, aucun middleware, aucune page de réglage. C'est un écart au squelette, qui garde un réglage.
- **Polices `fontsource`.** Sofia Sans Condensed (400, 600, 800) pour les titres et les chiffres, IBM Plex Sans (400, 500, 600) pour le texte, servies par les deux `fontsource()` de `vite.config.ts` depuis les paquets `@fontsource` verrouillés dans `pnpm-lock.yaml`.
- **Primitives base-ui possédées par le projet.** Les douze primitives de `resources/js/components/ui/` sont copiées de Next ; seule l'importation de `cn` change. `components.json` documente la provenance et ne sert pas à générer. Les quatre primitives que Next n'importe nulle part (`card`, `checkbox`, `toggle`, `toggle-group`) ne sont pas reprises.
- **Garde-fou sans Hallmark.** `resources/js/tests/theme-sources.test.ts` échoue en nommant le fichier de `resources/js` qui écrit une couleur en dur ou une transition globale. Seule exception : la couleur de la barre de progression Inertia dans `app.tsx`, qui ne lit pas les variables CSS. `theme-contrast.test.ts` vérifie les contrastes des deux modes et les valeurs hex de la spec.
- **Icônes statiques générées.** `php artisan app:icons` dessine avec GD les PNG de 180, 192 et 512 px aux couleurs de la palette claire. Les fichiers sont versionnés dans `public/` avec `manifest.webmanifest` et servis par le serveur web, sans session.
- **Toast par le flash Inertia.** Le serveur joint `Inertia::flash('toast', ['type' => 'error' | 'success', 'message' => …])` ; le layout écoute `router.on('flash', …)` et sonner affiche le message.

## Conséquences

- Une couleur, une police, un rayon ou une ombre qui n'est pas un token ne produit aucun style, ou fait échouer `make test-frontend`.
- Un token modifié demande de mettre à jour les littéraux de la spec dans les tests de contraste, les constantes de `GenerateAppIcons`, `public/favicon.svg`, `manifest.webmanifest` et les `theme-color` d'`app.blade.php`, puis de relancer `app:icons`. Les tests Pest et Vitest comparent tous aux mêmes littéraux : un oubli fait rougir l'un ou l'autre.
- Les primitives ne suivent plus shadcn. Une correction en amont se reporte à la main.
- Les primitives sont exclues du lint et de jscpd, mais pas du test de `theme-sources`.
- Le dessin de la marque existe deux fois, en SVG (`BrandMark`, `favicon.svg`) et en GD. Les deux utilisent les mêmes coordonnées en centièmes du côté.
- `thinking-orbs` peint ses propres couleurs, hors des tokens. Son thème `auto` suit le mode sombre.
- Ajouter un jour un réglage d'apparence demandera son propre changement OpenSpec et une ADR qui remplace la partie « mode sombre » de celle-ci.
