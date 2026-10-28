## Purpose

Rendre visible à l'orchestrateur (Docker Compose, Dokploy) un worker qui ne traite plus la file, pour qu'un import ou une recherche en attente ne reste pas bloqué sans que rien ne vire au rouge.

## ADDED Requirements

### Requirement: Signal de vie du worker
Le worker SHALL signaler qu'il tourne à chaque tour de sa boucle, que la file soit vide ou qu'il vienne de finir un job. Le signal MUST rester propre au conteneur du worker : un signal laissé par un conteneur précédent ou par le web ne compte pas.

#### Scenario: File vide
- **WHEN** le worker tourne depuis 10 minutes sans aucun job dans la file
- **THEN** son signal date de moins de 5 secondes

#### Scenario: Job terminé
- **WHEN** le worker finit un job
- **THEN** son signal date de la fin de ce job ou plus tard

#### Scenario: Nouveau conteneur
- **WHEN** un nouveau conteneur worker démarre à la place d'un ancien
- **THEN** il ne reprend aucun signal de l'ancien conteneur

#### Scenario: Web hors du signal
- **WHEN** le service `web` sert des requêtes pendant que le worker est arrêté
- **THEN** aucun signal du worker n'est écrit

### Requirement: Worker démarré après les migrations
Le worker SHALL démarrer seulement après la réussite des migrations du déploiement, pour ne jamais lire une file dont les tables manquent.

#### Scenario: Base neuve
- **GIVEN** une base PostgreSQL vide
- **WHEN** l'opérateur lance la stack
- **THEN** le worker démarre après la fin réussie du service `migrate` et devient sain

### Requirement: Worker vu malade quand il se fige
Le service worker de la stack SHALL avoir un healthcheck qui le déclare sain tant que son dernier signal a moins de 5 minutes, et malade au-delà ou sans signal. Pendant le démarrage, le healthcheck MUST laisser au worker le temps d'émettre un premier signal.

#### Scenario: Worker sain au démarrage
- **WHEN** l'opérateur lance `docker compose up --wait` avec une configuration valide
- **THEN** la commande rend la main avec les services `web` et `worker` tous deux sains

#### Scenario: Job figé
- **WHEN** un job reste bloqué plus de 5 minutes sans rendre la main à la boucle du worker
- **THEN** `docker compose ps` montre le service `worker` en `unhealthy`

#### Scenario: Job long mais fini
- **WHEN** un job dure 2 minutes (lecture d'une photo par l'IA) puis se termine
- **THEN** le service `worker` reste sain

#### Scenario: Aucun signal
- **WHEN** le worker n'a écrit aucun signal 60 secondes après son démarrage
- **THEN** le healthcheck échoue
