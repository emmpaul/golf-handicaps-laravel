## Purpose

Donner à l'application une identité visuelle propre au golf, lisible au téléphone en plein jour comme le soir : palette scoreboard claire et sombre, polices, marque d'application, couleurs des marques de score et chiffres alignés.

## ADDED Requirements

### Requirement: Thème sombre selon le système
L'application SHALL afficher le thème sombre quand l'appareil est réglé en mode sombre, et le thème clair sinon, sans réglage dans l'application. La couleur de la barre d'état (`theme-color`) MUST suivre le mode.

#### Scenario: Téléphone en mode sombre
- **WHEN** l'utilisateur ouvre l'accueil sur un téléphone réglé en mode sombre
- **THEN** la page s'affiche sur le fond `#0A111F` avec le texte `#EAEFF4`, et la barre d'onglets aussi

#### Scenario: Couleur de la barre d'état
- **WHEN** le navigateur lit la page d'accueil
- **THEN** il trouve une balise `theme-color` de valeur `#F4F7FB` pour `(prefers-color-scheme: light)` et une de valeur `#0A111F` pour `(prefers-color-scheme: dark)`

#### Scenario: Aucun réglage d'apparence
- **WHEN** l'utilisateur parcourt l'application
- **THEN** aucune page ni aucun contrôle ne permet de choisir le thème, et aucune réponse ne pose de cookie `appearance`

### Requirement: Contraste du texte
Dans les deux thèmes, le texte courant et le texte secondaire MUST garder un contraste d'au moins 4,5:1 avec chacun des fonds sur lesquels ils se posent (page, surface secondaire, survol). Les marques non textuelles (contour de champ, contour de focus, marque « au-dessus du par ») MUST garder au moins 3:1 contre le fond de la page.

#### Scenario: Texte secondaire en mode sombre
- **WHEN** une page affiche du texte secondaire en mode sombre
- **THEN** ce texte a un contraste d'au moins 4,5:1 avec le fond de la page

#### Scenario: Texte en mode clair
- **WHEN** une page s'affiche en mode clair
- **THEN** le texte courant et le texte secondaire ont chacun un contraste d'au moins 4,5:1 avec leur fond

#### Scenario: Contour de champ
- **WHEN** un champ s'affiche, en clair puis en sombre
- **THEN** son contour a un contraste d'au moins 3:1 avec le fond de la page

### Requirement: Palette sur mesure
L'application SHALL utiliser la palette scoreboard, commune à toutes les pages. Chaque couleur, chaque police, chaque rayon et chaque ombre MUST venir d'un token déclaré une fois pour le mode clair et, pour les couleurs, une fois pour le mode sombre. La couleur destructive reste distincte de la couleur principale.

#### Scenario: Valeurs en mode clair
- **WHEN** une page s'affiche en mode clair
- **THEN** le fond vaut `#F4F7FB`, le texte et le fond du bouton principal `#0F1D3E`, la couleur destructive `#9B540C`

#### Scenario: Valeurs en mode sombre
- **WHEN** une page s'affiche en mode sombre
- **THEN** le fond vaut `#0A111F`, le texte et le fond du bouton principal `#EAEFF4`, la couleur destructive `#EB9854`

#### Scenario: Aucune couleur hors des tokens
- **WHEN** un composant ou une page de l'interface contient une couleur écrite en dur (`#1b1b18`, `oklch(…)`, `rgb(…)`, une classe `bg-[#…]`) ou une classe de couleur Tailwind par défaut (`text-gray-500`)
- **THEN** `make test-frontend` échoue en nommant le fichier, ou la classe ne produit aucun style

### Requirement: Polices auto-hébergées
Les titres, les noms et les chiffres SHALL s'afficher en Sofia Sans Condensed, le texte, les libellés et les boutons en IBM Plex Sans. Les fichiers de police MUST être servis par l'application elle-même : aucune page ne charge de ressource depuis un autre domaine.

#### Scenario: Titre de page
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le titre de la page s'affiche en Sofia Sans Condensed et le texte courant en IBM Plex Sans

#### Scenario: Aucune requête vers un tiers
- **WHEN** le navigateur charge l'accueil
- **THEN** toutes les requêtes de la page, polices comprises, visent le domaine de l'application

