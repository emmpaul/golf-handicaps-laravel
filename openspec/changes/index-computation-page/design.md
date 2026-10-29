# Design

## Context

Voir `proposal.md` pour la raison. État actuel utile :

- `components/handicaps/computation-help.tsx` est un `<details>` de sept lignes fixes, monté par quatre pages (`players/index`, `rounds/show`, `courses/index`, `handicaps/index`) ; quatre tests de page et `computation-help.test.tsx` le vérifient.
- `HandicapController::index` lit `PlayerStandings` (index, provisoire, `colorSlot`), `PlayerHandicapHistory` (rounds du plus récent au plus ancien, différentiel, `countsInCurrent`) et `PlayerScoringStats`. `PlayerTabs` écrit en dur des liens vers `handicaps.index` et le `only` de cette page.
- `player_round_stats` garde `gross`, `adjusted`, `differential_t`, `counts_in_current`. La rating et le slope utilisés ne sont pas stockés : `RecomputePlayerStats::history()` les lit par une jointure gauche sur `course_ratings` (`lower(trim(...))` sur parcours et tees), et `rating_t` n'est rempli que pour une rating confirmée (contrainte `course_ratings_confirmed_rating_check`). Le par du round est la somme de `round_holes.par`, les coups sont dans `scores`.
- `Whs` (domaine pur) porte la table des retenus et des ajustements (`whsRule`, privée) et l'arrondi `roundHalfUp`.
- L'archive du design : `Calcul de l'index.dc.html`, un écran de 390 px. Ses couleurs et tailles sont celles des tokens, sauf le texte des règles (`oklch(0.88 0.005 260)`, Geist 400, 15/1,45) et deux tailles Geist Mono (36 px pour le différentiel de l'étape 02, 20 px pour les différentiels de la liste et les cases d'ajustement).

## Goals / Non-Goals

**Goals :**

- Une page fidèle à l'écran du design, qui montre des valeurs identiques à celles que le calcul a enregistrées : aucune règle de l'index réécrite côté navigateur.
- Une lecture : la page ne fait que des requêtes de lecture et ne déclenche aucun recalcul.

**Non-Goals :**

- Animation des marches au changement de joueur au-delà des transitions de couleur du design.
- Partage de la jointure des ratings entre la query et `RecomputePlayerStats` (voir D3).

## Decisions

### D1. Une route `/handicaps/calcul` dans `HandicapController`

`GET /handicaps/calcul`, nom `handicaps.computation`, action `HandicapController::computation`, dans le groupe protégé par la session du code d'accès comme `/handicaps`. Le choix du joueur reprend celui d'`index` (identifiant de `?joueur=` s'il nomme un joueur, sinon le premier de `PlayerStandings`), extrait dans une méthode privée partagée par les deux actions.

Alternative écartée : une sous-page `/handicaps/{player}/calcul`. Elle obligerait à un 404 pour un identifiant inconnu, alors que la page Handicaps ouvre le premier joueur ; la spec garde le même comportement sur les deux pages.

`/handicaps/calcul` ne contredit pas « Sous-adresse inconnue » (`/handicaps/<uuid>` reste 404) : la route est un segment fixe. `NavLink` marque déjà Handicaps courant pour un chemin sous `/handicaps` ; la tâche le vérifie par un test.

### D2. Une query `PlayerIndexComputation` qui renvoie les faits, `Whs` qui donne la règle

`App\Domain\Handicaps\Queries\PlayerIndexComputation::get(string $playerId)` renvoie :

```
array{
  rounds: list<array{roundId, playedOn, courseName, gross, adjusted, parTotal, ratingT: ?int, slope: ?int, differentialT, countsInCurrent}>,
  cappedHole: ?array{hole: int, par: int, strokes: int},
  meanT: ?int,
  adjustmentT: int,
}
```

- `rounds` : les 20 derniers, du plus récent au plus ancien, dans l'ordre de l'index (date de jeu, date d'enregistrement, id), en une requête : `player_round_stats` joint à `rounds`, la somme des pars en sous-requête, la jointure gauche sur `course_ratings`.
- `cappedHole` : une requête sur `scores` joint à `round_holes` pour ces rounds, `strokes > par + 4`, trié par date du round décroissante, puis `strokes − par` décroissant, puis numéro de trou ; la première ligne.
- `meanT` et `adjustmentT` : `Whs` expose la règle (`whsRule` devient publique sous le nom `retainedAndAdjustment(int $rounds)`, provisoire compris : `[n, 0]` sous 3 rounds) ; `meanT` est `Whs::roundHalfUp` (rendue publique elle aussi) de la moyenne des différentiels où `countsInCurrent`. L'index affiché reste `indexT` de `PlayerStandings` : la page ne le recalcule pas.

Alternative écartée : calculer moyenne et ajustement en TypeScript, comme le résumé de la page Handicaps (`ADJUSTMENT_T`). Deux implantations de l'arrondi finiraient par diverger ; le serveur a déjà `Whs`.

La table des 20 marches (retenus pour 1 à 20 rounds) est une constante de la page en TypeScript, écrite en littéraux de la ligne de règle : elle ne dépend pas du joueur, et le test de la page la compare à la ligne de règle.

### D3. La jointure des ratings recopiée, gardée par un test

La query reprend l'expression de jointure de `RecomputePlayerStats::history()`. Un scope Eloquent commun obligerait à toucher le recalcul, hors du périmètre. Le scénario « Rating seulement proposée » et un test qui compare, pour chaque round de la base de test, le différentiel enregistré à `Whs::differentialTenths` recalculé avec la rating et le slope que renvoie la query (par et 113 à défaut) garantissent que la page montre ce que le calcul a utilisé.

### D4. Props de la page

`handicaps/computation` reçoit `players` (`PlayerStandings`), `selectedPlayerId` et `computation` (sortie de D2, `null` sans joueur). Pas de prop différée : une requête de 20 lignes et une de quelques trous. Les onglets rechargent `only: ['selectedPlayerId', 'computation']`.

### D5. `PlayerTabs` paramétré

`PlayerTabs` reçoit `hrefFor(playerId)` et `only`, au lieu de les écrire en dur ; la page Handicaps passe ses valeurs actuelles. Le style ne change pas : les onglets non choisis restent gris, comme l'exige « Mise en page de la page Handicaps », alors que l'archive les dessine en encre sur cet écran.

### D6. Composants

Sous `resources/js/components/handicaps/computation/` : `step-heading` (numéro à la couleur du joueur, libellé, filet `rule`), `adjusted-score-step`, `differential-step`, `retained-step` (escalier et liste), `adjustment-step`, `index-step`, `special-cases`. Les sept lignes restent une constante unique (`RULE_LINES`) exportée par `computation-help.tsx`, que la page et ses tests lisent : le texte n'existe qu'à un endroit.

`computation-help.tsx` devient `ComputationLink({ playerId? })` : un `Link` Inertia « Comment l’index est calculé » vers `handicaps.computation`, avec `?joueur=` quand `playerId` est donné, en `text-link-13` gris souligné au survol, 44 px de haut. La page Handicaps passe le joueur choisi ; les trois autres pages n'en passent pas.

### D7. Tokens ajoutés

La palette reste fermée ; les valeurs du design entrent comme tokens, et `theme-tokens.test.ts` les compare aux littéraux de l'archive :

- `--color-ink-soft: oklch(0.88 0.005 260)` : texte des lignes de règle et des cas particuliers. Ajouté aux paires de `theme-contrast.test.ts` sur `bg` et `surface-1`.
- `--text-prose` (Geist 400, 15 px, interlignage 1,45) ; `--text-num-36` (Geist Mono 500, 36 px, 1, −0,04 em) ; `--text-num-20` (Geist Mono 500, 20 px, 1, −0,03 em). Ajoutés à l'extension de `tailwind-merge` dans `cn`.

Les autres textes de l'écran prennent les styles existants : `text-kpi` (30 px, étape 01), `text-num-m-17` (formule, moyennes), `text-display-xl` (index), `text-caption` et `text-caption-9` (libellés), `text-body` (cas particuliers, 14/1,5 au lieu de 14/1,45), `text-label-14` (parcours d'une ligne), `text-body-13` (phrase d'introduction).

### D8. Écarts assumés au design

- Lignes non retenues : l'archive les passe à l'opacité 0,42, ce qui met le texte sous 4,5:1. Elles prennent `ink-2` (texte) sans pastille ; la ligne retenue garde `ink` et la pastille de 8 px, au nom accessible « Retenu ».
- Pastille « SANS RATING » : la variante neutre de `Badge` (`ink-2` sur `surface-3`), celle de « Pastilles d'état » de `visual-theme`, au lieu du fond `rule` de l'archive.
- Case d'ajustement appliquée : `aria-current="true"` et un texte masqué « appliqué », en plus de la bordure à la couleur du joueur.
- Les mois abrégés (« OCT », « AOÛT », « SEPT ») sont écrits tels quels dans le DOM, comme les mots que le design change (`DESIGN.md`, « Échelle typographique ») ; le nom du parcours passe par `uppercase`.

### D9. Pas d'ADR

Aucune décision d'architecture : une page de lecture, une query, des tokens tirés du design, dans les conventions d'ADR 0013. `DESIGN.md` (tokens, échelle, composants Handicaps) suit.

## Risks / Trade-offs

- [La jointure recopiée diverge du recalcul] → test de cohérence de D3 ; une divergence le fait échouer.
- [Le trou plafonné vient d'un round plus ancien que le dernier, alors que « DERNIER ROUND » parle du dernier] → les deux cases sont séparées dans le design ; la règle de choix est écrite dans la spec. Accepté.
- [Vingt marches de 3 px d'écart à 320 px] → chaque marche garde environ 11 px ; le scénario « Escalier à 320 px » et la passe navigateur le vérifient.
- [Retrait de `<details>` des quatre pages] → leurs tests sont réécrits pour le lien, pas supprimés.

## Migration Plan

Aucune migration ni donnée. Déploiement normal de l'image. Retour arrière : redéployer l'image précédente.
