# Port vers Laravel + Inertia + React

Plan du passage de l'application Next.js actuelle à une application Laravel 13 + Inertia 3 + React 19 montée selon `/Users/epaul/www/skeletons/SKELETON.md`. Les décisions ont été tranchées le 2026-10-27 lors d'une séance de questions. Ce document sert de brief aux agents qui écriront les proposals OpenSpec.

## Décisions

| #   | Sujet                | Décision                                                                                                                                                                                                                                                                                                                                                           |
| --- | -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1   | Repo                 | Nouveau repo (`golf-handicaps-laravel`), bootstrappé selon SKELETON. Les specs OpenSpec actuelles servent de référence fonctionnelle. L'ancien repo reste déployable jusqu'au cutover, puis il est archivé.                                                                                                                                                        |
| 2   | OCR                  | **Supprimé.** Plus de tesseract, sharp, détection de panneau, homographie ni templates. La photo entière part à l'IA via le **Laravel AI SDK**, qui rend le brouillon en sortie structurée.                                                                                                                                                                        |
| 3   | Flux d'import        | Photo → job de file → IA → écran de correction (autosave conservé). L'écran `/recadrer`, les coins, `corners_source` et `ai_cells` disparaissent.                                                                                                                                                                                                                  |
| 4   | Budget IA            | Plafond mensuel gardé, défaut **5 $** (`AI_MONTHLY_BUDGET_USD`), table `ai_usage` gardée. Au-delà du plafond, l'import échoue avec « Budget IA du mois atteint » et la carte se saisit à la main. Modèle configurable par env.                                                                                                                                     |
| 5   | Auth                 | Code d'accès partagé (`APP_PASSCODE`) + session Laravel, même UX `/login`. Pas de comptes.                                                                                                                                                                                                                                                                         |
| 6   | Identifiants         | UUID partout, comme dans le squelette. L'importeur génère les UUID et garde la correspondance en mémoire. Les anciennes URL (`/rounds/12`) cassent, ce qui est accepté.                                                                                                                                                                                            |
| 7   | Front                | Port de l'UI actuelle : thème scoreboard, tokens, composants base-ui/shadcn, recharts. Pages adaptées à Inertia (props serveur, `useForm`, Wayfinder). Le starter kit est nettoyé. recharts n'est pas ajouté par `home-and-match-strokes` (DP3). Remplacée pour le thème et les tokens par `visual-redesign`, ADR 0013 ; les primitives base-ui restent. |
| 8   | Tests                | **Pas de Playwright, ni de tests navigateur.** Pest pour le back, Vitest pour le front. La passe navigateur de fin d'apply (`browser-use`) reste un outil d'agent, pas une suite de tests.                                                                                                                                                                         |
| 9   | Photos               | Disque Laravel local sur un volume nommé. Conversion WebP via `intervention/image` (driver Imagick). Les photos importées, déjà en WebP, sont copiées telles quelles.                                                                                                                                                                                              |
| 10  | Déploiement          | Une stack **docker-compose** Dokploy. Image unique publiée sur GHCR par la CI. Services `web`, `worker` (`queue:work`), `migrate` (one-shot) et `pgsql` 18 avec volume.                                                                                                                                                                                            |
| 11  | Import SQLite        | Commande artisan one-shot stricte, avec vérification (voir plus bas).                                                                                                                                                                                                                                                                                              |
| 12  | Cutover              | Fenêtre courte, même domaine, après répétition sur staging. L'ancien volume est gardé 30 jours pour le retour arrière.                                                                                                                                                                                                                                             |
| 13  | Couches sautées      | Fortify (retiré du kit), access-control (spatie/permission, `/admin`), audit-trail (activitylog), Mailpit/SMTP.                                                                                                                                                                                                                                                    |
| 14  | Découpage            | 11 changements OpenSpec, plus le bootstrap hors OpenSpec.                                                                                                                                                                                                                                                                                                          |
| 15  | Ordre du déploiement | Tôt : le changement de déploiement vient juste après app-access. Staging Dokploy dès la première page protégée.                                                                                                                                                                                                                                                    |

## Écarts au squelette (ADR 0005)

- **Pas de Fortify, de comptes ni de rôles** : un code partagé suffit pour un groupe d'amis. Les étapes 9 (`access-control`) et 12 (`audit-trail`) du bootstrap sont sautées. La couche 2.7 (sécurité des requêtes) est fusionnée dans `app-access`.
- **Pas de Mailpit** : `compose.yaml` de dev avec `laravel.test` + `pgsql` seulement.
- **Worker de file présent dès le départ** : l'import IA et la recherche SGT l'exigent. Sessions, cache et files restent en base, sans Redis.
- **Pas de tests navigateur** : `make check` = Pint, Larastan, Pest, lint/typecheck TS, Vitest, jscpd.
- **Couche 12 remplie** par ce projet, après le premier déploiement réel.

