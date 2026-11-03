# Design

## Context

Voir proposal.md, « Why ». La barre vient de `TABS` dans `resources/js/components/app-nav.tsx` (quatre entrées `{ href, label, icon }`, rendues par `NavLink`, qui marque la section courante et précharge les autres pages). La section courante se décide dans `resources/js/lib/sections.ts` : `inSection(url, href)` accepte la page de la section, ses sous-adresses, et les préfixes de `SECTION_PREFIXES` (aujourd'hui `/import` → `/imports`, `/rounds` → `/courses`). La rangée du haut est `PageTopRow` : sans `back`, « Golf » ; avec `back`, le lien de retour.

Côté serveur, rien ne change : `/courses` et `/strokes` existent avec leur contrôleur, leur session et leur 404 de sous-adresse.

## Goals / Non-Goals

**Goals :**
- Échanger un onglet sans toucher au rendu de la barre (taille, animation, compactage sous 375 px).
- Garder une seule source de vérité de la section courante (`SECTION_PREFIXES`).

**Non-Goals :**
- Aucun changement de route, de contrôleur, de requête ni de modèle.
- Aucune ADR : l'échange d'un onglet ne décide rien d'architecture. DESIGN.md, qui liste les onglets, est mis à jour.

## Decisions

### D1. L'onglet « Parcours » prend la place de « Coups », icône `Flag`

`TABS` devient Rounds, Handicaps, Parcours (`courses.index`), Joueurs. L'icône drapeau (`Flag`) de Coups désigne mieux un parcours de golf qu'un calcul de coups ; elle passe à Parcours. Alternative écartée : `MapPin` ou `LandPlot`, moins lisibles à 20 px et sans gain.

Largeur : « Parcours » ouvert est plus large que « Coups » (40 px). Le test de largeur de la barre (`app-nav.test.tsx`, B2/DO34) somme les classes et des largeurs de libellé mesurées dans Chrome ; la largeur de « Parcours » (Geist 600, 13 px) y est ajoutée après mesure dans Chrome. Elle reste sous « Handicaps » (66 px), le libellé le plus long, déjà tenu à 288 px : aucune classe de compactage ne change.

### D2. Section courante : `/strokes` relève de Handicaps, `/courses` est sa propre section

`SECTION_PREFIXES` devient `{ '/import': ['/imports'], '/handicaps': ['/strokes'] }`. `/courses` n'a plus besoin de préfixe : c'est l'adresse d'un onglet. Alternative écartée : déplacer `/strokes` sous `/handicaps/coups` pour hériter du préfixe ; elle casse l'adresse que le service worker garde (changement 15) et les favoris, pour rien.

### D3. Rangées du haut

- Page Parcours : `PageTopRow` sans `back` (« Golf »), comme toute page de section.
- Page Coups : `PageTopRow back={{ href: handicapsIndex.url(), label: 'Handicaps' }}`, comme « Calcul de l'index ».

### D4. Lien « Coups à donner » sur la page Handicaps, « Parcours » retiré de Rounds

Le titre de Handicaps passe dans la même ligne `flex items-end justify-between` que Rounds, avec un `Link` `TITLE_LINK` vers `strokes.index` sans `?joueur=` (la page Coups choisit plusieurs joueurs, elle n'a pas de joueur courant). Le lien « Parcours » de Rounds est retiré ; « Saisie manuelle » reste. Le lien de la rating sur la page d'un round reste : il mène au réglage d'une rating précise.

### D5. Préchargement

`NavLink` précharge déjà chaque onglet sauf la page courante : `/courses` sera préchargée, `/strokes` non. `/courses` porte des recherches SGT et un rafraîchissement pendant une recherche ; le préchargement fait un GET Inertia sans effet de bord, la page ne déclenche son rafraîchissement qu'une fois montée. Rien à changer dans `lib/prefetch.ts`.

## Risks / Trade-offs

- [La page Coups, moins visible, est encore moins utilisée] → c'est voulu ; le lien depuis Handicaps la garde à deux touches.
- [Le scénario « touche l'onglet des coups » du changement 15 `offline-pwa`, non archivé, devient faux] → reformuler ce scénario en « ouvre `/strokes` » dans `openspec/changes/offline-pwa/specs/offline-mode/spec.md` dans ce changement, sans toucher à son comportement.
- [La passe navigateur du changement 15 (tâche 6.3) parle d'ouvrir `/strokes`] → inchangée : l'adresse reste.
- [Coût de `/courses` préchargée à chaque page] → un GET de plus par visite au lieu de `/strokes` ; mesurable par `make perf-probe` (changement 14) si besoin.

## Migration Plan

Déploiement ordinaire, sans migration. Retour arrière : revenir au commit.
