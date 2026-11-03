## Purpose

Mesurer la vitesse de l'application déployée, toujours de la même façon et sans rien y écrire, et donner à chaque réponse le temps passé dans le serveur, pour comparer deux versions chiffre contre chiffre.

## ADDED Requirements

### Requirement: Temps du serveur dans chaque réponse
Chaque réponse de l'application, pages, visites Inertia, JSON, redirections et `/up` compris, SHALL porter un en-tête `Server-Timing` avec trois mesures : `app` (durée du traitement dans l'application, en millisecondes), `db` (durée cumulée des requêtes SQL) et `db-count` (leur nombre). L'en-tête MUST NOT contenir de requête SQL, de valeur ni de donnée du groupe.

#### Scenario: Page d'une section
- **GIVEN** une session ouverte
- **WHEN** le navigateur demande `/rounds`
- **THEN** la réponse 200 porte `Server-Timing` avec `app;dur=`, `db;dur=` et `db-count;dur=` suivis chacun d'un nombre positif ou nul

#### Scenario: Réponse sans requête SQL
- **WHEN** un client sans cookie demande `/api/version`
- **THEN** la réponse porte `Server-Timing` avec `db;dur=0` et `db-count;dur=0`

#### Scenario: Aucune donnée dans l'en-tête
- **GIVEN** un joueur nommé « Zoé »
- **WHEN** le navigateur demande `/players`
- **THEN** l'en-tête `Server-Timing` ne contient ni « Zoé », ni `select`, ni le nom d'une table

#### Scenario: Réponse refusée
- **WHEN** un client sans session demande `/rounds`
- **THEN** la redirection vers `/login` porte aussi `Server-Timing`

### Requirement: Mesure en lecture seule
Le système SHALL fournir une commande qui mesure une adresse donnée en n'envoyant qu'une requête d'écriture, l'ouverture de session par le code d'accès, puis des GET seulement. Elle MUST NOT créer, modifier ni supprimer de round, de joueur, d'import ou de rating.

#### Scenario: Requêtes envoyées
- **WHEN** l'opérateur lance la mesure contre une adresse
- **THEN** la commande envoie un seul `POST /login`, puis uniquement des `GET`, et aucune requête `PUT`, `PATCH` ni `DELETE`

#### Scenario: Pages mesurées
- **WHEN** la mesure se termine
- **THEN** elle a mesuré `/robots.txt`, `/up`, `/api/version`, `/login`, `/`, `/handicaps`, `/strokes`, `/rounds`, `/players`, `/courses` et `/import`, en page complète, et les cinq sections aussi en visite Inertia

#### Scenario: Code refusé
- **GIVEN** un code d'accès faux
- **WHEN** l'opérateur lance la mesure
- **THEN** la commande s'arrête en erreur avec « Code d'accès refusé », sans mesurer aucune page protégée

### Requirement: Code d'accès hors du dépôt
La commande de mesure SHALL lire le code d'accès dans la variable d'environnement `PERF_PASSCODE`, ou le demander sans l'afficher quand la variable est absente. Le code et le témoin de session MUST NOT apparaître dans la sortie, dans le rapport, ni dans aucun fichier du dépôt.

#### Scenario: Code par l'environnement
- **GIVEN** `PERF_PASSCODE` contient le bon code
- **WHEN** l'opérateur lance la mesure sans option de code
- **THEN** la commande ne pose aucune question et mesure les pages protégées

#### Scenario: Code demandé
- **GIVEN** `PERF_PASSCODE` est absent
- **WHEN** l'opérateur lance la mesure
- **THEN** la commande demande le code sans l'afficher à la saisie

#### Scenario: Rien de secret dans le rapport
- **GIVEN** le code `code-de-test`
- **WHEN** la mesure écrit son rapport
- **THEN** le rapport ne contient ni `code-de-test`, ni la valeur du témoin de session, ni celle du jeton XSRF

### Requirement: Rapport comparable
La mesure SHALL répéter chaque requête un nombre de fois choisi (20 par défaut) et donner pour chaque page la médiane, le 90e centile et le minimum du temps jusqu'au premier octet après la poignée de main TLS, la médiane de `app` et de `db-count` lus dans `Server-Timing`, et la taille compressée reçue. Le rapport MUST être un tableau Markdown daté avec l'adresse mesurée, écrit dans `reports/perf/`.

#### Scenario: Calcul des valeurs
- **GIVEN** cinq essais d'une page à 100, 120, 110, 300 et 105 ms
- **WHEN** la mesure calcule sa ligne
- **THEN** la ligne donne une médiane de 110 ms, un 90e centile de 300 ms et un minimum de 100 ms

#### Scenario: En-tête absent
- **GIVEN** une version déployée sans `Server-Timing`
- **WHEN** la mesure calcule sa ligne
- **THEN** les colonnes `app` et `db-count` affichent « — » et les autres colonnes sont remplies

#### Scenario: Rapport écrit
- **WHEN** la mesure de `https://golf.emmanuelpaul.com` se termine le 2026-11-02
- **THEN** un fichier `reports/perf/2026-11-02-golf.emmanuelpaul.com.md` contient l'adresse, la date, le nombre d'essais et une ligne par page mesurée
