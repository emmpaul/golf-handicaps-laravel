# Proposition : partie virtuelle

## Why

Le groupe ne joue pas toujours ensemble : un ami fait BETHPAGE BLACK seul un soir, deux autres le jouent le lendemain. Chaque round a son classement net, mais rien ne dit qui aurait gagné si les trois avaient joué la même partie. Les rounds sont déjà là, avec leur brut et l'index d'avant chaque round : il ne manque qu'une page qui les réunit.

Aucune entrée de `ROADMAP.md` ne couvre ce besoin. Le plus proche est 21 (`monthly-tournament`), qui suppose « le même parcours le même jour ». Ce changement ajoute l'entrée 25 `virtual-match`. Ses dépendances, le classement net d'un round (spec `rounds`) et les ratings (spec `course-ratings`), sont archivées.

## What Changes

- Nouvelle page « Partie virtuelle » à `/rounds/compare`, sous-page de Rounds hors de la barre, derrière la session du code d'accès. Elle ne fait qu'une lecture : rien n'est écrit en base.
- On y choisit un parcours connu et ses tees, puis des joueurs-rounds joués sur ce parcours et ces tees, à n'importe quelle date : un round par joueur, de 2 joueurs à tout le groupe.
- Le résultat suit le prototype B retenu par le mainteneur : un encadré « Aurait gagné », les joueurs-rounds choisis en pastilles avec « + Ajouter », le classement net, puis la carte combinée dépliée sur place.
- Le net de chaque joueur est celui de la page de son round : son brut moins le handicap de jeu tiré de son index juste avant ce round. Le rang reprend les règles du classement net d'un round.
- Le choix est gardé dans l'adresse : la page se rouvre ou s'envoie au groupe, toujours derrière le code d'accès.
- Sur la page d'un round, une suggestion nomme les joueurs qui ont joué le même parcours et les mêmes tees à 7 jours ou moins d'écart, avec un lien vers la partie virtuelle déjà remplie. Sans suggestion, un lien « Comparer avec d'autres rounds » ouvre la page avec ce round.
- La page Rounds gagne le lien « Comparer ».
- La démonstration (`make demo`) gagne un cas de partie virtuelle : un joueur seul la veille, deux joueurs le lendemain, sur le même parcours.
- `ROADMAP.md` gagne l'entrée 25 et une note sous l'entrée 21 : un tournoi pourrait reprendre des rounds de jours différents, en partant de la partie virtuelle.

Non-objectifs :

- enregistrer une partie virtuelle, lui donner un gagnant officiel ou la faire compter dans un bilan : c'est le terrain de `monthly-tournament` ;
- un bouton « Ignorer » sur la suggestion, qui demanderait une écriture ;
- les suggestions ailleurs que sur la page d'un round (accueil, liste des Rounds) ;
- d'autres formats que le net en partie par coups (match play, skins, Stableford), ou un net trou par trou ;
- comparer des tees différents ou des parcours différents ;
- toute modification du calcul de l'index, du handicap de jeu ou du classement net d'un round.

## Capabilities

### New Capabilities

- `virtual-match` : la partie virtuelle, c'est-à-dire le choix des joueurs-rounds d'un parcours, le classement net qui les réunit, la carte combinée et la suggestion faite depuis la page d'un round.

### Modified Capabilities

- `rounds` : la page Rounds donne le lien « Comparer » (« Liens et état vide de la page Rounds », « Mise en page de la page Rounds »). Ces deux exigences nomment encore le lien « Parcours », que `courses-tab-replaces-strokes` a retiré du code sans delta `rounds` ; le delta les remet d'accord avec le code ; la page d'un round place la suggestion ou le lien de comparaison entre le classement net et la carte (« Mise en page de la page d'un round »).
- `development-seed-data` : le jeu de démonstration contient une partie virtuelle prête à voir.

## Impact

- Domaines : `app/Domain/Rounds` (lecture des joueurs-rounds d'un parcours et des suggestions) et `app/Domain/Handicaps` (net de joueurs-rounds venant de rounds différents). Aucune Action, aucune migration, aucun modèle nouveau.
- HTTP : une route `GET /rounds/compare`, protégée par le groupe `web` comme les autres ; `RoundController::show` reçoit la suggestion.
- Front : une page Inertia `rounds/compare`, des composants sous `components/rounds`, les primitives et les tokens existants. Pas de nouvelle dépendance.
- Hors ligne : la page est une lecture `GET`. Chaque adresse visitée en ligne est donc gardée sur l'appareil, comme les autres pages (spec `offline-mode`).
- Confidentialité : rien ne sort de l'accès par code. L'adresse ne porte que des identifiants UUID, sans nom ni score.
- Retour arrière : retirer la route, la page, le lien et la suggestion suffit. Aucune donnée n'est écrite, donc il n'y a rien à migrer ni à effacer.
