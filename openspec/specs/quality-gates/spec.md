# quality-gates Specification

## Purpose
Garantir que chaque contrôle de qualité du projet (formatage, analyse statique, duplication, tests, architecture, messages de commit) se lance par une seule interface, la même en local et en CI, sans modifier les fichiers vérifiés.

## Requirements

### Requirement: Interface de commandes unique
Le projet SHALL exposer chaque contrôle de qualité et chaque opération courante de développement par une cible du Makefile racine. `make help` MUST lister chaque cible avec une description en français, groupée par section. Sans cible donnée, `make` affiche cette aide.

#### Scenario: Aide par défaut
- **WHEN** on lance `make` sans argument
- **THEN** la sortie liste au moins `setup`, `up`, `down`, `dev`, `dev-stop`, `browser`, `build`, `migrate`, `openspec`, `lint`, `format`, `analyse`, `duplicates`, `arch-test`, `test-backend`, `test-frontend`, `test`, `update-shards`, `check`, `commitlint` et `changed-code`, chacune suivie de sa description en français

#### Scenario: Cible sans description
- **WHEN** une cible publique du Makefile n'a pas de description `##`
- **THEN** un test du projet échoue en nommant cette cible

### Requirement: Même cible en local et en CI
Chaque cible SHALL lancer ses commandes dans le conteneur Sail en local et directement sur la machine quand la variable `CI` vaut `true`, sans autre différence de recette.

#### Scenario: Exécution locale
- **WHEN** on lance `make analyse` sur le poste, sans `CI`
- **THEN** PHPStan tourne dans le conteneur `laravel.test`

#### Scenario: Exécution en CI
- **WHEN** la CI lance `make analyse` avec `CI=true`
- **THEN** PHPStan tourne directement sur le runner, sans appel à Sail

### Requirement: Contrôles non mutants
Les cibles de contrôle (`lint`, `analyse`, `duplicates`, `arch-test`, `test-backend`, `test-frontend`, `test`, `check`, `commitlint`, `changed-code`) MUST NOT modifier un fichier suivi par git. Seules `format`, `setup` et `update-shards` écrivent des fichiers suivis.

#### Scenario: Fichier mal formaté
- **WHEN** un fichier PHP enfreint le preset Pint et qu'on lance `make lint`
- **THEN** la cible échoue en nommant le fichier, et `git status` ne montre aucune modification

#### Scenario: Correction du formatage
- **WHEN** on lance `make format` sur ce même fichier
- **THEN** le fichier est reformaté et `make lint` passe ensuite

### Requirement: Lint complet malgré un premier échec
`make lint` SHALL lancer le contrôle PHP et le contrôle TypeScript (formatage, lint typé, types) même quand le premier échoue, et échouer si l'un des deux échoue.

#### Scenario: PHP et TypeScript fautifs
- **WHEN** un fichier PHP et un fichier TSX sont mal formatés et qu'on lance `make lint`
- **THEN** la sortie nomme les deux fichiers et la cible finit en échec

### Requirement: Vérification complète en une passe
`make check` SHALL lancer, dans cet ordre, `lint`, `analyse`, `duplicates`, `test-backend` et `test-frontend`, chacune jusqu'au bout même après un échec. Il MUST afficher une ligne par étape, écrire le log complet de chacune dans `reports/check/<étape>.log`, afficher la fin du log de chaque étape en échec, et finir en échec si une étape échoue.

#### Scenario: Tout est vert
- **WHEN** aucune étape n'échoue
- **THEN** `make check` affiche cinq lignes `✓ <étape>` et sort en code 0

#### Scenario: Une étape rouge au milieu
- **WHEN** `duplicates` trouve un bloc dupliqué
- **THEN** `make check` lance quand même `test-backend` et `test-frontend`, affiche `✗ duplicates → reports/check/duplicates.log` suivi des dernières lignes de ce log, et sort en code non nul

#### Scenario: Rapports ignorés par git
- **WHEN** `make check` a écrit `reports/check/`
- **THEN** `git status` n'affiche pas `reports/`

### Requirement: Tests backend visés
`make test-backend` SHALL lancer toute la suite Pest en parallèle. Avec `FILES=<chemins>` ou `FILTER=<motif>`, il MUST lancer seulement les tests visés, dans un seul processus. `JUNIT=<chemin>` écrit un rapport JUnit.

#### Scenario: Un fichier visé
- **WHEN** on lance `make test-backend FILES=tests/Unit/LocalTimeTest.php`
- **THEN** seuls les tests de ce fichier tournent, sans Paratest

#### Scenario: Rapport JUnit
- **WHEN** on lance `make test-backend JUNIT=reports/backend.xml`
- **THEN** le fichier `reports/backend.xml` existe à la fin et liste chaque test lancé

### Requirement: Partitions de la suite backend
`make test-backend SHARD=<i>/<n>` SHALL lancer seulement la partition `i` de la suite parmi `n`. `SHARD` combiné avec `FILES` ou `FILTER` MUST échouer en code 2 avec un message, sans lancer de test.

#### Scenario: Partition demandée
- **WHEN** on lance `make test-backend SHARD=1/2` puis `make test-backend SHARD=2/2`
- **THEN** chaque classe de test tourne dans une et une seule des deux exécutions

