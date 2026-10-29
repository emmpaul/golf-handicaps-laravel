# Spec Delta

## ADDED Requirements

### Requirement: Thème sombre seul
L'application SHALL s'afficher dans le thème sombre du design, quel que soit le réglage clair ou sombre de l'appareil, sans réglage dans l'application. La page MUST déclarer `color-scheme: dark` pour que les contrôles natifs (listes, barres de défilement) soient sombres. Décision Q1 du mainteneur : le design ne définit aucun thème clair.

#### Scenario: Téléphone en mode clair
- **WHEN** l'utilisateur ouvre l'accueil sur un téléphone réglé en mode clair
- **THEN** la page s'affiche sur le fond `oklch(0.13 0.005 260)` (`#060709`) avec le texte `oklch(0.97 0.003 260)` (`#F4F5F7`)

#### Scenario: Couleur de la barre d'état
- **WHEN** le navigateur lit la page d'accueil
- **THEN** il trouve une seule balise `theme-color`, de valeur `#060709`, sans attribut `media`

#### Scenario: Aucun réglage d'apparence
- **WHEN** l'utilisateur parcourt l'application
- **THEN** aucune page ni aucun contrôle ne permet de choisir le thème, et aucune réponse ne pose de cookie `appearance`

### Requirement: Échelle typographique
Chaque texte SHALL prendre l'un des styles nommés du design, avec sa famille, sa graisse, sa taille, son interlignage et son approche. Aucun autre style de texte MUST NOT apparaître dans l'interface.

#### Scenario: Styles en Geist
- **WHEN** une page affiche un titre de page, un nom, un texte courant, un bouton et un champ
- **THEN** le titre de page est en Geist 600, 28 px, interlignage 1,05, approche −0,03 em ; le nom de marque « Golf » en Geist 600, 15 px ; le texte courant en Geist 400, 13 px, interlignage 1,5 ; le texte d'un bouton en Geist 600, 14 ou 15 px ; le texte d'un champ en Geist 500, 16 px

#### Scenario: Styles en Geist Mono
- **WHEN** une page affiche un libellé de section, un libellé de colonne, une méta et un grand nombre
- **THEN** le libellé de section est en Geist Mono 500, 11 px, approche 0,08 em, en capitales ; le libellé de colonne en Geist Mono 500, 10 px, approche 0,08 em, en capitales ; la méta en Geist Mono 500, 12 px ; l'index du classement de l'accueil et le net d'un round en Geist Mono 500, 44 px, approche −0,04 em ; l'index de la page Handicaps en Geist Mono 500, 88 px, approche −0,06 em

### Requirement: Rayons, ombre et filets
Chaque coin arrondi SHALL prendre l'un des rayons du design et chaque élément flottant l'ombre unique du design. Les séparations de liste MUST être des filets de 1 px, plus marqués sous un libellé de section qu'entre deux lignes.

#### Scenario: Rayons
- **WHEN** une page affiche une pilule, une feuille, un bouton principal collé en bas, une tuile, un champ, un sous-total et une case de coups
- **THEN** leurs rayons valent respectivement 999 px, 22 px (coins du haut), 14 px, 14 px (groupe de tuiles), 10 px, 8 px et 6 px

#### Scenario: Ombre
- **WHEN** la barre d'onglets, le bouton Importer et un toast s'affichent
- **THEN** chacun porte l'ombre `0 10px 30px rgba(0, 0, 0, 0.5)` et aucun autre élément ne porte d'ombre

#### Scenario: Filets
- **WHEN** une liste s'affiche sous un libellé de section
- **THEN** le filet sous le libellé est `oklch(0.27 0.008 260)` et le filet entre deux lignes `oklch(0.21 0.006 260)`, chacun de 1 px

### Requirement: Couleur de joueur
Chaque joueur SHALL avoir une couleur, prise dans la palette de six du design selon son rang de création (le premier créé prend la première couleur), en boucle après la sixième. La couleur MUST rester la même après un renommage et MUST NOT être la seule marque d'un joueur : son nom l'accompagne toujours.

#### Scenario: Joueurs de développement
- **GIVEN** les joueurs Gab, Manu puis Sean créés dans cet ordre
- **WHEN** l'utilisateur ouvre la page Joueurs
- **THEN** la pastille de Gab est `oklch(0.72 0.24 145)`, celle de Manu `oklch(0.62 0.22 260)` et celle de Sean `oklch(0.75 0.19 55)`

