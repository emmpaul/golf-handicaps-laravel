# app-shell Specification

## Purpose
Donner à toutes les pages de l'application un cadre commun utilisable au doigt sur téléphone, y compris ajoutée à l'écran d'accueil, et propre sur ordinateur : navigation, installation, zones sûres, largeur, cibles tactiles, version et messages éphémères.

## Requirements

### Requirement: Navigation entre sections sur téléphone
Le système SHALL afficher quatre sections (Rounds, Handicaps, Coups, Joueurs) dans une barre flottante en bas de l'écran, suivie du bouton Importer. La barre fait 60 px de haut, à 16 px des bords et 28 px au-dessus du bas de la zone sûre, fond `oklch(0.2 0.006 260)`, filet `oklch(0.27 0.008 260)`, rayon 999 px. Chaque onglet a son icône et son nom accessible ; seul l'onglet courant montre son libellé. La barre MUST NOT masquer le contenu de la page.

#### Scenario: Toutes les sections visibles à 320 px
- **WHEN** l'utilisateur ouvre `/handicaps` sur un écran de 320 px de large
- **THEN** les quatre onglets « Rounds », « Handicaps », « Coups », « Joueurs » et le bouton « Importer » sont entièrement visibles, chacun de 44 px de haut au moins, et le libellé « Handicaps » est affiché en entier

#### Scenario: Bas de page atteignable
- **WHEN** l'utilisateur fait défiler une page jusqu'en bas sur téléphone
- **THEN** la ligne de version, dernier élément de la page, s'affiche au-dessus de la barre flottante, sans être recouverte, la page ayant une marge basse de 108 px plus la zone sûre

#### Scenario: Ordinateur
- **WHEN** l'utilisateur ouvre l'application sur un écran de 1024 px de large
- **THEN** la même barre flottante est en bas de la colonne centrée, et aucune navigation n'est en haut

### Requirement: Adresses des sections
Les onglets SHALL viser `/rounds` (Rounds), `/handicaps` (Handicaps), `/strokes` (Coups) et `/players` (Joueurs), dans cet ordre, puis le bouton Importer `/import`. Chaque section MUST servir sa propre page ; aucune adresse ne sert de page « Section à venir », et une sous-adresse qu'aucune page ne sert répond 404.

#### Scenario: Section sans page
- **GIVEN** les cinq sections ont leur page
- **WHEN** l'utilisateur ouvre `/handicaps` puis `/strokes`
- **THEN** le système répond 200 avec la page Handicaps puis la page Coups, et aucune des deux réponses ne contient « Section à venir »

#### Scenario: Sous-adresse d'une section sans page
- **WHEN** l'utilisateur ouvre `/handicaps/0190a6b2-7c3e-7000-8000-000000000001`, puis `/strokes/xyz`
- **THEN** le système répond 404 les deux fois

#### Scenario: Section qui a sa page
- **WHEN** l'utilisateur ouvre `/rounds`, `/handicaps`, `/strokes`, `/players` puis `/import`
- **THEN** chacune répond 200 avec la page de sa section, et aucune réponse ne contient « Section à venir »

#### Scenario: Sous-adresse inconnue de l'import
- **WHEN** l'utilisateur ouvre `/import/xyz`
- **THEN** le système répond 404

#### Scenario: Ordre de la barre
- **WHEN** l'utilisateur parcourt la barre au clavier
- **THEN** il passe par « Rounds », « Handicaps », « Coups », « Joueurs » puis « Importer »

### Requirement: Section courante indiquée
La navigation SHALL marquer la section de la page affichée, de façon visible et annoncée aux lecteurs d'écran (`aria-current="page"`). Une sous-page MUST marquer sa section parente : une adresse sous `/courses/` relève de Rounds. Le bouton Importer porte `aria-current="page"` sur `/import`.

#### Scenario: Sous-page d'un round
- **WHEN** l'utilisateur ouvre une adresse sous `/rounds/`
- **THEN** le lien « Rounds » porte `aria-current="page"` et aucun autre lien de section ne le porte

#### Scenario: Import ouvert
- **WHEN** l'utilisateur ouvre `/import`
- **THEN** le bouton « Importer » porte `aria-current="page"` et aucun onglet ne le porte

#### Scenario: Parcours
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** le lien « Rounds » porte `aria-current="page"`

#### Scenario: Accueil
- **WHEN** l'utilisateur ouvre la page d'accueil
- **THEN** aucun lien de section ne porte `aria-current`

#### Scenario: Préfixe sans barre oblique
- **WHEN** l'utilisateur ouvre `/roundsx`
- **THEN** le lien « Rounds » ne porte pas `aria-current`

