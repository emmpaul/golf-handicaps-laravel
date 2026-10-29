## MODIFIED Requirements

### Requirement: Mise en page de la page Handicaps
À 390 px, la page Handicaps SHALL suivre le design : titre « Handicaps », un onglet en pilule par joueur (le sélecteur de « Un joueur à la fois sur la page Handicaps »), « INDEX ACTUEL », l'index du joueur choisi en grand, sa tendance et son résumé, le graphique des différentiels, le bloc STATS, l'historique, puis le lien « Comment l’index est calculé ».

#### Scenario: Onglets de joueur
- **WHEN** l'utilisateur ouvre `/handicaps` avec Gab, Manu et Sean, Gab choisi
- **THEN** chaque onglet fait 44 px de haut, rayon 999 px, Geist 600, 14 px, avec la pastille de 8 px du joueur ; celui de Gab a le fond et la bordure `oklch(0.97 0.003 260)` et le texte `oklch(0.13 0.005 260)`, les autres un fond transparent, la bordure `oklch(0.27 0.008 260)` et le texte gris

## ADDED Requirements

### Requirement: Lien vers le calcul de l'index
La page Joueurs, la page d'un round, la page Parcours et la page Handicaps SHALL proposer un lien « Comment l’index est calculé » vers la page « Calcul de l'index » (`/handicaps/calcul`). Depuis la page Handicaps, le lien MUST garder le joueur choisi (`/handicaps/calcul?joueur=<identifiant>`). Aucune de ces quatre pages MUST NOT afficher les lignes de règle elle-même.

#### Scenario: Lien sur la page Joueurs
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la page affiche le lien « Comment l’index est calculé » vers `/handicaps/calcul`, et aucune des sept lignes de « Lignes de règle du calcul de l'index »

#### Scenario: Ouvert au clavier
- **WHEN** l'utilisateur place le focus sur « Comment l’index est calculé » et appuie sur Entrée
- **THEN** la page « Calcul de l'index » s'ouvre

#### Scenario: Page Handicaps
- **GIVEN** les joueurs Gab, Manu et Sean
- **WHEN** l'utilisateur ouvre `/handicaps` et choisit Manu
- **THEN** le lien « Comment l’index est calculé » mène à `/handicaps/calcul?joueur=<identifiant de Manu>`, et la page n'affiche pas le texte « différentiel = score ajusté − par »

#### Scenario: Page d'un round et page Parcours
- **WHEN** l'utilisateur ouvre la carte `IMG_3064`, puis `/courses`
- **THEN** chacune affiche le lien « Comment l’index est calculé » vers `/handicaps/calcul`, et non « Parcours sans rating : la course rating vaut le par du round et le slope 113. »

### Requirement: Lignes de règle du calcul de l'index
La page « Calcul de l'index » SHALL afficher sept lignes de règle, dans cet ordre : le score ajusté (étape 01), le différentiel d'un round (02), le nombre de différentiels retenus (03), l'ajustement (04), l'index (05), puis l'index provisoire et le repli sans rating (cas particuliers). Leur texte MUST être exactement celui du scénario « Texte exact des lignes », quel que soit le joueur choisi, avec ou sans round.

#### Scenario: Texte exact des lignes
- **WHEN** l'utilisateur ouvre `/handicaps/calcul`
- **THEN** les sept lignes sont, dans l'ordre : « Score ajusté : chaque trou compte au plus par + 4. », « Différentiel d’un round : 113 / slope × (score ajusté − course rating), arrondi au dixième. », « Différentiels retenus : 1 de 3 à 5 rounds, 2 de 6 à 8, 3 de 9 à 11, 4 de 12 à 14, 5 à 15 ou 16, 6 à 17 ou 18, 7 à 19, 8 à 20. », « Ajustement : −2,0 à 3 rounds, −1,0 à 4 rounds et à 6 rounds, aucun sinon. », « Index : moyenne des meilleurs différentiels parmi les 20 derniers rounds, arrondie au dixième et plafonnée à 54,0. », « Index provisoire à 1 ou 2 rounds : moyenne de leurs différentiels, sans ajustement. » et « Parcours sans rating : la course rating vaut le par du round et le slope 113. »

