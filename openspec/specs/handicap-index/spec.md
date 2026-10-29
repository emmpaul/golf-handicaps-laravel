# handicap-index Specification

## Purpose
Calculer pour chaque joueur un index de handicap inspiré du WHS, à partir de ses rounds et de la course rating et du slope des parcours quand ils sont connus, et en garder la valeur après chacun de ses rounds.

## Requirements

### Requirement: Score ajusté par trou
Pour le handicap, le système SHALL compter chaque trou au plus par + 4 (quadruple bogey). Le score ajusté d'un joueur sur un round est la somme de ses 18 trous ainsi plafonnés. Le score brut affiché ne change pas.

#### Scenario: Trou plafonné
- **GIVEN** la carte `IMG_3064` où Manu fait 10 au trou 1 (par 4) au lieu de 7
- **WHEN** le round est enregistré
- **THEN** ce trou compte 8, le score ajusté de Manu vaut 89 et son brut affiché 91

#### Scenario: Aucun trou au-dessus du plafond
- **WHEN** la carte `IMG_3064` est enregistrée avec Gab et Manu
- **THEN** le score ajusté de Gab vaut 72 et celui de Manu 88

### Requirement: Différentiel d'un round
Le système SHALL calculer le différentiel d'un joueur sur un round : (113 / slope) × (score ajusté − course rating), arrondi au dixième le plus proche, 0,05 arrondi vers le haut. La course rating et le slope sont ceux confirmés pour le parcours et les tees du round (spec `course-ratings`). Sans rating confirmée, la course rating vaut le par total du round et le slope 113.

#### Scenario: Différentiels sans rating
- **GIVEN** « BETHPAGE BLACK / Red » sans rating
- **WHEN** la carte `IMG_3064` (par 71) est enregistrée
- **THEN** le différentiel de Gab vaut 1,0 et celui de Manu 17,0

#### Scenario: Différentiels avec rating
- **GIVEN** « BETHPAGE BLACK / Red » avec une rating de 71,4 et un slope de 144
- **WHEN** la carte `IMG_3064` est enregistrée
- **THEN** le différentiel de Gab vaut 0,5 et celui de Manu 13,0

#### Scenario: Round sous le par
- **GIVEN** « BETHPAGE BLACK / Red » sans rating
- **WHEN** Gab joue la carte `IMG_3064` avec 3 au trou 1 au lieu de 5
- **THEN** son différentiel vaut −1,0

#### Scenario: Proposition en attente
- **GIVEN** « BETHPAGE BLACK / Red » avec la proposition 71,4 / 144, non confirmée
- **WHEN** la carte `IMG_3064` est enregistrée
- **THEN** le différentiel de Gab vaut 1,0 et celui de Manu 17,0

### Requirement: Calcul de l'index
L'index d'un joueur SHALL se calculer sur ses 20 derniers rounds au plus, triés par date de jeu puis par date d'enregistrement. On applique la table WHS au nombre n de rounds : 3 → meilleur − 2,0 ; 4 → meilleur − 1,0 ; 5 → meilleur ; 6 → moyenne des 2 meilleurs − 1,0 ; 7-8 → 2 meilleurs ; 9-11 → 3 ; 12-14 → 4 ; 15-16 → 5 ; 17-18 → 6 ; 19 → 7 ; 20 → 8.

#### Scenario: Trois rounds
- **WHEN** les différentiels d'un joueur sont 17,0, 12,0 et 20,0
- **THEN** son index vaut 10,0

#### Scenario: Six rounds
- **WHEN** les différentiels d'un joueur sont 17,0, 12,0, 20,0, 15,0, 14,0 et 18,0
- **THEN** son index vaut 12,0

#### Scenario: Plus de 20 rounds
- **GIVEN** un joueur avec 21 rounds dont le plus ancien porte le meilleur différentiel, 0,0, et les 20 autres 17,0, 12,0, 20,0, 15,0, 14,0, 18,0, 16,0, 13,0, 19,0, 11,0, 17,5, 12,5, 16,5, 13,5, 18,5, 11,5, 15,5, 14,5, 19,5 et 10,5
- **WHEN** son index est calculé
- **THEN** le round le plus ancien n'entre pas dans le calcul et l'index vaut 12,3

