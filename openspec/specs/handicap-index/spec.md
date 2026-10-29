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
Sous les onglets de joueur, la page Handicaps SHALL afficher pour le joueur choisi « INDEX ACTUEL », suivi de la pastille « PROVISOIRE » pour un index provisoire, puis son index actuel en Geist Mono 500, 88 px. Un joueur sans round affiche « — » à la place de l'index et « Pas encore d'index. Un premier round donne un index provisoire. », sans tendance ni pastille.

#### Scenario: Joueur sans round
- **GIVEN** Sean sans round
- **WHEN** l'utilisateur choisit Sean
- **THEN** la section montre « — » en Geist Mono 500, 88 px, et « Pas encore d'index. Un premier round donne un index provisoire. », sans tendance

### Requirement: Tendance de l'index d'un joueur
À droite de l'index actuel, la page Handicaps SHALL afficher la tendance : l'écart entre l'index d'après son dernier round et celui d'après l'avant-dernier, « ▼ 0,4 » pour une baisse, « ▲ 0,4 » pour une hausse, « = stable » sans écart, en Geist Mono 500, 13 px, dans la couleur du joueur, absente avant le deuxième round.

#### Scenario: Résumé de Manu
- **GIVEN** Manu avait un index de 15,5 avant son dernier round et de 15,8 après
- **WHEN** l'utilisateur choisit Manu
- **THEN** la section montre « 15,8 » et « ▲ 0,3 »

#### Scenario: Tendance en baisse
- **GIVEN** l'index de Gab est passé de 3,0 à 2,6 avec son dernier round
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** « ▼ 0,4 » s'affiche à droite de « 2,6 »

### Requirement: Résumé de la règle de l'index
Sous l'index actuel, un résumé SHALL dire « {n} rounds · {règle}. » (« 1 round » au singulier), la règle étant « moyenne des {k} différentiels » ou « moyenne de 1 différentiel » pour un index provisoire, sinon « {k} meilleurs différentiels sur {m} » (« meilleur différentiel sur {m} » pour un seul), suivie de « − {ajustement} » quand un ajustement s'applique, {m} étant le nombre de rounds retenus, au plus 20. Le résumé MUST NOT donner de handicap de jeu.

#### Scenario: Index provisoire
- **GIVEN** Sean avec 2 rounds de différentiels 17,0 et 12,0
- **WHEN** l'utilisateur choisit Sean
- **THEN** « INDEX ACTUEL » est suivi de la pastille « PROVISOIRE », « 14,5 » s'affiche avec « ▼ 2,5 », et le résumé dit « 2 rounds · moyenne des 2 différentiels. »

#### Scenario: Premier round
- **GIVEN** Sean n'a qu'un round, de différentiel 17,0
- **WHEN** l'utilisateur choisit Sean
- **THEN** « 17,0 » s'affiche sans tendance, et le résumé dit « 1 round · moyenne de 1 différentiel. »

#### Scenario: Ajustement
- **GIVEN** Gab avec 3 rounds de différentiels 17,0, 12,0 et 20,0
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** « 10,0 » s'affiche et le résumé dit « 3 rounds · meilleur différentiel sur 3 − 2,0. »

#### Scenario: Index établi
- **GIVEN** Manu avec 20 rounds
- **WHEN** l'utilisateur choisit Manu
- **THEN** le résumé dit « 20 rounds · 8 meilleurs différentiels sur 20. » et ne contient pas « hcp de jeu »


### Requirement: Liste des rounds d'un joueur
Sous « DATE · PARCOURS », « DIFF. » et « APRÈS », la page Handicaps SHALL lister tous les rounds du joueur choisi, du plus récent au plus ancien par date de jeu, puis par date d'enregistrement. Chaque ligne MUST mener à la page de son round.

#### Scenario: Lignes de Gab
- **GIVEN** Gab avec des rounds le 2026-10-01 (différentiel 20,0), le 2026-10-10 (17,0) et le 2026-10-17 (12,0), sans rating
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** les lignes sont, dans l'ordre, « 17 oct. 2026 », « 12,0 », « 10,0 » avec la marque « Compte » ; « 10 oct. 2026 », « 17,0 », « 18,5 » sans marque ; « 1 oct. 2026 », « 20,0 », « 20,0 » sans marque

#### Scenario: Vers la page du round
- **WHEN** l'utilisateur touche la ligne du round du 2026-10-17 de Gab
- **THEN** la page de ce round s'ouvre