#### Scenario: Même texte pour chaque joueur
- **GIVEN** Gab avec des rounds et Sean sans round
- **WHEN** l'utilisateur ouvre la page sur Gab, puis sur Sean
- **THEN** les sept lignes sont les mêmes les deux fois

#### Scenario: Téléphone
- **WHEN** l'utilisateur ouvre `/handicaps/calcul` sur un écran de 320 px
- **THEN** les lignes passent à la ligne et la page ne défile pas horizontalement

### Requirement: Page « Calcul de l'index »
La page `/handicaps/calcul` SHALL exiger la session du code d'accès et afficher le lien de retour « Handicaps », le titre « Comment l’index est calculé », les onglets de joueur, puis « Chaque étape avec les rounds de {nom}. ». Le joueur choisi suit les règles de « Un joueur à la fois sur la page Handicaps » : premier par nom à l'ouverture, gardé dans `?joueur=`, premier joueur pour un identifiant inconnu ou mal formé. L'onglet Handicaps de la barre reste courant.

#### Scenario: Ouverture
- **GIVEN** les joueurs Gab, Manu et Sean
- **WHEN** l'utilisateur ouvre `/handicaps/calcul`
- **THEN** le système répond 200, Gab est marqué choisi (`aria-current="page"`) et la page affiche « Chaque étape avec les rounds de Gab. »

#### Scenario: Choix d'un joueur
- **WHEN** l'utilisateur touche « Manu » dans les onglets, puis recharge la page
- **THEN** l'adresse est `/handicaps/calcul?joueur=<identifiant de Manu>` et la page affiche « Chaque étape avec les rounds de Manu. », avant et après le rechargement

#### Scenario: Joueur inconnu
- **WHEN** l'utilisateur ouvre `/handicaps/calcul?joueur=0190a6b2-7c3e-7000-8000-000000000099`, puis `/handicaps/calcul?joueur=12`
- **THEN** le système répond 200 les deux fois, sur Gab

#### Scenario: Retour
- **WHEN** l'utilisateur, sur Manu, touche « Handicaps »
- **THEN** la page Handicaps s'ouvre sur Manu (`/handicaps?joueur=<identifiant de Manu>`)

#### Scenario: Sans session
- **GIVEN** aucune session ouverte
- **WHEN** le navigateur demande `/handicaps/calcul`
- **THEN** le système redirige vers `/login`, et la réponse ne contient aucun nom de joueur ni aucun score

#### Scenario: Aucun joueur
- **GIVEN** une base sans joueur
- **WHEN** l'utilisateur ouvre `/handicaps/calcul`
- **THEN** la page affiche « Aucun joueur. » sans onglets, puis les sept lignes de règle sans valeur

### Requirement: Étape 01, score ajusté
Sous « 01 SCORE AJUSTÉ » et sa ligne de règle, la page SHALL montrer le dernier trou plafonné du joueur parmi ses 20 derniers rounds : « TROU {n} · P{par} », les coups joués barrés, « MAX » et par + 4. Le trou vient du round le plus récent qui en a un ; dans ce round, le plus grand dépassement, puis le plus petit numéro. Sans trou plafonné, « Aucun trou plafonné sur les 20 derniers rounds. » le remplace. « DERNIER ROUND » donne « {ajusté} / {brut} » du dernier round.

#### Scenario: Trou plafonné
- **GIVEN** la carte `IMG_3064` où Manu fait 10 au trou 1 (par 4), son seul round
- **WHEN** l'utilisateur ouvre la page sur Manu
- **THEN** l'étape montre « TROU 1 · P4 », « 10 » barré, « MAX » et « 8 », et « DERNIER ROUND » « 89 / 91 »

#### Scenario: Aucun trou plafonné
- **GIVEN** Gab n'a joué que la carte `IMG_3064` (72 brut, aucun trou au-dessus de par + 4)
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** l'étape montre « Aucun trou plafonné sur les 20 derniers rounds. » et « DERNIER ROUND » « 72 / 72 », sans « TROU » ni « MAX »

