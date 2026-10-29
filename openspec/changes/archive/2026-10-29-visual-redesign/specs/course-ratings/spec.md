# Spec Delta

## Purpose

Tenir la course rating et le slope de chaque parcours et de ses tees, et les montrer sur la page Parcours pour la saisie, la correction et la recherche.

## MODIFIED Requirements

### Requirement: Page Parcours
Le système SHALL afficher une page Parcours qui liste chaque parcours connu, par nom puis par tees sans tenir compte de la casse, en un bloc par parcours : le nom suivi de « · » et des tees, le par du round le plus récent joué dessus (« PAR 71 »), une pastille de rating (« 71,4 / 144 », suivie de « · CONFIRMÉ » quand la recherche du parcours est « Confirmé », ou « SANS RATING ») et le nombre de rounds joués sur ce parcours et ces tees (« 3 rounds », « 1 round »). Sans round, la page affiche « Aucun parcours : enregistrez d'abord un round. ». La page MUST être atteignable depuis la liste des rounds et la page d'un round, sans nouvel onglet dans la barre de navigation.

#### Scenario: Parcours sans rating
- **GIVEN** la carte `IMG_3064` enregistrée, sans rating, et 3 rounds sur ce parcours
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** le bloc montre « BETHPAGE BLACK » suivi de « · Red », « PAR 71 », la pastille neutre « SANS RATING », « 3 rounds » et les champs « Rating » et « Slope » vides

#### Scenario: Parcours avec rating
- **GIVEN** « BETHPAGE BLACK / Red » confirmé à 71,4 / 144
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la pastille montre « 71,4 / 144 · CONFIRMÉ », texte `oklch(0.8 0.14 145)` sur fond `oklch(0.8 0.14 145 / 0.15)`, et les champs sont pré-remplis « 71,4 » et « 144 »

#### Scenario: Rating gardée pendant une nouvelle proposition
- **GIVEN** « BETHPAGE BLACK / Red » confirmé à 71,4 / 144, dont la recherche relancée est en « Proposition à confirmer »
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la pastille de rating montre « 71,4 / 144 » sans « · CONFIRMÉ », et le statut « Proposition à confirmer » s'affiche à côté

#### Scenario: Aucun parcours
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la page affiche « Aucun parcours : enregistrez d'abord un round. »

#### Scenario: Navigation
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la barre garde ses quatre onglets et le bouton Importer, et marque « Rounds » comme section courante

#### Scenario: Sans session
- **GIVEN** un navigateur sans session
- **WHEN** il ouvre `/courses`, envoie une saisie de rating ou une demande de retrait
- **THEN** la lecture est redirigée vers `/login`, et la saisie comme le retrait ne changent rien

## ADDED Requirements

### Requirement: Mise en page de la page Parcours
À 390 px, la page Parcours SHALL suivre le design : lien de retour « Rounds », titre « Parcours », texte « Course rating et slope par parcours et tees. Une valeur ne compte dans les index qu'une fois confirmée. », filet 20 px dessous, puis un bloc par parcours avec 18 px de marge verticale et un filet entre deux blocs.

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

### Requirement: Recherche SGT dans le bloc d'un parcours
Entre la pastille de rating et la saisie, le bloc d'un parcours SHALL montrer dans le style du design tout ce que la recherche sur Simulator Golf Tour a trouvé (spec ci-dessus, « Statuts de la recherche » et « Parcours SGT et proposition sur la page Parcours ») : statut en pastille d'état et note, parcours SGT et ses liens, proposition, tees lus ou candidats et leurs actions.

#### Scenario: Couleur des statuts
- **WHEN** la page Parcours affiche les statuts de recherche
- **THEN** « Recherche en cours » est en `oklch(0.8 0.12 200)` ; « Proposition à confirmer », « Parcours SGT à choisir » et « Tee à choisir » en ambre `oklch(0.78 0.16 60)` ; « Recherche échouée » en `oklch(0.7 0.17 25)` ; « Saisie manuelle » et « Introuvable sur SGT » en pastille neutre ; la note suit en Geist 400, 13 px, gris

#### Scenario: Parcours SGT et liens
- **GIVEN** un parcours SGT retenu
- **WHEN** le bloc s'affiche à 390 px
- **THEN** « SGT : {nom} » est en Geist 500, 14 px, son lieu en gris, et « Carte SGT » et « The Course View » sont des liens soulignés, Geist 500, 13 px, gris, de 40 px de haut

#### Scenario: Lignes de choix
- **GIVEN** des tees lus ou des candidats SGT
- **WHEN** le bloc s'affiche à 390 px
- **THEN** chaque tee ou candidat occupe une ligne de 52 px séparée par un filet `oklch(0.21 0.006 260)`, avec un bouton à contour de 44 px

#### Scenario: Proposition à confirmer
- **GIVEN** la recherche de « BETHPAGE BLACK / Red » aboutie sur le parcours SGT 30, sans rating
- **WHEN** l'utilisateur ouvre `/courses` à 390 px
- **THEN** le bloc montre la pastille « SANS RATING », la pastille ambre « Proposition à confirmer », « SGT : Bethpage Black » et « Farmingdale, NY », les liens « Carte SGT » et « The Course View », puis « Proposition : tee Red, 71,4 / 144 » et le bouton à contour « Confirmer » de 44 px, au-dessus de la saisie

#### Scenario: Recherche en cours
- **GIVEN** « CALANOVA GOLF CLUB / Yellow » en « Recherche en cours »
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** le bloc montre la pastille « Recherche en cours », texte `oklch(0.8 0.12 200)` sur fond `oklch(0.8 0.12 200 / 0.15)`, et l'indicateur d'attente à côté

#### Scenario: Candidats à 320 px
- **GIVEN** « SANCTUARY COVE / Blue » à choisir entre « Sanctuary » et « The Pines Sanctuary Cove »
- **WHEN** l'utilisateur ouvre `/courses` sur un écran de 320 px
- **THEN** chaque candidat occupe sa ligne avec son lieu, « Par 72 », son lien « Carte SGT » et le bouton « Choisir », et la page ne défile pas horizontalement

#### Scenario: Recherche échouée
- **GIVEN** « PAYNES VALLEY / Blue » en « Recherche échouée » avec la note « SGT injoignable »
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** le bloc montre la pastille rouge « Recherche échouée », « SGT injoignable » en gris, et le lien « Relancer la recherche » sous la saisie
