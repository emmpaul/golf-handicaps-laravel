# Base SQLite de Next pour les tests d'import

`drizzle/0000_init.sql` à `drizzle/0004_course_ratings.sql` sont les migrations Drizzle de l'application Next (`golf-handicaps`, dossier `drizzle/`), copiées à l'octet près. Elles ne contiennent que le schéma, aucune donnée.

`tests/Support/LegacySqlite/LegacySqliteFixture` les applique à chaque test sur un fichier temporaire, jusqu'au niveau demandé, puis y écrit des lignes inventées.

Aucune base réelle n'entre dans ce dossier, ni ailleurs dans le dépôt : ni `app.db`, ni copie de sauvegarde, ni photo de l'application Next. La sauvegarde réelle reste dans `backups/`, ignoré par git.

Ne pas reformater ni modifier les fichiers SQL : un `shasum -a 256` doit rendre les empreintes des fichiers de Next.
