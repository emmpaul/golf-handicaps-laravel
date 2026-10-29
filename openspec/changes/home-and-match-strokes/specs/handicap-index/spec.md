# Spec Delta

## ADDED Requirements

### Requirement: Un joueur à la fois sur la page Handicaps
La page Handicaps SHALL afficher un sélecteur des joueurs, par nom sans tenir compte de la casse, et la section d'un seul joueur à la fois, à toute largeur d'écran : le premier joueur de la liste à l'ouverture, puis celui choisi. Le joueur choisi MUST se retrouver dans l'adresse de la page (`/handicaps?joueur=<identifiant>`), pour qu'un retour arrière ou un rechargement le garde. Un identifiant inconnu ou mal formé ouvre le premier joueur. La page exige la session du code d'accès.

#### Scenario: Ouverture
- **GIVEN** les joueurs Gab, Manu et Sean
- **WHEN** l'utilisateur ouvre `/handicaps` sur un écran de 390 px
- **THEN** le sélecteur montre « Gab », « Manu » et « Sean », Gab est marqué choisi (`aria-current="page"`) et seule la section de Gab est affichée

#### Scenario: Choix d'un joueur
- **WHEN** l'utilisateur touche « Manu » dans le sélecteur, puis recharge la page
- **THEN** l'adresse porte l'identifiant de Manu, et seule la section de Manu est affichée, avant et après le rechargement

#### Scenario: Ordinateur
- **WHEN** l'utilisateur ouvre `/handicaps` sur un écran de 1440 px
- **THEN** le sélecteur est affiché et seule la section de Gab l'est

#### Scenario: Joueur inconnu
- **WHEN** l'utilisateur ouvre `/handicaps?joueur=0190a6b2-7c3e-7000-8000-000000000099`, puis `/handicaps?joueur=12`
- **THEN** le système répond 200 les deux fois, avec la section de Gab

#### Scenario: Aucun joueur
- **GIVEN** une base sans joueur
- **WHEN** l'utilisateur ouvre `/handicaps`
- **THEN** la page affiche « Aucun joueur. » sans sélecteur

#### Scenario: Sans session
- **GIVEN** aucune session ouverte
- **WHEN** le navigateur demande `/handicaps`
- **THEN** le système redirige vers `/login`, et la réponse ne contient aucun nom de joueur

#### Scenario: Sous-adresse inconnue
- **WHEN** l'utilisateur ouvre `/handicaps/0190a6b2-7c3e-7000-8000-000000000001`
- **THEN** le système répond 404

### Requirement: Résumé de l'index d'un joueur
En tête de la section du joueur choisi, la page Handicaps SHALL afficher son nom, son index actuel en grand, l'écart signé avec son index d'avant son dernier round (« −0,3 », « +0,1 », « = »), comme sur l'accueil, et la mention « provisoire » pour un index provisoire. Un joueur sans round affiche « pas encore d'index » sans écart ; un joueur à un seul round n'a pas d'écart.

#### Scenario: Résumé de Manu
- **GIVEN** Manu avait un index de 15,5 avant son dernier round et de 15,8 après
- **WHEN** l'utilisateur choisit Manu
- **THEN** la section de Manu commence par « Manu », « 15,8 » et « Écart +0,3 »

#### Scenario: Index provisoire
- **GIVEN** Sean avec 2 rounds de différentiels 17,0 et 12,0
- **WHEN** l'utilisateur choisit Sean
- **THEN** la section affiche « 14,5 », « Écart −2,5 » et « provisoire »

#### Scenario: Joueur sans round
- **GIVEN** Sean sans round
- **WHEN** l'utilisateur choisit Sean
- **THEN** la section affiche « pas encore d'index », aucun écart et « Aucun round pour l’instant. »

