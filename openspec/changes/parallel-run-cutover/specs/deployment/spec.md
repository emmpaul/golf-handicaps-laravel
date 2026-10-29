## MODIFIED Requirements

### Requirement: Bascule depuis l'application Next documentée
`docs/deployment.md` SHALL décrire, commande par commande, le passage de l'application Next à la stack Laravel en deux temps : une phase parallèle, où Next reste en production et seule source de vérité pendant que la stack Laravel tourne sur un domaine temporaire avec une copie importée par `golf:import-sqlite`, avec la comparaison des index et des rounds ; puis le déménagement sur le domaine principal et son retour arrière. La procédure MUST marquer chaque étape du mainteneur.

#### Scenario: Copie d'une base en WAL
- **GIVEN** la base SQLite de Next en mode WAL
- **WHEN** l'opérateur lit l'étape de copie de `docs/deployment.md`
- **THEN** elle copie la base par `VACUUM INTO` et vérifie son intégrité, au lieu de copier le seul fichier `app.db`

#### Scenario: Base qui a déjà les joueurs initiaux
- **GIVEN** la stack Laravel, dont le service `migrate` a créé les joueurs de `INITIAL_PLAYERS`
- **WHEN** l'opérateur suit l'étape d'import
- **THEN** la commande documentée passe `--force` dans un terminal interactif (`docker exec -it`) et dit de répondre oui à la confirmation qui nomme `players` et `player_aliases`

#### Scenario: Domaine temporaire
- **WHEN** l'opérateur installe la stack Laravel pour la phase parallèle
- **THEN** la procédure règle `APP_URL` sur `https://golf2.emmanuelpaul.com` et relie ce domaine au service `web` dans Dokploy, sans toucher au domaine ni aux applications de Next

#### Scenario: Saisie pendant la phase parallèle
- **GIVEN** la phase parallèle en cours
- **WHEN** l'opérateur lit la procédure
- **THEN** elle dit de ne rien saisir sur le domaine temporaire, parce que le réimport suivant vide la base Laravel, et de continuer à tout saisir dans Next

#### Scenario: Recherche des ratings pendant la phase parallèle
- **GIVEN** la phase parallèle en cours
- **WHEN** l'opérateur règle les variables de la stack sur le domaine temporaire
- **THEN** la procédure met `SGT_LOOKUP=off`, et le remet à `on` au déménagement avant le dernier import

#### Scenario: Copie rafraîchie
- **GIVEN** des rounds saisis dans Next après le dernier import
- **WHEN** l'opérateur veut les comparer
- **THEN** la procédure efface les copies précédentes, refait la copie et l'import avec `--force`, sans arrêter Next

#### Scenario: Comparaison des index et des rounds
- **GIVEN** un import terminé sur le domaine temporaire
- **WHEN** l'opérateur suit l'étape de comparaison
- **THEN** elle nomme, pour chaque joueur, ce qui doit être identique entre Next et la stack Laravel (index actuel, nombre de rounds, date, parcours, brut et différentiel des derniers rounds) et ce qui peut différer (rounds saisis dans Next après la copie, recherches de rating)

#### Scenario: Déménagement
- **GIVEN** la comparaison réussie
- **WHEN** l'opérateur déménage
- **THEN** il arrête les saisies sur Next, refait une copie fraîche et l'import avec `--force`, passe `APP_URL` et le domaine principal à la stack Laravel, retire le domaine temporaire, et garde Next arrêté avec son volume

#### Scenario: Retour arrière
- **GIVEN** le déménagement fait depuis moins de 30 jours
- **WHEN** l'opérateur suit la section de retour arrière
- **THEN** il rend le domaine principal à Next et redémarre ses applications, dont le volume a été gardé, sans toucher à la stack Laravel