#### Scenario: Même jour
- **GIVEN** les mêmes 21 rounds, tous joués le même jour et enregistrés dans le même ordre
- **WHEN** son index est calculé
- **THEN** le round enregistré en premier n'entre pas dans le calcul et l'index vaut 12,3

### Requirement: Index provisoire
Avec 1 ou 2 rounds, le système SHALL calculer un index provisoire égal à la moyenne des différentiels, marqué « provisoire ». Sans aucun round, le joueur n'a pas d'index.

#### Scenario: Deux rounds
- **WHEN** les différentiels d'un joueur sont 17,0 et 12,0
- **THEN** son index vaut 14,5 et il est provisoire

#### Scenario: Troisième round
- **WHEN** un joueur enregistre son troisième round
- **THEN** son index n'est plus provisoire

#### Scenario: Aucun round
- **WHEN** un joueur vient d'être créé
- **THEN** il n'a pas d'index

### Requirement: Arrondi et bornes de l'index
L'index SHALL être arrondi au dixième le plus proche, 0,05 arrondi vers le haut, et plafonné à 54,0. Un index négatif (joueur « plus ») est permis.

#### Scenario: Arrondi
- **WHEN** les différentiels d'un joueur sont 14,0, 14,5, 20,0, 20,0, 20,0 et 20,0 (moyenne des 2 meilleurs 14,25, moins 1,0)
- **THEN** l'index vaut 13,3

#### Scenario: Plafond
- **WHEN** les différentiels d'un joueur sont 63,0, 70,0 et 65,0 (61,0 calculé)
- **THEN** l'index vaut 54,0

#### Scenario: Index négatif
- **WHEN** les différentiels d'un joueur sont −1,0, 5,0 et 6,0
- **THEN** l'index vaut −3,0

### Requirement: Historique de l'index
Pour chaque joueur de chaque round, le système SHALL conserver le brut, le score ajusté, le différentiel, l'index juste avant ce round (aucun pour son premier round) et l'index juste après. Il SHALL marquer les rounds dont le différentiel compte dans l'index actuel du joueur.

#### Scenario: Index avant et après
- **GIVEN** Gab avec des rounds le 2026-10-01 (différentiel 20,0), le 2026-10-10 (17,0) et le 2026-10-17 (12,0)
- **WHEN** on lit son historique
- **THEN** l'index avant vaut aucun, 20,0 puis 18,5, et l'index après 20,0, 18,5 puis 10,0

#### Scenario: Différentiels retenus
- **GIVEN** un joueur avec 6 rounds de différentiels 17,0, 12,0, 20,0, 15,0, 14,0 et 18,0, dans l'ordre des dates
- **WHEN** on lit son historique
- **THEN** seuls les rounds à 12,0 et 14,0 sont marqués comme comptant dans l'index actuel

#### Scenario: Deux rounds provisoires
- **GIVEN** un joueur avec 2 rounds
- **WHEN** on lit son historique
- **THEN** les deux rounds sont marqués comme comptant

### Requirement: Recalcul complet
Après tout ajout, correction ou suppression de round, et après toute saisie, confirmation, choix d'un tee, correction ou retrait de la rating d'un parcours, le système SHALL recalculer les différentiels et tout l'historique d'index des joueurs concernés, y compris pour les rounds postérieurs à la date modifiée, avant de répondre. Un joueur retiré d'un round corrigé MUST être recalculé lui aussi. Une écriture refusée MUST NOT changer aucun historique.

#### Scenario: Round ajouté dans le passé
- **GIVEN** Gab avec un round le 2026-10-10 (différentiel 17,0) et un le 2026-10-17 (12,0)
- **WHEN** l'utilisateur enregistre un round de Gab daté du 2026-10-01 avec un différentiel de 20,0
- **THEN** l'index de Gab après le round du 2026-10-10 vaut 18,5 et après celui du 2026-10-17 vaut 10,0

