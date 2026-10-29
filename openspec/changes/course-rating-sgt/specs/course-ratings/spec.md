# Spec Delta

## MODIFIED Requirements

### Requirement: Rating et slope d'un parcours
Le système SHALL associer à chaque parcours connu, identifié par son nom et ses tees sans tenir compte de la casse ni des espaces autour, au plus une course rating, à une décimale entre 40,0 et 90,0, et un slope, entier entre 55 et 155. Seules des valeurs confirmées, saisies à la main ou proposées puis confirmées par un utilisateur, MUST entrer dans les calculs. Une rating confirmée s'applique à tous les rounds joués sur ce parcours et ces tees, passés et futurs.

#### Scenario: Même parcours, autre casse
- **GIVEN** « BETHPAGE BLACK / Red » avec une rating de 71,4 et un slope de 144
- **WHEN** un round est enregistré sur « Bethpage Black / red »
- **THEN** les différentiels de ce round utilisent 71,4 et 144

#### Scenario: Autres tees
- **GIVEN** « BETHPAGE BLACK / Red » avec une rating de 71,4 et un slope de 144
- **WHEN** un round est enregistré sur « BETHPAGE BLACK / Blue »
- **THEN** ce round est sans rating

#### Scenario: Proposition non confirmée
- **GIVEN** la carte `IMG_3064` enregistrée et la proposition 71,4 / 144 du tee Red pour « BETHPAGE BLACK / Red », non confirmée
- **WHEN** l'utilisateur ouvre la page de ce round
- **THEN** le round est « sans rating », et les différentiels de Gab et de Manu valent 1,0 et 17,0

## ADDED Requirements

### Requirement: Recherche lancée pour un parcours nouveau
À l'enregistrement ou à la correction d'un round dont le parcours et les tees n'ont encore aucune ligne de rating, sans tenir compte de la casse ni des espaces autour, le système SHALL lancer en arrière-plan la recherche de leur rating sur Simulator Golf Tour, sans retarder l'enregistrement. Une paire qui a déjà une ligne, recherchée, en cours de recherche ou saisie à la main, MUST NOT être recherchée de nouveau sans action de l'utilisateur.

#### Scenario: Premier round sur un parcours
- **WHEN** l'utilisateur enregistre le premier round sur « CALANOVA GOLF CLUB / Yellow »
- **THEN** le round est enregistré, et la page Parcours montre ce parcours en « Recherche en cours »

#### Scenario: Même parcours, autre casse
- **GIVEN** « BETHPAGE BLACK / Red » déjà recherché
- **WHEN** l'utilisateur enregistre un round sur « Bethpage Black / red »
- **THEN** aucune nouvelle recherche n'est lancée

#### Scenario: Parcours renommé par une correction
- **GIVEN** un round sur « PAYNES VALLEY / Blue », déjà recherché
- **WHEN** l'utilisateur corrige ce round en « THE SANCTUARY / White », sans ligne de rating
- **THEN** une recherche est lancée pour « THE SANCTUARY / White »

#### Scenario: Rating saisie avant la recherche
- **GIVEN** « BETHPAGE BLACK / Red » saisi à la main à 71,4 / 144
- **WHEN** un nouveau round est enregistré sur « BETHPAGE BLACK / Red »
- **THEN** aucune recherche n'est lancée et la rating reste 71,4 / 144

### Requirement: Recherche des parcours déjà enregistrés
Le système SHALL offrir une commande d'administration qui lance la recherche de chaque parcours connu qui n'a pas encore de ligne de rating, une seule fois par paire, et affiche le nombre de recherches lancées. Lancée deux fois de suite, la seconde fois MUST n'en lancer aucune. Le service `migrate` la lance à chaque déploiement (spec `deployment`).

#### Scenario: Première mise à jour
- **GIVEN** une base avec 6 paires parcours et tees, dont « PAYNES VALLEY / Blue » et « PAYNES VALLEY / Blue 6212yd », et aucune ligne de rating
- **WHEN** l'opérateur lance la commande
- **THEN** elle affiche « 6 recherches de rating mises en file. » et la page Parcours montre les 6 parcours en « Recherche en cours »

#### Scenario: Seconde exécution
- **WHEN** l'opérateur relance la commande juste après
- **THEN** elle affiche « 0 recherche de rating mise en file. » et s'arrête avec le code 0

