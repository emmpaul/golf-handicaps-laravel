# rounds Specification

## Purpose
Garder l'historique des rounds joués, permettre de les saisir à la main, de les consulter, de les corriger et de les supprimer, avec les mêmes contrôles de cohérence partout.

## Requirements

### Requirement: Contenu d'un round
Un round enregistré SHALL contenir : le parcours, les tees, la date, et pour chacun des 18 trous le par, l'index et la distance (facultative). Il contient aussi, pour 1 à 4 joueurs distincts dans l'ordre de la carte, les 18 scores bruts. Le round garde ses propres pars et index, même si un autre round sur le même parcours a des valeurs différentes.

#### Scenario: Round de référence
- **GIVEN** la carte `IMG_3064` saisie avec Gab et Manu
- **WHEN** l'utilisateur ouvre le round
- **THEN** le round affiche BETHPAGE BLACK, tees Red, 17 octobre 2026, par 71, Gab 72 brut et Manu 88 brut

#### Scenario: Valeurs propres au round
- **GIVEN** deux rounds sur « Bethpage Black / Red », le second avec un par 5 au trou 1
- **WHEN** l'utilisateur ouvre le premier round
- **THEN** le trou 1 du premier round garde son par 4

### Requirement: Parcours connus
Le système SHALL identifier un parcours par son nom et ses tees, sans tenir compte de la casse ni des espaces autour. Les pars, index et distances de référence d'un parcours sont ceux du round le plus récent, par date de jeu puis par date d'enregistrement, joué sur ce parcours et ces tees.

#### Scenario: Même parcours, autres tees
- **GIVEN** des rounds sur « Bethpage Black / Red » et « Bethpage Black / Blue »
- **WHEN** l'utilisateur ouvre la saisie manuelle
- **THEN** la liste des parcours connus contient ces deux entrées séparées

#### Scenario: Casse différente
- **GIVEN** des rounds sur « BETHPAGE BLACK / Red » et « Bethpage Black / red »
- **WHEN** l'utilisateur ouvre la saisie manuelle
- **THEN** la liste des parcours connus contient une seule entrée pour ce parcours et ces tees

### Requirement: Liste des rounds
La page Rounds SHALL lister les rounds du plus récent au plus ancien par date de jeu, puis par date d'enregistrement. Chaque ligne montre la date, le parcours, les tees, les joueurs avec leur brut dans l'ordre de la carte, et le ou les gagnants nets, et mène à la page du round. Des gagnants ex æquo sont tous nommés ; un round où personne n'a d'index montre « — ». La page donne un lien « Parcours » vers la page Parcours. Sans round, la page affiche « Aucun round enregistré. ».

#### Scenario: Ordre de la liste
- **GIVEN** des rounds joués le 2026-10-10 et le 2026-10-17
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** le round du 2026-10-17 apparaît en premier

#### Scenario: Ligne de la liste
- **GIVEN** le round de référence
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne montre « 17 octobre 2026 », « BETHPAGE BLACK », « Red », « Gab 72 » et « Manu 88 », sans « · » entre le parcours et les tees

#### Scenario: Gagnants nets ex æquo
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,0 avant la carte `IMG_3064`, sans rating
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne de la carte `IMG_3064` montre « Gab, Manu » comme gagnants nets

#### Scenario: Personne n'a d'index
- **GIVEN** la carte `IMG_3064`, premier round de Gab et de Manu
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la ligne montre « — » comme gagnant net

#### Scenario: Lien vers les parcours
- **WHEN** l'utilisateur touche « Parcours » sur `/rounds`
- **THEN** la page Parcours s'ouvre

