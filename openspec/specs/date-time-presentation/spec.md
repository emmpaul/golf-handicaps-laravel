# date-time-presentation Specification

## Purpose
Garantir qu'un instant est stocké et comparé en UTC, et que chaque date ou heure montrée au groupe est exprimée dans le fuseau du groupe, `America/Montreal`, heure avancée comprise, quel que soit le fuseau du serveur ou du poste.

## Requirements

### Requirement: Stockage en UTC
L'application SHALL écrire, lire et comparer chaque instant en UTC, en PHP comme dans PostgreSQL. Le fuseau de l'application reste `UTC` dans tous les environnements.

#### Scenario: Instant enregistré
- **WHEN** l'application enregistre l'instant 2026-11-01 23:30 heure de Montréal (heure normale)
- **THEN** la base contient `2026-11-02 04:30:00` en UTC

### Requirement: Fuseau d'affichage configuré
L'application SHALL avoir un seul fuseau d'affichage, `America/Montreal` par défaut, lu dans sa configuration. Changer ce réglage MUST suffire à changer le fuseau de chaque date ou heure affichée.

#### Scenario: Valeur par défaut
- **WHEN** l'application démarre avec le `.env.example` du dépôt
- **THEN** son fuseau d'affichage vaut `America/Montreal` et son fuseau de stockage `UTC`

### Requirement: Instants convertis avant affichage
Chaque instant envoyé à une page SHALL être converti de l'UTC vers le fuseau d'affichage avant d'être formaté. Une valeur brute lue dans une requête SQL MUST être lue comme de l'UTC avant cette conversion.

#### Scenario: Soir d'hiver
- **WHEN** l'instant `2026-11-02T04:30:00Z` est formaté en date et heure
- **THEN** l'affichage vaut `2026-11-01 23:30`

#### Scenario: Soir d'été
- **WHEN** l'instant `2026-07-15T03:30:00Z` est formaté en date et heure
- **THEN** l'affichage vaut `2026-07-14 23:30`

#### Scenario: Valeur brute d'une jointure
- **WHEN** la chaîne `2026-11-02 04:30:00` lue d'une colonne sans fuseau est formatée en date
- **THEN** l'affichage vaut `2026-11-01`

### Requirement: Bornes d'un jour local
Le début d'un jour donné SHALL être calculé à minuit dans le fuseau d'affichage, puis exprimé en UTC. Un filtre « ce jour-là » MUST couvrir de ce début inclus au début du jour suivant exclu, y compris les jours de 23 h et de 25 h du changement d'heure.

#### Scenario: Jour d'été
- **WHEN** on demande le début du jour `2026-07-15`
- **THEN** le résultat vaut `2026-07-15T04:00:00Z`

#### Scenario: Jour du retour à l'heure normale
- **WHEN** on demande les bornes du jour `2026-11-01`
- **THEN** le début vaut `2026-11-01T04:00:00Z` et la fin exclue `2026-11-02T05:00:00Z`, soit 25 heures

#### Scenario: Jour du passage à l'heure avancée
- **WHEN** on demande les bornes du jour `2026-03-08`
- **THEN** le début vaut `2026-03-08T05:00:00Z` et la fin exclue `2026-03-09T04:00:00Z`, soit 23 heures

### Requirement: Fuseau fixe pour les tests frontend
Les tests frontend SHALL tourner sous le fuseau `America/Montreal`, pour qu'une date lue au mauvais fuseau (une date `AAAA-MM-JJ` lue comme minuit UTC, par exemple) fasse échouer un test sur tout poste et en CI.

#### Scenario: Date sans heure
- **WHEN** un test frontend lit `new Date('2026-11-02')` et affiche le jour local
- **THEN** il obtient le 1er novembre, ce qui rend le défaut visible
