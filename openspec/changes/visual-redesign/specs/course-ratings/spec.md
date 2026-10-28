# Spec Delta

## Purpose

Tenir la course rating et le slope de chaque parcours et de ses tees, et les montrer sur la page Parcours pour la saisie, la correction et la recherche.

## ADDED Requirements

### Requirement: Mise en page de la page Parcours
À 390 px, la page Parcours SHALL suivre le design : lien de retour « Rounds », titre « Parcours », texte « Course rating et slope par parcours et tees. Une valeur ne compte dans les index qu'une fois confirmée. », filet 20 px dessous, puis un bloc par parcours avec 18 px de marge verticale. Cette exigence est réconciliée par la tâche 1.2 avec la « Page Parcours » de 6 et les ajouts de 9.

#### Scenario: Bloc d'un parcours sans rating
- **GIVEN** la carte `IMG_3064` enregistrée, sans rating, et 3 rounds sur ce parcours
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** le bloc montre « BETHPAGE BLACK » en Geist 600, 17 px, suivi de « · Red » gris, « PAR 71 » à droite en Geist Mono 500, 12 px, gris, puis la pastille neutre « SANS RATING » et « 3 rounds »

#### Scenario: Bloc d'un parcours avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la pastille montre « 71,4 / 144 · CONFIRMÉ », texte `oklch(0.8 0.14 145)` sur fond `oklch(0.8 0.14 145 / 0.15)`

#### Scenario: Saisie
- **WHEN** le bloc d'un parcours s'affiche à 390 px
- **THEN** les champs « RATING » (indication « ex. 71,4 ») et « SLOPE » (indication « ex. 144 ») et le bouton plein « Enregistrer » sont sur une rangée de 48 px, en deux colonnes égales et une colonne au contenu, espacées de 8 px ; le texte des champs est en Geist Mono 500, 16 px

#### Scenario: Liens d'action
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** le bloc s'affiche
- **THEN** « Relancer la recherche » et « Retirer la rating » s'affichent sous la saisie en liens soulignés, Geist 500, 13 px, gris, de 40 px de haut, et gardent leurs noms accessibles qui nomment le parcours et ses tees
