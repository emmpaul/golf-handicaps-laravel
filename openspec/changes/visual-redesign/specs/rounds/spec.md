# Spec Delta

## MODIFIED Requirements

### Requirement: Liste des rounds
La page Rounds SHALL lister les rounds du plus récent au plus ancien par date de jeu, puis par date d'enregistrement. Chaque ligne montre le jour et le mois abrégé, le parcours et ses tees joints par « · », les joueurs avec leur brut dans l'ordre de la carte, et sous « 1ER NET » le ou les gagnants nets ; elle mène à la page du round. Des gagnants ex æquo sont tous nommés ; un round où personne n'a d'index montre « — ». La page donne les liens « Parcours » et « Saisie manuelle ». Sans round, elle affiche « Aucun round. Importez une carte pour commencer. ».

#### Scenario: Ordre de la liste
- **GIVEN** des rounds joués le 2026-10-10 et le 2026-10-17
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** le round du 2026-10-17 apparaît en premier

#### Scenario: Ligne de la liste
- **GIVEN** le round de référence
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne montre « 17 » au-dessus de « OCT », « BETHPAGE BLACK · Red », « Gab 72 » et « Manu 88 », et sa date complète « 17 octobre 2026 » est lue par les lecteurs d'écran

#### Scenario: Mois abrégés
- **WHEN** des rounds sont joués chaque mois de 2026
- **THEN** leurs lignes montrent dans l'ordre « JANV », « FÉVR », « MARS », « AVR », « MAI », « JUIN », « JUIL », « AOÛT », « SEPT », « OCT », « NOV », « DÉC »

#### Scenario: Gagnants nets ex æquo
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,0 avant la carte `IMG_3064`, sans rating
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne de la carte `IMG_3064` montre « Gab, Manu » sous « 1ER NET »

#### Scenario: Personne n'a d'index
- **GIVEN** la carte `IMG_3064`, premier round de Gab et de Manu
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne montre « — » sous « 1ER NET »

#### Scenario: Lien vers les parcours
- **WHEN** l'utilisateur touche « Parcours » sur `/rounds`
- **THEN** la page Parcours s'ouvre

#### Scenario: Compteur
- **GIVEN** huit rounds
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la rangée du haut montre « 8 ROUNDS » à droite

#### Scenario: Aucun round
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la page affiche « Aucun round. Importez une carte pour commencer. » et le lien « Saisie manuelle »

### Requirement: En-tête d'un round
La page d'un round SHALL afficher sous le nom du parcours une ligne de méta en capitales qui joint par « · » les tees, la date au format `JJ.MM.AAAA`, le par et la rating, et dont la rating mène à la page Parcours.

#### Scenario: En-tête du round de référence
- **WHEN** l'utilisateur ouvre le round BETHPAGE BLACK / Red du 17 octobre 2026, sans rating
- **THEN** l'en-tête montre « BETHPAGE BLACK » puis « RED · 17.10.2026 · PAR 71 · SANS RATING », et « SANS RATING » est un lien souligné vers la page Parcours

#### Scenario: Round avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** l'utilisateur ouvre le round
- **THEN** la méta finit par « 71,4 / 144 », lien vers la page Parcours

### Requirement: Carte de score d'un round
La page d'un round SHALL afficher la carte de score en une rangée par trou : numéro, « P{par} · {index} », puis le score de chaque joueur dans l'ordre de la carte, sous un en-tête qui donne les cinq premières lettres de chaque nom en capitales. Une rangée « ALLER · PAR {n} » suit le trou 9, « RETOUR · PAR {n} » et « TOTAL · PAR {n} » suivent le trou 18, avec les sommes de chaque joueur. Les scores portent les marques de score, expliquées par une légende.

#### Scenario: Carte du round de référence
- **WHEN** l'utilisateur ouvre le round BETHPAGE BLACK / Red du 17 octobre 2026
- **THEN** la rangée du trou 1 montre « 1 », « P4 · 8 » et « 5 » pour Gab ; « ALLER · PAR 36 » montre 38 pour Gab, « RETOUR · PAR 35 » 34, « TOTAL · PAR 71 » 72, et Manu a 88 en total

#### Scenario: Marques sur la carte
- **WHEN** la carte du round de référence s'affiche
- **THEN** le « 4 » de Gab au trou 7 (par 5) porte la marque birdie et son « 5 » au trou 1 (par 4) la marque bogey

#### Scenario: Carte sur téléphone
- **WHEN** l'utilisateur ouvre à 320 puis 390 px un round de 4 joueurs
- **THEN** chaque rangée montre le trou, le par, l'index et les quatre scores, l'en-tête montre les quatre noms, et ni la carte ni la page ne défilent horizontalement

