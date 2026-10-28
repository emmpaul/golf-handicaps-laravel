## Purpose

Donner à toutes les pages de l'application un cadre commun utilisable au doigt sur téléphone, y compris ajoutée à l'écran d'accueil, et propre sur ordinateur : navigation, installation, zones sûres, largeur, cibles tactiles, version et messages éphémères.

## ADDED Requirements

### Requirement: Navigation entre sections sur téléphone
Sous 768 px de large, le système SHALL afficher les cinq sections (Importer, Rounds, Handicaps, Coups, Joueurs) dans une barre fixe en bas de l'écran, chacune avec une icône et son libellé, toutes visibles sans défilement dès 320 px. La barre MUST rester au-dessus de la barre d'accueil de l'appareil et ne jamais masquer le contenu de la page.

#### Scenario: Toutes les sections visibles à 320 px
- **WHEN** l'utilisateur ouvre `/import` sur un écran de 320 px de large
- **THEN** les liens « Importer », « Rounds », « Handicaps », « Coups » et « Joueurs » sont tous entièrement visibles dans la barre du bas

#### Scenario: Bas de page atteignable
- **WHEN** l'utilisateur fait défiler une page jusqu'en bas sur téléphone
- **THEN** la ligne de version, dernier élément de la page, s'affiche au-dessus de la barre de navigation, sans être recouverte

#### Scenario: Ordinateur
- **WHEN** l'utilisateur ouvre l'application sur un écran de 1024 px de large
- **THEN** les cinq sections sont dans la navigation du haut et aucune barre n'est fixée en bas

### Requirement: Adresses des sections
Les liens de section SHALL viser `/import` (Importer), `/rounds` (Rounds), `/handicaps` (Handicaps), `/strokes` (Coups) et `/players` (Joueurs), dans cet ordre. Tant qu'une section n'a pas sa page, son adresse et ses sous-adresses MUST répondre 200 avec une page « Section à venir » dans le cadre commun, sans donnée du groupe.

#### Scenario: Section sans page
- **GIVEN** la section Handicaps n'a pas encore sa page
- **WHEN** l'utilisateur ouvre `/handicaps`
- **THEN** le système répond 200 avec le titre « Handicaps », le texte « Section à venir », la navigation et la ligne de version

#### Scenario: Sous-adresse d'une section sans page
- **GIVEN** la section Rounds n'a pas encore sa page
- **WHEN** l'utilisateur ouvre `/rounds/0190a6b2-7c3e-7000-8000-000000000001`
- **THEN** le système répond 200 avec la page « Section à venir » de Rounds

### Requirement: Section courante indiquée
La navigation SHALL marquer la section de la page affichée, sur téléphone comme sur ordinateur, de façon visible et annoncée aux lecteurs d'écran (`aria-current="page"`). Une sous-page MUST marquer sa section parente : une adresse sous `/imports/` relève d'Importer, une adresse sous `/courses/` relève de Rounds.

#### Scenario: Sous-page d'un round
- **WHEN** l'utilisateur ouvre une adresse sous `/rounds/`
- **THEN** le lien « Rounds » porte `aria-current="page"` et aucun autre lien de section ne le porte

#### Scenario: Import ouvert
- **WHEN** l'utilisateur ouvre une adresse sous `/imports/`
- **THEN** le lien « Importer » porte `aria-current="page"`

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
Sur la barre d'onglets du bas, la section courante SHALL se distinguer des autres par sa couleur principale et par une forme (pastille derrière l'icône), pas seulement par la graisse du texte.

#### Scenario: Onglet Rounds courant
- **WHEN** l'utilisateur ouvre une sous-page de Rounds sur téléphone
- **THEN** l'onglet « Rounds » a une pastille derrière son icône et la couleur principale, les quatre autres n'en ont pas

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
L'application SHALL occuper tout l'écran, encoche et barre d'accueil comprises. L'en-tête, la barre de navigation du bas et toute barre fixée en bas de page MUST tenir compte des marges de zone sûre de l'appareil, pour qu'aucun texte ni bouton ne passe sous l'encoche, l'îlot ou la barre d'accueil.

#### Scenario: En-tête sous l'encoche
- **WHEN** l'application est ouverte en plein écran sur un iPhone à îlot
- **THEN** la marque et « Golf » s'affichent sous l'îlot, pas derrière

#### Scenario: Barre d'onglets au-dessus de la barre d'accueil
- **WHEN** l'application est ouverte depuis l'écran d'accueil d'un iPhone avec barre d'accueil
- **THEN** les libellés de la barre d'onglets s'affichent entièrement au-dessus de la barre d'accueil

### Requirement: En-tête net sous la barre d'état
Sur iPhone, dans Safari comme en application ajoutée à l'écran d'accueil, la marque et le texte de l'en-tête SHALL rester nets : aucun flou ni bandeau du navigateur ne les recouvre, en haut de page comme après défilement. La zone sous la barre d'état MUST prendre la couleur de l'en-tête, en mode clair comme en mode sombre.

#### Scenario: Safari en haut de page
- **WHEN** l'utilisateur ouvre l'accueil dans Safari sur iPhone
- **THEN** la marque et le mot « Golf » sont nets, et la zone sous la barre d'état a la couleur de l'en-tête

