# 0009. Modèle des rounds et contrôles de cohérence

## Statut

Acceptée, 2026-10-28 (changement OpenSpec `players-and-rounds`).

## Contexte

Le port Laravel reçoit ses premières données métier : les joueurs du groupe et leurs alias, les rounds joués au simulateur et leurs scores. Tout le reste du port s'appuie sur ce modèle : le calcul des index WHS (`handicap-index`) lit les scores par trou, l'import par l'IA (`scorecard-ai-import`) rattache un import à un round, l'import SQLite (`sqlite-import-command`) remappe les tables de Next une à une. Le modèle doit les servir sans changer.

Une carte se saisit à la main dans un éditeur qui signale ses problèmes à chaque frappe, souvent sur un téléphone au simulateur. Le serveur ne peut pas faire confiance à l'éditeur : une requête écrite hors de l'éditeur doit être refusée avec les mêmes messages. Enfin, un round peut avoir la photo de sa carte, qui montre les noms et scores du groupe et ne doit être lisible qu'avec le code d'accès (ADR 0007).

## Alternatives

- **18 scores dans une colonne `smallint[]` de `round_players`** : moins de lignes, mais les requêtes WHS, l'importeur et les contraintes par trou deviennent plus durs. Le volume (quelques centaines de rounds) ne le justifie pas.
- **Invariants dans le code seulement** : une Action oubliée ou un import écrirait une carte incohérente sans que la base le voie.
- **Contrôles au serveur seulement, par Precognition à chaque frappe** : un aller-retour par case sur le téléphone, et l'éditeur cesse de signaler les problèmes le temps d'une coupure.
- **Contrôles dans l'éditeur seulement** : une requête hors éditeur écrirait une carte incohérente.
- **Photos sur le disque `local`** : Laravel le sert sous `storage/{path}` par URL signée, hors de la session du code d'accès.

## Décision

- **Six tables en UUID v7, invariants en contraintes PostgreSQL.** `players` (index unique sur `lower(name)`), `player_aliases` (FK en cascade, index unique sur `lower(alias)`), `rounds` (`course_name`, `tees`, `played_on` en `date`, `photo_path` nullable), `round_holes` (uniques `(round_id, hole)` et `(round_id, stroke_index)`, `CHECK` sur trou, par, index et distance), `round_players` (FK `players` en `restrict`, uniques `(round_id, player_id)` et `(round_id, position)`, `CHECK` `position` 0–3), `scores` (FK composite `(round_id, player_id)` vers `round_players` en cascade, unique `(round_id, player_id, hole)`, `CHECK` `strokes` 1–15). Chaque table a son modèle et sa fabrique.
- **Les `CHECK` sont une seconde ligne.** Les contrôles refusent avant, avec un message. Une `QueryException` sur un `CHECK` est un bug, pas une erreur métier.
- **La carte est une valeur du domaine.** `App\Domain\Rounds\RoundCard` porte parcours, tees, date, 18 trous et 1 à 4 lignes, chaque case pouvant être vide. `RoundCardChecks::problems()` en donne les problèmes (`Problem { message, cells }`) avec les messages de la spec et les clés de case de Next (`par:3`, `player0:player`…).
- **Contrôles doubles.** L'éditeur les calcule à chaque frappe (`resources/js/lib/round-checks.ts`). `CreateRound` et `UpdateRound` les rejouent dans leur transaction et lèvent `IncoherentRoundCard` avant toute écriture. Le Form Request ne valide que la forme de la requête.
- **Une table de cas partagée.** `tests/fixtures/round-checks.json` liste des cartes et les problèmes attendus, écrits à la main. `tests/Unit/Rounds/RoundCardChecksTest.php` et `resources/js/lib/round-checks.test.ts` la parcourent tous deux : un écart entre PHP et TypeScript fait rougir l'un des deux.
- **Photos sur un disque `photos` non servi.** Driver `local`, racine `storage/app/photos`, sous le volume `storage` de la stack mais hors de la racine du disque `local`. `photo_path` est relatif à ce disque. Seule la route `rounds.photo`, derrière la session, la sert. `DeleteRound` efface le fichier après le commit.

## Conséquences

- Le calcul d'index (`player_round_stats`) se rattache par `round_id` et `player_id`, l'import par `round_id`, sans changer ces tables.
- Une correction remplace trous, lignes et scores du round : leurs `id` changent à chaque correction. Aucune table ne doit les référencer.
- Un contrôle ajouté ou modifié s'écrit deux fois, en PHP et en TypeScript, avec ses cas dans la table partagée.
- Un joueur qui a joué un round ne peut pas être supprimé (FK en `restrict`) ; la suppression d'un joueur reste hors du port.
- Un échec du disque après le commit d'une suppression laisse un fichier orphelin, jamais servi.
- Le service `backup` ne sauvegarde que la base (`pg_dump`) : les photos du volume `storage` n'y sont pas. Elles arrivent avec l'import (`scorecard-ai-import`), qui devra trancher leur sauvegarde.
