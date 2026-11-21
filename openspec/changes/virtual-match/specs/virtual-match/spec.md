# Spec Delta

## Purpose

Dire qui aurait gagné si des joueurs qui ont joué le même parcours à des jours différents avaient joué une seule partie ensemble, à partir des rounds déjà enregistrés, sans rien écrire.

## ADDED Requirements

### Requirement: Page Partie virtuelle protégée et sans écriture
La page Partie virtuelle SHALL être servie à `/rounds/compare` et exiger la session du code d'accès : sans session, la demande redirige vers `/login`. L'ouvrir, changer le parcours, ajouter ou retirer un joueur-round et déplier la carte MUST NOT écrire quoi que ce soit en base. Une sous-adresse de `/rounds/compare` répond 404.

#### Scenario: Sans session
- **GIVEN** aucune session ouverte
- **WHEN** le navigateur demande `/rounds/compare`
- **THEN** le système redirige vers `/login`, et la réponse ne contient ni nom de joueur ni parcours

#### Scenario: Aucune écriture
- **GIVEN** une session ouverte et des rounds sur « BETHPAGE BLACK / Red »
- **WHEN** l'utilisateur choisit ce parcours, ajoute trois joueurs-rounds, en retire un et déplie la carte combinée
- **THEN** la base n'a changé dans aucune table

#### Scenario: Sous-adresse inconnue
- **WHEN** l'utilisateur ouvre `/rounds/compare/xyz`
- **THEN** le système répond 404

### Requirement: Choix du parcours de la partie virtuelle
La page SHALL proposer, sous « PARCOURS », les parcours connus par nom puis tees (spec `rounds`, « Parcours connus »), et s'ouvrir sans choix sur le premier, comme la page Coups. Sous la liste, la ligne de rating (« rating 71,4 · slope 144 » ou « sans rating (slope 113) ») mène à `/courses`. Changer de parcours MUST vider le choix des joueurs-rounds.

#### Scenario: Ouverture sans choix
- **GIVEN** les parcours « BETHPAGE BLACK / Red » et « PAYNES VALLEY / Blue »
- **WHEN** l'utilisateur ouvre `/rounds/compare` sans rien dans l'adresse
- **THEN** la liste montre « BETHPAGE BLACK / Red », et la page affiche « Choisissez au moins 2 joueurs. » à la place de l'encadré et du classement

#### Scenario: Changement de parcours
- **GIVEN** Gab et Manu choisis sur « BETHPAGE BLACK / Red »
- **WHEN** l'utilisateur choisit « PAYNES VALLEY / Blue »
- **THEN** aucun joueur-round n'est choisi, et la page affiche « Choisissez au moins 2 joueurs. »

#### Scenario: Aucun parcours connu
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre `/rounds/compare`
- **THEN** la page affiche « Aucun parcours : enregistrez d'abord un round. » à la place de la liste des parcours

### Requirement: Joueurs-rounds d'un parcours
Un joueur-round SHALL être la ligne d'un joueur sur un round. Les joueurs-rounds proposés sont ceux des rounds joués sur le parcours et les tees choisis, à n'importe quelle date, sans tenir compte de la casse ni des espaces autour. Chacun se présente par le nom du joueur, la date du round (« 18 nov. 2026 »), son brut et ses partenaires (« seul » ou « avec Sean »).

#### Scenario: Rounds de jours différents
- **GIVEN** Gab seul sur « BETHPAGE BLACK / Red » le 2026-11-18, et Manu et Sean ensemble sur « Bethpage Black / red » le 2026-11-19
- **WHEN** l'utilisateur ouvre la liste d'ajout sur « BETHPAGE BLACK / Red »
- **THEN** la liste propose « Gab · 18 nov. 2026 · seul », « Manu · 19 nov. 2026 · avec Sean » et « Sean · 19 nov. 2026 · avec Manu », chacun avec son brut

#### Scenario: Autres tees exclus
- **GIVEN** un round de Gab sur « BETHPAGE BLACK / Blue »
- **WHEN** l'utilisateur ouvre la liste d'ajout sur « BETHPAGE BLACK / Red »
- **THEN** ce round de Gab n'est pas proposé

#### Scenario: Écart de dates quelconque
- **GIVEN** un round de Gab sur « BETHPAGE BLACK / Red » le 2026-03-02 et un de Manu le 2026-11-19
- **WHEN** l'utilisateur les choisit
- **THEN** la page les classe ensemble

