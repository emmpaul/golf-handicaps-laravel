# local-development-environment Specification

## Purpose
Permettre à une personne ou à un agent de faire tourner l'application sur un poste avec Docker, `make` et Node seulement, dans un environnement identique d'un poste à l'autre, avec PostgreSQL, le rechargement à chaud et un navigateur jetable pour la vérification visuelle.

## Requirements

### Requirement: Installation sur un clone neuf
`make setup` SHALL rendre un clone neuf utilisable sans PHP, Composer ni PNPM sur l'hôte : `.env` créé depuis `.env.example` s'il manque, dépendances PHP et JS installées depuis les lockfiles, clé d'application générée si vide, assets compilés, consignes d'agent régénérées, migrations appliquées. Il MUST NOT écraser un `.env` existant.

#### Scenario: Premier lancement
- **GIVEN** un clone neuf, sans `vendor/`, `node_modules/` ni `.env`, sur un hôte qui a Docker et `make`
- **WHEN** on lance `make setup`
- **THEN** la commande finit en code 0, `http://localhost` répond 200 et `make check` peut se lancer

#### Scenario: Fichier d'environnement déjà présent
- **GIVEN** un `.env` avec `APP_KEY` déjà rempli
- **WHEN** on relance `make setup`
- **THEN** `.env` et sa clé restent inchangés

### Requirement: Services de développement
L'environnement local SHALL comprendre deux services seulement : l'application (PHP 8.5) et PostgreSQL 18. Il MUST NOT démarrer de serveur de courriel, de Redis ni de worker. `make up` démarre les services et attend qu'ils soient sains ; `make down` les arrête en gardant les données.

#### Scenario: Démarrage
- **WHEN** on lance `make up`
- **THEN** la commande rend la main une fois PostgreSQL sain, et `vendor/bin/sail ps` liste exactement `laravel.test` et `pgsql`

#### Scenario: Données conservées
- **GIVEN** la base de développement contient des lignes
- **WHEN** on lance `make down` puis `make up`
- **THEN** les lignes sont toujours là

### Requirement: Port PostgreSQL de l'hôte
PostgreSQL SHALL être joignable depuis l'hôte sur le port `FORWARD_DB_PORT`, `5433` par défaut, pour ne pas entrer en conflit avec un PostgreSQL local. Depuis l'application, la base reste `pgsql:5432`.

#### Scenario: PostgreSQL de l'hôte déjà lancé
- **GIVEN** un PostgreSQL de l'hôte écoute déjà sur 5432
- **WHEN** on lance `make up` avec le `.env.example` par défaut
- **THEN** les deux services démarrent et l'application lit sa base

### Requirement: Base de test séparée
Le service PostgreSQL SHALL créer une base `testing` à l'initialisation de son volume, distincte de la base de développement, pour la suite backend.

#### Scenario: Volume neuf
- **WHEN** le volume PostgreSQL est créé par `make up`
- **THEN** les bases `laravel` et `testing` existent

### Requirement: Migrations sans perte
`make migrate` SHALL appliquer les migrations en attente sans effacer de table ni écrire de donnée.

#### Scenario: Migration en attente
- **GIVEN** une base de développement avec des lignes et une migration en attente qui ajoute une table
- **WHEN** on lance `make migrate`
- **THEN** la nouvelle table existe et les lignes existantes sont intactes

### Requirement: Rechargement à chaud et arrêt propre
`make dev` SHALL lancer Vite avec rechargement à chaud dans le conteneur. `make dev-stop` SHALL arrêter ce Vite, supprimer `public/hot` et fermer le navigateur lancé par `make browser`, sans arrêter un autre processus du conteneur ni un navigateur de l'utilisateur.

#### Scenario: Arrêt pendant un test frontend
- **GIVEN** `make dev` tourne et `make test-frontend` tourne dans le même conteneur
- **WHEN** on lance `make dev-stop`
- **THEN** Vite s'arrête, `public/hot` n'existe plus, et `make test-frontend` va à son terme

#### Scenario: Rien à arrêter
- **WHEN** on lance `make dev-stop` sans Vite ni navigateur lancés
- **THEN** la commande sort en code 0

### Requirement: Assets périmés
`make build` SHALL compiler les assets. Quand `public/hot` existe mais que son serveur Vite ne répond pas, il MUST supprimer `public/hot` avant de compiler.

#### Scenario: Vite interrompu
- **GIVEN** un `make dev` interrompu a laissé `public/hot`
- **WHEN** on lance `make build`
- **THEN** la sortie affiche « public/hot supprimé » et la page d'accueil charge les assets compilés

### Requirement: Navigateur jetable
`make browser` SHALL lancer sur l'hôte un Chrome sans interface, avec un profil propre sous `reports/browser/`, exposé sur le port de débogage `CDP_PORT` (9333 par défaut), et afficher `BU_CDP_URL=http://127.0.0.1:9333`. Si ce navigateur répond déjà, il affiche l'URL sans en lancer un second.

#### Scenario: Premier lancement
- **WHEN** on lance `make browser` sur un hôte qui a Chrome
- **THEN** la sortie se termine par `BU_CDP_URL=http://127.0.0.1:9333` et `http://127.0.0.1:9333/json/version` répond

#### Scenario: Navigateur déjà lancé
- **GIVEN** `make browser` a déjà été lancé
- **WHEN** on le relance
- **THEN** il affiche la même URL et un seul processus Chrome écoute sur 9333

#### Scenario: Chrome absent
- **WHEN** aucun binaire Chrome ou Chromium n'est trouvé
- **THEN** la commande échoue avec un message qui demande de régler `CHROME`

### Requirement: CLI OpenSpec épinglée
`make openspec ARGS="…"` SHALL lancer la version de la CLI OpenSpec verrouillée dans `pnpm-lock.yaml`, dans le conteneur, sans installation globale.

#### Scenario: Validation d'un changement
- **WHEN** on lance `make openspec ARGS="validate --all --strict"`
- **THEN** la CLI 1.14.1 du projet valide les changements et specs

### Requirement: Hook de commit sur l'hôte
Le hook `commit-msg` SHALL vérifier chaque message de commit local avec le Node de l'hôte (22.12 ou plus récent) et les dépendances installées dans le conteneur. Sans Node sur l'hôte, le commit MUST être refusé.

#### Scenario: Message non conforme
- **WHEN** on commit avec le message `Fix stuff.`
- **THEN** git refuse le commit et affiche les règles commitlint enfreintes
