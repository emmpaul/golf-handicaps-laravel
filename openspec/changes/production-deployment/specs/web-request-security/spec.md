## ADDED Requirements

### Requirement: HTTPS imposé au navigateur
Toute réponse servie en HTTPS, directement ou derrière un mandataire de confiance qui annonce `X-Forwarded-Proto: https`, SHALL porter `Strict-Transport-Security: max-age=31536000`, sans `includeSubDomains` ni `preload`. Une réponse servie en HTTP simple MUST NOT porter cet en-tête.

#### Scenario: Page derrière le proxy HTTPS
- **GIVEN** `TRUSTED_PROXIES` vaut `10.0.0.0/8`
- **WHEN** une requête vers `/login` arrive de `10.0.1.5` avec `X-Forwarded-Proto: https`
- **THEN** la réponse porte `Strict-Transport-Security: max-age=31536000`

#### Scenario: Healthcheck en HTTP
- **WHEN** l'orchestrateur demande `/up` en HTTP simple depuis le conteneur
- **THEN** la réponse ne porte pas `Strict-Transport-Security`

#### Scenario: Mandataire inconnu
- **GIVEN** `TRUSTED_PROXIES` vaut `10.0.0.0/8`
- **WHEN** une requête HTTP arrive de `198.51.100.9` avec `X-Forwarded-Proto: https`
- **THEN** la réponse ne porte pas `Strict-Transport-Security`

#### Scenario: Réponse d'erreur en HTTPS
- **WHEN** une requête HTTPS vers une adresse inconnue reçoit une erreur
- **THEN** la réponse d'erreur porte `Strict-Transport-Security: max-age=31536000`

### Requirement: Témoin de session réservé à HTTPS en production
En production, le témoin de session SHALL porter l'attribut `Secure`, sans réglage de plus, pour que le navigateur ne l'envoie jamais en HTTP simple. Hors production, il n'a pas `Secure` par défaut, pour que l'environnement local en HTTP garde sa session. Le témoin garde `HttpOnly` et `SameSite=Lax` partout.

#### Scenario: Connexion en production
- **GIVEN** l'application tourne avec `APP_ENV=production` et sans réglage du témoin
- **WHEN** un visiteur ouvre une session avec le bon code
- **THEN** le témoin de session posé porte `Secure`, `HttpOnly` et `SameSite=Lax`

#### Scenario: Connexion en local
- **GIVEN** l'application tourne avec `APP_ENV=local` et sans réglage du témoin
- **WHEN** un visiteur ouvre une session sur `http://localhost`
- **THEN** le témoin de session ne porte pas `Secure` et la session reste ouverte à la requête suivante
