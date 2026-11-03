## MODIFIED Requirements

### Requirement: Page Parcours
Le système SHALL afficher une page Parcours qui liste chaque parcours connu, par nom puis par tees sans tenir compte de la casse, en un bloc par parcours. Sans round, la page affiche « Aucun parcours : enregistrez d'abord un round. ». La page MUST être une section de la barre de navigation, l'onglet « Parcours », et rester atteignable depuis la page d'un round par sa rating.

#### Scenario: Aucun parcours
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la page affiche « Aucun parcours : enregistrez d'abord un round. »

#### Scenario: Navigation
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la barre garde ses quatre onglets et le bouton Importer, et marque « Parcours » comme section courante

#### Scenario: Ouverture par l'onglet
- **WHEN** l'utilisateur touche l'onglet « Parcours » depuis la page Rounds
- **THEN** la page Parcours s'ouvre

#### Scenario: Plus de lien depuis la liste des rounds
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la page n'affiche plus de lien « Parcours » à côté de son titre ; « Saisie manuelle » reste

#### Scenario: Depuis la page d'un round
- **WHEN** l'utilisateur touche la rating (« 71,4 / 144 » ou « SANS RATING ») sur la page d'un round
- **THEN** la page Parcours s'ouvre

#### Scenario: Sans session
- **GIVEN** un navigateur sans session
- **WHEN** il ouvre `/courses`, envoie une saisie de rating ou une demande de retrait
- **THEN** la lecture est redirigée vers `/login`, et la saisie comme le retrait ne changent rien

### Requirement: Mise en page de la page Parcours
À 390 px, la page Parcours SHALL suivre le design d'une page de section : rangée du haut « Golf » (lien vers l'accueil), titre « Parcours », texte « Course rating et slope par parcours et tees. Une valeur ne compte dans les index qu'une fois confirmée. », filet 20 px dessous, puis un bloc par parcours avec 18 px de marge verticale et un filet entre deux blocs.

#### Scenario: Rangée du haut
- **WHEN** l'utilisateur ouvre `/courses` à 390 px
- **THEN** la rangée du haut montre « Golf », lien vers l'accueil, sans lien de retour « Rounds », et le titre « Parcours » commence 22 px sous la rangée

#### Scenario: En-tête d'un bloc
- **GIVEN** la carte `IMG_3064` enregistrée, sans rating, et 3 rounds sur ce parcours
- **WHEN** l'utilisateur ouvre `/courses` à 390 px
- **THEN** « BETHPAGE BLACK » est en Geist 600, 17 px, suivi de « · Red » en graisse 400 grise, « PAR 71 » à droite en Geist Mono 500, 12 px, gris ; 10 px dessous, la pastille (Geist Mono 500, 12 px) puis « 3 rounds » gris

#### Scenario: Saisie
- **WHEN** le bloc d'un parcours s'affiche à 390 px
- **THEN** 14 px sous la pastille, les champs « RATING » (indication « ex. 71,4 ») et « SLOPE » (indication « ex. 144 ») et le bouton plein « Enregistrer » sont sur une rangée de 48 px, en deux colonnes égales et une colonne au contenu, espacées de 8 px ; le texte des champs est en Geist Mono 500, 16 px

#### Scenario: Liens d'action
- **GIVEN** « BETHPAGE BLACK / Red » confirmé à 71,4 / 144
- **WHEN** le bloc s'affiche
- **THEN** « Relancer la recherche » et « Retirer la rating » s'affichent sous la saisie en liens soulignés, Geist 500, 13 px, gris, de 40 px de haut, et gardent leurs noms accessibles qui nomment le parcours et ses tees
