# Spec Delta

## ADDED Requirements

### Requirement: Photos de carte acceptées en production
L'image de production SHALL accepter l'envoi d'une photo de carte jusqu'à 20 Mo et la convertir en WebP, sans réglage à faire dans Dokploy. Une photo plus lourde MUST être refusée avec un message qui donne la limite, sans erreur 500.

#### Scenario: Photo de téléphone
- **GIVEN** la stack de production lancée sur l'image construite par la CI
- **WHEN** l'utilisateur envoie une photo JPEG de 8 Mo
- **THEN** l'import est créé et sa photo est servie en `image/webp`

#### Scenario: Photo trop lourde
- **WHEN** l'utilisateur envoie un fichier de 25 Mo
- **THEN** le système refuse l'envoi avec « La photo dépasse 20 Mo » et aucun import n'est créé

### Requirement: Variables de la lecture par l'IA
La stack SHALL lire dans l'environnement du conteneur la clé d'API (`ANTHROPIC_API_KEY`), le modèle (`AI_MODEL`), le plafond mensuel (`AI_MONTHLY_BUDGET_USD`) et le fuseau du budget (`AI_BUDGET_TIME_ZONE`), pour `web` comme pour `worker`. Ces valeurs MUST NOT être inscrites dans l'image.

#### Scenario: Clé ajoutée après coup
- **GIVEN** la stack tourne sans `ANTHROPIC_API_KEY` et l'écran d'import affiche la lecture désactivée
- **WHEN** l'opérateur ajoute la clé dans Dokploy et redéploie
- **THEN** l'écran d'import propose l'envoi d'une photo et le worker lit les imports

#### Scenario: Image publiée
- **WHEN** on inspecte l'image publiée sur GHCR
- **THEN** elle ne contient aucune clé d'API