## Changements OpenSpec

Chaque changement suit le squelette : deux PR (code, puis archivage), `make check` vert. Les specs actuelles (`openspec/specs/*` du repo Next) sont la référence de comportement. Chaque changement reprend leurs requirements comme deltas, sauf ce que les décisions ci-dessus retirent.

### 0. Bootstrap (hors OpenSpec)

Étapes 1 à 5 du squelette : `laravel new` avec le kit React, Sail `--with=pgsql`, PHP 8.5, OpenSpec épinglé, Boost (`claude_code`), commitlint, simple-git-hooks. Retirer Fortify et `laravel/chisel` du kit.

### 1. `project-skeleton-and-quality-gates`

Étape 6 du squelette, sans Playwright : Makefile, Pint, Larastan 8, Pest sur PostgreSQL, Vitest, jscpd, tests d'architecture, UUID, fuseau (`app.display_timezone` = `America/Montreal`), CI GitHub Actions, route de version (`app-version`), `version.txt` et `CHANGELOG.md` tenus à la main.

- Capacités : `quality-gates`, `local-development-environment`, `persistence-identifiers`, `date-time-presentation`, `app-version`, `ci-pipeline`.

### 2. `visual-theme-port`

Étape 7 du squelette, version « port » : tokens clairs et sombres du thème scoreboard, polices, primitives `components/ui`, shell de l'app (`app-shell`), retours de chargement (`loading-feedback`). Tests Vitest de contraste et de composants repris.

- Capacités : `visual-theme`, `app-shell`, `loading-feedback`.

### 3. `app-access`

Middleware du code partagé, `/login`, déconnexion, session chiffrée de 90 jours, limiteur sur `/login`, en-têtes de sécurité, `TRUSTED_PROXIES`. Une page protégée vide pour le déploiement.

- Capacités : `app-access`, `web-request-security`.

### 4. `production-deployment`

Dockerfile multi-étapes (FrankenPHP, assets compilés, `composer install --no-dev`, caches Laravel), `docker-compose.yml` de prod, publication GHCR par la CI à chaque release, healthcheck `/up` pour web et healthcheck du worker, migrations dans le service `migrate`, sauvegardes Postgres, doc `docs/deployment.md`. Stack staging sur Dokploy.

- Capacité : `deployment`. Remplit la couche 12.

### 5. `players-and-rounds`

Joueurs et alias (unicité insensible à la casse), rounds, trous, joueurs du round, scores, saisie manuelle, édition, suppression, photo de round. `DemoSeeder` et cibles `migrate` / `fresh` / `demo` (étape 8 du squelette). `INITIAL_PLAYERS` repris.

- Capacités : `players`, `rounds`, `development-seed-data`.

### 6. `handicap-index`

Calcul WHS porté depuis `src/domain` : brut, ajusté, différentiel, index avant/après, rounds qui comptent. Table dérivée `player_round_stats`, recalculée après chaque écriture. Ratings et slopes saisis à la main sur `/courses`. Les tests du domaine sont portés en Pest avec les mêmes valeurs attendues.

- Capacités : `handicap-index`, `course-ratings` (partie manuelle).

### 8. `scorecard-ai-import`

Envoi de la photo, conversion WebP, job de file, lecture IA de la photo entière via le Laravel AI SDK en sortie structurée (même forme de brouillon que `src/scorecard/draft`), contrôles du brouillon, écran de correction avec autosave, création du round. Budget mensuel et `ai_usage`. Statuts `queued` / `reading` / `ready` / `failed`. Sans clé API, l'import est désactivé et la saisie se fait à la main.

