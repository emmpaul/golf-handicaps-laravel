# web-request-security Specification

## Purpose
Protéger chaque réponse HTTP de l'application contre l'encadrement, le reniflage de type et les fuites de référent, lire l'IP réelle du client derrière le proxy, et garder les sessions illisibles en base.

## Requirements

### Requirement: En-têtes de sécurité
Toute réponse HTTP de l'application, pages d'erreur, `/up`, `/login` et réponses JSON comprises, MUST porter `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` et `Content-Security-Policy: frame-ancestors 'none'`. Une réponse qui pose déjà sa propre `Content-Security-Policy` la garde.

#### Scenario: Page de l'application
- **GIVEN** le visiteur a ouvert une session
- **WHEN** il ouvre l'accueil
- **THEN** la réponse porte les quatre en-têtes avec ces valeurs exactes

#### Scenario: Réponses sans session
- **WHEN** un client sans session demande `/login`, `/up`, `GET /api/version` et `GET /api/versions`
- **THEN** chacune des quatre réponses porte les quatre en-têtes

#### Scenario: Redirection vers la saisie du code
- **WHEN** un visiteur sans session ouvre l'accueil
- **THEN** la redirection vers `/login` porte les quatre en-têtes

### Requirement: Mandataires de confiance
Le système SHALL lire l'IP du client dans `X-Forwarded-For` seulement quand la requête vient d'un mandataire listé dans `TRUSTED_PROXIES` (adresses ou réseaux séparés par des virgules). Quand la variable est vide, aucun mandataire n'est de confiance. Le joker `*` MUST NOT être accepté.

#### Scenario: Requête derrière le proxy de confiance
- **GIVEN** `TRUSTED_PROXIES` vaut `10.0.0.0/8`
- **WHEN** une requête arrive de `10.0.1.5` avec `X-Forwarded-For: 203.0.113.7`
- **THEN** le système voit l'IP du client `203.0.113.7`

#### Scenario: Mandataire inconnu
- **GIVEN** `TRUSTED_PROXIES` vaut `10.0.0.0/8`
- **WHEN** une requête arrive de `198.51.100.9` avec `X-Forwarded-For: 203.0.113.7`
- **THEN** le système voit l'IP du client `198.51.100.9`

#### Scenario: Variable vide
- **GIVEN** `TRUSTED_PROXIES` est vide
- **WHEN** une requête arrive de `10.0.1.5` avec `X-Forwarded-For: 203.0.113.7`
- **THEN** le système voit l'IP du client `10.0.1.5`

#### Scenario: Joker refusé
- **GIVEN** `TRUSTED_PROXIES` vaut `*`
- **WHEN** une requête arrive de `198.51.100.9` avec `X-Forwarded-For: 203.0.113.7`
- **THEN** le système voit l'IP du client `198.51.100.9`

### Requirement: Sessions chiffrées en base
Le système SHALL garder les sessions en base PostgreSQL, avec un contenu chiffré par la clé de l'application. Le contenu d'une ligne de session MUST NOT laisser lire en clair une donnée de la session ni le code d'accès.

#### Scenario: Ligne de session après connexion
- **GIVEN** le code configuré est `birdie-2026`
- **WHEN** un navigateur ouvre une session avec ce code
- **THEN** la base contient une ligne de session dont le contenu ne contient ni `birdie-2026` ni le nom d'aucune clé de session en clair