#### Scenario: Septième joueur
- **GIVEN** six joueurs existent
- **WHEN** l'utilisateur ajoute un septième joueur
- **THEN** sa pastille est `oklch(0.72 0.24 145)`, comme celle du premier

#### Scenario: Renommage
- **WHEN** l'utilisateur renomme Manu en « Emmanuel »
- **THEN** la pastille d'Emmanuel reste `oklch(0.62 0.22 260)`

### Requirement: Pastilles d'état
Les états courts (premier net, provisoire, à vérifier, lecture, confirmé, sans rating, échec) SHALL s'afficher en pastille : Geist Mono 500, 10 ou 12 px, en capitales, rayon 999 px, texte dans la couleur de l'état sur un fond de cette couleur à 15 %. Un état neutre prend le texte gris sur la surface `oklch(0.22 0.006 260)`.

#### Scenario: Premier net
- **WHEN** la page d'un round montre le vainqueur net
- **THEN** sa pastille « 1ER » a le texte `oklch(0.78 0.16 60)` sur le fond `oklch(0.78 0.16 60 / 0.15)`

#### Scenario: Parcours sans rating
- **WHEN** la page Parcours montre un parcours sans rating
- **THEN** sa pastille « SANS RATING » a le texte `oklch(0.68 0.01 260)` sur le fond `oklch(0.22 0.006 260)`

### Requirement: Contraste sur le thème sombre
Le texte courant et le texte secondaire MUST garder au moins 4,5:1 avec chacun de leurs fonds (page, surfaces 1 à 3, pastilles), et les marques non textuelles (contour de focus, marque « au-dessus du par ») au moins 3:1 contre le fond de la page. Trois cas exactement en sont exemptés et gardent la valeur du design (décision Q4 du mainteneur) : contour de champ, case à cocher décochée, texte du bouton rouge « Supprimer ». Aucune autre paire MUST NOT passer sous ces seuils.

#### Scenario: Texte secondaire
- **WHEN** une page affiche du texte secondaire `oklch(0.68 0.01 260)` sur le fond de la page puis sur la surface `oklch(0.22 0.006 260)`
- **THEN** son contraste vaut au moins 4,5:1 dans les deux cas (6,98:1 et 6,01:1)

#### Scenario: Contour de champ
- **WHEN** un champ s'affiche sur le fond de la page
- **THEN** son contour est `oklch(0.27 0.008 260)`, à 1,34:1 contre le fond : exception nommée, sous le seuil de 3:1

#### Scenario: Case à cocher décochée
- **WHEN** la page Coups affiche la case d'un joueur non choisi
- **THEN** sa bordure est `oklch(0.4 0.01 260)`, à 2,18:1 contre le fond : exception nommée, sous le seuil de 3:1

#### Scenario: Bouton de suppression
- **WHEN** la feuille de confirmation affiche le bouton « Supprimer » sur le fond `oklch(0.7 0.17 25)`
- **THEN** son texte est blanc (`#FFFFFF`), à 2,88:1 : exception nommée, sous le seuil de 4,5:1

#### Scenario: Aucune autre exception
- **WHEN** le test de contraste parcourt les paires de texte et de marque du thème
- **THEN** seules les trois paires exemptées passent sous leur seuil, et une quatrième paire sous le seuil le fait échouer en la nommant

### Requirement: Couleurs hors des paires de contraste
Quatre gris du thème SHALL rester hors des paires de contraste et MUST NOT servir de texte lisible : `oklch(0.5 0.01 260)` sert seulement à un contrôle désactivé (exempté par WCAG 1.4.3) ou au décor ; `oklch(0.4 0.01 260)`, `oklch(0.3 0.008 260)` et `oklch(0.21 0.006 260)` au décor masqué aux lecteurs d'écran et aux traits non porteurs d'information. Un texte indicatif lisible prend au moins `oklch(0.68 0.01 260)`.

#### Scenario: Indication d'un champ
- **WHEN** le champ « RATING » de la page Parcours affiche son indication « ex. 71,4 » sur la surface `oklch(0.17 0.006 260)`
- **THEN** l'indication est en `oklch(0.68 0.01 260)`, à au moins 4,5:1, et jamais en `oklch(0.5 0.01 260)` ni `oklch(0.4 0.01 260)`

#### Scenario: Couleurs hors des paires
- **WHEN** un composant écrit un texte actif, une indication de champ ou une méta en `oklch(0.5 0.01 260)` ou `oklch(0.4 0.01 260)`
- **THEN** `make test-frontend` échoue en nommant le fichier ; seul un contrôle désactivé peut prendre `oklch(0.5 0.01 260)`