### Requirement: Un seul round par joueur
Une partie virtuelle SHALL compter au plus un joueur-round par joueur, sans limite du nombre de joueurs. Une fois un joueur choisi, ses autres joueurs-rounds sur ce parcours MUST être grisés dans la liste d'ajout avec « déjà choisi », sans pouvoir être ajoutés ; retirer le joueur les rend de nouveau ajoutables.

#### Scenario: Deuxième round du même joueur
- **GIVEN** Gab choisi pour son round du 2026-11-18, et un autre round de Gab sur ce parcours le 2026-11-02
- **WHEN** l'utilisateur ouvre la liste d'ajout
- **THEN** « Gab · 2 nov. 2026 » est grisé avec « déjà choisi » et ne s'ajoute pas

#### Scenario: Cinq joueurs
- **GIVEN** cinq joueurs ayant chacun un round sur « BETHPAGE BLACK / Red »
- **WHEN** l'utilisateur les ajoute tous les cinq
- **THEN** la page les classe tous les cinq

### Requirement: Choix gardé dans l'adresse
Le parcours, les tees et les joueurs-rounds choisis, dans l'ordre du choix, SHALL être portés par l'adresse de la page, sans nom ni score, pour que rouvrir l'adresse redonne la même partie. Un joueur-round de l'adresse qui n'existe plus, qui n'est pas sur ce parcours et ces tees, ou dont le joueur est déjà choisi MUST être ignoré sans erreur.

#### Scenario: Adresse rouverte
- **GIVEN** une partie virtuelle de Gab, Manu et Sean sur « BETHPAGE BLACK / Red »
- **WHEN** l'utilisateur copie l'adresse et l'ouvre dans un autre navigateur avec une session
- **THEN** la page montre le même parcours, les mêmes trois joueurs-rounds et le même classement

#### Scenario: Round supprimé depuis
- **GIVEN** l'adresse d'une partie virtuelle de Gab, Manu et Sean, puis le round de Gab supprimé
- **WHEN** l'utilisateur rouvre l'adresse
- **THEN** la page montre Manu et Sean seulement, sans message d'erreur

#### Scenario: Adresse sans nom
- **WHEN** la page montre une partie virtuelle de Gab et Manu
- **THEN** l'adresse ne contient ni « Gab », ni « Manu », ni aucun score

### Requirement: Au moins deux joueurs
Tant que moins de deux joueurs-rounds sont choisis, la page SHALL afficher « Choisissez au moins 2 joueurs. » dans un cadre en pointillés à la place de l'encadré et du classement, et MUST NOT afficher le bouton de la carte combinée.

#### Scenario: Un seul joueur
- **WHEN** l'utilisateur n'a choisi que Gab
- **THEN** la page affiche « Choisissez au moins 2 joueurs. », sans encadré, sans classement et sans carte

### Requirement: Net d'un joueur-round
Le net d'un joueur-round SHALL être celui de la page de son round : son brut moins son handicap de jeu sur ce round, tiré de son index juste avant ce round, de la rating actuelle du parcours et des tees et du par total de ce round (spec `rounds`, « Handicap de jeu d'un round »). Rien n'est recalculé depuis l'index actuel du joueur.

#### Scenario: Trois joueurs, deux jours
- **GIVEN** « BETHPAGE BLACK / Red » à 71,4 / 144, par 71 ; Gab à 1,0 avant son round seul du 2026-11-18, où il joue 74 ; Manu à 17,4 et Sean à 12,5 avant leur round du 2026-11-19, où ils jouent 88 et 84
- **WHEN** l'utilisateur choisit ces trois joueurs-rounds
- **THEN** Manu a « 88 − 23 » et 65 net, Sean « 84 − 16 » et 68 net, Gab « 74 − 2 » et 72 net

#### Scenario: Index changé depuis
- **GIVEN** la partie virtuelle ci-dessus, puis un nouveau round de Gab qui porte son index à 5,0
- **WHEN** l'utilisateur rouvre la partie virtuelle
- **THEN** Gab a toujours « 74 − 2 » et 72 net

#### Scenario: Même net que la page du round
- **GIVEN** le round de Manu et Sean du 2026-11-19
- **WHEN** l'utilisateur compare la page de ce round et une partie virtuelle qui contient Manu de ce round
- **THEN** le handicap de jeu et le net de Manu sont les mêmes sur les deux pages