#### Scenario: Ratings déjà saisies
- **GIVEN** « BETHPAGE BLACK / Red » saisi à la main et « PAYNES VALLEY / Blue » sans ligne
- **WHEN** l'opérateur lance la commande
- **THEN** elle affiche « 1 recherche de rating mise en file. » et « BETHPAGE BLACK / Red » garde 71,4 / 144

### Requirement: Correspondance sur la liste Simulator Golf Tour
La recherche SHALL chercher dans la liste des parcours de Simulator Golf Tour un parcours de même nom, sans tenir compte de la casse, des accents, de la ponctuation, de « & » écrit « and » ni d'un « The » en tête, et de même par total que le round le plus récent de la paire. Un seul nom égal au même par MUST être retenu sans demande. Plusieurs noms égaux, ou à défaut les noms qui contiennent l'autre au même par, dix au plus, MUST être proposés au choix ; aucun donne « Introuvable sur SGT ».

#### Scenario: Nom en capitales avec « The »
- **WHEN** la recherche porte sur « THE SANCTUARY / White », par 72
- **THEN** le parcours SGT « Sanctuary » (Sedalia, CO, par 72) est retenu sans demande à l'utilisateur

#### Scenario: Par différent
- **WHEN** la recherche porte sur « PAYNES VALLEY / Blue », par 72, et que SGT liste « Paynes Valley » (par 72) et « Paynes Valley 19th » (par 3)
- **THEN** « Paynes Valley » est retenu, sans demande

#### Scenario: Noms qui se contiennent
- **WHEN** la recherche porte sur « SANCTUARY COVE / Blue », par 72, sans nom égal sur SGT
- **THEN** le parcours est « Parcours SGT à choisir » avec les candidats « Sanctuary » (par 72) et « The Pines Sanctuary Cove » (par 72), sans « Sanctuary GC » ni « Sanctuary Lake » (par 71)

#### Scenario: Un seul candidat en sous-chaîne
- **WHEN** un seul parcours SGT au même par contient le nom du round, sans nom égal
- **THEN** le parcours est « Parcours SGT à choisir » avec ce seul candidat, sans être retenu d'office

#### Scenario: Aucune correspondance
- **WHEN** aucun parcours SGT de même par n'a un nom égal ou qui se contient
- **THEN** le parcours est « Introuvable sur SGT »

#### Scenario: Aucun round sur la paire
- **WHEN** la recherche d'une paire démarre alors que son dernier round a été supprimé ou renommé
- **THEN** le parcours est « Saisie manuelle » avec la note « Aucun round sur ce parcours », sans requête vers SGT

### Requirement: Liste Simulator Golf Tour gardée 24 heures
Le système SHALL garder la liste des parcours de Simulator Golf Tour 24 heures après l'avoir téléchargée, et la réutiliser pour toutes les recherches de cette période. Une liste illisible MUST NOT être gardée.

#### Scenario: Plusieurs recherches dans la journée
- **WHEN** 6 recherches s'exécutent en moins de 24 heures
- **THEN** la liste SGT n'est téléchargée qu'une fois

#### Scenario: Liste périmée
- **GIVEN** une liste téléchargée il y a 25 heures
- **WHEN** une recherche s'exécute
- **THEN** la liste est téléchargée de nouveau

### Requirement: Proposition lue sur la carte SGT
Le parcours SGT retenu, le système SHALL lire sur son image de carte, par l'IA, le par et la rating et le slope de chaque tee, et proposer ceux du tee qui porte le nom des tees du round, sans tenir compte de la casse, des espaces ni d'une distance en fin de tees. Sans tee de ce nom, il MUST lister les tees lus et demander lequel retenir. Une rating hors de 40,0 à 90,0 ou un slope hors de 55 à 155 MUST rendre la lecture inexploitable.

#### Scenario: Proposition pour Bethpage Black
- **WHEN** la recherche aboutit pour « BETHPAGE BLACK / Red » et que l'IA lit Blue 77,5 / 155, White 74,2 / 148, Red 71,4 / 144, Junior 71,0 / 120 et PAR3 54,0 / 120, par 71
- **THEN** le parcours est « Proposition à confirmer » avec la proposition « tee Red, 71,4 / 144 », et l'index des joueurs ne change pas

#### Scenario: Distance dans les tees
- **WHEN** les tees du round sont « White 6023yd » et la carte SGT a un tee « White » à 74,2 / 148
- **THEN** la proposition est « tee White, 74,2 / 148 »

#### Scenario: Tee absent de la carte
- **WHEN** les tees du round sont « Gold » et la carte SGT a les tees Blue, White, Red, Junior et PAR3
- **THEN** le parcours est « Tee à choisir » et la page liste les cinq tees lus avec leur rating et leur slope

