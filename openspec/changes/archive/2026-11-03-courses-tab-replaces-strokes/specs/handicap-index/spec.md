## ADDED Requirements

### Requirement: Lien vers la page Coups
La page Handicaps SHALL afficher, à droite de son titre « Handicaps », le lien « Coups à donner » vers la page Coups (`/strokes`), dans le style des liens de titre (Geist 500, 14 px, 44 px de zone de toucher), comme « Saisie manuelle » sur la page Rounds, avec ou sans joueur. Le lien MUST NOT transmettre le joueur choisi : la page Coups garde son propre choix de joueurs.

#### Scenario: Lien affiché
- **GIVEN** Gab et Manu, Gab choisi
- **WHEN** l'utilisateur ouvre `/handicaps?joueur=<identifiant de Gab>`
- **THEN** le lien « Coups à donner » s'affiche sur la ligne du titre « Handicaps », à droite, et mène à `/strokes` sans paramètre

#### Scenario: Sans joueur
- **GIVEN** une base sans joueur
- **WHEN** l'utilisateur ouvre `/handicaps`
- **THEN** la page affiche « Aucun joueur. » et le lien « Coups à donner »

#### Scenario: Ouverture
- **WHEN** l'utilisateur touche « Coups à donner »
- **THEN** la page Coups s'ouvre et l'onglet « Handicaps » reste la section courante