### Requirement: Classement de la partie virtuelle
Sous « CLASSEMENT NET » et « BRUT − HCP », la page SHALL ranger les joueurs-rounds choisis par net croissant, en rang de compétition, des ex æquo dans l'ordre du choix, chaque premier avec la pastille ambre « 1ER », comme le classement net d'un round. Chaque ligne donne le rang, le nom, « {brut} − {handicap de jeu} », la date du round et le net.

#### Scenario: Classement à trois
- **GIVEN** les trois joueurs-rounds du scénario « Trois joueurs, deux jours »
- **WHEN** la page s'affiche
- **THEN** les lignes sont « 1 », Manu, « 1ER », « 88 − 23 », « 19 nov. 2026 », « 65 » ; « 2 », Sean, « 84 − 16 », « 68 » ; « 3 », Gab, « 74 − 2 », « 18 nov. 2026 », « 72 »

#### Scenario: Ex æquo de jours différents
- **GIVEN** Gab à 70 net le 2026-11-18 et Manu à 70 net le 2026-11-19, choisis dans cet ordre
- **WHEN** la page s'affiche
- **THEN** Gab puis Manu sont tous deux au rang « 1 » avec la pastille « 1ER »

### Requirement: Joueur sans index dans la partie virtuelle
Un joueur-round sans index juste avant son round MUST venir après les joueurs classés, sans rang ni pastille, avec « {brut} · pas encore d'index » et « — » comme net, comme sur la page d'un round.

#### Scenario: Premier round de Sean
- **GIVEN** Sean qui joue son tout premier round, 95, choisi avec Gab et Manu
- **WHEN** la page s'affiche
- **THEN** Sean vient en dernier, sans rang, avec « 95 · pas encore d'index » et « — » comme net

### Requirement: Encadré du gagnant
Avec au moins deux joueurs classés, la page SHALL afficher au-dessus des pastilles un encadré « AURAIT GAGNÉ » : le nom du premier en grand, puis « {net} net · {n} coups devant {nom} » (« 1 coup » au singulier), le nom étant celui du deuxième, ou les deuxièmes ex æquo joints par « et ». Des premiers ex æquo donnent « ÉGALITÉ », leurs noms joints par « et » et « {net} net ».

#### Scenario: Un gagnant
- **GIVEN** le classement Manu 65, Sean 68, Gab 72
- **WHEN** la page s'affiche
- **THEN** l'encadré dit « AURAIT GAGNÉ », « Manu » et « 65 net · 3 coups devant Sean »

#### Scenario: Un coup d'écart
- **GIVEN** Manu à 65 net et Sean à 66 net
- **WHEN** la page s'affiche
- **THEN** l'encadré dit « 65 net · 1 coup devant Sean »

#### Scenario: Égalité en tête
- **GIVEN** Gab et Manu à 70 net, Sean à 72
- **WHEN** la page s'affiche
- **THEN** l'encadré dit « ÉGALITÉ », « Gab et Manu » et « 70 net »

#### Scenario: Un seul joueur classé
- **GIVEN** Gab classé et Sean sans index, seuls choisis
- **WHEN** la page s'affiche
- **THEN** la page n'affiche pas d'encadré, et le classement montre Gab au rang « 1 » puis Sean sans rang

### Requirement: Pastilles des joueurs-rounds choisis
Sous l'encadré, chaque joueur-round choisi SHALL avoir une pastille, dans l'ordre du choix, avec la couleur du joueur, son nom et la date courte du round (« 18 nov. »). Toucher une pastille retire ce joueur-round ; elle est annoncée « Retirer Gab, 18 nov. 2026 ». Une dernière pastille « + Ajouter » ouvre la liste d'ajout.

#### Scenario: Retrait
- **GIVEN** Gab, Manu et Sean choisis
- **WHEN** l'utilisateur touche la pastille de Gab
- **THEN** il reste Manu et Sean, l'adresse ne porte plus le round de Gab, et le classement se refait sur ces deux joueurs

#### Scenario: Nom accessible
- **WHEN** la pastille de Gab s'affiche pour son round du 2026-11-18
- **THEN** elle montre « Gab » et « 18 nov. » et son nom accessible est « Retirer Gab, 18 nov. 2026 »

### Requirement: Liste d'ajout
« + Ajouter » SHALL ouvrir la liste des joueurs-rounds du parcours et des tees choisis, du round le plus récent au plus ancien par date de jeu puis par date d'enregistrement, chacun avec son nom, sa date, ses partenaires et son brut. Toucher un joueur-round l'ajoute à la fin du choix et ferme la liste. La liste se ferme aussi sans rien ajouter.

