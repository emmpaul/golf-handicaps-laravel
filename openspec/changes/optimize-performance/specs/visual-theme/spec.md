## ADDED Requirements

### Requirement: Polices téléchargées une fois
Chaque graisse de Geist (400, 500, 600) et de Geist Mono (500, 600) SHALL être servie par un seul fichier WOFF2, sous-ensemble latin. La page MUST précharger ces fichiers seulement, et aucun autre format de police ne doit être déclaré. Le texte affiché avant l'arrivée d'une police MUST utiliser une police de repli aux métriques ajustées sur Geist.

#### Scenario: Premier chargement
- **GIVEN** un navigateur au cache vide
- **WHEN** il charge `/login` puis ouvre une session et arrive sur l'accueil
- **THEN** il télécharge au plus cinq fichiers de police, tous en `.woff2`, chacun une fois, et aucun fichier `.woff`

#### Scenario: Préchargements utiles
- **WHEN** le navigateur charge l'accueil
- **THEN** chaque fichier annoncé par `<link rel="preload" as="font">` est un fichier de police déclaré par une règle `@font-face` de la page, et la console ne signale aucun préchargement inutilisé

#### Scenario: Accents du français
- **GIVEN** un round sur le parcours « Pebble Beach » et un joueur nommé « Zoé Bélanger-Çaron »
- **WHEN** l'utilisateur ouvre la page Rounds
- **THEN** le nom s'affiche en Geist, accents et cédille compris, sans requête vers un fichier de police d'un autre sous-ensemble

#### Scenario: Pas de saut à l'arrivée de la police
- **GIVEN** une connexion lente qui retarde les polices
- **WHEN** l'accueil s'affiche avec la police de repli puis avec Geist
- **THEN** le décalage de mise en page cumulé (CLS) mesuré par le navigateur reste sous 0,01