#### Scenario: Partition et fichier combinés
- **WHEN** on lance `make test-backend SHARD=1/4 FILES=tests/Unit/LocalTimeTest.php`
- **THEN** la cible affiche « SHARD ne se combine pas avec FILES ou FILTER » et sort en code 2

### Requirement: Tests sur PostgreSQL
La suite backend SHALL tourner sur PostgreSQL, dans une base `testing` distincte de la base de développement. Elle MUST NOT tourner sur SQLite.

#### Scenario: Base de développement intacte
- **WHEN** la base de développement contient des lignes et qu'on lance `make test-backend`
- **THEN** ces lignes sont toujours présentes après les tests

### Requirement: Tests frontend
`make test-frontend` SHALL lancer les tests Vitest du dossier `resources/js` sous le fuseau `America/Montreal`, quel que soit le fuseau du poste ou du runner. `FILES=` et `JUNIT=` se comportent comme pour le backend.

#### Scenario: Poste réglé sur un autre fuseau
- **WHEN** le poste est réglé sur `Europe/Paris` et qu'on lance `make test-frontend`
- **THEN** les tests lisent l'instant `2026-11-02T04:30:00Z` à 23 h 30, heure de Montréal

### Requirement: Analyse statique et duplication
`make analyse` SHALL lancer Larastan au niveau 8 sur `app/`, `bootstrap/app.php`, `config/`, `database/` et `routes/`. `make duplicates` SHALL échouer dès qu'un bloc dupliqué est trouvé dans le code PHP ou TypeScript du projet (seuil 0), hors exclusions nommées.

#### Scenario: Bloc dupliqué
- **WHEN** deux fonctions TypeScript de `resources/js` partagent plus de 70 jetons identiques
- **THEN** `make duplicates` échoue en nommant les deux emplacements

#### Scenario: Erreur de type PHP
- **WHEN** une méthode de `app/` renvoie `?string` alors que sa signature déclare `string`
- **THEN** `make analyse` échoue en nommant le fichier et la ligne

### Requirement: Règles d'architecture
Une suite de tests d'architecture SHALL échouer quand le code applicatif utilise `dd`, `ddd`, `dump`, `ray` ou `var_dump`, enfreint le preset php de Pest, ou quand le code du domaine dépend de HTTP, d'Inertia ou des intégrations. `make arch-test` lance cette suite seule.

#### Scenario: Helper de débogage oublié
- **WHEN** une classe de `app/` appelle `dump()`
- **THEN** `make arch-test` échoue en nommant la classe

#### Scenario: Domaine qui dépend de HTTP
- **WHEN** une classe de `App\Domain` importe `Illuminate\Http\Request`
- **THEN** `make arch-test` échoue

#### Scenario: Intégration qui dépend d'un contrôleur
- **WHEN** une classe de `App\Integrations` importe une classe de `App\Http`
- **THEN** `make arch-test` échoue

### Requirement: Consignes d'agent sans contournement du Makefile
Un test SHALL échouer quand `AGENTS.md` contient l'une des prescriptions `artisan test`, `bin pint`, `vendor/bin/pest`, `vendor/bin/phpstan`, `pnpm run build` ou `ask the user to run`, en citant le numéro de ligne, la prescription et le texte de la ligne.

#### Scenario: Consigne Boost réintroduite
- **WHEN** la surcharge `.ai/guidelines/pint/core.blade.php` est supprimée et qu'`AGENTS.md` est régénéré avec une ligne qui prescrit `sail bin pint --dirty`
- **THEN** le test échoue avec un message de la forme `AGENTS.md:<ligne> [bin pint] <texte>`

### Requirement: Messages de commit vérifiés
`make commitlint` SHALL vérifier chaque commit de la plage `FROM...TO` (par défaut `origin/main` à `HEAD`) contre Conventional Commits, la liste fermée de scopes et une limite de 100 caractères par ligne de corps.

#### Scenario: Corps trop long
- **WHEN** un commit de la plage a une ligne de corps de 101 caractères
- **THEN** `make commitlint` échoue en nommant ce commit

#### Scenario: Scope inconnu
- **WHEN** un commit de la plage s'intitule `feat(golf): ajoute x`
- **THEN** `make commitlint` échoue sur la règle `scope-enum`

### Requirement: Classement documentation ou code
`make changed-code` SHALL afficher `false` quand chaque fichier modifié de la plage `FROM...TO` est de la documentation, et `true` sinon, ou quand la plage est vide. Sont de la documentation : un `.md` à la racine, `version.txt`, tout fichier sous `docs/`, `openspec/` ou `.ai/guidelines/`.

#### Scenario: Proposition OpenSpec seule
- **WHEN** la plage ne modifie que `openspec/changes/x/proposal.md` et `ROADMAP.md`
- **THEN** `make changed-code` affiche `false`

#### Scenario: Passage de version
- **WHEN** la plage ne modifie que `version.txt` et `CHANGELOG.md`
- **THEN** `make changed-code` affiche `false`

#### Scenario: Markdown hors des chemins de documentation
- **WHEN** la plage modifie `resources/js/README.md`
- **THEN** `make changed-code` affiche `true`

#### Scenario: Fichier déplacé vers docs
- **WHEN** la plage déplace `app/Foo.php` vers `docs/foo.php`
- **THEN** `make changed-code` affiche `true`

#### Scenario: Workflow de CI
- **WHEN** la plage ne modifie que `.github/workflows/ci.yml`
- **THEN** `make changed-code` affiche `true`
