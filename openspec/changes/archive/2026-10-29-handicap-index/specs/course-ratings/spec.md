# Spec Delta

## Purpose

Donner à chaque parcours connu (nom et tees) une course rating et un slope, pour que les différentiels et le handicap de jeu tiennent compte de la difficulté du parcours. Ce changement couvre la saisie à la main ; la recherche sur Simulator Golf Tour s'y ajoutera.

## ADDED Requirements

### Requirement: Rating et slope d'un parcours
Le système SHALL associer à chaque parcours connu, identifié par son nom et ses tees sans tenir compte de la casse ni des espaces autour, au plus une course rating, à une décimale entre 40,0 et 90,0, et un slope, entier entre 55 et 155. Une rating saisie s'applique à tous les rounds joués sur ce parcours et ces tees, passés et futurs.

#### Scenario: Même parcours, autre casse
- **GIVEN** « BETHPAGE BLACK / Red » avec une rating de 71,4 et un slope de 144
- **WHEN** un round est enregistré sur « Bethpage Black / red »
- **THEN** les différentiels de ce round utilisent 71,4 et 144

#### Scenario: Autres tees
- **GIVEN** « BETHPAGE BLACK / Red » avec une rating de 71,4 et un slope de 144
- **WHEN** un round est enregistré sur « BETHPAGE BLACK / Blue »
- **THEN** ce round est sans rating

### Requirement: Saisie et correction à la main
Sur la page Parcours, le système SHALL permettre de saisir ou de corriger la rating et le slope de chaque parcours. La rating accepte une virgule ou un point et une décimale au plus ; le slope est un entier. Une valeur hors bornes, vide ou mal formée MUST être refusée sans rien enregistrer, avec « Rating entre 40,0 et 90,0 » ou « Slope entre 55 et 155 ». Une saisie acceptée entre aussitôt dans les calculs.

#### Scenario: Saisie acceptée
- **WHEN** l'utilisateur saisit « 71,4 » et « 144 » pour « BETHPAGE BLACK / Red »
- **THEN** la ligne du parcours affiche « 71,4 / 144 » et un message « Rating enregistrée. » s'affiche

#### Scenario: Point décimal
- **WHEN** l'utilisateur saisit « 90.0 » et « 155 »
- **THEN** la rating 90,0 et le slope 155 sont enregistrés

#### Scenario: Bornes basses incluses
- **WHEN** l'utilisateur saisit « 40,0 » et « 55 »
- **THEN** la rating 40,0 et le slope 55 sont enregistrés

#### Scenario: Slope hors bornes
- **WHEN** l'utilisateur saisit « 71,4 » et « 160 »
- **THEN** la saisie est refusée avec « Slope entre 55 et 155 » sous le champ du slope, et rien n'est enregistré

#### Scenario: Slope non entier
- **WHEN** l'utilisateur saisit « 71,4 » et « 144,5 »
- **THEN** la saisie est refusée avec « Slope entre 55 et 155 »

#### Scenario: Rating refusée
- **WHEN** l'utilisateur saisit pour la rating « 39,9 », « 90,1 », « 71,45 », « abc » ou rien, avec un slope de 144
- **THEN** la saisie est refusée avec « Rating entre 40,0 et 90,0 » sous le champ de la rating, et rien n'est enregistré

#### Scenario: Correction
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144 et la carte `IMG_3064` enregistrée
- **WHEN** l'utilisateur corrige à « 70,0 » et « 130 »
- **THEN** la ligne affiche « 70,0 / 130 », le différentiel de Gab vaut 1,7 et celui de Manu 15,6

#### Scenario: Parcours sans round
- **WHEN** une saisie vise un parcours et des tees sur lesquels aucun round n'est enregistré
- **THEN** elle est refusée avec « Parcours introuvable » et rien n'est enregistré

### Requirement: Retrait d'une rating
Sur la page Parcours, le système SHALL proposer « Retirer la rating » sur chaque parcours qui a une rating, et sur lui seul. Le retrait efface la rating et le slope du parcours et de ses tees, sans tenir compte de la casse ni des espaces autour : le parcours redevient « sans rating », ses rounds se calculent de nouveau sur le par total du round et le slope 113, et le message « Rating retirée. » s'affiche.

