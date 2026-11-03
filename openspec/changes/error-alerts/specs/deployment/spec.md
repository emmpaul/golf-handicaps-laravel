# Spec Delta

## ADDED Requirements

### Requirement: Variable du suivi d'erreurs

La stack SHALL lire `SENTRY_LARAVEL_DSN` dans l'environnement de `migrate`, `web` et `worker`. La variable est facultative et sa valeur MUST NOT être inscrite dans l'image. Une valeur mal formée MUST faire échouer `migrate` avec un message qui nomme la variable, comme les autres réglages invalides.

#### Scenario: Variable absente

- **GIVEN** `SENTRY_LARAVEL_DSN` n'est pas défini dans l'environnement de la stack
- **WHEN** l'opérateur lance la stack
- **THEN** `web` et `worker` deviennent sains et aucune alerte ne part

#### Scenario: Variable ajoutée après coup

- **GIVEN** la stack tourne sans `SENTRY_LARAVEL_DSN`
- **WHEN** l'opérateur ajoute la variable dans Dokploy et redéploie
- **THEN** la prochaine exception non prévue de `web` ou de `worker` déclenche une alerte

#### Scenario: Valeur mal formée

- **GIVEN** `SENTRY_LARAVEL_DSN` vaut `pas-un-dsn`
- **WHEN** l'opérateur lance la stack
- **THEN** `migrate` sort en erreur avec un message qui commence par `SENTRY_LARAVEL_DSN`, et `web` et `worker` restent sur `Created`

#### Scenario: Image publiée

- **WHEN** on inspecte l'image publiée sur GHCR
- **THEN** elle ne contient aucun DSN
