# Spec Delta

## Purpose

Prévenir le mainteneur quand l'application casse en production ou qu'un import échoue, sans jamais faire sortir de nom de joueur ni de score vers le service d'alerte.

## ADDED Requirements

### Requirement: Alerte sur une exception non prévue

Le système SHALL envoyer au service de suivi d'erreurs une alerte pour toute exception non prévue d'une requête web. L'exception MUST rester journalisée sur la sortie d'erreur du conteneur, avec son message.

#### Scenario: Erreur 500 sur une page

- **GIVEN** le suivi d'erreurs est configuré
- **WHEN** une requête sur une page provoque une exception non prévue
- **THEN** une alerte part avec le type de cette exception, et l'exception apparaît dans le journal de `web` avec son message

#### Scenario: Page inconnue

- **WHEN** une requête reçoit une réponse 404
- **THEN** aucune alerte ne part

#### Scenario: Requête sans session

- **WHEN** une requête sans code d'accès valide reçoit une réponse 401 ou est redirigée vers `/login`
- **THEN** aucune alerte ne part

### Requirement: Aucune alerte pour un refus métier

Un refus métier affiché à la personne SHALL rester sans alerte. Le refus MUST garder son affichage actuel : toast d'erreur, ou réponse 422 pour une requête JSON hors Inertia.

#### Scenario: Joueur en double

- **GIVEN** le suivi d'erreurs est configuré et un joueur `Manu` existe
- **WHEN** l'utilisateur ajoute un joueur `Manu`
- **THEN** le toast d'erreur s'affiche et aucune alerte ne part

### Requirement: Alerte sur l'échec définitif d'un job

Le système SHALL envoyer une alerte quand un job de la file échoue définitivement, et une seule pour cet échec. Une tentative qui échoue et sera réessayée MUST NOT déclencher d'alerte, mais MUST rester journalisée.

#### Scenario: Erreur passagère puis réussite

- **GIVEN** la lecture d'un import échoue sur une erreur réseau à sa première tentative
- **WHEN** la seconde tentative rend un brouillon
- **THEN** aucune alerte ne part, et l'erreur de la première tentative apparaît dans le journal de `worker`

#### Scenario: Deux erreurs de suite

- **GIVEN** la lecture d'un import échoue sur une erreur réseau à ses deux tentatives
- **WHEN** le job tombe en échec définitif
- **THEN** exactement une alerte part, avec le type de l'exception, et l'import est en « Échec » avec « Lecture IA indisponible »

#### Scenario: Recherche de rating sans réponse de SGT

- **GIVEN** Simulator Golf Tour ne répond pas aux deux tentatives d'une recherche de rating
- **WHEN** le job tombe en échec définitif
- **THEN** exactement une alerte part, et la ligne du parcours finit avec « SGT injoignable »

### Requirement: Alerte sur un import en échec sans exception

Le système SHALL envoyer une alerte quand la lecture d'un import se termine en échec sans exception. L'alerte MUST indiquer la raison affichée à l'utilisateur, et les alertes d'une même raison MUST être regroupées dans le service d'alerte.

#### Scenario: Réponse de l'IA inexploitable

- **WHEN** l'IA répond par un brouillon qui ne respecte pas la forme attendue
- **THEN** exactement une alerte part avec la raison « Lecture IA indisponible »

#### Scenario: Budget du mois atteint

- **GIVEN** le plafond du mois est atteint
- **WHEN** le worker commence la lecture d'un import
- **THEN** exactement une alerte part avec la raison « Budget IA du mois atteint »

#### Scenario: Lecture désactivée

- **GIVEN** aucune clé d'IA n'est configurée au moment où le worker prend un import
- **WHEN** l'import passe en « Échec »
- **THEN** exactement une alerte part avec la raison « Lecture des photos désactivée : aucune clé d’IA n’est configurée. »

#### Scenario: Deux imports au-delà du budget

- **GIVEN** le plafond du mois est atteint
- **WHEN** le worker commence la lecture de deux imports
- **THEN** deux alertes partent, regroupées sous une même empreinte

#### Scenario: Recherche de rating laissée à la saisie manuelle

- **WHEN** une recherche de rating finit en « Saisie manuelle » sans exception
- **THEN** aucune alerte ne part

### Requirement: Aucune donnée privée dans une alerte

Une alerte MUST NOT contenir de nom de joueur, de score, de message d'exception, de corps de requête, de query string, d'en-tête, de témoin, de variable locale ou de fil d'Ariane. Elle SHALL se limiter au type de l'exception ou à la raison de l'échec, à la pile d'appels, à la méthode et au chemin de la requête, et à l'environnement.

#### Scenario: Message d'exception avec un nom de joueur

- **WHEN** une exception de message `Key (name)=(Manu) already exists` est signalée
- **THEN** l'alerte porte le type de l'exception et ne contient nulle part `Manu`, et le journal du conteneur garde ce message

#### Scenario: Requête avec des données

- **WHEN** une exception survient sur `POST /rounds?player=Manu` avec un corps contenant un score `87` et un témoin de session
- **THEN** l'alerte porte la méthode `POST` et le chemin `/rounds`, et ne contient ni `Manu`, ni le corps, ni le témoin

#### Scenario: Code d'accès saisi

- **WHEN** une exception survient pendant un `POST /login` avec le code `birdie-2026`
- **THEN** l'alerte ne contient nulle part `birdie-2026`

#### Scenario: Journaux et requêtes SQL avant l'erreur

- **GIVEN** une requête a écrit un journal et lancé des requêtes SQL
- **WHEN** elle provoque ensuite une exception
- **THEN** l'alerte ne contient ni ce journal ni ces requêtes SQL

### Requirement: Suivi d'erreurs facultatif

Sans configuration du suivi d'erreurs, le système SHALL ne rien envoyer et fonctionner sans erreur. C'est le cas du développement local et des tests.

#### Scenario: Exception sans configuration

- **GIVEN** le suivi d'erreurs n'est pas configuré
- **WHEN** une requête provoque une exception non prévue
- **THEN** la réponse et le journal restent ceux d'aujourd'hui, et aucune requête ne part vers le service d'alerte

### Requirement: Application indépendante du service d'alerte

Une panne du service d'alerte MUST NOT changer la réponse à l'utilisateur ni le résultat d'un job. L'envoi d'une alerte MUST NOT dépendre de la base de données, et il SHALL rendre la main en moins de 5 secondes.

#### Scenario: Service d'alerte injoignable

- **GIVEN** le service d'alerte ne répond pas
- **WHEN** un import passe en « Échec » avec « Budget IA du mois atteint »
- **THEN** l'import reste en « Échec » avec cette raison, et le job se termine sans erreur

#### Scenario: Base de données arrêtée

- **GIVEN** le service `pgsql` est arrêté et le suivi d'erreurs est configuré
- **WHEN** une requête arrive sur `web` et échoue sur sa connexion à la base
- **THEN** une alerte part
