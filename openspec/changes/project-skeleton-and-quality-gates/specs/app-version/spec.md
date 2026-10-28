# Spec Delta

## Purpose

Dire à une machine quelle version de l'application tourne, pour vérifier qu'un déploiement est en ligne, et garder pour le groupe un journal lisible de ce qui change d'une version à l'autre.

## ADDED Requirements

### Requirement: Route de version
`GET /api/version` SHALL répondre 200 avec l'objet JSON `{ "version": <chaîne>, "commit": <chaîne ou null> }` et l'en-tête `Cache-Control: no-store`. `version` est la version publiée, sans préfixe `v`. `commit` est le sha complet (40 caractères hexadécimaux minuscules) du commit construit, ou `null` quand il est inconnu ou mal formé. La réponse ne lit pas la base.

#### Scenario: Build qui connaît son commit
- **GIVEN** l'application est à la version `0.6.0` et connaît le commit `4794e1b0c0ffee00000000000000000000000000`
- **WHEN** un client demande `GET /api/version`
- **THEN** le système répond 200 avec `{ "version": "0.6.0", "commit": "4794e1b0c0ffee00000000000000000000000000" }` et `Cache-Control: no-store`

#### Scenario: Commit inconnu
- **GIVEN** l'application est à la version `0.6.0` sans commit connu
- **WHEN** un client demande `GET /api/version`
- **THEN** le système répond 200 avec `{ "version": "0.6.0", "commit": null }`

#### Scenario: Commit mal formé
- **GIVEN** le commit fourni à l'application vaut `4794e1b`
- **WHEN** un client demande `GET /api/version`
- **THEN** `commit` vaut `null`

#### Scenario: Base injoignable
- **GIVEN** PostgreSQL est arrêté
- **WHEN** un client demande `GET /api/version`
- **THEN** le système répond 200 avec la version

### Requirement: Route de version sans session
La route de version SHALL rester lisible sans session ni code d'accès, en `GET` et `HEAD` seulement. Elle MUST NOT ouvrir de session ni poser de cookie, et ne donne aucune donnée du groupe. Une autre méthode reçoit 405.

#### Scenario: Visiteur sans cookie
- **WHEN** un client sans cookie demande `GET /api/version`
- **THEN** le système répond 200 sans redirection et sans en-tête `Set-Cookie`

#### Scenario: Requête HEAD
- **WHEN** un client envoie `HEAD /api/version`
- **THEN** le système répond 200 sans corps

#### Scenario: Écriture sur la route de version
- **WHEN** un client envoie `POST /api/version`
- **THEN** le système répond 405

### Requirement: Version tenue dans le dépôt
La version de l'application SHALL être lue dans le fichier `version.txt` à la racine du dépôt, qui contient une version `MAJEUR.MINEUR.CORRECTIF` seule. Un test MUST échouer quand ce fichier manque ou contient autre chose.

#### Scenario: Fichier valide
- **GIVEN** `version.txt` contient `0.1.0` suivi d'un saut de ligne
- **WHEN** un client demande `GET /api/version`
- **THEN** `version` vaut `0.1.0`

#### Scenario: Fichier invalide
- **GIVEN** `version.txt` contient `v0.1`
- **WHEN** on lance `make test-backend`
- **THEN** un test échoue en citant le contenu du fichier

### Requirement: Journal des changements
Le dépôt SHALL tenir un fichier `CHANGELOG.md` en français, avec en tête une section « Non publié » qui reçoit les changements de chaque pull request de code. Toute version autre que `0.0.0` inscrite dans `version.txt` MUST avoir sa section `## [X.Y.Z] - AAAA-MM-JJ` dans `CHANGELOG.md`.

#### Scenario: Version publiée sans section
- **GIVEN** `version.txt` contient `0.2.0` et `CHANGELOG.md` n'a pas de section `## [0.2.0]`
- **WHEN** on lance `make test-backend`
- **THEN** un test échoue en nommant la version `0.2.0`

#### Scenario: Avant la première version
- **GIVEN** `version.txt` contient `0.0.0` et `CHANGELOG.md` n'a qu'une section « Non publié »
- **WHEN** on lance `make test-backend`
- **THEN** le test du journal passe
