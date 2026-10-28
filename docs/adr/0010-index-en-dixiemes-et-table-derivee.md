# 0010. Calcul de l'index en dixièmes entiers et table dérivée

## Statut

Acceptée, 2026-10-28 (changement OpenSpec `handicap-index`).

## Contexte

Le port calcule l'index WHS de chaque joueur, le classement net de chaque round et accepte une course rating et un slope saisis à la main par parcours et tees. Les nombres doivent être ceux de l'application Next au dixième près : l'import SQLite (`sqlite-import-command`) les comparera ligne à ligne. L'index d'un joueur dépend de tout son historique : un round ajouté à une date passée, une correction, une suppression ou une rating saisie changent les index des rounds qui suivent. Les pages lisent ces index à chaque affichage et doivent les voir justes dès la réponse de l'écriture.

## Alternatives

- **Colonnes `numeric(4,1)` et calcul en `bcmath`** : plus lisible en base, mais l'arrondi diffère de Next sur les demi-dixièmes, et l'import devrait convertir pour comparer.
- **Calcul à la lecture, sans table** : chaque page rejoue l'historique de chaque joueur, et l'index d'avant un round se recalcule à chaque affichage du classement.
- **Recalcul par un job de file après le commit** : les pages afficheraient un index faux jusqu'au passage du worker ; la spec demande le recalcul avant de répondre.
- **Recalcul incrémental du seul round écrit** : faux dès qu'un round arrive à une date passée ou qu'une rating change ; l'historique entier du joueur doit être rejoué.
- **Rating appliquée aux seuls rounds joués après sa saisie** : un même parcours donnerait deux différentiels pour un même score selon la date ; Next l'applique à tout l'historique de la paire.

## Décision

- **Calcul pur.** `App\Domain\Handicaps\Whs` (score ajusté, différentiel, index, handicap de jeu et de parcours) et `NetRanking` (rang de compétition) sont des ports un pour un de `handicap.ts` et `strokes.ts`, sur des valeurs `final readonly` (`CourseRating`, `RatedCourse`, `RoundDifferential`, `HandicapIndex`, `NetEntry`). Une règle d'architecture leur interdit `Illuminate` et `App\Models` : ils se testent sans base, avec les valeurs des tests Next.
- **Dixièmes entiers, arrondi de Next.** Différentiels, index et ratings sont stockés en dixièmes entiers (`differential_t`, `index_before_t`, `index_after_t`, `rating_t`). Chaque division flottante est suivie de `(int) floor($x + 0.5)`, la formule de `Math.floor(x + 0.5)` : 0,05 vers le haut, y compris pour un négatif. Le front reçoit les dixièmes et les formate.
- **Table dérivée `player_round_stats`.** Une ligne par joueur et par round : brut, ajusté, différentiel, index d'avant et d'après, `counts_in_current`. FK composite `(round_id, player_id)` vers `round_players` en cascade : un joueur retiré d'un round perd sa ligne, même si un recalcul était oublié. `CHECK` des index à 540 au plus.
- **Recalcul synchrone sous verrou.** `RecomputePlayerStats` rejoue l'historique complet de chaque joueur touché, dans la transaction de l'écriture (`CreateRound`, `UpdateRound`, `DeleteRound`, `SaveCourseRating`, `RemoveCourseRating`), après les contrôles de cohérence. Elle verrouille les joueurs par `lockForUpdate()` dans l'ordre des `id` : deux écritures qui touchent un même joueur se sérialisent sans interblocage.
- **Rating appliquée à tout l'historique de la paire.** `course_ratings` a une ligne par paire parcours / tees, unique sur `(lower(trim(course_name)), lower(trim(tees)))` ; la même expression sert à chaque jointure, jamais une comparaison en PHP. Saisir, corriger ou retirer une rating recalcule tous les joueurs qui ont un round sur la paire. Sans rating, le calcul prend le par du round et le slope 113.
- **Recalcul complet à chaque déploiement.** Le service `migrate` finit par `php artisan golf:recompute-stats`, qui recalcule tous les joueurs qui ont un round. La commande est idempotente ; son échec garde `web` et `worker` arrêtés, comme une migration en échec.

## Conséquences

- Une nouvelle écriture qui touche rounds, lignes, scores ou ratings doit appeler `RecomputePlayerStats` dans sa transaction ; ses tests lisent `player_round_stats` après l'écriture. La FK composite et la commande de `migrate` rattrapent un oubli au plus tard au déploiement suivant.
- Handicap de jeu et net ne sont pas stockés : ils se calculent à la lecture depuis `index_before_t` et la rating courante.
- Le staging, une restauration de dump (`docker start -a "$P-migrate-1"`) et l'import SQLite obtiennent leur historique sans étape à la main.
- La durée de `migrate` croît avec le nombre de rounds : quelques secondes pour quelques centaines, à surveiller dans `docker logs "$P-migrate-1"` au-delà de quelques milliers.
- Changer ou retirer une rating réécrit d'un coup l'historique des joueurs de la paire : c'est voulu, comme dans Next.