### Requirement: Section courante visible sur la barre d'onglets
Sur la barre, l'onglet courant SHALL se distinguer par une pilule de 44 px de haut en `oklch(0.97 0.003 260)`, son icône et son libellé (Geist 600, 13 px, 8 px après l'icône) en `oklch(0.13 0.005 260)` ; les autres onglets n'ont ni fond ni libellé visible, icône en `oklch(0.68 0.01 260)`. Le passage d'un onglet à l'autre anime le fond, la couleur et la largeur du libellé en 350 ms.

#### Scenario: Onglet Rounds courant
- **WHEN** l'utilisateur ouvre une sous-page de Rounds sur téléphone
- **THEN** l'onglet « Rounds » a la pilule claire et son libellé visible, les trois autres onglets n'ont ni pilule ni libellé visible et gardent leur nom accessible

### Requirement: Installation sur l'écran d'accueil
Le système SHALL fournir un manifest d'application (nom « Handicaps golf », nom court « Golf », affichage `standalone`, adresse de départ `/`, langue `fr`) et une icône d'application propre, de 180 px pour iOS, et de 192 et 512 px pour le manifest. Ouverte depuis l'écran d'accueil, l'application MUST s'afficher sans barre de navigateur.

#### Scenario: Manifest servi
- **WHEN** le navigateur charge le manifest référencé par la page
- **THEN** il obtient `name` égal à « Handicaps golf », `short_name` à « Golf », `display` à `standalone`, `start_url` à `/` et des icônes PNG de 192 et 512 px

#### Scenario: Icône iOS
- **WHEN** Safari iOS cherche l'icône d'écran d'accueil de la page
- **THEN** il trouve une image PNG de 180 × 180 px

#### Scenario: Manifest et icônes sans session
- **WHEN** un client sans cookie demande le manifest ou une icône
- **THEN** il reçoit le fichier, sans redirection et sans en-tête `Set-Cookie`

### Requirement: Zones sûres en plein écran
L'application SHALL occuper tout l'écran, encoche et barre d'accueil comprises. La rangée du haut, la barre flottante, le bouton Importer et toute barre collée en bas de page MUST tenir compte des marges de zone sûre de l'appareil, pour qu'aucun texte ni bouton ne passe sous l'encoche, l'îlot ou la barre d'accueil.

#### Scenario: En-tête sous l'encoche
- **WHEN** l'application est ouverte en plein écran sur un iPhone à îlot
- **THEN** « Golf » et les actions de la rangée du haut s'affichent sous l'îlot, pas derrière

#### Scenario: Barre d'onglets au-dessus de la barre d'accueil
- **WHEN** l'application est ouverte depuis l'écran d'accueil d'un iPhone avec barre d'accueil
- **THEN** la barre flottante et le bouton Importer sont entièrement au-dessus de la barre d'accueil

### Requirement: En-tête net sous la barre d'état
Sur iPhone, dans Safari comme en application ajoutée à l'écran d'accueil, la zone sous la barre d'état SHALL rester opaque, de la couleur du fond de la page, en haut de page comme après défilement : aucun texte de la page ne passe flou ou lisible derrière la barre d'état. La rangée du haut n'est pas collée.

#### Scenario: Safari en haut de page
- **WHEN** l'utilisateur ouvre l'accueil dans Safari sur iPhone
- **THEN** « Golf » est net sous la barre d'état, et la zone sous la barre d'état a la couleur du fond

#### Scenario: Après défilement
- **WHEN** l'utilisateur fait défiler une page plus haute que l'écran dans Safari sur iPhone
- **THEN** la rangée du haut part avec la page, et aucun texte de la page n'apparaît derrière la barre d'état

#### Scenario: Mode sombre
- **WHEN** l'iPhone est en mode clair ou sombre et l'accueil est ouvert dans Safari
- **THEN** la zone sous la barre d'état a la couleur `#060709` et « Golf » est net

#### Scenario: Application ajoutée à l'écran d'accueil
- **WHEN** l'utilisateur ouvre l'application depuis son icône d'écran d'accueil
- **THEN** la rangée du haut commence sous la barre d'état, sans bande d'une autre couleur au-dessus

#### Scenario: En-tête opaque
- **WHEN** une page s'affiche à 390 px
- **THEN** la bande collée en haut, de la hauteur de la zone sûre, a un fond calculé entièrement opaque égal au fond de `html` et de `body`, sans filtre d'arrière-plan

### Requirement: Pas de défilement horizontal de la page
Dès 320 px de large, aucune page ni aucun état ouvert (feuille, liste, calendrier) MUST NOT élargir la page au-delà de l'écran, et aucun contenu MUST NOT défiler horizontalement dans son propre cadre, sauf la carte de score d'un round, qui garde des colonnes de score de 44 px et défile alors dans son cadre. Aucune information MUST NOT être masquée sans moyen de la voir : un nom trop long se coupe par « … » et reste lisible en entier ailleurs sur la page ou dans son nom accessible.