#### Scenario: Rating retirée
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144 et la carte `IMG_3064` enregistrée
- **WHEN** l'utilisateur touche « Retirer la rating » sur la ligne de « BETHPAGE BLACK / Red »
- **THEN** la ligne affiche « sans rating » et les champs « Rating » et « Slope » vides, le message « Rating retirée. » s'affiche, et les différentiels de Gab et de Manu valent 1,0 et 17,0

#### Scenario: Retrait sans tenir compte de la casse
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** une demande de retrait vise « bethpage black / RED »
- **THEN** « BETHPAGE BLACK / Red » redevient « sans rating »

#### Scenario: Parcours sans rating
- **GIVEN** « PAYNES VALLEY / Blue » sans rating
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la ligne de « PAYNES VALLEY / Blue » ne propose pas « Retirer la rating »

### Requirement: Retrait refusé
Une demande de retrait qui vise un parcours sans round MUST être refusée avec « Parcours introuvable », et une demande qui vise un parcours sans rating avec « Parcours déjà sans rating », sans rien changer.

#### Scenario: Retrait d'une rating déjà retirée
- **GIVEN** « PAYNES VALLEY / Blue » sans rating, avec un round enregistré
- **WHEN** une demande de retrait vise « PAYNES VALLEY / Blue »
- **THEN** elle est refusée avec « Parcours déjà sans rating » et rien ne change

#### Scenario: Retrait d'un parcours sans round
- **WHEN** une demande de retrait vise un parcours et des tees sur lesquels aucun round n'est enregistré
- **THEN** elle est refusée avec « Parcours introuvable » et rien ne change

### Requirement: Page Parcours
Le système SHALL afficher une page Parcours qui liste chaque parcours connu, par nom puis par tees sans tenir compte de la casse, avec ses tees, le par du round le plus récent joué dessus, et sa rating et son slope (« 71,4 / 144 ») ou « sans rating ». Sans round, la page affiche « Aucun parcours : enregistrez d'abord un round. ». La page MUST être atteignable depuis la liste des rounds et la page d'un round, sans nouvelle section dans la navigation principale.

#### Scenario: Parcours sans rating
- **GIVEN** la carte `IMG_3064` enregistrée, sans rating
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la ligne montre « BETHPAGE BLACK », « Tees Red », « Par 71 », « sans rating » et les champs « Rating » et « Slope » vides

#### Scenario: Parcours avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la ligne montre « 71,4 / 144 » et les champs pré-remplis « 71,4 » et « 144 »

#### Scenario: Aucun parcours
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la page affiche « Aucun parcours : enregistrez d'abord un round. »

#### Scenario: Navigation
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la navigation garde ses cinq sections et marque « Rounds » comme section courante

#### Scenario: Sans session
- **GIVEN** un navigateur sans session
- **WHEN** il ouvre `/courses`, envoie une saisie de rating ou une demande de retrait
- **THEN** la lecture est redirigée vers `/login`, et la saisie comme le retrait ne changent rien

### Requirement: Champs nommés par parcours
Sur la page Parcours, chaque champ de saisie et chaque bouton « Retirer la rating » SHALL porter un nom accessible qui nomme son parcours et ses tees, et la page MUST NOT défiler horizontalement à 320 px.

#### Scenario: Noms accessibles
- **WHEN** l'utilisateur ouvre `/courses` avec « BETHPAGE BLACK / Red » et « PAYNES VALLEY / Blue »
- **THEN** les champs s'appellent « Rating de BETHPAGE BLACK / Red », « Slope de BETHPAGE BLACK / Red », « Rating de PAYNES VALLEY / Blue » et « Slope de PAYNES VALLEY / Blue »

#### Scenario: Bouton de retrait nommé
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** le bouton « Retirer la rating » de cette ligne s'appelle « Retirer la rating de BETHPAGE BLACK / Red »

#### Scenario: Téléphone
- **WHEN** l'utilisateur ouvre `/courses` sur un écran de 320 px
- **THEN** chaque ligne empile le parcours, la rating et la saisie, et la page ne défile pas horizontalement