#### Scenario: Round le plus récent d'abord
- **GIVEN** Manu avec un round le 2026-10-10 où il fait 11 au trou 5 (par 4), puis la carte `IMG_3064` le 2026-10-17 avec 10 au trou 1 (par 4)
- **WHEN** l'utilisateur ouvre la page sur Manu
- **THEN** l'étape montre « TROU 1 · P4 », et non le trou 5

#### Scenario: Plus grand dépassement du round
- **GIVEN** la carte `IMG_3064` où Manu fait 9 au trou 1 (par 4) et 10 au trou 2 (par 4)
- **WHEN** l'utilisateur ouvre la page sur Manu
- **THEN** l'étape montre « TROU 2 · P4 », « 10 » barré et « 8 »

### Requirement: Étape 02, différentiel
Sous « 02 DIFFÉRENTIEL » et sa ligne de règle, la page SHALL écrire la formule avec le dernier round du joueur : « 113 », « / », le slope (« SLOPE »), « × ( », le score ajusté (« AJUSTÉ »), « − », la course rating (« RATING »), « ) ». Ce sont la rating et le slope qu'utilise le calcul : la rating confirmée du parcours et des tees, sinon le par du round et 113. Dessous, « {PARCOURS} · {jour} {MOIS} » et le différentiel du round dans la couleur du joueur.

#### Scenario: Round sans rating
- **GIVEN** Gab n'a joué que la carte `IMG_3064` le 2026-10-17, « BETHPAGE BLACK / Red » sans rating
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la formule montre « 113 », « 113 », « 72 » et « 71 », puis « BETHPAGE BLACK · 17 OCT » et « 1,0 » en `oklch(0.72 0.24 145)`

#### Scenario: Round avec rating
- **GIVEN** « BETHPAGE BLACK / Red » avec une rating confirmée de 71,4 et un slope de 144, et la carte `IMG_3064` le seul round de Manu
- **WHEN** l'utilisateur ouvre la page sur Manu
- **THEN** la formule montre « 113 », « 144 », « 88 » et « 71,4 », puis « 13,0 »

#### Scenario: Rating seulement proposée
- **GIVEN** « BETHPAGE BLACK / Red » avec une rating proposée par Simulator Golf Tour, pas encore confirmée
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la formule montre « 113 » pour le slope et « 71 » pour la rating, et « 1,0 »

### Requirement: Étape 03, différentiels retenus
Sous « 03 DIFFÉRENTIELS RETENUS » et sa ligne de règle, la page SHALL dessiner 20 marches, une par nombre de rounds de 1 à 20, de hauteur proportionnelle au nombre de différentiels retenus ; la marche du nombre de rounds du joueur (au plus 20) prend sa couleur et porte ce nombre retenu. Dessous, « ROUNDS JOUÉS » et « {n} → {k} RETENU » (« RETENUS » au-delà de 1), puis la liste de ses 20 derniers rounds.

#### Scenario: Trois rounds
- **GIVEN** Gab avec 3 rounds de différentiels 17,0, 12,0 et 20,0
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la marche 3 est en `oklch(0.72 0.24 145)` et porte « 1 », et la page affiche « 3 → 1 RETENU »

#### Scenario: Six rounds
- **GIVEN** Gab avec 6 rounds de différentiels 17,0, 12,0, 20,0, 15,0, 14,0 et 18,0
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la marche 6 porte « 2 » et la page affiche « 6 → 2 RETENUS »

#### Scenario: Index provisoire
- **GIVEN** Sean avec 2 rounds
- **WHEN** l'utilisateur ouvre la page sur Sean
- **THEN** la marche 2 est à la couleur de Sean et porte « 2 », et la page affiche « 2 → 2 RETENUS »

### Requirement: Liste des 20 derniers rounds de l'étape 03
Sous « 20 DERNIERS ROUNDS · {n} » et « DIFF. », la page SHALL lister les 20 derniers rounds du joueur, du plus récent au plus ancien, dans l'ordre de l'index : le jour et le mois en capitales abrégées (« 17 », « OCT »), le parcours, « {ajusté} ajusté · {rating} / {slope} » (« par {par} » à la place de la rating sans rating confirmée), puis le différentiel. Un round retenu dans l'index actuel porte la pastille du joueur, au nom accessible « Retenu » ; les autres sont en gris.