#### Scenario: Aucun round
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/rounds`
- **THEN** la page affiche « Aucun round enregistré. » et le lien « Saisie manuelle »

### Requirement: En-tête d'un round
La page d'un round SHALL afficher sous le nom du parcours les tees, la date et le par comme éléments distincts, sans les joindre par des points médians.

#### Scenario: En-tête du round de référence
- **WHEN** l'utilisateur ouvre le round BETHPAGE BLACK / Red du 17 octobre 2026
- **THEN** l'en-tête montre « Tees Red », « 17 octobre 2026 » et « Par 71 », et son texte ne contient pas « · »

### Requirement: Carte de score d'un round
La page d'un round SHALL afficher la carte de score : une colonne par trou (1 à 18) avec les totaux OUT, IN et TOT, et une ligne pour le par, l'index et chaque joueur dans l'ordre de la carte. Les scores portent les marques de score (spec `visual-theme`). Sous 768 px, la carte MUST présenter l'aller (1 à 9, OUT) puis le retour (10 à 18, IN, TOT) en deux blocs empilés, sans défilement horizontal.

#### Scenario: Carte du round de référence
- **WHEN** l'utilisateur ouvre le round BETHPAGE BLACK / Red du 17 octobre 2026
- **THEN** la carte montre pour Gab « 5 » au trou 1, « 38 » en OUT, « 34 » en IN et « 72 » en TOT, et pour Manu « 88 » en TOT

#### Scenario: Marques sur la carte
- **WHEN** la carte du round de référence s'affiche
- **THEN** le « 4 » de Gab au trou 7 (par 5) porte la marque birdie et son « 5 » au trou 1 (par 4) la marque bogey

#### Scenario: Carte sur téléphone
- **WHEN** l'utilisateur ouvre à 320 puis 390 px un round de 4 joueurs
- **THEN** la carte montre un bloc aller (1 à 9, OUT) au-dessus d'un bloc retour (10 à 18, IN, TOT), « Par », « Index » et les quatre noms sont visibles dans chaque bloc, et ni la carte ni la page ne défilent horizontalement

### Requirement: Photo d'un round
Quand un round a une photo de carte, sa page SHALL l'afficher avec le texte de remplacement « Carte du round », avec un lien qui l'ouvre seule. La photo MUST n'être servie qu'avec une session ouverte par le code d'accès, avec le type `image/webp`. Un round sans photo n'affiche aucune zone de photo.

#### Scenario: Round avec photo
- **GIVEN** le round de référence de la base de démonstration, qui a une photo
- **WHEN** l'utilisateur ouvre le round
- **THEN** la page affiche l'image « Carte du round », servie avec le type `image/webp`

#### Scenario: Round sans photo
- **GIVEN** un round saisi à la main
- **WHEN** l'utilisateur ouvre le round
- **THEN** la page n'affiche aucune image « Carte du round »

#### Scenario: Photo sans session
- **WHEN** un visiteur sans session demande l'adresse de la photo d'un round
- **THEN** le système le redirige vers `/login` sans envoyer l'image

#### Scenario: Fichier manquant
- **GIVEN** un round dont le fichier de photo a disparu du disque
- **WHEN** l'utilisateur demande l'adresse de sa photo
- **THEN** le système répond 404 avec « Photo introuvable »

### Requirement: Round introuvable
Une adresse de round dont l'identifiant n'est pas un UUID ou ne désigne aucun round SHALL répondre 404, pour la page, la correction, la photo et toute écriture.

#### Scenario: Ancienne adresse numérique
- **WHEN** l'utilisateur ouvre `/rounds/12`
- **THEN** le système répond 404

#### Scenario: Round supprimé
- **GIVEN** un round supprimé
- **WHEN** l'utilisateur ouvre son ancienne adresse
- **THEN** le système répond 404

### Requirement: Saisie entièrement manuelle
Le système SHALL permettre de créer un round sans photo depuis l'éditeur de round vide : une ligne de joueur, la date du jour à l'heure de Montréal, toutes les autres cases vides. Après l'enregistrement, le système SHALL ouvrir la liste des rounds et confirmer « Round enregistré. ».

#### Scenario: Ouverture
- **GIVEN** il est 23 h 30 le 17 octobre 2026 à Montréal
- **WHEN** l'utilisateur ouvre « Saisie manuelle »
- **THEN** l'éditeur montre une ligne de joueur, la date « 17 octobre 2026 » et des cases vides

#### Scenario: Round saisi
- **WHEN** l'utilisateur saisit la carte `IMG_3064` pour Gab et Manu et enregistre
- **THEN** la liste des rounds s'ouvre, affiche « Round enregistré. » et montre le round en premier

### Requirement: Pré-remplissage depuis un parcours connu
Dans l'éditeur, l'utilisateur SHALL pouvoir choisir un parcours connu. Le choix remplit le nom du parcours, les tees, les pars, les index et les distances avec ceux du parcours, sans toucher à la date ni aux lignes de joueur.

#### Scenario: Parcours déjà connu
- **GIVEN** un round sur « Bethpage Black / Red »
- **WHEN** l'utilisateur crée un round sans photo et choisit « Bethpage Black / Red »
- **THEN** les pars, index et distances sont ceux de ce round, et le par du trou 1 vaut 4

### Requirement: Totaux calculés
Dans l'éditeur, les totaux OUT, IN et TOT du par, de la distance et de chaque ligne de joueur SHALL être calculés à partir des trous et mis à jour à chaque frappe. Un total dont un trou est vide s'affiche vide. Les totaux ne se saisissent pas.

#### Scenario: Aller complet
- **WHEN** l'utilisateur saisit pour la ligne 1 les scores 5, 5, 3, 5, 4, 4, 4, 3, 5 aux trous 1 à 9
- **THEN** le OUT de la ligne 1 affiche 38

#### Scenario: Trou manquant
- **WHEN** le score du trou 9 de la ligne 1 est vide
- **THEN** le OUT et le TOT de la ligne 1 sont vides

### Requirement: Contrôles de cohérence
Le système SHALL vérifier : parcours, tees et date remplis ; chaque par entre 3 et 5 ; les index forment une permutation de 1 à 18 ; chaque score entre 1 et 15 ; chaque distance remplie est un entier de 1 à 999 ; 1 à 4 lignes, chacune associée à un joueur, sans joueur en double. Chaque case en défaut MUST être surlignée, et les contrôles se recalculent à chaque modification.

#### Scenario: Index en double
- **WHEN** les index saisis contiennent deux fois la valeur 1 et pas de 11
- **THEN** les deux cases d'index valant 1 sont surlignées avec « Index 1 en double »

#### Scenario: Par hors bornes
- **WHEN** l'utilisateur saisit 6 comme par du trou 3
- **THEN** la case est surlignée avec « Par 6 hors de 3 à 5 »

#### Scenario: Score hors bornes
- **WHEN** l'utilisateur saisit 16 comme score du trou 2 de la ligne 1
- **THEN** la case est surlignée avec « Score 16 hors de 1 à 15 »

#### Scenario: Joueur en double
- **WHEN** les lignes 1 et 2 sont associées à Gab
- **THEN** les deux choix de joueur sont surlignés avec « Joueur en double »

#### Scenario: Distance vide
- **WHEN** toutes les distances sont vides et tout le reste est correct
- **THEN** aucun problème n'est signalé

#### Scenario: Correction d'un score
- **GIVEN** la case du trou 2 de la ligne 1 contient 16
- **WHEN** l'utilisateur la remplace par 5
- **THEN** le surlignage « Score 16 hors de 1 à 15 » disparaît

### Requirement: Enregistrement bloqué tant que la carte est incohérente
Le système MUST refuser d'enregistrer un round tant qu'un contrôle de cohérence est en défaut, y compris quand la demande arrive au serveur sans passer par l'éditeur. Le bouton d'enregistrement SHALL indiquer le nombre de problèmes restants, et ne rien enregistrer tant qu'il en reste.

#### Scenario: Carte complète et cohérente
- **WHEN** toutes les cases obligatoires sont remplies et tous les contrôles passent
- **THEN** l'utilisateur peut enregistrer et le round apparaît dans la liste des rounds

#### Scenario: Problème restant
- **WHEN** une ligne de joueur n'est associée à personne et tout le reste est correct
- **THEN** le bouton affiche « 1 problème à corriger » et l'enregistrement est impossible

#### Scenario: Plusieurs problèmes
- **WHEN** il reste trois problèmes
- **THEN** le bouton affiche « 3 problèmes à corriger »

#### Scenario: Demande incohérente reçue par le serveur
- **WHEN** une demande d'enregistrement arrive avec un par de 6 au trou 3
- **THEN** le système refuse sans rien écrire en base et signale « Par 6 hors de 3 à 5 »

### Requirement: Saisie manuelle neuve sans alerte
Dans une saisie entièrement manuelle, une case obligatoire encore vide que l'utilisateur n'a pas quittée SHALL s'afficher comme « à remplir », sans la couleur ni l'état d'erreur. Elle passe en erreur dès que l'utilisateur la quitte vide, ou à la première tentative d'enregistrement. Le bouton d'enregistrement MUST compter tous les problèmes, cases vides non touchées comprises.

#### Scenario: Ouverture d'une saisie manuelle
- **WHEN** l'utilisateur ouvre « Saisie manuelle » sans choisir de parcours
- **THEN** aucune case n'est marquée en erreur, et le bouton affiche le nombre de problèmes restants

#### Scenario: Case quittée vide
- **WHEN** l'utilisateur entre dans la case du par du trou 1 puis la quitte sans rien saisir
- **THEN** cette case est marquée en erreur et les autres cases vides non touchées ne le sont pas

### Requirement: Lignes de joueur
L'éditeur SHALL permettre d'ajouter une ligne de joueur, jusqu'à 4, et de retirer une ligne tant qu'il en reste plus d'une. Chaque ligne choisit son joueur dans la liste des joueurs, ou crée un nouveau joueur par « Nouveau joueur… », avec les mêmes règles de nom que la page Joueurs ; le joueur créé est aussitôt associé à la ligne.

#### Scenario: Ajout d'une ligne oubliée
- **GIVEN** un éditeur à 2 lignes
- **WHEN** l'utilisateur clique sur « Ajouter une ligne », l'associe à Sean et saisit ses 18 scores
- **THEN** l'éditeur a 3 lignes et la ligne 3 est associée à Sean

#### Scenario: Quatre lignes au plus
- **GIVEN** un éditeur à 4 lignes
- **THEN** « Ajouter une ligne » n'est pas proposé

#### Scenario: Joueur créé depuis une ligne
- **WHEN** l'utilisateur choisit « Nouveau joueur… » sur la ligne 1, saisit « Julien » et clique sur « Créer »
- **THEN** Julien existe dans la liste des joueurs et la ligne 1 lui est associée, sans perdre les cases déjà saisies

#### Scenario: Joueur existant créé depuis une ligne
- **GIVEN** le joueur Gab existe
- **WHEN** l'utilisateur crée « gab » depuis la ligne 1
- **THEN** l'éditeur affiche « Ce joueur existe déjà » à côté de la ligne 1 et aucune case saisie n'est perdue

### Requirement: Lignes de joueur groupées sur téléphone
Dans l'éditeur, chaque ligne de joueur SHALL former un bloc : numéro de ligne sur une rangée, liste du joueur et bouton de retrait côte à côte sur la suivante. Le bouton de retrait MUST NOT se retrouver seul sur une rangée, quelle que soit la largeur à partir de 320 px.

#### Scenario: Ligne à 320 px
- **WHEN** l'éditeur à deux lignes est ouvert sur un écran de 320 px
- **THEN** la liste « Joueur de la ligne 1 » et le bouton « Retirer la ligne 1 » sont sur la même rangée, sous « Ligne 1 »

### Requirement: Grille de saisie sur téléphone
Sous 768 px, la grille de l'éditeur SHALL présenter une ligne par trou : numéro, par, index, puis le score de chaque ligne de joueur, avec OUT après le trou 9, IN et TOT après le trou 18. La distance reste modifiable pour chaque trou. Les noms des lignes MUST rester visibles en tête pendant le défilement vertical, et la grille MUST NOT défiler horizontalement à 320 px avec 4 lignes. Chaque case garde le même nom accessible qu'en grille large.

#### Scenario: Quatre joueurs à 320 px
- **WHEN** l'éditeur à 4 lignes de joueur est ouvert sur un écran de 320 px
- **THEN** le par, l'index et les 4 scores du trou 1 sont visibles sur une même ligne, et la grille ne défile pas horizontalement

#### Scenario: Noms en tête pendant le défilement
- **WHEN** l'utilisateur fait défiler la grille jusqu'au trou 18 sur un écran de 390 px
- **THEN** les noms des lignes de joueur restent visibles en haut de la grille

#### Scenario: Même case, autre disposition
- **WHEN** l'utilisateur saisit « 5 » dans la case nommée « Ligne 1 trou 9 » à 390 px
- **THEN** la même case existe à 1440 px dans la grille large, avec la même valeur et le même contrôle

#### Scenario: Grille large sur ordinateur
- **WHEN** l'éditeur est ouvert à 1440 px
- **THEN** la grille a une colonne par trou

### Requirement: Choix de la date au calendrier
Dans l'éditeur, la date SHALL s'afficher en toutes lettres en français et se choisir dans un calendrier qui s'ouvre au toucher ou au clic, en français, semaine commençant le lundi, ouvert sur le mois de la date affichée avec ce jour sélectionné, mois et année choisis sans parcourir les mois un par un. La date enregistrée MUST être le jour choisi, quel que soit le fuseau de l'appareil.

#### Scenario: Ouverture sur la date du round
- **GIVEN** l'éditeur du round de référence, daté du 17 octobre 2026
- **WHEN** l'utilisateur ouvre le calendrier
- **THEN** le calendrier affiche octobre 2026, le 17 est sélectionné et la première colonne est le lundi

#### Scenario: Choix d'un autre jour
- **WHEN** l'utilisateur choisit le 13 dans le calendrier ouvert sur octobre 2026 et enregistre
- **THEN** la date affichée devient « 13 octobre 2026 » et le round enregistré a la date 2026-10-13

#### Scenario: Changement de mois et d'année
- **WHEN** l'utilisateur, sur un calendrier ouvert sur octobre 2026, choisit mars puis 2025 puis le 4
- **THEN** la date affichée devient « 4 mars 2025 »

#### Scenario: Fuseau horaire à l'ouest de Greenwich
- **WHEN** sur un appareil réglé à l'heure de Montréal, l'utilisateur ouvre un round enregistré le 2026-10-17
- **THEN** la date affichée est « 17 octobre 2026 », pas le 16

### Requirement: Calendrier utilisable au clavier et au doigt
Le calendrier SHALL se piloter au clavier : flèches pour changer de jour, Entrée pour choisir, Échap pour fermer sans rien changer, le focus revenant ensuite sur le champ « Date ». Sur téléphone, chaque jour MUST offrir une zone de toucher d'au moins 44 px et le calendrier MUST tenir dans l'écran sans défilement horizontal.

#### Scenario: Choix au clavier
- **WHEN** l'utilisateur ouvre le calendrier au clavier sur le 17 octobre 2026, appuie deux fois sur Flèche gauche puis sur Entrée
- **THEN** la date affichée devient « 15 octobre 2026 » et le focus est sur le champ « Date »

#### Scenario: Fermeture sans choix
- **WHEN** l'utilisateur ouvre le calendrier puis appuie sur Échap
- **THEN** le calendrier se ferme et la date affichée ne change pas

#### Scenario: Calendrier sur iPhone
- **WHEN** l'utilisateur ouvre le calendrier sur un écran de 390 px
- **THEN** chaque jour mesure au moins 44 px de côté et la page n'a pas de défilement horizontal

### Requirement: Marques sur le champ de date
Le champ « Date » SHALL porter les mêmes marques qu'une autre case de l'éditeur : surlignage et annonce aux lecteurs d'écran quand la date manque.

#### Scenario: Date vidée
- **WHEN** l'utilisateur efface la date puis quitte le champ
- **THEN** le champ « Date » est surligné, annoncé en erreur, et compté dans les problèmes du bouton

### Requirement: Détection de doublon
Avant d'enregistrer, le système SHALL avertir l'utilisateur si un autre round existe déjà avec le même parcours et les mêmes tees (sans tenir compte de la casse), la même date et les mêmes bruts pour les mêmes joueurs. L'avertissement « Ce round semble déjà enregistré » mène au round existant ; l'utilisateur peut annuler ou enregistrer quand même.

#### Scenario: Même carte saisie deux fois
- **GIVEN** le round de référence est enregistré
- **WHEN** l'utilisateur saisit de nouveau la carte `IMG_3064` et enregistre
- **THEN** le système affiche « Ce round semble déjà enregistré » avec un lien vers le round existant, et n'enregistre rien

#### Scenario: Enregistrer quand même
- **GIVEN** l'avertissement de doublon est affiché
- **WHEN** l'utilisateur clique sur « Enregistrer quand même »
- **THEN** le second round est enregistré et la liste en montre deux le 17 octobre 2026

#### Scenario: Bruts différents
- **GIVEN** le round de référence est enregistré
- **WHEN** l'utilisateur saisit la même carte avec Manu à 87 et enregistre
- **THEN** le round est enregistré sans avertissement

#### Scenario: Correction du round lui-même
- **WHEN** l'utilisateur ouvre la correction du round de référence et enregistre sans rien changer
- **THEN** le système n'affiche pas d'avertissement de doublon

### Requirement: Correction d'un round enregistré
Le système SHALL permettre de modifier un round enregistré (parcours, tees, date, pars, index, distances, joueurs, scores), dans le même éditeur et avec les mêmes contrôles que la saisie manuelle, pré-rempli avec le round. Après l'enregistrement, la page du round s'ouvre avec « Round enregistré. ». La photo du round ne change pas.

#### Scenario: Score corrigé après coup
- **WHEN** l'utilisateur corrige le score de Manu au trou 1 de 7 à 6 sur le round de référence et enregistre
- **THEN** la page du round s'ouvre et Manu a 87 brut

#### Scenario: Joueur remplacé
- **WHEN** l'utilisateur associe la ligne 2 du round de référence à Sean au lieu de Manu et enregistre
- **THEN** le round montre Gab et Sean, et plus Manu

### Requirement: Suppression d'un round
Le système SHALL permettre de supprimer un round depuis sa page, après une confirmation. La suppression MUST retirer le round, ses trous, ses joueurs et ses scores, et effacer sa photo du serveur. Les joueurs du round restent.

#### Scenario: Suppression confirmée
- **WHEN** l'utilisateur supprime un round et confirme
- **THEN** la liste des rounds s'ouvre, affiche « Round supprimé. » et ne montre plus le round

#### Scenario: Suppression annulée
- **WHEN** l'utilisateur demande la suppression puis annule
- **THEN** le round reste inchangé

#### Scenario: Round avec photo supprimé
- **GIVEN** le round de référence de la base de démonstration, qui a une photo
- **WHEN** l'utilisateur le supprime et confirme
- **THEN** l'ancienne adresse de la photo répond 404 et le fichier n'existe plus sur le disque

#### Scenario: Joueurs conservés
- **WHEN** l'utilisateur supprime le seul round de Sean
- **THEN** Sean figure toujours sur la page Joueurs

### Requirement: Classement net d'un round
Pour chaque joueur d'un round, dans l'ordre de la carte, la page du round SHALL afficher le brut, le handicap de jeu, le net et le rang net. Le net vaut brut moins handicap de jeu. Le rang est un rang de compétition : des nets égaux partagent le même rang, et le rang suivant saute les places partagées.

#### Scenario: Rang après des ex æquo
- **GIVEN** quatre joueurs dont les nets valent 80, 70, 59 et 70
- **WHEN** l'utilisateur ouvre le round
- **THEN** leurs rangs sont 4, 2, 1 et 2

#### Scenario: Joueur sans index
- **WHEN** un joueur joue son tout premier round
- **THEN** son handicap de jeu et son net s'affichent « — » avec la mention « pas encore d'index », et il n'est pas classé en net

### Requirement: Handicap de jeu d'un round
Le handicap de jeu d'un joueur sur un round SHALL être son handicap de parcours : index du joueur juste avant ce round × slope / 113 + (course rating − par total du round), arrondi à l'entier le plus proche (0,5 vers le haut), avec la rating et le slope du parcours et des tees du round (spec `course-ratings`). Sans rating, c'est l'index d'avant le round arrondi à l'entier.

#### Scenario: Classement net à deux
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,4 juste avant un round sans rating
- **WHEN** Gab y joue 72 et Manu 88
- **THEN** Gab a un handicap de jeu de 1 et un net de 71, Manu de 17 et un net de 71, et les deux sont premiers ex æquo

#### Scenario: Classement net avec rating
- **GIVEN** Gab avec un index de 1,0 et Manu de 17,4 juste avant un round sur « BETHPAGE BLACK / Red » à 71,4 / 144, par 71
- **WHEN** Gab y joue 72 et Manu 88
- **THEN** Gab a un handicap de jeu de 2 et un net de 70, Manu de 23 et un net de 65, Manu est premier et Gab deuxième

#### Scenario: Index d'avant le round
- **GIVEN** Gab à 1,0 et Manu à 17,0 après un round le 2026-10-10, et la carte `IMG_3064` le 2026-10-17
- **WHEN** l'utilisateur ouvre la carte `IMG_3064`
- **THEN** les handicaps de jeu sont 1 et 17, calculés depuis l'index d'avant ce round et non depuis celui d'après

#### Scenario: Joueur plus
- **GIVEN** un joueur avec un index de −1,6 juste avant un round sans rating
- **WHEN** il y joue 70
- **THEN** son handicap de jeu vaut −2 et son net 72

### Requirement: Classement lisible sur téléphone
Le classement net d'un round SHALL montrer toutes ses colonnes (joueur, brut, handicap de jeu, net, rang) sans en couper aucune sur un écran de 320 px, et la page MUST NOT défiler horizontalement. Le nom du ou des gagnants nets est marqué du rouge « sous le par ».

#### Scenario: Classement à 320 px
- **WHEN** l'utilisateur ouvre à 320 px un round de Manu et Sean où les deux ont un index
- **THEN** les en-têtes « Brut », « Net » et « Rang » et leurs valeurs sont entièrement visibles sans défilement horizontal

### Requirement: Rating affichée sur le round
La page d'un round SHALL afficher la course rating et le slope utilisés pour ses différentiels et son classement net, en deux éléments distincts sans « · », ou « Sans rating (par 71, slope 113) » avec le par total du round. Cette ligne MUST mener à la page Parcours.

#### Scenario: Round avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144
- **WHEN** l'utilisateur ouvre la carte `IMG_3064`
- **THEN** la page affiche « Rating 71,4 » et « Slope 144 », sans « · », et cette ligne mène à la page Parcours

#### Scenario: Round sans rating
- **WHEN** l'utilisateur ouvre un round de par 72 sur un parcours sans rating
- **THEN** la page affiche « Sans rating (par 72, slope 113) » et cette ligne mène à la page Parcours
