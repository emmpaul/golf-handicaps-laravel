# 0014. Import unique de la base SQLite de Next

## Statut

Acceptée, 2026-10-29 (changement OpenSpec `sqlite-import-command`). Modifiée le 2026-10-29 par le changement `parallel-run-cutover` : le cutover n'est plus une bascule franche après une répétition sur le staging, mais une mise en route en parallèle sur `golf2.emmanuelpaul.com`, Next restant en production, puis un déménagement sur le domaine principal. La commande et ses décisions ne changent pas.

## Contexte

Le groupe joue sur l'application Next.js depuis des mois : joueurs, alias, rounds, photos des cartes, ratings en cours de recherche, imports et consommation IA vivent dans une base SQLite en WAL et un dossier de photos, sur le volume `golf-data`. Le cutover (décision 12 de `docs/PORT_TO_LARAVEL.md`) doit les reprendre en une fois dans PostgreSQL, sans perte, et la nouvelle application doit afficher les mêmes index que l'ancienne. `ARCHITECTURE.md` veut que toute écriture passe par une Action du domaine. Les Actions d'écriture (`CreateRound`, `AddPlayer`, `AssignAlias`…) rejouent des contrôles que Next n'avait pas tous, recalculent l'historique à chaque round et mettent une recherche SGT en file par paire parcours et tees. La base réelle a deux joueurs créés dans la même milliseconde, une distance 0 hors du `CHECK` de la cible, et les colonnes de date de la cible sont à la seconde.

## Alternatives

- **Passer par les Actions** : des rounds que Next a acceptés pourraient être refusés, le recalcul serait quadratique, et chaque round mettrait des recherches en file.
- **Copier `player_round_stats`** : rien ne prouverait que le calcul du port donne les mêmes index que Next.
- **Décaler d'une seconde les dates à égalité** pour garder l'ordre de création : on modifie une donnée pour en corriger une autre.
- **Lire `__drizzle_migrations`** pour contrôler le niveau de la base : son `hash` dépend du texte exact des fichiers de Next ; le schéma suffit et se teste.
- **Une intégration `app/Integrations/NextSqlite`** : une base que l'on quitte n'est pas un service tiers appelé par l'application, et le code écrit dans les tables du domaine.
- **Un `app.db` binaire versionné comme fixture** : une base réelle pourrait s'y glisser par erreur.

## Décision

- **Commande unique** `golf:import-sqlite`, appuyée sur un domaine `App\Domain\LegacyImport` que seule elle utilise (règle d'architecture). Elle refuse une base cible non vide, sauf `--force` confirmé dans un terminal ; `--dry-run` fait tout puis annule.
- **Insertions directes** par `DB::table()`, hors des Actions. La base reçoit les données telles que Next les a validées ; les `CHECK` et contraintes de PostgreSQL restent la garde. Seule normalisation : une distance hors de 1 à 999 devient vide.
- **Une transaction** pour toutes les tables, photos comprises : une photo écrite est effacée si la transaction échoue. Les photos des lignes vidées par `--force` sont effacées et les recherches de rating `pending` mises en file seulement après le commit.
- **UUID attribués dans l'ordre SQLite** : les UUID v7 d'une table sont générés, triés, puis attribués dans l'ordre des `id` SQLite. `order by created_at, id` rend l'ordre de Next malgré la troncature à la seconde.
- **Statistiques recalculées puis comparées** : `RecomputePlayerStats` reconstruit `player_round_stats`, comparée colonne par colonne à celle de Next. Un seul écart annule l'import et la commande liste chaque différence.
- **Connexion `legacy` déclarée à la volée, en lecture seule** (`Pdo\Sqlite::OPEN_READONLY`), contrôlée contre la liste littérale des tables et colonnes du niveau Drizzle `0004`.
- **Fixture générée** à chaque test depuis les migrations Drizzle de Next, copiées à l'octet près, avec des valeurs littérales.
- **Commande gardée** jusqu'à l'archivage du dépôt Next.

## Conséquences

- Les écritures de l'import échappent aux règles des Actions (contrôles de cohérence, alias, recherches SGT) : la comparaison des statistiques et les contraintes de la base en tiennent lieu. Toute nouvelle contrainte de la cible peut faire échouer un import qui passait ; on corrige alors la donnée dans Next.
- Un écart de calcul entre le port et Next bloque le cutover jusqu'à sa correction, dans le port ou dans Next ; il n'est jamais toléré.
- Les dates de Next perdent leurs millisecondes ; l'ordre de création, lui, est gardé par les UUID.
- La base de production a déjà les joueurs initiaux de `migrate` : le cutover passe par `--force`, en interactif (`docs/deployment.md`, « Bascule depuis Next »).
- La commande sert plusieurs fois, sur la même stack : pendant la phase parallèle sur `golf2.emmanuelpaul.com`, chaque réimport d'une copie fraîche vide la base Laravel par `--force`, puis une dernière fois au déménagement. Rien n'est saisi sur `golf2` entre deux imports (`parallel-run-cutover`).
- `pdo_sqlite` doit rester dans l'image de production tant que la commande existe.
- La commande et son domaine se retirent avec l'archivage du dépôt Next, par un changement OpenSpec.
