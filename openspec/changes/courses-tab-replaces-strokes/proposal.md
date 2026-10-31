# Proposal

## Why

L'onglet « Coups » de la barre flottante sert peu : le groupe saisit ses rounds et le classement net désigne le gagnant, sans préparer les coups rendus avant la partie. La page Parcours, elle, où l'on confirme les ratings proposées par Simulator Golf Tour, n'est atteignable que par un lien de la page Rounds ou de la page d'un round. Elle mérite la place de l'onglet.

## What Changes

- La barre flottante garde quatre onglets : « Rounds », « Handicaps », « Parcours » (`/courses`, icône drapeau) et « Joueurs », puis le bouton Importer. L'onglet « Coups » disparaît de la barre.
- La page Parcours devient une page de section : rangée du haut « Golf » au lieu du lien de retour « Rounds », onglet « Parcours » marqué courant sur `/courses`, et non plus « Rounds ».
- La page Coups (`/strokes`) reste, inchangée dans son calcul, mais devient une sous-page de Handicaps : lien de retour « Handicaps » dans la rangée du haut, onglet « Handicaps » marqué courant.
- La page Handicaps gagne, à droite de son titre, le lien « Coups à donner » vers `/strokes`, dans le style du lien « Parcours » de la page Rounds aujourd'hui.
- La page Rounds perd son lien « Parcours », devenu redondant avec l'onglet ; le lien de la rating sur la page d'un round reste.
- Les onglets préchargés (changement 14, en cours) suivent la barre : `/courses` est préchargée, `/strokes` ne l'est plus.

Aucune rupture d'adresse : `/strokes` et `/courses` répondent comme avant.

## Capabilities

### New Capabilities

Aucune.

### Modified Capabilities

- `app-shell` : les quatre sections de la barre, leurs adresses et leur ordre (« Parcours » remplace « Coups ») ; la section parente des sous-pages (`/strokes` relève de Handicaps, `/courses` n'est plus rattachée à Rounds).
- `course-ratings` : la page Parcours est un onglet de la barre et non plus une page atteinte depuis Rounds ; sa rangée du haut montre « Golf » au lieu du lien de retour « Rounds ».
- `match-strokes` : la page Coups s'ouvre depuis la page Handicaps et montre le lien de retour « Handicaps ».
- `handicap-index` : la page Handicaps porte le lien « Coups à donner ».

## Impact

- **ROADMAP** : nouvelle entrée 16 `courses-tab-replaces-strokes`. Dépend de 7 (page Coups), 9 (page Parcours) et 11 (barre flottante), tous faits. Pas de dépendance au code de 14 ni de 15, mais deux changements en cours touchent la barre :
  - 14 `optimize-performance` précharge « les quatre onglets » : sa formulation reste vraie, seule la liste préchargée change ;
  - 15 `offline-pwa` a un scénario « touche l'onglet des coups » dans `offline-mode` : reformulé en « ouvre `/strokes` » dans ce changement (le comportement hors ligne de `/strokes` ne change pas, la page restant visitée en ligne depuis Handicaps).
- **Domaines** : interface seulement (barre, rangée du haut, trois pages). Aucun contrôleur, route, requête ni donnée ne change.
- **Code** : `resources/js/components/app-nav.tsx`, `resources/js/lib/sections.ts`, pages `courses/index.tsx`, `strokes/index.tsx`, `handicaps/index.tsx`, `rounds/index.tsx`, et leurs tests Vitest.
- **Confidentialité** : rien de nouveau ; les deux pages exigent déjà la session du code d'accès.
- **Retour arrière** : revenir au commit précédent ; aucune migration, aucune donnée.

## Non-goals

- Supprimer la page Coups ou changer son calcul.
- Renommer « Parcours » en « Terrains » : la page, ses textes et ses specs gardent « Parcours » (décision du mainteneur).
- Changer l'ordre, le style ou le nombre des onglets, ou le bouton Importer.
- Ajouter un lien vers les coups sur l'accueil.
