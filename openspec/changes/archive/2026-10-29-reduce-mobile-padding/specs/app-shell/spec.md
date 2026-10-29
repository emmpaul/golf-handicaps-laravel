## MODIFIED Requirements

### Requirement: Rangée du haut des pages
Chaque page SHALL commencer par une rangée de 44 px de haut : « Golf » à gauche (lien vers l'accueil, sauf sur l'accueil) pour une page de section, ou un lien de retour (chevron de 18 px et libellé en Geist 500, 14 px, gris) pour une sous-page. Ses actions sont à droite. La rangée défile avec la page. Le haut de la rangée MUST être à 54 px du haut de l'écran pour « Golf » seul ou suivi d'une valeur, à 44 px pour une rangée à lien de retour ou à actions ; quand la zone sûre du haut dépasse cette mesure, la rangée commence au bas de la zone sûre, sans marge ajoutée.

#### Scenario: Page de section
- **WHEN** l'utilisateur ouvre la page Joueurs à 390 px
- **THEN** « Golf » est à 16 px du bord gauche et le titre « Joueurs » commence 22 px sous la rangée

#### Scenario: Sous-page
- **WHEN** l'utilisateur ouvre la page d'un round
- **THEN** la rangée du haut montre le lien de retour « Rounds » à gauche, « Modifier » et « Supprimer » à droite, et le nom du parcours 14 px dessous

#### Scenario: Accueil
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la rangée montre « Golf » sans lien et « Se déconnecter » à droite

#### Scenario: Page de section sans zone sûre
- **GIVEN** une zone sûre du haut nulle (navigateur d'ordinateur, Safari avec sa barre d'adresse)
- **WHEN** l'utilisateur ouvre la page Handicaps
- **THEN** la rangée « Golf » commence à 54 px du haut de la page

#### Scenario: Page de section en application d'écran d'accueil
- **GIVEN** l'application ouverte depuis l'écran d'accueil d'un iPhone dont la zone sûre du haut fait 59 px
- **WHEN** l'utilisateur ouvre la page Handicaps
- **THEN** la rangée « Golf » commence à 59 px du haut de l'écran, juste sous la barre d'état, et non à 69 px

#### Scenario: Sous-page en application d'écran d'accueil
- **GIVEN** l'application ouverte depuis l'écran d'accueil d'un iPhone dont la zone sûre du haut fait 59 px
- **WHEN** l'utilisateur ouvre la page d'un round
- **THEN** la rangée du lien de retour commence à 59 px du haut de l'écran

### Requirement: Colonne unique à toute largeur
Le système SHALL présenter la mise en page du téléphone à toute largeur : jusqu'à 390 px, la page occupe l'écran ; au-delà, son contenu, la barre d'onglets et le bouton Importer tiennent dans une colonne de 390 px centrée. Décision Q3 du mainteneur : le design ne dessine pas d'écran large. Le contenu de chaque page, connexion comprise, MUST garder des marges latérales de 16 px, celles de la barre flottante.

#### Scenario: Ordinateur
- **WHEN** l'utilisateur ouvre la page Rounds à 1440 px
- **THEN** le contenu tient dans une colonne de 390 px centrée, la barre d'onglets flotte en bas de cette colonne, et aucune navigation n'est en haut

#### Scenario: Téléphone étroit
- **WHEN** l'utilisateur ouvre la page Rounds à 320 px
- **THEN** la colonne fait 320 px et les marges latérales restent de 16 px

#### Scenario: Contenu aligné sur la barre flottante
- **WHEN** l'utilisateur ouvre la page Handicaps à 390 px
- **THEN** le bord gauche de « Golf » et celui de la barre d'onglets sont tous deux à 16 px du bord de la colonne

#### Scenario: Page de connexion
- **WHEN** l'utilisateur ouvre la page de connexion à 390 px
- **THEN** son contenu est à 16 px des bords gauche et droit