#### Scenario: Score corrigé
- **GIVEN** Manu avec 88 brut le 2026-10-10 et la carte `IMG_3064` le 2026-10-17, sans rating
- **WHEN** l'utilisateur corrige le trou 1 de Manu de 7 à 6 sur la carte `IMG_3064`
- **THEN** le différentiel de Manu sur ce round vaut 16,0 et son index après ce round 16,5

#### Scenario: Joueur remplacé
- **GIVEN** Manu avec 88 brut le 2026-10-10 et la carte `IMG_3064` le 2026-10-17
- **WHEN** l'utilisateur associe la ligne de Manu de la carte `IMG_3064` à Sean
- **THEN** Manu n'a plus que son round du 2026-10-10, avec un index après de 17,0, et Sean a un round avec un différentiel de 17,0

#### Scenario: Round supprimé
- **GIVEN** Gab avec des rounds le 2026-10-01 (20,0), le 2026-10-10 (17,0) et le 2026-10-17 (12,0)
- **WHEN** l'utilisateur supprime le round du 2026-10-01
- **THEN** l'index de Gab après le round du 2026-10-10 vaut 17,0 et après celui du 2026-10-17 vaut 14,5

#### Scenario: Rating saisie après coup
- **GIVEN** Manu avec la carte `IMG_3064` sur « BETHPAGE BLACK / Red » sans rating, différentiel 17,0
- **WHEN** l'utilisateur saisit 71,4 et 144 pour « BETHPAGE BLACK / Red »
- **THEN** le différentiel de Manu sur ce round vaut 13,0, et ses index après ce round et après les suivants sont recalculés

#### Scenario: Proposition confirmée
- **GIVEN** Manu avec 88 brut le 2026-10-10 sur un autre parcours de par 71 sans rating (différentiel 17,0) et la carte `IMG_3064` le 2026-10-17 sur « BETHPAGE BLACK / Red », avec la proposition 71,4 / 144
- **WHEN** l'utilisateur confirme la proposition
- **THEN** le différentiel de Manu sur la carte `IMG_3064` vaut 13,0 et son index après ce round 15,0

#### Scenario: Rating retirée
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144 et la carte `IMG_3064` enregistrée, différentiels de Gab 0,5 et de Manu 13,0
- **WHEN** l'utilisateur retire la rating de « BETHPAGE BLACK / Red »
- **THEN** le différentiel de Gab sur ce round vaut 1,0 et celui de Manu 17,0, et leurs index après ce round et après les suivants sont recalculés

#### Scenario: Correction refusée
- **GIVEN** la carte `IMG_3064` enregistrée
- **WHEN** l'utilisateur la corrige avec un par 6 et l'enregistrement est refusé
- **THEN** l'historique de Gab et de Manu reste inchangé

### Requirement: Recalcul d'une base existante
Le système SHALL offrir une commande d'administration qui recalcule l'historique de tous les joueurs à partir des rounds et des ratings enregistrés. Lancée deux fois de suite, elle MUST donner le même historique. Sur une base sans round, elle MUST s'arrêter avec le code 0 sans rien écrire. Le service `migrate` la lance à chaque déploiement (spec `deployment`).

#### Scenario: Base sans historique
- **GIVEN** une base qui contient la carte `IMG_3064` et aucun historique d'index
- **WHEN** l'administrateur lance la commande de recalcul
- **THEN** Gab a un différentiel de 1,0 et Manu de 17,0 sur ce round, et la commande s'arrête avec le code 0

#### Scenario: Deux lancements
- **WHEN** l'administrateur lance la commande de recalcul deux fois
- **THEN** l'historique est le même après chaque lancement

#### Scenario: Base vide
- **GIVEN** une base sans round
- **WHEN** l'administrateur lance la commande de recalcul
- **THEN** elle affiche « 0 joueur recalculé. », n'écrit aucune ligne d'historique et s'arrête avec le code 0

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