#### Scenario: Six rounds
- **GIVEN** Gab avec 6 rounds de différentiels 17,0, 12,0, 20,0, 15,0, 14,0 et 18,0, dans l'ordre des dates
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la liste porte « 20 DERNIERS ROUNDS · 6 », six lignes du plus récent au plus ancien, et seules les lignes « 12,0 » et « 14,0 » portent la pastille « Retenu »

#### Scenario: Ligne d'un round sans rating
- **GIVEN** la carte `IMG_3064` du 2026-10-17, seul round de Gab, « BETHPAGE BLACK / Red » sans rating
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la ligne montre « 17 », « OCT », « BETHPAGE BLACK », « 72 ajusté · par 71 / 113 » et « 1,0 »

#### Scenario: Ligne d'un round avec rating
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144, confirmée
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la ligne montre « 72 ajusté · 71,4 / 144 » et « 0,5 »

#### Scenario: Plus de 20 rounds
- **GIVEN** Manu avec 21 rounds
- **WHEN** l'utilisateur ouvre la page sur Manu
- **THEN** la liste porte « 20 DERNIERS ROUNDS · 20 » et 20 lignes, sans le round le plus ancien, et la page affiche « 20 → 8 RETENUS »

### Requirement: Étape 04, ajustement
Sous « 04 AJUSTEMENT » et sa ligne de règle, la page SHALL montrer quatre cases : « 3 ROUNDS » « −2,0 », « 4 ROUNDS » « −1,0 », « 6 ROUNDS » « −1,0 » et « AUTRES » « 0 ». La case du nombre de rounds du joueur (au plus 20) est mise en valeur, bordure et libellé à sa couleur, et annoncée « appliqué » aux lecteurs d'écran ; « AUTRES » pour tout autre nombre, provisoire compris.

#### Scenario: Trois rounds
- **GIVEN** Gab avec 3 rounds
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** seule la case « 3 ROUNDS » « −2,0 » est mise en valeur

#### Scenario: Cinq rounds
- **GIVEN** Gab avec 5 rounds
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** seule la case « AUTRES » « 0 » est mise en valeur

### Requirement: Étape 05, index
Sous « 05 INDEX » et sa ligne de règle, la page SHALL montrer deux lignes : « Meilleur différentiel » pour un seul retenu, sinon « Moyenne des {k} meilleurs ({d1} · {d2} …) », du plus petit au plus grand (« Moyenne des {k} différentiels » pour un index provisoire), avec la moyenne arrondie au dixième ; puis « Ajustement » et sa valeur, « aucun » sans ajustement. Dessous, « INDEX {NOM} » avec la pastille du joueur et son index actuel en Geist Mono 500, 88 px.

#### Scenario: Six rounds
- **GIVEN** Gab avec 6 rounds de différentiels 17,0, 12,0, 20,0, 15,0, 14,0 et 18,0
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** l'étape montre « Moyenne des 2 meilleurs (12,0 · 14,0) » et « 13,0 », « Ajustement » et « −1,0 », puis « INDEX GAB » et « 12,0 »

#### Scenario: Trois rounds
- **GIVEN** Gab avec 3 rounds de différentiels 17,0, 12,0 et 20,0
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** l'étape montre « Meilleur différentiel » et « 12,0 », « Ajustement » et « −2,0 », puis « 10,0 »

#### Scenario: Sans ajustement
- **GIVEN** Gab avec 5 rounds de différentiels 17,0, 12,0, 20,0, 15,0 et 14,0
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** l'étape montre « Meilleur différentiel » et « 12,0 », « Ajustement » et « aucun », puis « 12,0 »

#### Scenario: Index provisoire
- **GIVEN** Sean avec 2 rounds de différentiels 17,0 et 12,0
- **WHEN** l'utilisateur ouvre la page sur Sean
- **THEN** l'étape montre « Moyenne des 2 différentiels (12,0 · 17,0) » et « 14,5 », « Ajustement » et « aucun », puis « 14,5 »

#### Scenario: Plafond
- **GIVEN** Gab avec 3 rounds de différentiels 63,0, 70,0 et 65,0
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** l'étape montre « Meilleur différentiel » et « 63,0 », « Ajustement » et « −2,0 », puis « 54,0 »