- Capacités : `scorecard-import`, `scorecard-ai-completion` (réécrite : l'IA lit tout au lieu de compléter). La capacité `scorecard-panel-detection` est retirée.

### 7. `home-and-match-strokes`

Après 8 (`ROADMAP.md`). Tableau de bord d'accueil, dont le bloc « Imports à vérifier » sur la table `imports` de 8, page des handicaps (liste des différentiels ; le graphique arrive avec `visual-redesign`), coups rendus en match.

- Capacités : `home-dashboard`, `match-strokes`.

### 9. `course-rating-sgt`

Recherche SGT par le worker : liste des parcours (cache 24 h), image de carte, lecture IA des tees, statuts `pending` → `confirmed`, confirmation et recalcul de l'historique. `SGT_LOOKUP=on|off`.

- Capacités : `course-ratings` (partie SGT), `worker-health`.

### 10. `sqlite-import-command`

Commande `php artisan golf:import-sqlite {db} {--uploads=} {--force} {--dry-run}` (contrat plus bas).

- Capacité : `legacy-import`.

Puis le **cutover** (procédure plus bas), et un archivage final du repo Next.

## Commande d'import SQLite

```
php artisan golf:import-sqlite /chemin/app.db --uploads=/chemin/uploads [--force] [--dry-run]
```

- Ouvre le fichier SQLite en lecture seule (connexion `legacy` déclarée à la volée).
- Refuse si une table métier Postgres contient déjà des lignes. `--force` les vide d'abord, après confirmation interactive.
- Fait tout dans **une seule transaction** Postgres. Une erreur annule l'import entier.
- `--dry-run` fait tout puis annule, et affiche le rapport.

Correspondance des tables :

| SQLite               | Postgres         | Traitement                                                                                                                                                          |
| -------------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `players`            | `players`        | Nouvel UUID, `created_at` ms → `timestamptz` UTC.                                                                                                                   |
| `player_aliases`     | `player_aliases` | FK remappée.                                                                                                                                                        |
| `rounds`             | `rounds`         | Nouvel UUID, `played_on` → `date`, photo copiée.                                                                                                                    |
| `round_holes`        | `round_holes`    | FK remappée.                                                                                                                                                        |
| `round_players`      | `round_players`  | FK remappées.                                                                                                                                                       |
| `scores`             | `scores`         | FK remappées.                                                                                                                                                       |
| `player_round_stats` | —                | **Non copiée** : recalculée par le code PHP, puis comparée ligne à ligne aux valeurs SQLite. Un écart fait échouer l'import.                                        |
| `course_ratings`     | `course_ratings` | Copiée. Les statuts non terminaux (`pending`) sont remis en file après le commit.                                                                                   |
| `imports`            | `imports`        | `draft` et `correction` copiés. `corners`, `corners_source` et `ai_cells` abandonnés. `queued` / `reading` → `failed`, note « Import interrompu par la migration ». |
| `ai_usage`           | `ai_usage`       | Copiée (le budget du mois en cours reste juste).                                                                                                                    |
| `jobs`               | —                | Ignorée. La file Next est vidée avant le cutover.                                                                                                                   |

Photos : chaque `photo_path` (`uploads/<uuid>.webp`) est copié vers le disque Laravel. Une photo qui n'est pas en WebP est convertie. Une photo absente fait échouer l'import et la commande liste les chemins manquants.

Rapport final : nombre de lignes par table (source → cible), photos copiées et nombre de stats comparées identiques.

Tests Pest : une fixture `app.db` construite à la main (schéma Drizzle 0000 → 0004 appliqué, quelques rounds dont les stats sont connues) importée, puis assertions sur les comptes, les UUID remappés, le recalcul identique, le refus sur une base non vide et le rollback sur une photo manquante.

## Topologie de production

```
docker-compose.yml (Dokploy)
├── migrate  image GHCR, `php artisan migrate --force`, restart: no
├── web      image GHCR, FrankenPHP :8080, healthcheck /up, depends_on migrate (completed)
├── worker   image GHCR, `php artisan queue:work --tries=2`, depends_on migrate (completed)
└── pgsql    postgres:18-alpine, volume pgdata, healthcheck pg_isready
volumes: pgdata, storage (photos, monté sur migrate, web et worker)
```

Les variables vivent dans l'environnement Dokploy : `APP_KEY`, `APP_PASSCODE`, `ANTHROPIC_API_KEY`, `AI_MONTHLY_BUDGET_USD`, `AI_BUDGET_TIME_ZONE`, `SGT_LOOKUP`, `INITIAL_PLAYERS`, `TRUSTED_PROXIES`, `DB_*`. Aucun secret n'est dans l'image.

## Cutover

1. Répétition sur staging : copier `app.db` et `uploads/` de la prod, lancer `golf:import-sqlite`, comparer à l'œil l'accueil, les handicaps et quelques rounds avec la prod.
2. Le jour J : arrêter `golf-web`, attendre que la file du worker soit vide, arrêter `golf-worker`.
3. Copier `app.db` et `uploads/` depuis le volume `golf-data`.
4. Lancer `golf:import-sqlite` dans le conteneur `web` de la stack Laravel et lire le rapport.
5. Basculer le domaine sur la stack Laravel et vérifier `/login`, l'accueil et les handicaps.
6. Garder le volume `golf-data` et les deux applications Dokploy arrêtées pendant 30 jours. Pour revenir en arrière, rebasculer le domaine et redémarrer les deux applications.

## Défauts retenus sans question (à contester au besoin)

- Modèle IA par défaut : le Sonnet le plus récent, par `AI_MODEL`. Le prix par modèle est gardé dans une table de config pour `ai_usage`.
- Healthcheck du worker : un battement écrit dans `/tmp` à chaque job et à chaque tour de boucle (`Queue::looping`), seuil de 5 min, comme aujourd'hui.
- Versionnement : version tenue à la main dans `version.txt`, `CHANGELOG.md` en anglais complété par chaque pull request de code, tags `vX.Y.Z` posés par le mainteneur. Pas de release-please. Conventional Commits restent vérifiés par commitlint (ADR 0005).
- Prose, specs et UI en français (décision 8 du squelette).
