# Spec Delta

## Purpose

Fixer la forme des identifiants en base : chaque enregistrement applicatif porte un UUID ordonné dans le temps, pour qu'une adresse ne révèle ni le volume ni l'ordre de création, et l'application ne garde aucune table de comptes.

## ADDED Requirements

### Requirement: Clés primaires applicatives en UUID
Chaque table d'un modèle applicatif SHALL avoir une clé primaire de type `uuid` natif PostgreSQL, générée par l'application en UUID version 7. Un test d'architecture MUST échouer quand un modèle applicatif ne génère pas d'UUID.

#### Scenario: Enregistrement créé
- **WHEN** l'application crée un enregistrement d'un modèle applicatif
- **THEN** sa clé primaire est un UUID dont le chiffre de version vaut `7`, et deux enregistrements créés l'un après l'autre ont des clés croissantes

#### Scenario: Modèle sans UUID
- **WHEN** un modèle applicatif est ajouté sans génération d'UUID
- **THEN** `make arch-test` échoue en nommant le modèle

### Requirement: Clés étrangères applicatives en UUID
Chaque clé étrangère entre tables applicatives SHALL être de type `uuid` et porter une contrainte de clé étrangère PostgreSQL.

#### Scenario: Référence vers un enregistrement absent
- **WHEN** l'application écrit une ligne dont la clé étrangère désigne un UUID qui n'existe pas dans la table cible
- **THEN** PostgreSQL refuse l'écriture

### Requirement: Tables techniques du framework
Les tables techniques du framework (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) SHALL garder les identifiants que le framework leur donne. La règle des UUID ne s'applique qu'aux tables des modèles applicatifs.

#### Scenario: Job en file
- **WHEN** un job est mis en file sur la connexion `database`
- **THEN** il est écrit dans `jobs` avec un identifiant entier, sans erreur

### Requirement: Aucune table de comptes
La base SHALL ne contenir ni table de comptes ni table de jetons de réinitialisation de mot de passe, et une session MUST NOT être liée à une personne. L'accès à l'application repose sur un code partagé, sans compte.

#### Scenario: Base migrée
- **WHEN** on applique toutes les migrations sur une base vide
- **THEN** les tables `users` et `password_reset_tokens` n'existent pas et la table `sessions` n'a pas de colonne `user_id`

#### Scenario: Session ouverte
- **WHEN** un visiteur ouvre une session avec le pilote `database`
- **THEN** la ligne de `sessions` s'écrit sans erreur et ne contient aucun identifiant de personne