#### Scenario: Légende
- **WHEN** la carte s'affiche
- **THEN** une légende sous la carte montre « EAGLE », « BIRDIE », « BOGEY » et « DOUBLE + », chacun avec sa marque

### Requirement: Suppression d'un round
Le système SHALL permettre de supprimer un round depuis sa page, après une confirmation dans la feuille « Supprimer ce round ? », qui rappelle le parcours et la date et prévient que les index seront recalculés. La suppression MUST retirer le round, ses trous, ses joueurs et ses scores, et effacer sa photo du serveur. Les joueurs du round restent.

#### Scenario: Suppression confirmée
- **WHEN** l'utilisateur touche « Supprimer » sur la page du round, puis « Supprimer » dans la feuille
- **THEN** la liste des rounds s'ouvre, affiche « Round supprimé. » et ne montre plus le round

#### Scenario: Texte de la feuille
- **WHEN** l'utilisateur demande la suppression du round de référence
- **THEN** la feuille montre « Supprimer ce round ? » et « BETHPAGE BLACK · 17 oct. 2026. Les index de tous les joueurs seront recalculés. », puis les boutons « Supprimer » et « Annuler »

#### Scenario: Suppression annulée
- **WHEN** l'utilisateur demande la suppression puis touche « Annuler »
- **THEN** le round reste inchangé

#### Scenario: Round avec photo supprimé
- **GIVEN** le round de référence de la base de démonstration, qui a une photo
- **WHEN** l'utilisateur le supprime et confirme
- **THEN** l'ancienne adresse de la photo répond 404 et le fichier n'existe plus sur le disque

#### Scenario: Joueurs conservés
- **WHEN** l'utilisateur supprime le seul round de Sean
- **THEN** Sean figure toujours sur la page Joueurs

### Requirement: Lignes de joueur groupées sur téléphone
Dans l'éditeur, chaque ligne de joueur SHALL tenir sur une seule rangée : « L{n} », la liste du joueur, le nom lu sur la carte s'il y en a un, puis le bouton de retrait de 44 px. Le bouton de retrait MUST NOT se retrouver seul sur une rangée, quelle que soit la largeur à partir de 320 px.

#### Scenario: Ligne à 320 px
- **WHEN** l'éditeur à deux lignes est ouvert sur un écran de 320 px
- **THEN** « L1 », la liste « Joueur de la ligne 1 » et le bouton « Retirer la ligne 1 » sont sur la même rangée

#### Scenario: Ligne sans joueur
- **WHEN** une ligne n'est associée à aucun joueur
- **THEN** sa liste a une bordure ambre `oklch(0.78 0.16 60)` et affiche « Choisir un joueur »

### Requirement: Grille de saisie sur téléphone
La grille de l'éditeur SHALL présenter une ligne par trou : numéro, par, index, puis le score de chaque ligne de joueur, avec « ALL. » après le trou 9, « RET. » et « TOT. » après le trou 18. Les noms des lignes MUST rester visibles en tête pendant le défilement vertical, et la grille MUST NOT défiler horizontalement à 320 px avec 4 lignes. Chaque case garde son nom accessible. Les distances se saisissent dans le bloc « Distances » replié sous la grille (décision Q6).

#### Scenario: Quatre joueurs à 320 px
- **WHEN** l'éditeur à 4 lignes de joueur est ouvert sur un écran de 320 px
- **THEN** le par, l'index et les 4 scores du trou 1 sont visibles sur une même ligne, et la grille ne défile pas horizontalement

#### Scenario: Noms en tête pendant le défilement
- **WHEN** l'utilisateur fait défiler la grille jusqu'au trou 18 sur un écran de 390 px
- **THEN** les noms des lignes de joueur restent visibles en haut de la grille, chacun dans la couleur de son joueur, ou en ambre pour une ligne sans joueur

#### Scenario: Même case, autre disposition
- **WHEN** l'utilisateur saisit « 5 » dans la case nommée « Ligne 1 trou 9 » à 390 px
- **THEN** la même case existe à 1440 px, avec la même valeur et le même contrôle

#### Scenario: Bloc Distances
- **WHEN** l'utilisateur ouvre l'éditeur du round de référence à 390 px
- **THEN** sous la grille, le bloc « Distances » est replié ; ouvert, il montre une case par trou nommée « Distance trou 1 » à « Distance trou 18 », de 44 px de haut, la case du trou 1 valant 420, et ni le bloc ni la page ne défilent horizontalement à 320 px

#### Scenario: Distance en erreur dans un bloc replié
- **WHEN** l'utilisateur saisit 1200 comme distance du trou 3 puis replie le bloc « Distances »
- **THEN** le problème reste compté par le bouton d'enregistrement et listé dans la barre d'enregistrement

