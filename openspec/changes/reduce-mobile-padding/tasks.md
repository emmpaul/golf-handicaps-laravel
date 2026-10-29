## 1. Rangée du haut

- [x] 1.1 RED : dans un test de `PageTopRow` (nouveau `resources/js/components/page-top-row.test.tsx`), rendre la rangée « Golf » seule et exiger la classe littérale `pt-[max(54px,var(--safe-top))]` ; rendre une rangée à retour et exiger `pt-[max(44px,var(--safe-top))]`. Vérifier qu'il échoue sur le code actuel (`+10px`) avec `make test`.
- [x] 1.2 GREEN : remplacer `pt-[max(54px,calc(var(--safe-top)+10px))]` par `pt-[max(54px,var(--safe-top))]` dans `resources/js/components/page-top-row.tsx` et mettre à jour son commentaire ; `make test` vert.

## 2. Gouttière

- [x] 2.1 RED : dans `resources/js/tests/theme-tokens.test.ts`, lire `resources/css/tokens.css` et exiger `--gutter: 16px` et `--gutter-nav: 16px` ; vérifier l'échec sur la valeur actuelle de 20 px.
- [x] 2.2 GREEN : passer `--gutter` à `16px` dans `resources/css/tokens.css` ; mettre à jour les intitulés « gutters of 20 px » de `resources/js/tests/pages/login.test.tsx` et le commentaire de `AppLayout` (« marges de 20 px ») ; `make test` vert.

## 3. Documentation

- [x] 3.1 Mettre à jour DESIGN.md, « Cadre et mesures » : marges latérales de 16 px (`--gutter` = `--gutter-nav`), haut de la rangée « Golf » à `max(54px, safe-top)`. Relire le diff.
- [x] 3.2 Ajouter au ROADMAP.md une ligne pour `reduce-mobile-padding` (dépend de 11) ; relire le diff.

## 4. Vérification

- [x] 4.1 `make check` vert.
- [x] 4.2 Passe navigateur avec browser-use : `make up`, `make dev`, `make browser` ; à 390 px, ouvrir `/handicaps`, `/rounds/<id>` et `/login`, mesurer « Golf » à 54 px du haut et à 16 px du bord gauche, aligné sur la barre flottante ; captures dans `reports/captures/reduce-mobile-padding/` ; puis `make dev-stop`.
- [x] 4.3 Relire les scénarios de la delta `app-shell` un à un ; noter que les scénarios « en application d'écran d'accueil » (59 px) restent à vérifier par le mainteneur sur iPhone après déploiement.