#### Scenario: Par de la carte différent
- **WHEN** l'IA lit un par 72 sur la carte d'un parcours dont le round a un par 71
- **THEN** le parcours est « Saisie manuelle » avec la note « Par de la carte SGT différent »

#### Scenario: Image qui n'est pas une carte
- **WHEN** l'IA répond que l'image n'est pas une carte de score, ou lit un slope de 160
- **THEN** le parcours est « Saisie manuelle » avec la note « Lecture IA impossible »

### Requirement: Lecture de la carte sous le budget IA
La lecture d'une carte SGT SHALL utiliser la clé d'API, le modèle, le plafond mensuel et le cumul du mois de la lecture des photos (spec `scorecard-ai-completion`). Avant l'appel, si le cumul plus le coût maximal d'une lecture de carte dépasse le plafond, aucun appel MUST NOT être fait. Chaque appel facturé MUST être compté, même si sa réponse est inexploitable.

#### Scenario: Sans clé d'API
- **GIVEN** aucune clé d'API n'est configurée
- **WHEN** la recherche de « BETHPAGE BLACK / Red » retient le parcours SGT 30
- **THEN** aucun appel n'est fait, et le parcours est « Saisie manuelle » avec la note « Lecture IA désactivée » et le lien « Carte SGT »

#### Scenario: Budget atteint
- **GIVEN** un plafond de 5 $, un coût maximal de lecture de carte de 19 000 micro-dollars et un cumul du mois de 4 990 000 micro-dollars
- **WHEN** la recherche de « BETHPAGE BLACK / Red » retient le parcours SGT 30
- **THEN** aucun appel n'est fait, et le parcours est « Saisie manuelle » avec la note « Budget IA du mois atteint »

#### Scenario: Appel compté
- **GIVEN** le modèle coûte 2 micro-dollars par token d'entrée et 10 par token de sortie
- **WHEN** une lecture de carte facture 1 500 tokens d'entrée et 300 tokens de sortie
- **THEN** le cumul du mois augmente d'exactement 6 000 micro-dollars

#### Scenario: Réponse inexploitable facturée
- **WHEN** l'IA répond par un slope de 160, facturé 1 500 tokens d'entrée et 300 de sortie au même modèle
- **THEN** le cumul du mois augmente de 6 000 micro-dollars et le parcours est « Saisie manuelle » avec la note « Lecture IA impossible »

### Requirement: Données envoyées pour une recherche
Une recherche SHALL n'envoyer à Simulator Golf Tour que la demande de sa liste de parcours et celle de l'image de carte du parcours retenu, par son identifiant, et à Anthropic que cette image, telle que servie par SGT, avec une consigne fixe. Elle MUST NOT envoyer ni le nom de parcours saisi, ni le nom d'un joueur, ni un score, ni un index.

