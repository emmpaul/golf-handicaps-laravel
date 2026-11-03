## MODIFIED Requirements

### Requirement: Navigation entre sections sur téléphone
Le système SHALL afficher quatre sections (Rounds, Handicaps, Parcours, Joueurs) dans une barre flottante en bas de l'écran, suivie du bouton Importer. La barre fait 60 px de haut, à 16 px des bords et 28 px au-dessus du bas de la zone sûre, fond `oklch(0.2 0.006 260)`, filet `oklch(0.27 0.008 260)`, rayon 999 px. Chaque onglet a son icône et son nom accessible ; seul l'onglet courant montre son libellé. La barre MUST NOT masquer le contenu de la page.

#### Scenario: Toutes les sections visibles à 320 px
- **WHEN** l'utilisateur ouvre `/handicaps` sur un écran de 320 px de large
- **THEN** les quatre onglets « Rounds », « Handicaps », « Parcours », « Joueurs » et le bouton « Importer » sont entièrement visibles, chacun de 44 px de haut au moins, et le libellé « Handicaps » est affiché en entier

#### Scenario: Libellé « Parcours » à 320 px
- **WHEN** l'utilisateur ouvre `/courses` sur un écran de 320 px de large
- **THEN** le libellé « Parcours » est affiché en entier, et la barre et le bouton « Importer » tiennent entre les marges de 16 px

#### Scenario: Plus d'onglet Coups
- **WHEN** l'utilisateur ouvre n'importe quelle page de section
- **THEN** la barre ne contient aucun lien nommé « Coups » ni aucun lien vers `/strokes`

#### Scenario: Bas de page atteignable
- **WHEN** l'utilisateur fait défiler une page jusqu'en bas sur téléphone
- **THEN** la ligne de version, dernier élément de la page, s'affiche au-dessus de la barre flottante, sans être recouverte, la page ayant une marge basse de 108 px plus la zone sûre

#### Scenario: Ordinateur
- **WHEN** l'utilisateur ouvre l'application sur un écran de 1024 px de large
- **THEN** la même barre flottante est en bas de la colonne centrée, et aucune navigation n'est en haut

### Requirement: Adresses des sections
Les onglets SHALL viser `/rounds` (Rounds), `/handicaps` (Handicaps), `/courses` (Parcours) et `/players` (Joueurs), dans cet ordre, puis le bouton Importer `/import`. Chaque section MUST servir sa propre page ; aucune adresse ne sert de page « Section à venir », et une sous-adresse qu'aucune page ne sert répond 404. La page Coups reste servie à `/strokes`, hors de la barre.

#### Scenario: Section sans page
- **GIVEN** les cinq sections ont leur page
- **WHEN** l'utilisateur ouvre `/handicaps` puis `/courses`
- **THEN** le système répond 200 avec la page Handicaps puis la page Parcours, et aucune des deux réponses ne contient « Section à venir »

#### Scenario: Sous-adresse d'une section sans page
- **WHEN** l'utilisateur ouvre `/handicaps/0190a6b2-7c3e-7000-8000-000000000001`, puis `/strokes/xyz`
- **THEN** le système répond 404 les deux fois

#### Scenario: Section qui a sa page
- **WHEN** l'utilisateur ouvre `/rounds`, `/handicaps`, `/courses`, `/players` puis `/import`
- **THEN** chacune répond 200 avec la page de sa section, et aucune réponse ne contient « Section à venir »

#### Scenario: Page Coups toujours servie
- **WHEN** l'utilisateur ouvre `/strokes` avec une session
- **THEN** le système répond 200 avec la page Coups

#### Scenario: Sous-adresse inconnue de l'import
- **WHEN** l'utilisateur ouvre `/import/xyz`
- **THEN** le système répond 404

#### Scenario: Ordre de la barre
- **WHEN** l'utilisateur parcourt la barre au clavier
- **THEN** il passe par « Rounds », « Handicaps », « Parcours », « Joueurs » puis « Importer »

### Requirement: Section courante indiquée
La navigation SHALL marquer la section de la page affichée, de façon visible et annoncée aux lecteurs d'écran (`aria-current="page"`). Une sous-page MUST marquer sa section parente : la page Coups, `/strokes`, relève de Handicaps. Le bouton Importer porte `aria-current="page"` sur `/import`.

#### Scenario: Sous-page d'un round
- **WHEN** l'utilisateur ouvre une adresse sous `/rounds/`
- **THEN** le lien « Rounds » porte `aria-current="page"` et aucun autre lien de section ne le porte

#### Scenario: Import ouvert
- **WHEN** l'utilisateur ouvre `/import`
- **THEN** le bouton « Importer » porte `aria-current="page"` et aucun onglet ne le porte

#### Scenario: Parcours
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** le lien « Parcours » porte `aria-current="page"` et le lien « Rounds » ne le porte pas

#### Scenario: Coups
- **WHEN** l'utilisateur ouvre `/strokes`
- **THEN** le lien « Handicaps » porte `aria-current="page"` et aucun autre lien de section ne le porte

#### Scenario: Accueil
- **WHEN** l'utilisateur ouvre la page d'accueil
- **THEN** aucun lien de section ne porte `aria-current`

#### Scenario: Préfixe sans barre oblique
- **WHEN** l'utilisateur ouvre `/roundsx`, puis `/coursesx`
- **THEN** ni le lien « Rounds » ni le lien « Parcours » ne porte `aria-current`
