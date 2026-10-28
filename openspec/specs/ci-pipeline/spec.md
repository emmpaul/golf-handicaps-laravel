# ci-pipeline Specification

## Purpose
Définir ce que l'intégration continue garantit sur les pull requests et sur `main` : quels contrôles tournent selon les fichiers modifiés, comment les résultats de tests sont rapportés, et comment les workflows eux-mêmes restent sûrs et à jour.

## Requirements

### Requirement: Contrôles légers sur chaque exécution
Chaque pull request et chaque push sur `main` SHALL lancer `make commitlint` sur la plage de commits concernée, puis `make lint`, `make analyse`, `make duplicates` et la validation stricte de tous les changements et specs OpenSpec, quels que soient les fichiers modifiés.

#### Scenario: Spec OpenSpec invalide
- **WHEN** une PR ne modifie que `openspec/changes/x/specs/y/spec.md` et y laisse une exigence sans scénario
- **THEN** le job `static` échoue sur la validation OpenSpec

#### Scenario: PR de documentation avec un commit mal formé
- **WHEN** une PR ne modifie que `openspec/` avec un commit intitulé `bad message`
- **THEN** le job `static` échoue sur commitlint

#### Scenario: PR de documentation conforme
- **WHEN** une PR ne modifie que `docs/adr/0001-monolithe-modulaire.md` avec des commits conformes
- **THEN** le job `static` réussit

### Requirement: Tests sautés pour la documentation seule
Les jobs `test-backend`, `test-frontend` et `report` SHALL être sautés quand chaque fichier modifié de la plage est de la documentation au sens de `make changed-code`, et lancés dans tous les autres cas.

#### Scenario: Proposition OpenSpec
- **WHEN** une PR ne modifie que `openspec/changes/x/tasks.md` et `ROADMAP.md`
- **THEN** `test-backend`, `test-frontend` et `report` sont sautés

#### Scenario: Passage de version
- **WHEN** une PR ne modifie que `version.txt` et `CHANGELOG.md`
- **THEN** `test-backend`, `test-frontend` et `report` sont sautés

#### Scenario: Un fichier de code parmi la documentation
- **WHEN** une PR modifie `openspec/changes/x/tasks.md` et `app/Domain/LocalTime.php`
- **THEN** `test-backend`, `test-frontend` et `report` tournent

#### Scenario: Lockfile seul
- **WHEN** une PR ne modifie que `pnpm-lock.yaml`
- **THEN** les tests tournent

### Requirement: Plage de commits classée
Sur une PR, la plage SHALL aller du commit de base au dernier commit de la PR, sans le commit de fusion synthétique. Sur un push sur `main`, elle va du commit précédent au commit poussé. Quand le commit précédent est inconnu, nul ou absent de l'historique, la CI MUST lancer tous les tests.

#### Scenario: Premier push
- **WHEN** un push arrive sur `main` sans commit précédent connu
- **THEN** les jobs de tests tournent

#### Scenario: Historique réécrit
- **WHEN** le commit précédent du push n'est pas un ancêtre du commit poussé
- **THEN** la plage se réduit au dernier commit, marquée comme repli, et les jobs de tests tournent

### Requirement: Tests backend sur PostgreSQL en CI
Le job `test-backend` SHALL compiler les assets puis lancer `make test-backend` contre un service PostgreSQL 18 dans un seul job, sans Sail ni matrice.

#### Scenario: Test qui rend une page
- **WHEN** un test Feature rend la page d'accueil Inertia en CI
- **THEN** il trouve le manifeste Vite et passe

### Requirement: Rapport de tests
Après les jobs de tests, la CI SHALL publier un seul rapport « Tests » qui réunit les résultats JUnit du backend et du frontend, conservés même quand un job de tests échoue. Le rapport MUST échouer quand aucun résultat n'est trouvé.

#### Scenario: Test backend rouge
- **WHEN** un test Pest échoue sur une PR de code
- **THEN** `test-backend` échoue et le rapport « Tests » nomme le test en échec

#### Scenario: Résultats manquants
- **WHEN** les jobs de tests tournent sans produire de fichier JUnit
- **THEN** le job `report` échoue

### Requirement: Exécutions remplacées annulées
Un nouveau push sur une PR SHALL annuler l'exécution en cours de cette PR. Sur `main`, une exécution en cours MUST aller à son terme.

#### Scenario: Deux pushes rapprochés sur une PR
- **WHEN** deux commits sont poussés sur la même PR à 15 s d'écart
- **THEN** l'exécution du premier finit « cancelled » et seule celle du second va au bout

#### Scenario: Deux fusions rapprochées sur main
- **WHEN** deux PR sont fusionnées sur `main` à 30 s d'écart
- **THEN** l'exécution de la première va à son terme

### Requirement: Durée bornée des jobs
Chaque job SHALL porter une durée maximale explicite : 5 minutes pour `changes` et `report`, 10 minutes pour `static` et `test-frontend`, 15 minutes pour `test-backend`.

#### Scenario: Test bloqué
- **WHEN** un test backend attend indéfiniment
- **THEN** le job `test-backend` est arrêté au bout de 15 minutes et finit en échec

### Requirement: Workflows sûrs
Chaque action tierce SHALL être épinglée par sha de commit, avec sa version en commentaire, et chaque checkout MUST ne pas conserver le jeton. Les workflows n'ont que la permission de lecture du contenu, sauf le job `report`, qui écrit des checks. Un audit zizmor SHALL tourner sur chaque PR ou push qui modifie `.github/`.

#### Scenario: Action épinglée par étiquette
- **WHEN** une PR remplace un sha par `actions/checkout@v5` dans `.github/workflows/ci.yml`
- **THEN** le workflow zizmor échoue sur cette ligne

#### Scenario: PR sans fichier de workflow
- **WHEN** une PR ne modifie que `app/`
- **THEN** le workflow zizmor ne tourne pas

### Requirement: Mises à jour des actions
Dependabot SHALL proposer chaque semaine une seule PR qui regroupe les mises à jour des actions GitHub, après un délai de 7 jours depuis leur publication, avec des messages de commit `ci(deps): …` acceptés par commitlint.

#### Scenario: Nouvelle version d'une action
- **WHEN** une nouvelle version de `actions/checkout` est publiée depuis plus de 7 jours
- **THEN** Dependabot ouvre une PR groupée dont le commit commence par `ci(deps): bump` et dont le job `static` passe commitlint