#### Scenario: Grille large sur ordinateur
- **WHEN** l'éditeur est ouvert à 1440 px
- **THEN** la grille garde une rangée par trou, dans la colonne centrée de 390 px

### Requirement: Classement net d'un round
Pour chaque joueur d'un round, la page du round SHALL afficher le rang net, le nom, « {brut} − {handicap de jeu} » et le net, les joueurs rangés par rang net croissant, des ex æquo dans l'ordre de la carte. Le net vaut brut moins handicap de jeu. Le rang est un rang de compétition : des nets égaux partagent le même rang, et le rang suivant saute les places partagées. Chaque premier porte la pastille ambre « 1ER ». Un joueur sans index MUST venir après les joueurs classés, sans rang ni pastille, avec « {brut} · pas encore d'index » et « — » comme net : il n'est pas classé en net.

#### Scenario: Rang après des ex æquo
- **GIVEN** quatre joueurs dont les nets valent, dans l'ordre de la carte, 80, 70, 59 et 70
- **WHEN** l'utilisateur ouvre le round
- **THEN** les lignes sont, dans l'ordre, le troisième au rang « 1 » avec « 1ER », le deuxième et le quatrième au rang « 2 », puis le premier au rang « 4 »

#### Scenario: Classement avec rating
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,4 juste avant un round sur « BETHPAGE BLACK / Red » à 71,4 / 144, par 71, où Gab joue 72 et Manu 88
- **WHEN** l'utilisateur ouvre le round
- **THEN** la première ligne est « 1 », Manu, « 1ER », « 88 − 23 », « 65 », et la seconde « 2 », Gab, « 72 − 2 », « 70 »

#### Scenario: Ex æquo
- **GIVEN** Gab et Manu à 71 net sur la carte `IMG_3064`, sans rating
- **WHEN** l'utilisateur ouvre le round
- **THEN** Gab puis Manu sont tous deux au rang « 1 » avec la pastille « 1ER »

#### Scenario: Joueur sans index
- **GIVEN** un round où Sean joue son tout premier round, 95, avec Gab et Manu
- **WHEN** l'utilisateur ouvre le round
- **THEN** Sean vient en dernier, sans rang, avec « 95 · pas encore d'index » et « — » comme net

### Requirement: Classement lisible sur téléphone
Le classement net d'un round SHALL montrer pour chaque joueur son rang, son nom, « {brut} − {handicap de jeu} » et son net sans en couper aucun sur un écran de 320 px, un nom trop long coupé par « … », et la page MUST NOT défiler horizontalement. Les premiers nets sont marqués par la pastille ambre « 1ER », pas par la couleur de leur nom.

#### Scenario: Classement à 320 px
- **WHEN** l'utilisateur ouvre à 320 px un round de Manu et Sean où les deux ont un index
- **THEN** le rang, « {brut} − {handicap de jeu} » et le net de chaque ligne sont entièrement visibles sans défilement horizontal

### Requirement: Rating affichée sur le round
La page d'un round SHALL afficher la course rating et le slope utilisés pour ses différentiels et son classement net à la fin de sa ligne de méta (spec ci-dessus, « En-tête d'un round ») : « 71,4 / 144 », ou « SANS RATING » quand le parcours n'a pas de rating, le par total du round étant déjà dans la méta. Cette valeur MUST être un lien vers la page Parcours.

#### Scenario: Round avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** l'utilisateur ouvre la carte `IMG_3064`
- **THEN** la méta finit par « 71,4 / 144 », lien vers la page Parcours

#### Scenario: Round sans rating
- **WHEN** l'utilisateur ouvre un round de par 72 sur un parcours sans rating
- **THEN** la méta contient « PAR 72 » et finit par « SANS RATING », lien vers la page Parcours

## ADDED Requirements

