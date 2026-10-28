# 0002. PostgreSQL et UUID

## Statut

Acceptée, 2026-10-27 (changement OpenSpec `project-skeleton-and-quality-gates`).

## Contexte

L'application Next.js d'origine stocke ses données dans SQLite avec des identifiants entiers. Une adresse comme `/rounds/12` révèle le nombre de parties et leur ordre de création. Le port Laravel part sur une base neuve, sans donnée réelle : c'est le seul moment où le type des identifiants change sans migration de données (couche 2.2 du squelette, décision 6 de `docs/PORT_TO_LARAVEL.md`).

Le kit React de Laravel crée aussi des tables de comptes (`users`, `password_reset_tokens`) et lie chaque session à une personne par `sessions.user_id`. L'application n'a pas de comptes : l'accès repose sur un code partagé.

## Alternatives

- **SQLite en test, PostgreSQL en production** : plus rapide à démarrer, mais les types, les contraintes et le planificateur diffèrent. Un test vert ne prouverait rien sur la base de production.
- **Entiers auto-incrémentés** : simples et compacts, mais visibles dans les adresses.
- **UUID v4** : opaques, mais aléatoires. Les index B-tree se fragmentent à chaque insertion.
- **ULID en texte** : ordonnés, mais stockés sur 26 caractères au lieu de 16 octets.
- **Entier interne et UUID public** : deux identifiants à garder cohérents dans chaque requête, chaque relation et chaque route.
- **Garder `users` en UUID**, comme le squelette : une table morte, que rien n'écrit.
- **Garder `sessions.user_id` seul** : une colonne morte, qui suggère un lien entre une session et une personne.

## Décision

- PostgreSQL partout : en local sous Sail, dans les tests (`DB_CONNECTION=pgsql` dans `phpunit.xml`, base `testing`) et en CI.
- Chaque modèle de `App\Models` utilise `HasUuids` : clé primaire `uuid` native, UUID version 7 générés par l'application, ordonnés dans le temps. Chaque clé étrangère applicative est un `foreignUuid` avec contrainte.
- Un test d'architecture l'impose : `make arch-test` échoue en nommant un modèle sans `HasUuids`.
- Les tables techniques du framework (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) gardent les identifiants que le framework leur donne.
- Aucune table de comptes. La migration initiale du kit est réécrite en place et ne crée plus que `sessions`, sans `user_id`. `App\Models\User` et sa fabrique sont supprimés.
- Le pilote de session `database` est remplacé par `App\Http\Session\AnonymousDatabaseSessionHandler`, enregistré dans `AppServiceProvider`. Cette sous-classe du gestionnaire du framework ne surcharge qu'`addUserInformation()` : le framework écrit `user_id` dès que le contrat `Guard` est lié, ce qui est toujours le cas.

## Conséquences

- Une adresse ne révèle ni le volume ni l'ordre de création. Les anciennes adresses de l'application Next.js (`/rounds/12`) cassent, ce qui est accepté.
- Les index restent compacts et peu fragmentés grâce à l'ordre des UUID v7.
- La règle d'architecture passe à vide tant qu'`App\Models` est vide. Elle a été vérifiée par mutation pendant l'apply, et le premier modèle (`players-and-rounds`) la rend effective.
- Une fois des données réelles en base, réécrire une migration initiale n'est plus permis : un changement de type d'identifiant demandera une migration de données explicite.
- En local, la migration initiale ayant changé, `make migrate` ne suffit pas : `vendor/bin/sail artisan migrate:fresh` une fois.
- Le gestionnaire de session diverge du framework sur une méthode. Il se relit à chaque version majeure de Laravel ; `tests/Feature/SessionStorageTest.php` écrit une vraie session en base et devient rouge si le remplacement disparaît.
- La configuration effective de `auth` garde le provider `users` et les réglages `passwords` par défaut du framework, fusionnés depuis sa propre configuration. Le garde `web` reste résoluble, mais rien ne charge de personne.
