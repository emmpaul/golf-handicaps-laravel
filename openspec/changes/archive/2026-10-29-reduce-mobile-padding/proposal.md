## Why

Sur un vrai iPhone, en application ajoutée à l'écran d'accueil, les pages ont trop de marge : la rangée « Golf » commence à `safe-top + 10 px` (≈ 69 px) alors que la zone sûre (≈ 59 px) laisse déjà de l'air sous la barre d'état, et la gouttière latérale de 20 px rétrécit une colonne de 390 px au plus. Suite de la vérification des zones sûres sur iPhone que l'entrée 11 `visual-redesign` du ROADMAP laissait au mainteneur.

## What Changes

- La rangée du haut d'une page de section (« Golf » seul ou suivi d'une valeur) commence au bas de la zone sûre, sans les 10 px ajoutés : haut à `max(54 px, zone sûre)`. Sans zone sûre (Safari avec barre d'adresse, ordinateur), la mesure du design reste de 54 px.
- La rangée à lien de retour ou à actions garde `max(44 px, zone sûre)`.
- Les marges latérales du contenu passent de 20 à 16 px sur toutes les pages, connexion comprise, alignées sur la barre flottante.
- DESIGN.md et le ROADMAP reprennent les nouvelles mesures.

Hors périmètre : l'espacement entre sections d'une page, la hauteur de la rangée (44 px), la marge de 22 px sous « Golf », la barre flottante, les feuilles et le toast (leurs propres marges ne changent pas), tout breakpoint.

## Capabilities

### New Capabilities

Aucune.

### Modified Capabilities

- `app-shell` : la rangée du haut ne s'ajoute plus 10 px sous la zone sûre ; les marges latérales passent de 20 à 16 px.

## Impact

- Code : `resources/js/components/page-top-row.tsx`, jeton `--gutter` de `resources/css/tokens.css` (utilisé par `AppLayout`, `BareLayout`, `EditorSaveBar`, `Toaster`), tests associés.
- Docs : DESIGN.md (« Cadre et mesures »), ROADMAP.md.
- Dépendances : entrée 11 `visual-redesign`, faite. Domaine : présentation seulement, pas de donnée, pas d'accès ni de vie privée en jeu.
- Retour arrière : revenir au commit, rien à migrer.