#### Scenario: Requêtes vers SGT
- **WHEN** la recherche de « BETHPAGE BLACK / Red » aboutit
- **THEN** les seules requêtes vers SGT sont `GET https://simulatorgolftour.com/courses` (si la liste n'est pas gardée) et `GET` de l'image de carte du parcours 30, aucune ne contient « BETHPAGE BLACK »

#### Scenario: Requête vers Anthropic
- **GIVEN** les joueurs Gab, Manu et Sean et la carte `IMG_3064` enregistrée
- **WHEN** la carte SGT de Bethpage Black est lue
- **THEN** la requête contient une seule image, celle de SGT, et ne contient ni « Gab », ni « Manu », ni « Sean », ni « BETHPAGE BLACK »

### Requirement: Simulator Golf Tour injoignable
Quand la liste ou l'image de carte de Simulator Golf Tour ne répond pas, répond par une erreur ou dans un format inattendu, le système SHALL réessayer la recherche une fois, puis mettre le parcours en « Recherche échouée » avec la note « SGT injoignable ». La saisie à la main et « Relancer la recherche » MUST rester possibles.

#### Scenario: Deux échecs
- **WHEN** la liste SGT répond par une erreur HTTP 503 aux deux tentatives
- **THEN** le parcours est « Recherche échouée », la note « SGT injoignable » et l'action « Relancer la recherche » s'affichent

#### Scenario: Échec passager
- **WHEN** la liste SGT ne répond pas à la première tentative puis répond normalement à la seconde
- **THEN** la recherche aboutit comme si aucune erreur n'avait eu lieu

#### Scenario: Page sans liste
- **WHEN** la page SGT ne contient plus le bloc de la liste des parcours
- **THEN** le parcours finit en « Recherche échouée » avec la note « SGT injoignable », et aucune liste n'est gardée

### Requirement: Recherche désactivée
Le système SHALL lire `SGT_LOOKUP`, `on` par défaut. À `off`, une recherche MUST NOT faire aucune requête vers Simulator Golf Tour ni aucun appel à l'IA, et finit en « Saisie manuelle » avec la note « Recherche SGT désactivée ». Une valeur autre que `on` ou `off` MUST faire échouer le démarrage du web, du worker et des migrations avec un message qui nomme `SGT_LOOKUP`.

#### Scenario: Recherche à off
- **GIVEN** `SGT_LOOKUP=off`
- **WHEN** un round est enregistré sur un parcours nouveau
- **THEN** aucune requête n'est faite vers SGT et le parcours est « Saisie manuelle » avec la note « Recherche SGT désactivée »

#### Scenario: Variable absente
- **WHEN** le worker démarre sans `SGT_LOOKUP`
- **THEN** les recherches interrogent SGT

#### Scenario: Valeur inconnue
- **WHEN** le web ou le worker démarre avec `SGT_LOOKUP=yes`
- **THEN** le démarrage échoue avec un message qui nomme `SGT_LOOKUP`

### Requirement: Statuts de la recherche
Sur la page Parcours, chaque parcours qui a une ligne de rating SHALL afficher l'état de sa recherche par l'un de ces libellés exacts : « Recherche en cours », « Proposition à confirmer », « Parcours SGT à choisir », « Tee à choisir », « Saisie manuelle », « Introuvable sur SGT », « Recherche échouée » ou « Confirmé », suivi de sa note quand il en a une.

#### Scenario: Recherche échouée
- **GIVEN** « PAYNES VALLEY / Blue » en échec avec la note « SGT injoignable »
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la ligne montre « sans rating », « Recherche échouée » et « SGT injoignable »

#### Scenario: Rating confirmée
- **GIVEN** « BETHPAGE BLACK / Red » confirmé à 71,4 / 144
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la ligne montre « 71,4 / 144 » et « Confirmé », sans note

### Requirement: Parcours SGT et proposition sur la page Parcours
Sur la page Parcours, un parcours dont un parcours SGT est retenu SHALL montrer son nom et son lieu SGT et les liens « Carte SGT » (image de carte) et « The Course View », ouverts dans un nouvel onglet. Il SHALL montrer selon son état la proposition avec « Confirmer », les tees lus chacun avec « Retenir », ou les candidats SGT chacun avec son lieu, son par, son lien « Carte SGT » et « Choisir ».

#### Scenario: Proposition à confirmer
- **GIVEN** la recherche de « BETHPAGE BLACK / Red » aboutie sur le parcours SGT 30
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la ligne montre « sans rating », « SGT : Bethpage Black » et « Farmingdale, NY », « Proposition : tee Red, 71,4 / 144 », l'action « Confirmer », un lien « Carte SGT » vers `https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg` et un lien « The Course View » vers `https://thecourseview.com/courses/bethpage-black`

#### Scenario: Lien The Course View d'un nom avec ponctuation
- **GIVEN** le parcours SGT retenu « Diamond Hawk Golf Course & Pub »
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** le lien « The Course View » mène à `https://thecourseview.com/courses/diamond-hawk-golf-course-pub`

#### Scenario: Candidats
- **GIVEN** « SANCTUARY COVE / Blue » à choisir entre « Sanctuary » (Sedalia, CO, par 72) et « The Pines Sanctuary Cove » (Hope Island, Queensland, Australia, par 72)
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la ligne liste les deux candidats avec leur lieu, « Par 72 », un lien « Carte SGT » et l'action « Choisir »

#### Scenario: Sans parcours SGT retenu
- **GIVEN** « CALA NOVA / White » « Introuvable sur SGT »
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** la ligne ne montre ni lien « Carte SGT », ni lien « The Course View »

### Requirement: Page Parcours rafraîchie pendant une recherche
Tant qu'au moins un parcours est « Recherche en cours », la page Parcours SHALL relire l'état des parcours toutes les 2 secondes, sans recharger la page ni perdre une saisie en cours, et MUST cesser de le faire dès qu'aucun parcours n'est en recherche.

#### Scenario: Recherche qui aboutit
- **GIVEN** la page Parcours ouverte avec « BETHPAGE BLACK / Red » en « Recherche en cours »
- **WHEN** la recherche aboutit
- **THEN** la ligne passe à « Proposition à confirmer » dans les 2 secondes, sans action de l'utilisateur

#### Scenario: Aucune recherche en cours
- **GIVEN** aucun parcours en « Recherche en cours »
- **WHEN** l'utilisateur laisse la page Parcours ouverte une minute
- **THEN** la page ne relit pas l'état des parcours

### Requirement: Confirmation d'une proposition
Sur la page Parcours, « Confirmer » SHALL enregistrer la rating et le slope proposés comme valeurs confirmées du parcours et de ses tees, passer le parcours à « Confirmé », recalculer l'historique de tous les joueurs qui ont un round sur ce parcours, et afficher « Rating confirmée. ».

#### Scenario: Confirmation
- **GIVEN** la carte `IMG_3064` enregistrée et la proposition 71,4 / 144 du tee Red pour « BETHPAGE BLACK / Red »
- **WHEN** l'utilisateur touche « Confirmer »
- **THEN** la ligne affiche « 71,4 / 144 » et « Confirmé », le message « Rating confirmée. » s'affiche, et les différentiels de Gab et de Manu sur cette carte passent de 1,0 à 0,5 et de 17,0 à 13,0

### Requirement: Choix d'un tee lu
Sur la page Parcours, « Retenir » sur un tee lu SHALL enregistrer sa rating et son slope comme valeurs confirmées du parcours, passer le parcours à « Confirmé », recalculer l'historique des joueurs du parcours, et afficher « Rating confirmée. ».

#### Scenario: Tee retenu
- **GIVEN** « BETHPAGE BLACK / Gold » en « Tee à choisir » avec les tees lus de la carte SGT 30
- **WHEN** l'utilisateur touche « Retenir » sur le tee White
- **THEN** la ligne affiche « 74,2 / 148 » et « Confirmé », et les rounds de « BETHPAGE BLACK / Gold » se calculent sur 74,2 et 148

### Requirement: Choix d'un parcours SGT candidat
Sur la page Parcours, « Choisir » sur un candidat SGT SHALL retenir ce parcours SGT, avec son nom et son lieu, remettre le parcours en « Recherche en cours » et relancer la recherche à partir de la lecture de sa carte, sans refaire la correspondance des noms, et afficher « Recherche relancée. ».

#### Scenario: Candidat choisi
- **GIVEN** « SANCTUARY COVE / Blue » à choisir entre « Sanctuary » et « The Pines Sanctuary Cove »
- **WHEN** l'utilisateur touche « Choisir » sur « The Pines Sanctuary Cove »
- **THEN** la ligne passe à « Recherche en cours » avec « SGT : The Pines Sanctuary Cove », puis sa carte est lue et sa proposition ou ses tees s'affichent

### Requirement: Relance de la recherche
Sur la page Parcours, chaque parcours qui a une ligne de rating SHALL proposer « Relancer la recherche ». La relance oublie le parcours SGT retenu, remet le parcours en « Recherche en cours », relance toute la recherche et affiche « Recherche relancée. ». Elle MUST NOT changer une rating confirmée avant une nouvelle confirmation. Deux relances rapprochées MUST donner une seule recherche exécutée.

#### Scenario: Recherche relancée sur un parcours confirmé
- **GIVEN** « BETHPAGE BLACK / Red » confirmé à 71,4 / 144
- **WHEN** l'utilisateur relance la recherche et qu'elle aboutit sur une proposition
- **THEN** la ligne affiche « 71,4 / 144 » et « Proposition à confirmer », et les différentiels restent calculés sur 71,4 et 144 jusqu'à une nouvelle confirmation

#### Scenario: Relance après un échec
- **GIVEN** « PAYNES VALLEY / Blue » en « Recherche échouée », « SGT injoignable »
- **WHEN** l'utilisateur relance la recherche et que SGT répond
- **THEN** la note disparaît et le parcours passe à l'issue de la nouvelle recherche

#### Scenario: Double relance
- **WHEN** l'utilisateur relance deux fois la recherche de « PAYNES VALLEY / Blue » avant que la première ait fini
- **THEN** une seule lecture de la carte est faite et comptée dans le budget

### Requirement: Saisie à la main d'un parcours recherché
Une rating saisie à la main SHALL passer le parcours à « Confirmé », quel que soit l'état de sa recherche, et garder ce qui a été lu sur SGT. Une saisie pour une paire qui n'a pas encore de ligne MUST créer la ligne « Confirmé » sans lancer de recherche.

#### Scenario: Saisie pendant une proposition
- **GIVEN** la proposition 71,4 / 144 du tee Red pour « BETHPAGE BLACK / Red »
- **WHEN** l'utilisateur saisit « 71,0 » et « 140 »
- **THEN** la ligne affiche « 71,0 / 140 » et « Confirmé », et les rounds du parcours se calculent sur 71,0 et 140

### Requirement: Recherche gardée après un retrait
Le retrait d'une rating confirmée SHALL redonner au parcours l'état de sa dernière recherche : « Proposition à confirmer » s'il a une proposition lue, sinon « Tee à choisir » s'il a des tees lus, sinon « Parcours SGT à choisir » s'il a des candidats, sinon « Saisie manuelle ».

#### Scenario: Proposition qui revient
- **GIVEN** « BETHPAGE BLACK / Red » confirmé à 71,4 / 144 depuis la proposition du tee Red
- **WHEN** l'utilisateur touche « Retirer la rating »
- **THEN** la ligne affiche « sans rating », « Proposition à confirmer » et « Proposition : tee Red, 71,4 / 144 »

#### Scenario: Rating saisie sans recherche
- **GIVEN** « PAYNES VALLEY / Blue » saisi à la main avant toute recherche
- **WHEN** l'utilisateur retire la rating
- **THEN** la ligne affiche « sans rating » et « Saisie manuelle »

### Requirement: Actions de recherche refusées
Une confirmation sans proposition lue MUST être refusée avec « Aucune proposition à confirmer », un tee absent des tees lus avec « Tee absent de la carte SGT », un candidat absent des candidats avec « Parcours SGT absent des candidats », sans rien changer. Une action qui vise une ligne de rating inconnue MUST répondre 404 sans rien changer.

#### Scenario: Confirmation sans proposition
- **GIVEN** « CALA NOVA / White » « Introuvable sur SGT »
- **WHEN** une demande de confirmation vise ce parcours
- **THEN** elle est refusée avec « Aucune proposition à confirmer » et rien ne change

#### Scenario: Tee inconnu
- **GIVEN** « BETHPAGE BLACK / Gold » en « Tee à choisir » avec les tees lus de la carte SGT 30
- **WHEN** une demande retient le tee « Gold »
- **THEN** elle est refusée avec « Tee absent de la carte SGT » et rien ne change

#### Scenario: Candidat inconnu
- **GIVEN** « SANCTUARY COVE / Blue » à choisir entre les parcours SGT 477 et 1951
- **WHEN** une demande choisit le parcours SGT 30
- **THEN** elle est refusée avec « Parcours SGT absent des candidats » et rien ne change

#### Scenario: Ligne inconnue
- **WHEN** une demande de relance vise un identifiant de ligne de rating qui n'existe pas
- **THEN** elle reçoit une réponse 404 et rien ne change

### Requirement: Actions de recherche nommées par parcours
Sur la page Parcours, chaque action de la recherche SHALL porter un nom accessible qui nomme son parcours et ses tees, ou son candidat : « Confirmer 71,4 / 144 pour BETHPAGE BLACK / Red », « Retenir le tee White pour BETHPAGE BLACK / Gold », « Choisir Sanctuary (Sedalia, CO) », « Relancer la recherche pour BETHPAGE BLACK / Red ». La page MUST NOT défiler horizontalement à 320 px, avec des candidats ou des tees lus.

#### Scenario: Noms accessibles
- **GIVEN** « BETHPAGE BLACK / Red » en « Proposition à confirmer » à 71,4 / 144
- **WHEN** l'utilisateur ouvre `/courses`
- **THEN** les boutons de la ligne s'appellent « Confirmer 71,4 / 144 pour BETHPAGE BLACK / Red » et « Relancer la recherche pour BETHPAGE BLACK / Red »

#### Scenario: Téléphone avec candidats
- **GIVEN** « SANCTUARY COVE / Blue » avec deux candidats
- **WHEN** l'utilisateur ouvre `/courses` sur un écran de 320 px
- **THEN** chaque candidat s'empile sous le parcours, et la page ne défile pas horizontalement

### Requirement: Actions de recherche protégées
Les actions de la recherche (confirmer, retenir un tee, choisir un candidat, relancer) SHALL exiger la session du code d'accès.

#### Scenario: Sans session
- **GIVEN** un navigateur sans session
- **WHEN** il envoie une confirmation, un choix de tee, un choix de candidat ou une relance
- **THEN** il est redirigé vers `/login` et rien ne change
