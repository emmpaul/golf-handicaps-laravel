## Context

Mesures actuelles (DESIGN.md, « Cadre et mesures ») : `PageTopRow` pose `pt-[max(54px,calc(var(--safe-top)+10px))]` pour « Golf » seul et `pt-[max(44px,var(--safe-top))]` pour une rangée à retour ou à actions. `--gutter` (20 px, `tokens.css`) donne les marges de `main` dans `AppLayout` et `BareLayout`, la marge négative d'`EditorSaveBar` (`-mx-(--gutter)`, remplie ensuite de `px-(--gutter-nav)`) et la largeur maximale du toast. `--gutter-nav` (16 px) sert la barre flottante et l'intérieur d'`EditorSaveBar`.

## Goals / Non-Goals

**Goals:** retirer les 10 px ajoutés sous la zone sûre ; ramener la gouttière du contenu à 16 px partout par le seul jeton.

**Non-Goals:** toucher aux minimums 54/44 px sans zone sûre, aux espacements internes des pages, aux marges des feuilles (24 px 20 px 34 px, fixées par leur propre requirement).

## Decisions

- **Changer la valeur de `--gutter`, garder les deux jetons.** `--gutter: 16px` suffit : `AppLayout`, `BareLayout`, `EditorSaveBar` et `Toaster` suivent sans modification. Garder `--gutter-nav` distinct laisse la barre flottante libre d'évoluer seule ; fusionner les jetons toucherait plus de fichiers pour rien. Alternative écartée : remplacer `px-(--gutter)` par `px-(--gutter-nav)` dans les layouts, qui détournerait le jeton de la barre.
- **`EditorSaveBar` reste pleine largeur.** `-mx-(--gutter)` suit la nouvelle valeur ; la barre continue de toucher les bords de la colonne, son intérieur reste à 16 px.
- **Haut de la rangée de section : `pt-[max(54px,var(--safe-top))]`.** Même forme que la variante à retour ; la rangée de 44 px centre déjà « Golf » à 22 px sous la zone sûre.
- **Tests.** jsdom ne calcule ni `env()` ni les variables CSS : les tests de composant vérifient la classe posée (`pt-[max(54px,var(--safe-top))]`, absence de `+10px`), et un test lit `tokens.css` pour exiger `--gutter: 16px`. La mesure réelle sur iPhone reste une vérification manuelle du mainteneur, comme pour `visual-redesign`.

## Risks / Trade-offs

- [« Golf » plus proche de l'îlot] → la rangée de 44 px centre le texte ; il reste ≈ 13 px entre le bas de la zone sûre et le haut des lettres. Vérification sur iPhone avant fermeture.
- [Le toast peut s'élargir de 8 px] → sa largeur maximale suit `--gutter` ; sans effet visible à 390 px pour les messages courts.
- [Scénarios d'autres specs citant 20 px] → seules les marges du contenu changent ; les feuilles et la carte de score gardent leurs mesures.

## Migration Plan

Déploiement d'image habituel ; retour arrière par l'image précédente. Pas de donnée.
