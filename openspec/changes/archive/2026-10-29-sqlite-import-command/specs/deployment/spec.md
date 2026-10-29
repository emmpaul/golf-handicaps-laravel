# Spec Delta

## ADDED Requirements

### Requirement: Bascule depuis l'application Next documentée
`docs/deployment.md` SHALL décrire la bascule de l'application Next vers la stack Laravel, commande par commande : répétition sur le staging, arrêt de Next, copie cohérente de sa base SQLite et de ses photos, import avec `golf:import-sqlite`, lecture du rapport, bascule du domaine, contrôles, retour arrière. La procédure MUST désigner chaque étape que seul le mainteneur exécute.

#### Scenario: Copie d'une base en WAL
- **GIVEN** la base SQLite de Next en mode WAL
- **WHEN** l'opérateur lit l'étape de copie de `docs/deployment.md`
- **THEN** elle copie la base par `VACUUM INTO` et vérifie son intégrité, au lieu de copier le seul fichier `app.db`

#### Scenario: Base qui a déjà les joueurs initiaux
- **GIVEN** la stack Laravel de production, dont le service `migrate` a créé les joueurs de `INITIAL_PLAYERS`
- **WHEN** l'opérateur suit l'étape d'import
- **THEN** la commande documentée passe `--force` dans un terminal interactif (`docker exec -it`) et dit de répondre oui à la confirmation qui nomme `players` et `player_aliases`

#### Scenario: Retour arrière
- **GIVEN** la bascule faite depuis moins de 30 jours
- **WHEN** l'opérateur suit la section de retour arrière
- **THEN** il rebascule le domaine sur Next et redémarre ses applications, dont le volume a été gardé, sans toucher à la stack Laravel