#### Scenario: Après défilement
- **WHEN** l'utilisateur fait défiler une page plus haute que l'écran dans Safari sur iPhone
- **THEN** l'en-tête reste en haut, net, et aucun texte de la page n'apparaît flou derrière la barre d'état

#### Scenario: Mode sombre
- **WHEN** l'iPhone est en mode sombre et l'accueil est ouvert dans Safari
- **THEN** la zone sous la barre d'état a la couleur sombre de l'en-tête et le mot « Golf » est net

#### Scenario: Application ajoutée à l'écran d'accueil
- **WHEN** l'utilisateur ouvre l'application depuis son icône d'écran d'accueil
- **THEN** l'en-tête commence sous la barre d'état, net, sans bande d'une autre couleur au-dessus

#### Scenario: En-tête opaque
- **WHEN** une page s'affiche à 390 px, en clair puis en sombre
- **THEN** l'en-tête est collé en haut de la fenêtre, son fond calculé est entièrement opaque et sans filtre d'arrière-plan, et il a la même couleur que le fond de `html` et de `body`

### Requirement: Pas de défilement horizontal de la page
Dès 320 px de large, aucune page ni aucun état ouvert (dialogue, liste, calendrier) MUST NOT élargir la page au-delà de l'écran. Sous 768 px, aucun contenu MUST NOT défiler horizontalement dans son propre cadre. À partir de 768 px, un contenu plus large que l'écran SHALL défiler dans son propre cadre. Aucune information MUST NOT être masquée sans moyen de la voir.

#### Scenario: Pages du cadre commun
- **WHEN** l'utilisateur ouvre l'accueil puis chacune des cinq sections à 320 puis 390 px
- **THEN** sur chacune, la largeur de la page reste égale à celle de l'écran

#### Scenario: Boîte de dialogue à 320 px
- **WHEN** une boîte de confirmation s'ouvre sur un écran de 320 px de large
- **THEN** la largeur de la page reste égale à celle de l'écran et la boîte tient entièrement dans l'écran

### Requirement: Cibles tactiles et texte des champs sur téléphone
Sous 768 px, chaque bouton, champ, liste déroulante et lien d'action SHALL offrir une zone de toucher d'au moins 44 px de haut, et le texte des champs et des listes déroulantes MUST faire au moins 16 px, pour qu'iOS ne zoome pas au focus. À partir de 768 px, les contrôles gardent leur taille d'ordinateur.

#### Scenario: Bouton sur téléphone
- **WHEN** un bouton de taille normale s'affiche sur un écran de 390 px
- **THEN** il mesure au moins 44 px de haut

#### Scenario: Liste déroulante sur iPhone
- **WHEN** une liste déroulante s'affiche sur un écran de 390 px
- **THEN** elle mesure au moins 44 px de haut et son texte fait 16 px

#### Scenario: Champ sur iPhone
- **WHEN** un champ de saisie s'affiche sur un écran de 390 px
- **THEN** il mesure au moins 44 px de haut et son texte fait 16 px

#### Scenario: Ordinateur inchangé
- **WHEN** un bouton de taille normale s'affiche à 1440 px de large
- **THEN** il mesure 32 px de haut

### Requirement: Contenu défilant signalé
À partir de 768 px, un contenu plus large que sa zone qui défile dans son propre cadre SHALL montrer qu'il reste du contenu à voir, par un fondu au bord du cadre de chaque côté où il en reste. La colonne des libellés de ligne MUST rester visible pendant le défilement horizontal. Le cadre MUST se faire défiler au clavier.

#### Scenario: Cadre au début
- **WHEN** un cadre de 300 px montre un contenu de 900 px, au début
- **THEN** un fondu apparaît au bord droit et aucun au bord gauche

#### Scenario: Cadre au milieu
- **WHEN** l'utilisateur fait défiler ce cadre de 200 px
- **THEN** un fondu apparaît aux deux bords

#### Scenario: Cadre à la fin
- **WHEN** l'utilisateur fait défiler ce cadre jusqu'au bout
- **THEN** le fondu est au bord gauche seulement, et la colonne des libellés reste visible à gauche

#### Scenario: Contenu entier visible
- **WHEN** le cadre s'élargit jusqu'à contenir tout le contenu
- **THEN** aucun fondu n'apparaît

### Requirement: Message éphémère
Quand le serveur joint à sa réponse un message pour l'utilisateur (une erreur ou une confirmation), le système SHALL l'afficher sur la page suivante dans un message éphémère aux couleurs du thème, l'annoncer aux lecteurs d'écran et permettre de le fermer. Une erreur MUST se distinguer d'une confirmation par sa couleur et son annonce.

#### Scenario: Erreur métier
- **WHEN** le serveur renvoie l'utilisateur sur la page avec le message d'erreur « Ce joueur existe déjà. »
- **THEN** la page affiche « Ce joueur existe déjà. » dans un message de la couleur destructive, annoncé aussitôt par le lecteur d'écran

#### Scenario: Confirmation
- **WHEN** le serveur renvoie l'utilisateur avec le message de confirmation « Round enregistré. »
- **THEN** la page affiche « Round enregistré. » dans un message neutre, annoncé poliment

#### Scenario: Visite suivante
- **WHEN** l'utilisateur ouvre une autre page après avoir vu le message
- **THEN** le message n'est pas affiché une seconde fois

#### Scenario: Fermeture
- **WHEN** l'utilisateur ferme le message
- **THEN** il disparaît