### Requirement: Palette du design
L'application SHALL utiliser la palette du design, commune à toutes les pages. Chaque couleur, chaque police, chaque rayon et chaque ombre MUST venir d'un token déclaré une seule fois. La couleur destructive reste distincte de la couleur principale et de l'accent ambre.

#### Scenario: Fonds et encres
- **WHEN** une page s'affiche
- **THEN** le fond vaut `oklch(0.13 0.005 260)`, les surfaces `oklch(0.17 0.006 260)`, `oklch(0.2 0.006 260)` et `oklch(0.22 0.006 260)`, le texte et le fond du bouton principal `oklch(0.97 0.003 260)`, le texte secondaire et les indications de champ `oklch(0.68 0.01 260)`, le texte d'un bouton bloqué `oklch(0.5 0.01 260)`

#### Scenario: Couleurs porteuses de sens
- **WHEN** une page affiche l'accent, le rouge, l'information et la confirmation
- **THEN** l'accent (bouton Importer, imports à vérifier, premier net, provisoire, case vide) vaut `oklch(0.78 0.16 60)`, le rouge (sous le par, suppression, baisse de forme) `oklch(0.7 0.17 25)`, l'information (lecture en cours, repère IA) `oklch(0.8 0.12 200)` et la confirmation (rating confirmée, moyenne bonne) `oklch(0.8 0.14 145)`

#### Scenario: Palette des joueurs
- **WHEN** le thème déclare les couleurs de joueur
- **THEN** elles valent, dans l'ordre, `oklch(0.72 0.24 145)`, `oklch(0.62 0.22 260)`, `oklch(0.75 0.19 55)`, `oklch(0.72 0.17 200)`, `oklch(0.58 0.26 300)` et `oklch(0.85 0.19 105)`

#### Scenario: Aucune couleur hors des tokens
- **WHEN** un composant ou une page de l'interface contient une couleur écrite en dur (`#1b1b18`, `oklch(…)`, `rgb(…)`, une classe `bg-[#…]`) ou une classe de couleur Tailwind par défaut (`text-gray-500`)
- **THEN** `make test-frontend` échoue en nommant le fichier, ou la classe ne produit aucun style

### Requirement: Animations du design
Le système SHALL animer, comme le design, l'entrée des pages, l'appui sur un élément, la barre d'onglets, les onglets de joueur, le changement d'index, les barres de différentiels, les cases à cocher, le sélecteur de mode, les cases de coups, les tuiles d'import, le bouton d'enregistrement, le toast et la feuille. La courbe par défaut est `cubic-bezier(0.2, 0.8, 0.2, 1)`, notée « courbe » ci-dessous.

#### Scenario: Appui
- **WHEN** l'utilisateur appuie sur une ligne de liste, un onglet, un onglet de joueur, le bouton Importer, une tuile d'import ou le bouton « Enregistrer » de l'éditeur
- **THEN** la ligne passe à l'opacité 0,6 en 150 ms ; l'onglet à l'échelle 0,94, l'onglet de joueur à 0,96, le bouton Importer à 0,92, la tuile à 0,97 et le bouton « Enregistrer » à 0,98, chacun en 150 ms

#### Scenario: Changement d'onglet
- **WHEN** l'utilisateur passe de l'onglet Rounds à l'onglet Handicaps
- **THEN** le fond, la couleur et le remplissage (12 → 14 px) des deux onglets changent en 350 ms (courbe), le libellé « Handicaps » s'ouvre de 0 à 90 px de largeur maximale et de 0 à 8 px de marge en 350 ms (courbe), son opacité de 0 à 1 en 250 ms (`ease`), et celui de « Rounds » se referme de même

#### Scenario: Changement de joueur sur la page Handicaps
- **WHEN** l'utilisateur touche l'onglet de Manu alors que Gab est affiché
- **THEN** l'onglet de Manu prend son fond, sa couleur et sa bordure en 300 ms (courbe) ; l'index de Gab s'efface (opacité en 250 ms `ease`) en montant de 10 px (350 ms, courbe), l'index de Manu le remplace 140 ms plus tard en redescendant à sa place ; chaque barre de différentiel change de hauteur en 600 ms (courbe) et de couleur en 300 ms (`ease`)

#### Scenario: Barres à l'ouverture
- **WHEN** l'utilisateur ouvre la page Handicaps
- **THEN** chaque barre de différentiel part de 4 px de haut et grandit jusqu'à sa hauteur en 600 ms (courbe), et chaque segment de la barre de répartition du bloc STATS part d'une largeur nulle et s'élargit jusqu'à sa part en 700 ms (courbe), de nouveau à chaque changement de joueur

