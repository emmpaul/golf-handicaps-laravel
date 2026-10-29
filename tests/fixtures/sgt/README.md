# Pages et cartes de Simulator Golf Tour

Copies reprises telles quelles de l'application Next (`golf-handicaps`, `tests/fixtures/sgt/`), où elles ont été prises le 2026-10-21 avec `curl` et le `User-Agent` de Next (changement `add-course-rating`, décision 4). Aucun test ne joint Simulator Golf Tour : `Http::fake` sert ces fichiers (design `course-rating-sgt`, D11).

Pour une nouvelle prise, utiliser le `User-Agent` de cette application (design `course-rating-sgt`, Q12), celui qu'envoie `SgtClient` :

```sh
UA='golf-handicaps-laravel (+https://github.com/emmpaul/golf-handicaps-laravel)'
curl -A "$UA" -o courses.html https://simulatorgolftour.com/courses
curl -A "$UA" -o scorecard_30.jpg 'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg?v=1.1'
```

Puis réduire `courses.html` aux 14 parcours ci-dessous, comme décrit plus bas : les tests écrivent ces parcours en littéraux.

## `courses.html`

Page `/courses` réduite à 14 parcours sur 2 740. Le bloc `<script id='courses-data' type='application/json'>` garde son texte d'origine : en-tête (`v`, `updated`, `cardUrl` avec ses `\/`, `splashUrl`, `defaultSplash`) et lignes copiées telles quelles, coordonnées à rallonge et `null` compris, dans l'ordre alphabétique de la page. Le bloc vide `courses-ratings` qui le suit sur la page est gardé, le reste du HTML (menus, scripts, modale, indicateur de chargement) est retiré, sauf les deux `div` qui entourent les blocs.

Chaque ligne vaut `[id, nom, lieu, par, difficulté, …, latitude, longitude]` (13 champs). Parcours gardés et rôle de chacun dans les tests :

| id   | Nom                            | Par | Rôle                                                                  |
| ---- | ------------------------------ | --- | --------------------------------------------------------------------- |
| 30   | Bethpage Black                 | 71  | parcours de référence (carte `IMG_3064`) : nom égal, retenu           |
| 3216 | Calanova Golf Club             | 72  | parcours de la prod ; « CALA NOVA » ne le trouve pas                  |
| 573  | Paynes Valley                  | 72  | parcours de la prod : nom égal, retenu                                |
| 574  | Paynes Valley 19th             | 3   | contient « Paynes Valley », par différent : exclu                     |
| 477  | Sanctuary                      | 72  | « THE SANCTUARY » de la prod (`the` de tête retiré)                   |
| 3291 | Sanctuary GC                   | 72  | candidat en sous-chaîne, même par                                     |
| 1873 | Sanctuary Lake                 | 71  | candidat en sous-chaîne, autre par : exclu                            |
| 1969 | Sanctuary Moon                 | 72  | sous-chaîne, latitude et longitude `null`                             |
| 1951 | The Pines Sanctuary Cove       | 72  | sous-chaîne, nom en « The » : candidat de « SANCTUARY COVE » avec 477 |
| 3466 | Tualatin Country Club          | 72  | parcours de la prod                                                   |
| 3222 | Diamond Hawk Golf Course & Pub | 72  | `&` normalisé en `and`, slug The Course View observé                  |
| 1841 | 401 Par Golf, Par 3 Course.    | 63  | difficulté `null`, ponctuation du slug                                |
| 2341 | 3 Ponds Farm                   | 71  | première ligne de la page                                             |
| 2184 | Zoar Village G.C.              | 72  | dernière ligne de la page                                             |

## `scorecard_30.jpg`

Carte SGT de Bethpage Black, telle que servie (JPEG 905x527, 103 915 octets), jointe sans réencodage à la lecture par l'IA (D4). Ligne « Slope/Rating » : Blue 77.5/155, White 74.2/148, Red 71.4/144, Junior 71.0/120, PAR3 54.0/120 ; par 71.