### Requirement: Liste des rounds d'un joueur
Sous le résumé, la page Handicaps SHALL lister tous les rounds du joueur choisi, du plus récent au plus ancien par date de jeu, puis par date d'enregistrement : date, parcours et tees en deux éléments distincts sans « · », différentiel et index juste après ce round. Les rounds dont le différentiel compte dans l'index actuel (spec `handicap-index`, « Historique de l'index ») portent la marque « Compte » et un différentiel en gras. Chaque ligne MUST mener à la page de son round.

#### Scenario: Lignes de Gab
- **GIVEN** Gab avec des rounds le 2026-10-01 (différentiel 20,0), le 2026-10-10 (17,0) et le 2026-10-17 (12,0), sans rating
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** les lignes sont, dans l'ordre, « 17 oct. 2026 », « 12,0 », « 10,0 » avec la marque « Compte » ; « 10 oct. 2026 », « 17,0 », « 18,5 » sans marque ; « 1 oct. 2026 », « 20,0 », « 20,0 » sans marque

#### Scenario: Parcours sur la ligne
- **GIVEN** la carte `IMG_3064` sur « BETHPAGE BLACK / Red », premier round de Gab
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** la ligne montre « BETHPAGE BLACK » et « Red », sans « · », « 1,0 », « 1,0 » et la marque « Compte »

#### Scenario: Vers la page du round
- **WHEN** l'utilisateur touche la ligne du round du 2026-10-17 de Gab
- **THEN** la page de ce round s'ouvre

#### Scenario: Plus de 20 rounds
- **GIVEN** Manu avec 21 rounds
- **WHEN** l'utilisateur ouvre `/handicaps` sur Manu
- **THEN** les 21 lignes s'affichent, et le round le plus ancien ne porte pas la marque « Compte »

### Requirement: Marque « Compte » sur la ligne du round
Dans la liste des rounds d'un joueur, la marque « Compte » SHALL rester sur la même ligne que la date du round, dès 320 px de large, et la liste MUST NOT faire défiler la page horizontalement.

#### Scenario: Ligne d'un différentiel retenu à 320 px
- **WHEN** la liste des rounds de Gab s'affiche sur un écran de 320 px
- **THEN** la date « 17 oct. 2026 » et sa marque « Compte » sont à la même hauteur, sur une seule ligne, et la page ne défile pas horizontalement

## MODIFIED Requirements

### Requirement: Explication du calcul
La page Joueurs, la page d'un round, la page Parcours et la page Handicaps SHALL proposer une explication repliée, titrée « Comment l’index est calculé », fermée à l'ouverture de la page. Le titre MUST s'ouvrir au clavier, et l'explication dépliée MUST NOT faire défiler la page horizontalement à 320 px.

#### Scenario: Fermée à l'ouverture
- **WHEN** l'utilisateur ouvre `/players`
- **THEN** la page affiche « Comment l’index est calculé » et aucune des sept lignes n'est visible

#### Scenario: Ouverte au clavier
- **WHEN** l'utilisateur place le focus sur « Comment l’index est calculé » et appuie sur Entrée
- **THEN** l'explication se déplie

#### Scenario: Page Handicaps
- **WHEN** l'utilisateur ouvre `/handicaps`
- **THEN** la page affiche « Comment l’index est calculé », fermée, et non le texte « différentiel = score ajusté − par »

### Requirement: Texte de l'explication du calcul
Dépliée, l'explication du calcul SHALL afficher sept lignes, dans cet ordre : le score ajusté, le différentiel d'un round, l'index, le nombre de différentiels retenus, l'ajustement, l'index provisoire et le repli sans rating. Leur texte MUST être exactement celui du scénario « Texte exact », le même sur chaque page qui la propose.

#### Scenario: Texte exact
- **WHEN** l'utilisateur déplie « Comment l’index est calculé »
- **THEN** les sept lignes sont, dans l'ordre : « Score ajusté : chaque trou compte au plus par + 4. », « Différentiel d’un round : 113 / slope × (score ajusté − course rating), arrondi au dixième. », « Index : moyenne des meilleurs différentiels parmi les 20 derniers rounds, arrondie au dixième et plafonnée à 54,0. », « Différentiels retenus : 1 de 3 à 5 rounds, 2 de 6 à 8, 3 de 9 à 11, 4 de 12 à 14, 5 à 15 ou 16, 6 à 17 ou 18, 7 à 19, 8 à 20. », « Ajustement : −2,0 à 3 rounds, −1,0 à 4 rounds et à 6 rounds, aucun sinon. », « Index provisoire à 1 ou 2 rounds : moyenne de leurs différentiels, sans ajustement. » et « Parcours sans rating : la course rating vaut le par du round et le slope 113. »

#### Scenario: Dépliée sur la page Joueurs
- **WHEN** l'utilisateur ouvre `/players` puis active « Comment l’index est calculé »
- **THEN** la page affiche « Différentiel d’un round : 113 / slope × (score ajusté − course rating), arrondi au dixième. » et les six autres lignes, dans l'ordre

#### Scenario: Page d'un round et page Parcours
- **WHEN** l'utilisateur ouvre la carte `IMG_3064`, puis `/courses`, et déplie l'explication sur chacune
- **THEN** chacune affiche « Parcours sans rating : la course rating vaut le par du round et le slope 113. » et les six autres lignes

#### Scenario: Dépliée sur la page Handicaps
- **WHEN** l'utilisateur ouvre `/handicaps` puis active « Comment l’index est calculé »
- **THEN** la page affiche les sept lignes du scénario « Texte exact », dans l'ordre

#### Scenario: Téléphone
- **WHEN** l'utilisateur déplie l'explication sur `/players` sur un écran de 320 px
- **THEN** les lignes passent à la ligne et la page ne défile pas horizontalement