#### Scenario: Case à cocher
- **WHEN** l'utilisateur coche Sean sur la page Coups
- **THEN** le fond et la bordure de la case changent en 200 ms (`ease`), la coche apparaît en 200 ms (`ease`) et passe de l'échelle 0,4 à 1 en 300 ms (`cubic-bezier(0.3, 1.6, 0.5, 1)`)

#### Scenario: Sélecteur de mode
- **WHEN** l'utilisateur passe du mode « Écart » au mode « Complet »
- **THEN** le curseur clair glisse d'une demi-largeur en 350 ms (courbe), les libellés changent de couleur en 300 ms (courbe) et les cases de coups changent de fond en 300 ms (`ease`)

#### Scenario: Tuile d'import au survol
- **WHEN** le pointeur survole la tuile « Choisir une photo »
- **THEN** son fond passe à `oklch(0.2 0.006 260)` en 200 ms

#### Scenario: Bouton d'enregistrement
- **WHEN** le dernier problème de l'éditeur est corrigé
- **THEN** le bouton « Enregistrer » passe du fond `oklch(0.22 0.006 260)` au fond `oklch(0.97 0.003 260)` et du texte `oklch(0.5 0.01 260)` au texte `oklch(0.13 0.005 260)` en 300 ms (`ease`)

#### Scenario: Toast
- **WHEN** un message éphémère apparaît
- **THEN** il descend de 16 px et passe de l'échelle 0,96 à 1 en 400 ms (courbe), son opacité de 0 à 1 en 250 ms (`ease`), reste 2,2 s puis repart de même

#### Scenario: Feuille
- **WHEN** la feuille de confirmation s'ouvre puis se ferme
- **THEN** le voile change d'opacité en 300 ms (`ease`) et la feuille monte depuis le bas de l'écran (translation de 100 % à 0) en 400 ms (courbe), puis redescend de même

#### Scenario: Même onglet touché
- **WHEN** l'utilisateur touche l'onglet de la section où il est déjà
- **THEN** la page remonte en haut par un défilement doux, sans rejouer l'entrée de page

## MODIFIED Requirements

### Requirement: Polices auto-hébergées
Les textes, titres, noms et boutons SHALL s'afficher en Geist, les chiffres, libellés de section, libellés de colonne et métas en Geist Mono. Les fichiers de police MUST être servis par l'application elle-même : aucune page ne charge de ressource depuis un autre domaine, Google Fonts compris.

#### Scenario: Titre de page
- **WHEN** l'utilisateur ouvre la page Rounds
- **THEN** le titre « Rounds » s'affiche en Geist 600 et le libellé « 1ER NET » en Geist Mono 500

#### Scenario: Aucune requête vers un tiers
- **WHEN** le navigateur charge l'accueil
- **THEN** toutes les requêtes de la page, polices comprises, visent le domaine de l'application, et aucune ne vise `fonts.googleapis.com` ni `fonts.gstatic.com`

### Requirement: Marque d'app dans l'en-tête
La rangée du haut de chaque page SHALL afficher le mot « Golf » en Geist 600, 15 px, sans dessin de marque ni « ⛳ » ; hors de l'accueil, il mène à l'accueil sous le nom accessible « Golf ». L'icône d'écran d'accueil garde le dessin du drapeau, aux couleurs du design (décision Q10).

#### Scenario: En-tête sur téléphone
- **WHEN** l'utilisateur ouvre la page Rounds
- **THEN** la rangée du haut montre « Golf » sans image ni SVG à côté, et le lien vers `/` a le nom accessible « Golf »

#### Scenario: Même marque sur l'écran d'accueil
- **WHEN** le navigateur demande l'icône d'écran d'accueil
- **THEN** l'icône montre le drapeau sur le fond `#060709`, fanion `#F66D67`, mât `#F4F5F7`, green `#191B1D`

### Requirement: Marques de score
Partout où l'application affiche un score par trou, un eagle ou mieux SHALL être marqué d'un double cercle et un birdie d'un cercle, dans la couleur « sous le par », chiffre compris ; un bogey d'un carré et un double bogey ou plus d'un double carré, dans la couleur « au-dessus du par », chiffre en encre. Un score au par MUST rester sans marque. Chaque marque MUST avoir un équivalent textuel.

#### Scenario: Eagle
- **WHEN** une case affiche 3 sur un par 5
- **THEN** elle montre « 3 » en `oklch(0.7 0.17 25)` dans un cercle de 24 px inscrit dans un cercle de 32 px, traits de 1,5 px de la même couleur, annoncé « 3, eagle »