#### Scenario: Ajout
- **GIVEN** Manu et Sean choisis
- **WHEN** l'utilisateur touche « + Ajouter » puis « Gab · 18 nov. 2026 · seul »
- **THEN** la liste se ferme, la pastille de Gab vient après celles de Manu et Sean, et le classement compte trois joueurs

#### Scenario: Fermeture sans ajout
- **WHEN** l'utilisateur ouvre la liste d'ajout puis la ferme
- **THEN** le choix n'a pas changé

### Requirement: Carte combinée
Sous le classement, le bouton « Voir la carte combinée » SHALL déplier sur place, sans requête au serveur, une carte d'une rangée par trou : numéro, « P{par} · {index} », puis le score de chaque joueur dans l'ordre du choix, avec les marques de score de la page d'un round. Chaque colonne porte les cinq premières lettres du nom en capitales et la date courte du round. Les rangées « ALLER », « RETOUR » et « TOTAL » donnent les sommes.

#### Scenario: Carte à trois
- **GIVEN** les trois joueurs-rounds du scénario « Trois joueurs, deux jours »
- **WHEN** l'utilisateur touche « Voir la carte combinée »
- **THEN** la carte montre les colonnes « GAB », « MANU » et « SEAN » avec « 18 nov. », « 19 nov. » et « 19 nov. », et « TOTAL · PAR 71 » donne 74, 88 et 84

#### Scenario: Repli
- **GIVEN** la carte combinée dépliée
- **WHEN** l'utilisateur touche « Masquer la carte combinée »
- **THEN** la carte se replie, et le choix et l'adresse n'ont pas changé

#### Scenario: Beaucoup de joueurs à 320 px
- **GIVEN** six joueurs-rounds choisis
- **WHEN** la carte combinée est dépliée sur un écran de 320 px
- **THEN** la carte défile dans son cadre, et la page ne défile pas horizontalement

### Requirement: Pars et index de la carte combinée
Les pars et index de trou de la carte combinée SHALL être ceux du joueur-round dont le round est le plus récent, par date de jeu puis par date d'enregistrement. Si un autre round choisi a un par ou un index de trou différent sur au moins un trou, la page MUST afficher sous la carte « Les pars ou les index de trou diffèrent entre ces rounds. ». Le net n'en dépend pas.

#### Scenario: Par différent
- **GIVEN** Gab le 2026-11-18 avec un par 4 au trou 1, et Manu le 2026-11-19 avec un par 5 au trou 1, sur le même parcours et les mêmes tees
- **WHEN** l'utilisateur les choisit et déplie la carte
- **THEN** le trou 1 montre « P5 », et la note « Les pars ou les index de trou diffèrent entre ces rounds. » s'affiche sous la carte

#### Scenario: Cartes identiques
- **GIVEN** des rounds aux mêmes pars et index sur tous les trous
- **WHEN** la carte est dépliée
- **THEN** aucune note ne s'affiche

### Requirement: Suggestion sur la page d'un round
La page d'un round SHALL proposer, sous le classement net, les joueurs absents de sa carte qui ont un round sur le même parcours et les mêmes tees à 7 jours ou moins avant ou après sa date. Pour chacun, le round retenu est le plus proche en date, le plus récent en cas d'égalité. Un seul encadré nomme ces joueurs avec la date de leur round et mène à la partie virtuelle.

#### Scenario: Ami seul la veille
- **GIVEN** Gab seul sur « BETHPAGE BLACK / Red » le 2026-11-18, et Manu et Sean sur ce parcours le 2026-11-19
- **WHEN** l'utilisateur ouvre le round de Manu et Sean
- **THEN** l'encadré dit « Gab (18 nov.) a joué ce parcours. » et son lien « Voir la partie virtuelle » ouvre `/rounds/compare` avec Manu et Sean de ce round, puis Gab de son round, déjà choisis

#### Scenario: Plusieurs joueurs
- **GIVEN** le round de Manu du 2026-11-19, un round de Gab le 2026-11-18 et un de Sean le 2026-11-20 sur le même parcours et les mêmes tees
- **WHEN** l'utilisateur ouvre le round de Manu
- **THEN** un seul encadré dit « Gab (18 nov.) et Sean (20 nov.) ont joué ce parcours. », et son lien les choisit tous les deux avec Manu