#### Scenario: Plus de 20 rounds
- **GIVEN** Manu avec 21 rounds
- **WHEN** l'utilisateur ouvre `/handicaps` sur Manu
- **THEN** les 21 lignes s'affichent, et le round le plus ancien ne porte pas la marque « Compte »

### Requirement: Ligne d'un round du joueur
Chaque ligne de la liste des rounds de la page Handicaps SHALL montrer la date (« 17 oct. 2026 »), dessous le parcours et ses tees joints par « · », coupés par « … » s'ils sont trop longs, puis le différentiel et l'index juste après ce round en Geist Mono. Les rounds dont le différentiel compte dans l'index actuel (spec `handicap-index`, « Historique de l'index ») portent la marque « Compte » (spec ci-dessous).

#### Scenario: Parcours sur la ligne
- **GIVEN** la carte `IMG_3064` sur « BETHPAGE BLACK / Red », premier round de Gab, différentiel 1,0, index après 1,0
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** la ligne montre « 17 oct. 2026 » et la marque « Compte », « BETHPAGE BLACK · Red » en Geist 400, 12 px, gris, puis « 1,0 » et « 1,0 » (l'index après en gris), sur 52 px de haut au moins


### Requirement: Marque « Compte » sur la ligne du round
Dans la liste des rounds d'un joueur, la marque « Compte » SHALL être un point de 6 px à la couleur du joueur, juste après la date, au nom accessible « Compte » ; elle MUST rester sur la même ligne que la date dès 320 px de large, et la liste MUST NOT faire défiler la page horizontalement.

#### Scenario: Ligne d'un différentiel retenu à 320 px
- **WHEN** la liste des rounds de Gab s'affiche sur un écran de 320 px
- **THEN** la date « 17 oct. 2026 » et son point « Compte » sont à la même hauteur, sur une seule ligne, et la page ne défile pas horizontalement

#### Scenario: Couleur du point
- **GIVEN** Gab, premier joueur créé, et un round qui compte dans son index
- **WHEN** la ligne de ce round s'affiche
- **THEN** son point est `oklch(0.72 0.24 145)` et les lecteurs d'écran le lisent « Compte »

### Requirement: Mise en page de la page Handicaps
À 390 px, la page Handicaps SHALL suivre le design : titre « Handicaps », un onglet en pilule par joueur (le sélecteur de « Un joueur à la fois sur la page Handicaps »), « INDEX ACTUEL », l'index du joueur choisi en grand, sa tendance et son résumé, le graphique des différentiels, le bloc STATS, l'historique, puis l'explication repliée du calcul.

#### Scenario: Onglets de joueur
- **WHEN** l'utilisateur ouvre `/handicaps` avec Gab, Manu et Sean, Gab choisi
- **THEN** chaque onglet fait 44 px de haut, rayon 999 px, Geist 600, 14 px, avec la pastille de 8 px du joueur ; celui de Gab a le fond et la bordure `oklch(0.97 0.003 260)` et le texte `oklch(0.13 0.005 260)`, les autres un fond transparent, la bordure `oklch(0.27 0.008 260)` et le texte gris

### Requirement: Graphique des différentiels
La page Handicaps SHALL montrer, sous « DIFFÉRENTIELS » et « COMPTE », une barre par round pour les 20 derniers rounds du joueur, du plus ancien au plus récent, dans 150 px de haut : différentiel au-dessus, date « J/M » dessous. Une barre qui compte dans l'index prend la couleur du joueur, les autres `oklch(0.3 0.008 260)`. Un différentiel nul ou négatif donne une barre de 4 px.

#### Scenario: Hauteur des barres
- **GIVEN** les différentiels de Gab sont 2,0, 5,5 et 11,0
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** les barres font 20, 55 et 110 px de haut, au plus 36 px de large, coins du haut de 4 px, et portent au-dessus « 2 », « 5,5 » et « 11 »

#### Scenario: Barre qui compte
- **GIVEN** le round du 17 octobre 2026 compte dans l'index de Gab
- **WHEN** le graphique de Gab s'affiche
- **THEN** sa barre a la couleur `oklch(0.72 0.24 145)` et la date « 17/10 » dessous

### Requirement: Étiquettes du graphique des différentiels
Au-delà de 10 barres, le graphique des différentiels SHALL écrire le différentiel et la date d'une barre sur deux jusqu'à 16 barres, d'une sur trois au-delà, en partant de la plus récente, toujours étiquetée ; ces étiquettes MUST NOT se chevaucher ni sortir du graphique, et celles des autres barres MUST rester lues par les lecteurs d'écran.

#### Scenario: Vingt barres sur téléphone
- **GIVEN** Gab a joué 20 rounds, du 1er au 20 octobre 2026
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab à 320 puis 390 px
- **THEN** les dates « 2/10 », « 5/10 » … « 20/10 » s'écrivent sous une barre sur trois avec leur différentiel au-dessus, aucune ne chevauche sa voisine, et « 20/10 » et son différentiel tiennent dans le graphique

### Requirement: Statistiques de jeu d'un joueur
Sous le graphique des différentiels, la page Handicaps SHALL montrer « STATS » et le nombre de trous joués, puis trois valeurs : « MOY. BRUT », moyenne des bruts arrondie au dixième ; « MEILLEUR BRUT », le plus petit brut ; « PAR OU MIEUX », la part des trous joués en par ou mieux, en pour cent entier. Elles portent sur tous les rounds enregistrés du joueur, tous parcours, sans limite de nombre, rating ignorée ; un round compte 18 trous.

#### Scenario: Un round
- **GIVEN** Gab n'a joué que la carte de référence `IMG_3064` (72 brut)
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** le bloc montre « STATS » et « 18 TROUS », puis « 72,0 » sous « MOY. BRUT », « 72 » sous « MEILLEUR BRUT » et « 78% » sous « PAR OU MIEUX »

#### Scenario: Deux rounds
- **GIVEN** Gab a joué la carte de référence (72 brut) et un second round sur les mêmes trous avec les scores de Manu de cette carte (88 brut)
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** le bloc montre « 36 TROUS », « 80,0 », « 72 » et « 56% »

#### Scenario: Plus de 20 rounds
- **GIVEN** Manu a 21 rounds
- **WHEN** l'utilisateur ouvre `/handicaps` sur Manu
- **THEN** le bloc montre « 378 TROUS » : les 21 rounds comptent, pas seulement les 20 de l'index

#### Scenario: Aucun round
- **WHEN** l'utilisateur choisit Sean, qui n'a aucun round
- **THEN** le bloc « STATS » n'apparaît pas

### Requirement: Règles de calcul des statistiques
L'écart d'un trou SHALL valoir le score moins le par de ce trou enregistré avec le round. Les arrondis MUST se faire sur la valeur exacte, au plus proche, la moitié vers le haut : un dixième pour les moyennes, un entier pour les pourcentages. L'écart d'une moyenne par par est celui de la moyenne affichée, arrondie, au par, pour que la case reste cohérente. Chaque pourcentage est rapporté à tous les trous joués du joueur ; les pourcentages ne sont pas ajustés pour faire 100.

#### Scenario: Moitié vers le haut
- **GIVEN** Manu n'a joué que la carte de référence, où ses quatre par 3 valent 3, 3, 4 et 3
- **WHEN** l'utilisateur ouvre `/handicaps` sur Manu
- **THEN** sa moyenne sur les par 3 s'affiche « 3,3 » (3,25 arrondi vers le haut)

#### Scenario: Pourcentage arrondi
- **GIVEN** Gab n'a joué que la carte de référence, avec 14 trous en par ou mieux sur 18
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** « PAR OU MIEUX » vaut « 78% » (77,8 arrondi)

### Requirement: Répartition des trous
Sous les trois valeurs, la page Handicaps SHALL répartir les trous joués en cinq classes d'écart : « Eagle ou mieux » (−2 ou moins, un 1 sur un par 3 compris), « Birdies » (−1), « Pars » (0), « Bogeys » (+1), « Double bogey + » (+2 ou plus, tous regroupés). Chaque classe donne son nombre de trous et son pourcentage, avec sa marque de score ; « Eagle ou mieux » ne s'affiche que s'il compte au moins un trou. Une barre de 12 px en donne les proportions.

#### Scenario: Répartition d'un round
- **GIVEN** Gab n'a joué que la carte de référence
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** la répartition montre « Birdies » 3 et « 17% », « Pars » 11 et « 61% », « Bogeys » 4 et « 22% », « Double bogey + » 0 et « 0% », sans ligne « Eagle ou mieux »

#### Scenario: Répartition de deux rounds
- **GIVEN** Gab a joué la carte de référence et un second round sur les mêmes trous avec les scores de Manu de cette carte
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** la répartition montre « Birdies » 4 et « 11% », « Pars » 16 et « 44% », « Bogeys » 11 et « 31% », « Double bogey + » 5 et « 14% »

#### Scenario: Trou en un sur un par 3
- **GIVEN** Gab n'a joué que la carte de référence, corrigée avec 1 au trou 3 (par 3)
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** la répartition montre « Eagle ou mieux » 1 et « 6% », « Birdies » 3 et « 17% », « Pars » 10 et « 56% », « Bogeys » 4 et « 22% », et « MOY. BRUT » vaut « 70,0 »

#### Scenario: Barre des proportions
- **WHEN** la répartition de Gab sur la carte de référence s'affiche
- **THEN** la barre, fond `oklch(0.2 0.006 260)`, coins de 6 px, montre des segments séparés de 2 px de largeurs 16,7 %, 61,1 %, 22,2 % et 0 %, aux couleurs `oklch(0.7 0.17 25 / 0.55)` (birdies), `oklch(0.97 0.003 260 / 0.85)` (pars), `oklch(0.6 0.01 260 / 0.7)` (bogeys) et `oklch(0.4 0.01 260)` (double bogey +) ; un segment d'eagle prend `oklch(0.7 0.17 25)`

### Requirement: Moyennes par par
Sous la répartition, la page Handicaps SHALL montrer une case par par (3, 4, 5) que le joueur a joué : « PAR 3 », la moyenne de ses scores sur ces trous, et l'écart de cette moyenne affichée au par, précédé de « + » s'il est positif ou nul et de « − » sinon. La teinte de l'écart, décidée sur la moyenne exacte, avant arrondi, est en `oklch(0.8 0.14 145)` jusqu'à 0,3, en gris jusqu'à 1,0, en `oklch(0.7 0.17 25)` au-delà. Un par jamais joué n'a pas de case.

#### Scenario: Un round
- **GIVEN** Gab n'a joué que la carte de référence
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** les cases montrent « PAR 3 », « 3,0 », « +0,0 » en vert ; « PAR 4 », « 4,3 », « +0,3 » en vert (écart 0,27) ; « PAR 5 », « 4,3 », « −0,7 » en vert

#### Scenario: Écart gris
- **GIVEN** Gab a joué la carte de référence et un second round sur les mêmes trous avec les scores de Manu de cette carte
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** les cases montrent « 3,1 » et « +0,1 » en vert, « 4,7 » et « +0,7 » en gris, « 5,3 » et « +0,3 » en gris (écart 0,33, au-dessus de 0,3 avant arrondi)

#### Scenario: Écart rouge
- **GIVEN** Manu n'a joué que la carte de référence
- **WHEN** l'utilisateur ouvre `/handicaps` sur Manu
- **THEN** les cases montrent « 3,3 » et « +0,3 » en vert, « 5,1 » et « +1,1 » en rouge, « 6,3 » et « +1,3 » en rouge

#### Scenario: Demi-valeur sous le par
- **GIVEN** Gab n'a joué que la carte de référence, avec 2 au trou 3 : ses quatre par 3 font 11 coups
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** la case du par 3 montre « 2,8 » (2,75 arrondi vers le haut) et « −0,2 » en vert, l'écart de « 2,8 » à 3, et non « −0,3 »

### Requirement: Mise en page du bloc STATS
À 390 px, le bloc STATS SHALL suivre le design : « STATS » 34 px sous le graphique ; les trois valeurs en colonnes égales, 16 px de marge verticale, valeur en Geist Mono 500, 30 px, libellé en Geist Mono 500, 10 px, gris ; la barre 18 px dessous ; une ligne de 40 px par classe ; les cases par par 22 px dessous, séparées de 1 px, coins du groupe de 12 px, fond `oklch(0.17 0.006 260)`.

#### Scenario: Ligne de classe
- **WHEN** la ligne « Pars » de Gab s'affiche
- **THEN** elle montre la marque de 18 px, « Pars » en Geist 500, 14 px, « 61% » en Geist Mono 500, 12 px, gris, et « 11 » en Geist Mono 500, 17 px, aligné à droite dans 40 px

#### Scenario: Case par par
- **WHEN** la case « PAR 4 » de Gab s'affiche
- **THEN** elle a 14 px et 12 px de marge, « PAR 4 » en Geist Mono 500, 10 px, gris, « 4,3 » en Geist Mono 500, 22 px, et « +0,3 » en Geist Mono 500, 11 px
