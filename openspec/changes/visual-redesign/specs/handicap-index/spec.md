# Spec Delta

## Purpose

Calculer l'index de handicap de chaque joueur selon le WHS et le montrer sur la page Handicaps, joueur par joueur, avec l'historique de ses différentiels.

## MODIFIED Requirements

### Requirement: Résumé de l'index d'un joueur
Sous les onglets de joueur, la page Handicaps SHALL afficher pour le joueur choisi « INDEX ACTUEL », suivi de la pastille « PROVISOIRE » pour un index provisoire, puis son index actuel en Geist Mono 500, 88 px, et à sa droite la tendance : l'écart entre l'index d'après son dernier round et celui d'après l'avant-dernier, « ▼ 0,4 » pour une baisse, « ▲ 0,4 » pour une hausse, « = stable » sans écart, en Geist Mono 500, 13 px, dans la couleur du joueur, absente avant le deuxième round. Dessous, un résumé dit « {n} rounds · {règle}. » (« 1 round » au singulier), la règle étant « moyenne des {k} différentiels » ou « moyenne de 1 différentiel » pour un index provisoire, sinon « {k} meilleurs différentiels sur {m} » (« meilleur différentiel sur {m} » pour un seul), suivie de « − {ajustement} » quand un ajustement s'applique, {m} étant le nombre de rounds retenus, au plus 20. Le résumé MUST NOT donner de handicap de jeu. Un joueur sans round affiche « — » à la place de l'index et « Pas encore d'index. Un premier round donne un index provisoire. », sans tendance ni pastille.

#### Scenario: Résumé de Manu
- **GIVEN** Manu avait un index de 15,5 avant son dernier round et de 15,8 après
- **WHEN** l'utilisateur choisit Manu
- **THEN** la section montre « 15,8 » et « ▲ 0,3 »

#### Scenario: Tendance en baisse
- **GIVEN** l'index de Gab est passé de 3,0 à 2,6 avec son dernier round
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** « ▼ 0,4 » s'affiche à droite de « 2,6 »

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

#### Scenario: Joueur sans round
- **GIVEN** Sean sans round
- **WHEN** l'utilisateur choisit Sean
- **THEN** la section montre « — » en Geist Mono 500, 88 px, et « Pas encore d'index. Un premier round donne un index provisoire. », sans tendance

### Requirement: Liste des rounds d'un joueur
Sous « DATE · PARCOURS », « DIFF. » et « APRÈS », la page Handicaps SHALL lister tous les rounds du joueur choisi, du plus récent au plus ancien par date de jeu, puis par date d'enregistrement : la date (« 17 oct. 2026 »), dessous le parcours et ses tees joints par « · », coupés par « … » s'ils sont trop longs, puis le différentiel et l'index juste après ce round en Geist Mono. Les rounds dont le différentiel compte dans l'index actuel (spec `handicap-index`, « Historique de l'index ») portent la marque « Compte » (spec ci-dessous). Chaque ligne MUST mener à la page de son round.

#### Scenario: Lignes de Gab
- **GIVEN** Gab avec des rounds le 2026-10-01 (différentiel 20,0), le 2026-10-10 (17,0) et le 2026-10-17 (12,0), sans rating
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** les lignes sont, dans l'ordre, « 17 oct. 2026 », « 12,0 », « 10,0 » avec la marque « Compte » ; « 10 oct. 2026 », « 17,0 », « 18,5 » sans marque ; « 1 oct. 2026 », « 20,0 », « 20,0 » sans marque

#### Scenario: Parcours sur la ligne
- **GIVEN** la carte `IMG_3064` sur « BETHPAGE BLACK / Red », premier round de Gab, différentiel 1,0, index après 1,0
- **WHEN** l'utilisateur ouvre `/handicaps` sur Gab
- **THEN** la ligne montre « 17 oct. 2026 » et la marque « Compte », « BETHPAGE BLACK · Red » en Geist 400, 12 px, gris, puis « 1,0 » et « 1,0 » (l'index après en gris), sur 52 px de haut au moins

#### Scenario: Vers la page du round
- **WHEN** l'utilisateur touche la ligne du round du 2026-10-17 de Gab
- **THEN** la page de ce round s'ouvre

#### Scenario: Plus de 20 rounds
- **GIVEN** Manu avec 21 rounds
- **WHEN** l'utilisateur ouvre `/handicaps` sur Manu
- **THEN** les 21 lignes s'affichent, et le round le plus ancien ne porte pas la marque « Compte »

### Requirement: Marque « Compte » sur la ligne du round
Dans la liste des rounds d'un joueur, la marque « Compte » SHALL être un point de 6 px à la couleur du joueur, juste après la date, au nom accessible « Compte » ; elle MUST rester sur la même ligne que la date dès 320 px de large, et la liste MUST NOT faire défiler la page horizontalement.

#### Scenario: Ligne d'un différentiel retenu à 320 px
- **WHEN** la liste des rounds de Gab s'affiche sur un écran de 320 px
- **THEN** la date « 17 oct. 2026 » et son point « Compte » sont à la même hauteur, sur une seule ligne, et la page ne défile pas horizontalement

#### Scenario: Couleur du point
- **GIVEN** Gab, premier joueur créé, et un round qui compte dans son index
- **WHEN** la ligne de ce round s'affiche
- **THEN** son point est `oklch(0.72 0.24 145)` et les lecteurs d'écran le lisent « Compte »

## ADDED Requirements

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