#### Scenario: Round le plus proche
- **GIVEN** le round de Manu du 2026-11-19, et deux rounds de Gab sur ce parcours, le 2026-11-14 et le 2026-11-21
- **WHEN** l'utilisateur ouvre le round de Manu
- **THEN** l'encadré nomme « Gab (21 nov.) »

#### Scenario: Hors de la fenêtre
- **GIVEN** le round de Manu du 2026-11-19 et un round de Gab sur ce parcours le 2026-11-11
- **WHEN** l'utilisateur ouvre le round de Manu
- **THEN** aucun encadré de suggestion ne s'affiche

#### Scenario: Joueur déjà sur la carte
- **GIVEN** le round de Manu et Sean du 2026-11-19, et un round de Sean seul sur ce parcours le 2026-11-18
- **WHEN** l'utilisateur ouvre le round de Manu et Sean
- **THEN** aucun encadré de suggestion ne s'affiche

#### Scenario: Autres tees
- **GIVEN** le round de Manu du 2026-11-19 sur « BETHPAGE BLACK / Red » et un round de Gab sur « BETHPAGE BLACK / Blue » le 2026-11-18
- **WHEN** l'utilisateur ouvre le round de Manu
- **THEN** aucun encadré de suggestion ne s'affiche

### Requirement: Suggestion sans écriture ni rejet
La suggestion SHALL se calculer à chaque ouverture de la page du round et rester affichée tant que sa règle s'applique. Elle MUST NOT offrir de bouton pour l'ignorer et MUST NOT écrire quoi que ce soit.

#### Scenario: Suggestion à chaque visite
- **GIVEN** le round de Manu et Sean du 2026-11-19 avec la suggestion de Gab
- **WHEN** l'utilisateur ouvre la partie virtuelle, revient, puis rouvre le round
- **THEN** l'encadré de suggestion est toujours là, sans bouton pour l'ignorer, et la base n'a pas changé

### Requirement: Lien de comparaison sans suggestion
Sans suggestion, la page d'un round SHALL afficher sous le classement net le lien « Comparer avec d'autres rounds », qui ouvre `/rounds/compare` sur le parcours et les tees du round, avec ses joueurs déjà choisis dans l'ordre de la carte.

#### Scenario: Round sans voisin
- **GIVEN** un round de Gab et Manu sans autre round sur ce parcours dans les 7 jours
- **WHEN** l'utilisateur touche « Comparer avec d'autres rounds »
- **THEN** la partie virtuelle s'ouvre sur ce parcours et ces tees, avec Gab et Manu de ce round choisis

### Requirement: Mise en page de la page Partie virtuelle
À 390 px, la page SHALL suivre la variante B du prototype validé, avec les tokens existants : rangée du haut avec le lien de retour « Rounds » vers `/rounds`, titre « Partie virtuelle », « PARCOURS », sa liste et la ligne de rating, l'encadré du gagnant, les pastilles, « CLASSEMENT NET » et ses lignes en mise en page de la page d'un round, puis le bouton de la carte combinée.

#### Scenario: Lien de retour
- **WHEN** l'utilisateur ouvre `/rounds/compare` à 390 px
- **THEN** la rangée du haut montre le lien de retour « Rounds », et le toucher ouvre `/rounds`

#### Scenario: Encadré du gagnant
- **WHEN** Manu aurait gagné
- **THEN** l'encadré a le fond `oklch(0.17 0.006 260)` et des coins de 16 px, « AURAIT GAGNÉ » en Geist Mono 500, 12 px, gris, la pastille de 10 px à la couleur de Manu et « Manu » en Geist 600, 48 px, puis l'écart en Geist Mono 500, 15 px, gris

#### Scenario: Pastilles
- **WHEN** Gab, Manu et Sean sont choisis
- **THEN** chaque pastille fait 44 px de haut, coins de 999 px, fond `oklch(0.2 0.006 260)`, nom en Geist 500, 14 px, date en Geist Mono 500, 12 px, `oklch(0.68 0.01 260)` ; « + Ajouter » a un contour en pointillés `oklch(0.27 0.008 260)` sans fond

#### Scenario: Page à 320 px
- **WHEN** l'utilisateur ouvre une partie virtuelle de quatre joueurs à 320 px
- **THEN** les pastilles passent à la ligne, chaque ligne du classement est entièrement visible, et la page ne défile pas horizontalement