### Requirement: Cas particuliers de la page « Calcul de l'index »
Sous « CAS PARTICULIERS », la page SHALL montrer la pastille « PROVISOIRE » en ambre suivie de la ligne de l'index provisoire, puis la pastille neutre « SANS RATING » suivie de la ligne du repli sans rating. Si un des 20 derniers rounds du joueur est sans rating confirmée, la page ajoute « Ex. {parcours} → rating {par} · slope 113 », tiré du plus récent d'entre eux.

#### Scenario: Exemple sans rating
- **GIVEN** la carte `IMG_3064` sur « BETHPAGE BLACK / Red » sans rating, seul round de Gab
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la page affiche « Ex. BETHPAGE BLACK → rating 71 · slope 113 »

#### Scenario: Tous les parcours notés
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144, confirmée, et la carte `IMG_3064` seul round de Gab
- **WHEN** l'utilisateur ouvre la page sur Gab
- **THEN** la page affiche « SANS RATING » et sa ligne, sans ligne « Ex. »

### Requirement: Joueur sans round sur la page « Calcul de l'index »
Pour un joueur sans round, la page SHALL afficher les cinq étapes et les cas particuliers avec leurs lignes de règle, l'escalier et les quatre cases d'ajustement sans mise en valeur, et « Pas encore d'index. Un premier round donne un index provisoire. » à la place de l'index. Elle MUST NOT montrer de trou, de formule chiffrée, de liste de rounds, de moyenne ni d'exemple.

#### Scenario: Sean sans round
- **GIVEN** Sean sans round
- **WHEN** l'utilisateur ouvre la page sur Sean
- **THEN** la page affiche les sept lignes de règle et « Pas encore d'index. Un premier round donne un index provisoire. », aucune marche ni case en couleur, et ni « DERNIER ROUND », ni « 20 DERNIERS ROUNDS », ni « INDEX SEAN »

### Requirement: Mise en page de la page « Calcul de l'index »
À 390 px, la page SHALL suivre le design « Calcul de l'index » : titre en Geist 600, 28 px ; chaque étape sous un libellé de section en Geist Mono 500, 11 px, dont le numéro prend la couleur du joueur ; lignes de règle en Geist 400, 15 px, interlignage 1,45 ; grands nombres en Geist Mono. Dès 320 px, rien ne défile de côté.

#### Scenario: Libellé d'étape
- **GIVEN** Gab choisi
- **WHEN** l'étape « 02 DIFFÉRENTIEL » s'affiche à 390 px
- **THEN** « 02 » est en `oklch(0.72 0.24 145)`, « DIFFÉRENTIEL » en gris, en Geist Mono 500, 11 px, au-dessus d'un filet `oklch(0.27 0.008 260)`, et le différentiel « 1,0 » en Geist Mono 500, 36 px

#### Scenario: Escalier à 320 px
- **GIVEN** Manu avec 20 rounds
- **WHEN** l'utilisateur ouvre la page sur Manu à 320 px
- **THEN** les 20 marches tiennent dans la largeur, la marche 20 porte « 8 », et la page ne défile pas horizontalement

#### Scenario: Index en grand
- **WHEN** l'étape « 05 INDEX » de Gab s'affiche
- **THEN** l'index est en Geist Mono 500, 88 px, aligné à droite de « INDEX GAB »

## REMOVED Requirements

### Requirement: Explication du calcul
**Reason**: Le bloc replié de sept lignes est remplacé par la page « Calcul de l'index », qui déroule le calcul avec les rounds du joueur (design « Calcul de l'index »).
**Migration**: Les quatre pages portent le lien de « Lien vers le calcul de l'index » ; les lignes s'affichent sur la page, selon « Lignes de règle du calcul de l'index ».

### Requirement: Texte de l'explication du calcul
**Reason**: Les sept lignes ne s'affichent plus dans un bloc replié mais sur la page « Calcul de l'index », dans l'ordre de ses étapes.
**Migration**: Voir « Lignes de règle du calcul de l'index », qui garde le texte exact de chaque ligne.