#### Scenario: Birdie
- **WHEN** une case affiche 3 sur un par 4
- **THEN** elle montre « 3 » en `oklch(0.7 0.17 25)` dans un cercle de 30 px, trait de 1,5 px, annoncé « 3, birdie »

#### Scenario: Bogey
- **WHEN** une case affiche 5 sur un par 4
- **THEN** elle montre « 5 » dans un carré de 28 px, trait de 1,5 px `oklch(0.6 0.01 260)`, annoncé « 5, bogey »

#### Scenario: Double bogey
- **WHEN** une case affiche 7 sur un par 4
- **THEN** elle montre « 7 » en graisse 600 dans un carré de 24 px inscrit dans un carré de 32 px, traits `oklch(0.6 0.01 260)`, annoncé « 7, double bogey ou plus »

#### Scenario: Par
- **WHEN** une case affiche 4 sur un par 4
- **THEN** elle montre « 4 » sans cercle ni carré, annoncé « 4 »

### Requirement: Chiffres alignés
Les nombres affichés en colonne (scores, différentiels, index, handicaps de jeu, nets, coups) SHALL s'afficher en Geist Mono, pour qu'un « 1 » et un « 8 » occupent la même largeur.

#### Scenario: Colonne de nombres
- **WHEN** « 15,8 » et « 17,0 » s'affichent l'un sous l'autre dans une colonne de nombres
- **THEN** les virgules des deux valeurs sont alignées verticalement

### Requirement: Mouvement des contrôles
Toutes les animations et transitions du design SHALL être reprises, avec leurs propriétés, durées et courbes (requirement « Animations du design »). Chaque transition MUST nommer ses propriétés ; la hauteur, la largeur, la largeur maximale, la marge et le remplissage sont permis là où le design les anime. Quand l'appareil demande un mouvement réduit, entrées de page, feuilles, toasts et barres MUST prendre leur état final sans déplacement, zoom ni croissance.

#### Scenario: Entrée d'une page
- **WHEN** l'utilisateur ouvre une page
- **THEN** son contenu passe d'une opacité 0 à 1 en 320 ms (`ease`) et de 10 px plus bas à sa place en 420 ms (`cubic-bezier(0.2, 0.8, 0.2, 1)`)

#### Scenario: Mouvement réduit sur une boîte de dialogue
- **WHEN** l'utilisateur, avec le mouvement réduit activé, demande la suppression d'un round
- **THEN** la feuille apparaît à sa place sans glisser, et le voile sans fondu

#### Scenario: Pas de transition globale
- **WHEN** un composant de l'interface déclare une transition sur toutes les propriétés (`transition-all`, `transition: all`)
- **THEN** `make test-frontend` échoue en nommant le fichier

### Requirement: Focus visible au clavier
Chaque élément interactif (lien, bouton, onglet, champ, case, liste) SHALL montrer, dès qu'il reçoit le focus au clavier et sans fondu, un contour de 2 px en `oklch(0.97 0.003 260)` décalé de 2 px, soit au moins 3:1 contre ce qui l'entoure. Le design ne dessine pas de focus ; ce contour est le seul ajout.

#### Scenario: Tabulation dans la barre d'onglets
- **WHEN** l'utilisateur passe au clavier sur l'onglet « Handicaps »
- **THEN** l'onglet montre le contour de focus dans la même image, sans transition

### Requirement: Couleurs du manifest
Le manifest de l'application SHALL déclarer comme `theme_color` et `background_color` la couleur de fond du design.

#### Scenario: Manifest servi
- **WHEN** le navigateur demande `/manifest.webmanifest`
- **THEN** `theme_color` et `background_color` valent `#060709`

## REMOVED Requirements

### Requirement: Thème sombre selon le système
**Reason**: Le design ne définit qu'un thème sombre et le mainteneur a choisi le sombre seul (décision Q1) ; l'application ne suit plus le réglage clair ou sombre de l'appareil.
**Migration**: Remplacée par « Thème sombre seul ». Un thème clair futur demandera ses tokens au designer et son propre changement.

### Requirement: Contraste du texte
**Reason**: Les seuils sont gardés, mais pour un seul thème et avec les surfaces du design.
**Migration**: Remplacée par « Contraste sur le thème sombre ».

### Requirement: Palette sur mesure
**Reason**: La palette scoreboard claire et sombre est remplacée par celle du design.
**Migration**: Remplacée par « Palette du design ».