### Requirement: Mise en page de la page Rounds
À 390 px, la page Rounds SHALL suivre le design : titre « Rounds » 22 px sous la rangée du haut, liens « Parcours » et « Saisie manuelle » à droite du titre (Geist 500, 14 px, 16 px d'écart), filet 12 px dessous, puis une ligne par round en grille de trois colonnes (44 px, reste, contenu) espacées de 14 px, 16 px de marge verticale, filet entre deux lignes.

#### Scenario: Colonne de date
- **WHEN** l'utilisateur ouvre `/rounds` avec le round de référence
- **THEN** « 17 » est en Geist Mono 500, 22 px, et « OCT » 4 px dessous en Geist Mono 500, 10 px, approche 0,08 em, gris

#### Scenario: Colonnes du parcours et du gagnant
- **WHEN** la ligne du round de référence s'affiche
- **THEN** « BETHPAGE BLACK » est en Geist 600, 15 px, suivi de « · Red » en graisse 400 grise ; « Gab 72 » et « Manu 88 » sont en Geist Mono 500, 12 px, gris, chacun après une pastille de 6 px à la couleur du joueur ; « 1ER NET » est en Geist Mono 500, 10 px, gris, et le gagnant dessous en Geist 600, 14 px, ambre

### Requirement: Mise en page de la page d'un round
À 390 px, la page d'un round SHALL suivre le design : nom du parcours en titre de page 14 px sous la rangée du haut, méta en Geist Mono 500, 12 px, gris, 8 px dessous, libellés « CLASSEMENT NET » et « BRUT − HCP » 30 px plus bas, une ligne par joueur classé, puis la carte de score 44 px plus bas et la légende 16 px sous la carte.

#### Scenario: Ligne du classement
- **WHEN** l'utilisateur ouvre le round de référence à 390 px
- **THEN** chaque ligne a 18 px de marge verticale : le rang en Geist Mono 500, 18 px, gris, dans 22 px ; le nom en Geist 600, 22 px, après une pastille de 10 px à la couleur du joueur ; « 72 − 1 » dessous en Geist Mono 500, 15 px, gris ; le net à droite en Geist Mono 500, 44 px

#### Scenario: Rangées de la carte
- **WHEN** la carte de score s'affiche à 390 px
- **THEN** chaque rangée de trou fait 44 px de haut, le numéro en Geist Mono 500, 17 px, dans 40 px, « P4 · 8 » en Geist Mono 500, 12 px, gris, chaque score dans une colonne de 52 px ; les rangées de sous-total font 40 px, fond `oklch(0.17 0.006 260)`, rayon 8 px

### Requirement: Photo et explication sur la page d'un round
La page d'un round SHALL garder, sous la légende, la photo de la carte quand elle existe (rayon 12 px, proportions 16/10, fond `oklch(0.2 0.006 260)`) puis le bloc replié « Comment l’index est calculé », que le design ne dessine pas.

#### Scenario: Round avec photo
- **GIVEN** le round de référence de la base de démonstration, qui a une photo
- **WHEN** l'utilisateur ouvre le round à 390 px
- **THEN** l'image « Carte du round » s'affiche sous la légende, sur toute la largeur moins 20 px de chaque côté, aux coins de 12 px

### Requirement: Mise en page de l'éditeur
À 390 px, l'éditeur SHALL suivre le design : lien de retour (« Rounds » pour un nouveau round, « Round » pour une modification, « Importer » pour une vérification), titre « Nouveau round », « Modifier le round » ou « Vérifier la carte », champs PARCOURS et TEES côte à côte (reste et 110 px), DATE dessous, « LIGNES DE LA CARTE », les lignes, puis la grille. La barre d'onglets et le bouton Importer ne s'affichent pas.

#### Scenario: Nouveau round
- **WHEN** l'utilisateur touche « Saisie manuelle »
- **THEN** l'éditeur s'ouvre avec le lien de retour « Rounds » et le titre « Nouveau round », sans barre d'onglets

#### Scenario: Champs
- **WHEN** l'éditeur s'affiche à 390 px
- **THEN** chaque champ fait 48 px, fond `oklch(0.17 0.006 260)`, rayon 10 px, texte Geist 500, 16 px, sous un libellé en Geist Mono 500, 10 px, gris, à 6 px

#### Scenario: Cases de la grille
- **WHEN** la grille s'affiche à 390 px
- **THEN** chaque case fait 44 px de haut, rayon 8 px, Geist Mono 500, 16 px ; une case de score sous le par a le fond `oklch(0.7 0.17 25 / 0.22)` et la bordure `oklch(0.7 0.17 25 / 0.5)` ; une case de score vide, une fois en erreur, a le fond `oklch(0.78 0.16 60 / 0.08)` et la bordure `oklch(0.78 0.16 60 / 0.6)`

### Requirement: Barre d'enregistrement de l'éditeur
L'éditeur SHALL garder en bas de l'écran une barre collée au-dessus de la zone sûre, sur un dégradé du fond vers le transparent : les messages des problèmes restants joints par « · » en Geist 500, 13 px, ambre, après un point ambre de 6 px, puis le bouton d'enregistrement de 56 px, rayon 14 px. Le bouton garde ses libellés et son blocage (« Enregistrement bloqué tant que la carte est incohérente »).

#### Scenario: Problèmes restants
- **WHEN** la ligne 1 n'a pas de joueur et tout le reste est correct
- **THEN** la barre montre le message du joueur manquant en ambre et le bouton « 1 problème à corriger », fond `oklch(0.22 0.006 260)`, texte `oklch(0.5 0.01 260)`

#### Scenario: Carte cohérente
- **WHEN** tous les contrôles passent
- **THEN** la barre ne montre aucun message et le bouton « Enregistrer » a le fond `oklch(0.97 0.003 260)` et le texte `oklch(0.13 0.005 260)`
