## ADDED Requirements

### Requirement: Image éprouvée sur chaque changement de code
Une PR ou un push qui touche du code SHALL construire l'image de production et lancer la stack de production avec `docker compose up --wait`, sans rien publier. Le job `image` MUST échouer quand l'image ne se construit pas, quand un service n'atteint pas l'état sain, ou quand `/up` et `/login` ne répondent pas 200 depuis l'hôte du runner.

#### Scenario: Dockerfile cassé
- **WHEN** une PR modifie le `Dockerfile` de sorte que `composer install` échoue
- **THEN** le job `image` échoue et la PR n'est pas fusionnable

#### Scenario: Worker jamais sain
- **WHEN** une PR change la commande du service `worker` de sorte qu'il ne signale jamais sa vie
- **THEN** le job `image` échoue sur `docker compose up --wait`

#### Scenario: Stack saine
- **WHEN** une PR modifie `app/` sans casser l'image
- **THEN** le job `image` réussit après que `/up` et `/login` ont répondu 200

### Requirement: Publication de l'image depuis main
Un push sur `main` dont tous les jobs de l'exécution réussissent SHALL publier l'image sur `ghcr.io/emmpaul/golf-handicaps-laravel` sous les tags `main` et le sha complet du commit, pour `linux/amd64`. Un push sur `main` dont un job échoue MUST NOT publier.

#### Scenario: Fusion d'une PR de code
- **WHEN** une PR de code est fusionnée sur `main` et que tous les jobs de l'exécution réussissent
- **THEN** `ghcr.io/emmpaul/golf-handicaps-laravel:main` et `:<sha>` pointent sur le même digest, construit depuis ce commit

#### Scenario: Test rouge sur main
- **WHEN** `test-backend` échoue sur un push sur `main`
- **THEN** le job `publish` ne tourne pas et le tag `main` garde l'image précédente

#### Scenario: Fusion d'une PR de documentation
- **WHEN** une PR qui ne modifie que `openspec/` est fusionnée sur `main`
- **THEN** aucune image n'est publiée

### Requirement: Publication de l'image d'une version
Un tag `vX.Y.Z` posé sur un commit SHALL lancer tous les contrôles, puis publier l'image sous `vX.Y.Z`, `latest` et le sha du commit. Quand `version.txt` du commit ne contient pas `X.Y.Z`, le job `publish` MUST échouer sans rien publier.

#### Scenario: Version publiée
- **GIVEN** `version.txt` contient `0.1.0` sur le commit tagué
- **WHEN** le mainteneur pousse le tag `v0.1.0`
- **THEN** `:v0.1.0`, `:latest` et `:<sha>` pointent sur le même digest, et `GET /api/version` d'un conteneur lancé depuis `:v0.1.0` répond `"version": "0.1.0"`

#### Scenario: Tag qui ne correspond pas à la version
- **GIVEN** `version.txt` contient `0.1.0` sur le commit tagué
- **WHEN** le mainteneur pousse le tag `v0.2.0`
- **THEN** le job `publish` échoue en nommant `0.1.0` et `0.2.0`, et aucun tag d'image n'est créé ni déplacé

### Requirement: Publication réservée à main et aux tags
Une pull request, y compris venant d'un fork, MUST NOT publier d'image ni obtenir le droit d'écrire des paquets.

#### Scenario: Pull request
- **WHEN** une PR de code passe tous ses contrôles
- **THEN** le job `publish` est sauté et aucune image n'est poussée

## MODIFIED Requirements

### Requirement: Tests sautés pour la documentation seule
Les jobs `test-backend`, `test-frontend`, `image` et `report` SHALL être sautés quand chaque fichier modifié de la plage est de la documentation au sens de `make changed-code`, et lancés dans tous les autres cas.

#### Scenario: Proposition OpenSpec
- **WHEN** une PR ne modifie que `openspec/changes/x/tasks.md` et `ROADMAP.md`
- **THEN** `test-backend`, `test-frontend`, `image` et `report` sont sautés

#### Scenario: Passage de version
- **WHEN** une PR ne modifie que `version.txt` et `CHANGELOG.md`
- **THEN** `test-backend`, `test-frontend`, `image` et `report` sont sautés

#### Scenario: Un fichier de code parmi la documentation
- **WHEN** une PR modifie `openspec/changes/x/tasks.md` et `app/Domain/LocalTime.php`
- **THEN** `test-backend`, `test-frontend`, `image` et `report` tournent

#### Scenario: Lockfile seul
- **WHEN** une PR ne modifie que `pnpm-lock.yaml`
- **THEN** les tests tournent

#### Scenario: Stack de production seule
- **WHEN** une PR ne modifie que `docker-compose.yml`
- **THEN** `image` tourne

### Requirement: Durée bornée des jobs
Chaque job SHALL porter une durée maximale explicite : 5 minutes pour `changes` et `report`, 10 minutes pour `static` et `test-frontend`, 15 minutes pour `test-backend`, `image` et `publish`.

#### Scenario: Test bloqué
- **WHEN** un test backend attend indéfiniment
- **THEN** le job `test-backend` est arrêté au bout de 15 minutes et finit en échec

#### Scenario: Stack qui ne devient jamais saine
- **WHEN** le service `web` reste au démarrage sans jamais devenir sain
- **THEN** le job `image` finit en échec au plus tard au bout de 15 minutes

### Requirement: Workflows sûrs
Chaque action tierce SHALL être épinglée par sha de commit, avec sa version en commentaire, et chaque checkout MUST ne pas conserver le jeton. Les workflows n'ont que la permission de lecture du contenu, sauf le job `report`, qui écrit des checks, et le job `publish`, qui écrit des paquets. Un audit zizmor SHALL tourner sur chaque PR ou push qui modifie `.github/`.

#### Scenario: Action épinglée par étiquette
- **WHEN** une PR remplace un sha par `actions/checkout@v5` dans `.github/workflows/ci.yml`
- **THEN** le workflow zizmor échoue sur cette ligne

#### Scenario: PR sans fichier de workflow
- **WHEN** une PR ne modifie que `app/`
- **THEN** le workflow zizmor ne tourne pas

#### Scenario: Permission d'écrire des paquets
- **WHEN** on lit les permissions déclarées dans `.github/workflows/ci.yml`
- **THEN** seul le job `publish` a `packages: write`