### Requirement: Marque d'app dans l'en-tête
L'en-tête SHALL afficher la marque de l'application (le même dessin que les icônes d'écran d'accueil, dans les couleurs de la palette) suivie de « Golf ». La marque est décorative pour les lecteurs d'écran ; le lien vers l'accueil garde le nom accessible « Golf ».

#### Scenario: En-tête sur téléphone
- **WHEN** l'utilisateur ouvre une page de l'application
- **THEN** l'en-tête montre la marque dessinée et le mot « Golf », le lien vers `/` a le nom accessible « Golf » et le texte de l'en-tête ne contient pas « ⛳ »

#### Scenario: Même marque sur l'écran d'accueil
- **WHEN** le navigateur demande l'icône d'écran d'accueil
- **THEN** l'icône montre le même dessin que l'en-tête : fond `#0F1D3E`, green `#46566E`, mât `#F4F7FB`, fanion `#CB1E21`

### Requirement: Marques de score
Partout où l'application affiche un score par trou, un score sous le par SHALL être marqué d'un cercle dans la couleur « sous le par », un score au-dessus du par d'un carré dans la couleur « au-dessus du par », et un score au par MUST rester sans marque. Chaque marque MUST avoir un équivalent textuel pour les lecteurs d'écran.

#### Scenario: Birdie
- **WHEN** une case affiche 3 sur un par 4
- **THEN** elle montre « 3 » dans un cercle de la couleur « sous le par » (`#CB1E21` en clair, `#F86B65` en sombre), annoncé « 3, birdie »

#### Scenario: Bogey
- **WHEN** une case affiche 5 sur un par 4
- **THEN** elle montre « 5 » dans un carré de la couleur « au-dessus du par » (`#46566E` en clair, `#A9B9CA` en sombre), annoncé « 5, bogey »

#### Scenario: Par
- **WHEN** une case affiche 4 sur un par 4
- **THEN** elle montre « 4 » sans cercle ni carré, annoncé « 4 »

### Requirement: Chiffres alignés
Les nombres affichés en colonne (scores, différentiels, index, handicaps de jeu, nets, coups) SHALL utiliser des chiffres à chasse fixe, pour qu'un « 1 » et un « 8 » occupent la même largeur.

#### Scenario: Colonne de nombres
- **WHEN** « 15,8 » et « 17,0 » s'affichent l'un sous l'autre dans une colonne de nombres
- **THEN** les virgules des deux valeurs sont alignées verticalement

### Requirement: Mouvement des contrôles
Les transitions des contrôles SHALL ne porter que sur des propriétés nommées (couleur, opacité, transformation), jamais sur toutes les propriétés ni sur la largeur, la hauteur ou la position. Quand l'appareil demande un mouvement réduit, l'ouverture et la fermeture des boîtes de dialogue MUST se faire sans déplacement ni zoom.

#### Scenario: Mouvement réduit sur une boîte de dialogue
- **WHEN** l'utilisateur, avec le mouvement réduit activé, ouvre une boîte de confirmation
- **THEN** la boîte apparaît sans glisser ni grandir

#### Scenario: Pas de transition globale
- **WHEN** un composant de l'interface déclare une transition sur toutes les propriétés (`transition-all`, `transition: all`)
- **THEN** `make test-frontend` échoue en nommant le fichier

### Requirement: Focus visible au clavier
Chaque élément interactif (lien, bouton, onglet, champ, case, liste) SHALL montrer un contour de focus dès qu'il reçoit le focus au clavier, sans fondu, avec un contraste d'au moins 3:1 contre ce qui l'entoure.

#### Scenario: Tabulation dans la barre d'onglets
- **WHEN** l'utilisateur passe au clavier sur l'onglet « Handicaps »
- **THEN** l'onglet montre un contour de focus dans la même image, sans transition

### Requirement: Couleurs du manifest
Le manifest de l'application SHALL déclarer comme `theme_color` et `background_color` la couleur de fond de la palette en mode clair, pas `#ffffff`.

#### Scenario: Manifest servi
- **WHEN** le navigateur demande `/manifest.webmanifest`
- **THEN** `theme_color` et `background_color` valent `#F4F7FB`
