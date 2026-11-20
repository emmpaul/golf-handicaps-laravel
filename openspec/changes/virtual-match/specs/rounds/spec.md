# Spec Delta

## MODIFIED Requirements

### Requirement: Liens et état vide de la page Rounds
La page Rounds SHALL donner les liens « Comparer » et « Saisie manuelle ». « Comparer » ouvre la page Partie virtuelle (spec `virtual-match`) ; la page Parcours s'ouvre par son onglet (spec `app-shell`), sans lien sur cette page. Sans round, elle affiche « Aucun round. Importez une carte pour commencer. ».

#### Scenario: Lien vers les parcours
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la page ne montre aucun lien « Parcours » à côté du titre, et l'onglet « Parcours » de la barre ouvre la page Parcours

#### Scenario: Lien vers la partie virtuelle
- **WHEN** l'utilisateur touche « Comparer » sur `/rounds`
- **THEN** la page `/rounds/compare` s'ouvre, sans joueur-round choisi

#### Scenario: Aucun round
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la page affiche « Aucun round. Importez une carte pour commencer. » et le lien « Saisie manuelle »

### Requirement: Mise en page de la page Rounds
À 390 px, la page Rounds SHALL suivre le design : titre « Rounds » 22 px sous la rangée du haut, liens « Comparer » et « Saisie manuelle » à droite du titre (Geist 500, 14 px, 16 px d'écart), filet 12 px dessous, puis une ligne par round en grille de trois colonnes (44 px, reste, contenu) espacées de 14 px, 16 px de marge verticale, filet entre deux lignes.

#### Scenario: Colonne de date
- **WHEN** l'utilisateur ouvre `/rounds` avec le round de référence
- **THEN** « 17 » est en Geist Mono 500, 22 px, et « OCT » 4 px dessous en Geist Mono 500, 10 px, approche 0,08 em, gris

#### Scenario: Colonnes du parcours et du gagnant
- **WHEN** la ligne du round de référence s'affiche
- **THEN** « BETHPAGE BLACK » est en Geist 600, 15 px, suivi de « · Red » en graisse 400 grise ; « Gab 72 » et « Manu 88 » sont en Geist Mono 500, 12 px, gris, chacun après une pastille de 6 px à la couleur du joueur ; « 1ER NET » est en Geist Mono 500, 10 px, gris, et le gagnant dessous en Geist 600, 14 px, ambre

#### Scenario: Deux liens à 320 px
- **WHEN** l'utilisateur ouvre `/rounds` sur un écran de 320 px
- **THEN** « Comparer » et « Saisie manuelle » sont entièrement visibles, chacun avec une zone de toucher de 44 px de haut au moins, et la page ne défile pas horizontalement

### Requirement: Mise en page de la page d'un round
À 390 px, la page d'un round SHALL suivre le design : nom du parcours en titre de page 14 px sous la rangée du haut, méta en Geist Mono 500, 12 px, gris, 8 px dessous, libellés « CLASSEMENT NET » et « BRUT − HCP » 30 px plus bas, une ligne par joueur classé, l'encadré de suggestion ou le lien « Comparer avec d'autres rounds » (spec `virtual-match`) 24 px plus bas, puis la carte de score 44 px plus bas et la légende 16 px sous la carte.

#### Scenario: Ligne du classement
- **WHEN** l'utilisateur ouvre le round de référence à 390 px
- **THEN** chaque ligne a 18 px de marge verticale : le rang en Geist Mono 500, 18 px, gris, dans 22 px ; le nom en Geist 600, 22 px, après une pastille de 10 px à la couleur du joueur ; « 72 − 1 » dessous en Geist Mono 500, 15 px, gris ; le net à droite en Geist Mono 500, 44 px

#### Scenario: Rangées de la carte
- **WHEN** la carte de score s'affiche à 390 px
- **THEN** chaque rangée de trou fait 44 px de haut, le numéro en Geist Mono 500, 17 px, dans 40 px, « P4 · 8 » en Geist Mono 500, 12 px, gris, chaque score dans une colonne de 52 px ; les rangées de sous-total font 40 px, fond `oklch(0.17 0.006 260)`, rayon 8 px

#### Scenario: Encadré de suggestion
- **GIVEN** un round avec une suggestion de partie virtuelle
- **WHEN** l'utilisateur ouvre ce round à 390 px
- **THEN** l'encadré vient entre le classement et la carte : fond `oklch(0.17 0.006 260)`, coins de 12 px, texte en Geist 400, 14 px, lien « Voir la partie virtuelle » en Geist 600, 14 px, ambre, d'une zone de toucher de 44 px de haut au moins
