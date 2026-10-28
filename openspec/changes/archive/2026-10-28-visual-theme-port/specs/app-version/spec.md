## ADDED Requirements

### Requirement: Version affichée dans l'interface
Chaque page du cadre commun SHALL afficher en bas une ligne discrète avec la version en cours, préfixée de `v`, suivie de « · » et des 7 premiers caractères du commit quand il est connu. Pour toute version autre que `0.0.0`, la ligne MUST être un lien vers la release GitHub de cette version ; en `0.0.0`, elle reste du texte. Sous 768 px, le lien offre une zone de toucher de 44 px de haut.

#### Scenario: Version publiée avec commit
- **GIVEN** l'application est à la version `0.6.0` et connaît le commit `4794e1b0c0ffee00000000000000000000000000`
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bas de page affiche « v0.6.0 · 4794e1b », lié à `https://github.com/emmpaul/golf-handicaps-laravel/releases/tag/v0.6.0`

#### Scenario: Version publiée sans commit
- **GIVEN** l'application est à la version `0.6.0` sans commit connu
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bas de page affiche « v0.6.0 », lié à la même release

#### Scenario: Avant la première version
- **GIVEN** `version.txt` contient `0.0.0`
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bas de page affiche « v0.0.0 » sans lien

#### Scenario: Commit mal formé
- **GIVEN** le commit fourni à l'application vaut `4794e1b`
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bas de page affiche la version seule, sans commit