#### Scenario: Pages du cadre commun
- **WHEN** l'utilisateur ouvre l'accueil, chacune des quatre sections et `/import` à 320 puis 390 px
- **THEN** sur chacune, la largeur de la page reste égale à celle de l'écran

#### Scenario: Boîte de dialogue à 320 px
- **WHEN** la feuille de confirmation s'ouvre sur un écran de 320 px de large
- **THEN** la largeur de la page reste égale à celle de l'écran et la feuille tient entièrement dans l'écran

#### Scenario: Carte de score à quatre joueurs
- **WHEN** l'utilisateur ouvre à 320 px un round de quatre joueurs
- **THEN** les colonnes des joueurs passent de 52 à 44 px, « P4 · 16 » reste entier, un nom trop long pour sa colonne finit par « … », la carte défile horizontalement dans son cadre et la page ne défile pas horizontalement

### Requirement: Cibles tactiles et texte des champs sur téléphone
Chaque bouton, champ, liste déroulante, onglet et lien d'action SHALL offrir une zone de toucher d'au moins 44 px de haut, et le texte des champs et des listes déroulantes MUST faire 16 px, pour qu'iOS ne zoome pas au focus. Les champs et listes du design font 48 px, les boutons de liste 44 px, le bouton principal collé en bas 56 px.

#### Scenario: Bouton sur téléphone
- **WHEN** le bouton « Ajouter » de la page Joueurs s'affiche sur un écran de 390 px
- **THEN** il mesure 48 px de haut

#### Scenario: Liste déroulante sur iPhone
- **WHEN** la liste du parcours de la page Coups s'affiche sur un écran de 390 px
- **THEN** elle mesure 48 px de haut et son texte fait 16 px

#### Scenario: Champ sur iPhone
- **WHEN** le champ « Nouveau joueur » s'affiche sur un écran de 390 px
- **THEN** il mesure 48 px de haut et son texte fait 16 px

#### Scenario: Ordinateur inchangé
- **WHEN** le bouton « Ajouter » s'affiche à 1440 px de large
- **THEN** il mesure 48 px de haut, comme sur téléphone

### Requirement: Message éphémère
Quand le serveur joint à sa réponse un message pour l'utilisateur (une erreur ou une confirmation), le système SHALL l'afficher sur la page suivante dans une pilule centrée à 52 px sous le haut de la zone sûre, Geist 600, 14 px, marges 12 px 16 px, pendant 2,2 s. Une confirmation est claire avec une coche (`oklch(0.97 0.003 260)`, texte `oklch(0.13 0.005 260)`) ; une erreur MUST s'en distinguer par sa pilule rouge sans coche (décision Q7) et son annonce. Toucher la pilule la ferme.

#### Scenario: Erreur métier
- **WHEN** le serveur renvoie l'utilisateur sur la page avec le message d'erreur « Ce joueur existe déjà. »
- **THEN** la page affiche « Ce joueur existe déjà. » dans une pilule `oklch(0.7 0.17 25)` au texte `oklch(0.13 0.005 260)`, sans coche, annoncée aussitôt par le lecteur d'écran

#### Scenario: Confirmation
- **WHEN** le serveur renvoie l'utilisateur avec le message de confirmation « Round enregistré. »
- **THEN** la page affiche « Round enregistré. » dans la pilule claire avec la coche, annoncée poliment

#### Scenario: Visite suivante
- **WHEN** l'utilisateur ouvre une autre page après avoir vu le message
- **THEN** le message n'est pas affiché une seconde fois

#### Scenario: Fermeture
- **WHEN** l'utilisateur touche la pilule ou son bouton « Fermer le message »
- **THEN** elle disparaît

### Requirement: Bouton Importer
À droite de la barre d'onglets, le système SHALL afficher un bouton rond de 60 px, fond ambre `oklch(0.78 0.16 60)`, icône d'appareil photo de 24 px en `oklch(0.13 0.005 260)`, qui ouvre `/import` sous le nom accessible « Importer ». Quand des imports sont prêts à vérifier, une pastille claire de 20 px en haut à droite du bouton MUST en donner le nombre.

#### Scenario: Un import à vérifier
- **GIVEN** un import prêt à vérifier, sans round
- **WHEN** l'utilisateur ouvre la page Rounds
- **THEN** le bouton « Importer » porte la pastille « 1 », texte `oklch(0.13 0.005 260)` sur fond `oklch(0.97 0.003 260)`, Geist Mono 600, 11 px

