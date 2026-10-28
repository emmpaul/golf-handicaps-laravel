# Spec Delta

## Purpose

Transformer la photo d'une carte de score en round enregistré : envoi, lecture en tâche de fond, liste des imports et écran de vérification.

## ADDED Requirements

### Requirement: Mise en page de l'écran Importer
À 390 px, l'écran Importer SHALL suivre le design : titre « Importer » et lien « Saisie manuelle » à sa droite, texte « Photo de l'écran de fin de round, prise au téléphone. », « NOUVELLE CARTE », deux tuiles côte à côte, puis « IMPORTS » avec leur nombre et la liste. Cette exigence remplace la disposition de « Une action principale à l'import » (tâche 1.2).

#### Scenario: Tuiles sur iPhone
- **WHEN** l'utilisateur ouvre `/import` sur un iPhone
- **THEN** « Prendre une photo » (icône d'appareil photo ambre, « CAMÉRA ») et « Choisir une photo » (icône d'image claire, « PHOTOTHÈQUE ») sont deux tuiles de même largeur, fond `oklch(0.17 0.006 260)`, séparées par un filet de 1 px, dans un groupe aux coins de 14 px, et le champ de fichier garde le nom « Photo de la carte »

#### Scenario: Ordinateur sans écran tactile
- **WHEN** l'utilisateur ouvre `/import` sur un ordinateur sans écran tactile
- **THEN** seule la tuile « Choisir une photo » s'affiche, sur toute la largeur du groupe

#### Scenario: Aucun import
- **WHEN** la liste des imports est vide
- **THEN** l'écran affiche « IMPORTS », « 0 » et « Aucun import en attente. »

### Requirement: Ligne d'un import
Chaque import SHALL s'afficher sur une ligne : vignette de sa photo de 56 × 42 px aux coins de 6 px, en titre l'instant d'envoi à l'heure de Montréal en Geist 600, 15 px (décision Q5), et pastille d'état à droite ; dessous, ses actions en boutons de 44 px. Un import en lecture n'a aucune action. Aucun bouton « Recadrer » n'existe.

#### Scenario: Import prêt
- **GIVEN** un import prêt à vérifier, envoyé le 23 octobre 2026 à 16 h 58 à Montréal
- **WHEN** l'utilisateur ouvre `/import`
- **THEN** sa ligne montre « 23 oct. 2026 · 16:58 », la pastille ambre « À VÉRIFIER », le bouton plein « Vérifier » sur la largeur restante et un bouton carré de 44 px à contour, icône de corbeille rouge, nommé « Supprimer »

#### Scenario: Import en lecture
- **WHEN** un import est en lecture
- **THEN** sa ligne montre la pastille « LECTURE… », texte `oklch(0.8 0.12 200)` sur fond `oklch(0.8 0.12 200 / 0.15)`, et aucun bouton

#### Scenario: Import en échec
- **WHEN** un import est en « Échec » faute de budget
- **THEN** sa ligne montre la pastille rouge « ÉCHEC », la raison « Budget IA du mois atteint » en Geist 400, 13 px, gris, et les boutons « Ouvrir l'écran de correction vide » (plein), « Relancer la lecture » (contour) et « Supprimer » (corbeille)

### Requirement: Mise en page de l'écran de vérification
L'écran de vérification d'un import SHALL suivre la mise en page de l'éditeur (spec `rounds`), avec le titre « Vérifier la carte », le lien de retour « Importer », la photo 18 px sous le titre (proportions 16/10, coins de 12 px), le repère « Carte lue par l’IA, vérifie chaque case » en `oklch(0.8 0.12 200)` et l'indicateur de sauvegarde en Geist Mono 500, 11 px, gris. Le nom lu s'affiche « lu » au-dessus de « « GAB » », Geist Mono 500, 11 px, dans 64 px.

#### Scenario: Écran de la carte de référence
- **WHEN** l'utilisateur touche « Vérifier » sur l'import de la carte de référence
- **THEN** l'écran montre « Importer » en lien de retour, « Vérifier la carte », la photo, le repère IA, et sur la rangée de la ligne 1 « L1 », la liste du joueur, « lu « GAB » » et le bouton de retrait

#### Scenario: Pas de recadrage
- **WHEN** l'écran de vérification s'affiche
- **THEN** il ne propose ni « Recadrer » ni « Recadrer la photo »