#### Scenario: Aucun import à vérifier
- **GIVEN** aucun import prêt à vérifier
- **WHEN** l'utilisateur ouvre la page Rounds
- **THEN** le bouton « Importer » n'a pas de pastille

#### Scenario: Ouverture
- **WHEN** l'utilisateur touche le bouton « Importer »
- **THEN** la page `/import` s'ouvre

### Requirement: Rangée du haut des pages
Chaque page SHALL commencer par une rangée de 44 px de haut : « Golf » à gauche (lien vers l'accueil, sauf sur l'accueil) pour une page de section, ou un lien de retour (chevron de 18 px et libellé en Geist 500, 14 px, gris) pour une sous-page. Ses actions sont à droite. La rangée défile avec la page.

#### Scenario: Page de section
- **WHEN** l'utilisateur ouvre la page Joueurs à 390 px
- **THEN** « Golf » est à 20 px du bord gauche et le titre « Joueurs » commence 22 px sous la rangée

#### Scenario: Sous-page
- **WHEN** l'utilisateur ouvre la page d'un round
- **THEN** la rangée du haut montre le lien de retour « Rounds » à gauche, « Modifier » et « Supprimer » à droite, et le nom du parcours 14 px dessous

#### Scenario: Accueil
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la rangée montre « Golf » sans lien et « Se déconnecter » à droite

### Requirement: Colonne unique à toute largeur
Le système SHALL présenter la mise en page du téléphone à toute largeur : jusqu'à 390 px, la page occupe l'écran ; au-delà, son contenu, la barre d'onglets et le bouton Importer tiennent dans une colonne de 390 px centrée. Décision Q3 du mainteneur : le design ne dessine pas d'écran large.

#### Scenario: Ordinateur
- **WHEN** l'utilisateur ouvre la page Rounds à 1440 px
- **THEN** le contenu tient dans une colonne de 390 px centrée, la barre d'onglets flotte en bas de cette colonne, et aucune navigation n'est en haut

#### Scenario: Téléphone étroit
- **WHEN** l'utilisateur ouvre la page Rounds à 320 px
- **THEN** la colonne fait 320 px et les marges latérales restent de 20 px

### Requirement: Feuille de confirmation
Une action à confirmer SHALL ouvrir une feuille montant du bas de l'écran sur un voile `rgba(0, 0, 0, 0.6)` : fond `oklch(0.17 0.006 260)`, filet du haut `oklch(0.27 0.008 260)`, coins du haut de 22 px, marges 24 px 20 px 34 px, titre en Geist 600, 20 px, texte en Geist 400, 14 px, puis le bouton d'action et « Annuler », de 52 px chacun. Toucher le voile MUST fermer la feuille sans rien faire. À l'ouverture, le focus MUST aller sur « Annuler », jamais sur l'action destructive.

#### Scenario: Voile touché
- **WHEN** l'utilisateur ouvre la feuille de suppression d'un round puis touche le voile
- **THEN** la feuille se ferme et le round reste

#### Scenario: Échap
- **WHEN** l'utilisateur ouvre la feuille au clavier puis appuie sur Échap
- **THEN** la feuille se ferme et le focus revient sur « Supprimer » de la rangée du haut

#### Scenario: Focus initial sur « Annuler »
- **WHEN** l'utilisateur ouvre au clavier la feuille de suppression d'un round puis appuie sur Entrée
- **THEN** la feuille se ferme et le round reste

### Requirement: Libellé de section
Une liste ou un groupe de champs SHALL être introduit par un libellé de section : Geist Mono 500, 11 px, approche 0,08 em, capitales, gris `oklch(0.68 0.01 260)`, 10 px au-dessus d'un filet de 1 px `oklch(0.27 0.008 260)` ; une valeur à droite (compte, unité) prend le même style.

#### Scenario: Imports
- **GIVEN** un import
- **WHEN** l'utilisateur ouvre `/import`
- **THEN** la liste est introduite par « IMPORTS » à gauche et « 1 » à droite, au-dessus d'un filet

### Requirement: Navigation masquée pendant l'édition
Sur les écrans de l'éditeur de round (nouveau round, modification d'un round, vérification d'un import), le système SHALL masquer la barre d'onglets et le bouton Importer : le lien de retour de la rangée du haut et la barre d'enregistrement en tiennent lieu.

#### Scenario: Vérification d'un import
- **WHEN** l'utilisateur ouvre l'écran de vérification d'un import prêt à vérifier
- **THEN** ni la barre d'onglets ni le bouton « Importer » ne sont affichés, et le lien de retour « Importer » ramène à `/import`

#### Scenario: Page d'un round
- **WHEN** l'utilisateur ouvre la page d'un round
- **THEN** la barre d'onglets et le bouton « Importer » sont affichés
