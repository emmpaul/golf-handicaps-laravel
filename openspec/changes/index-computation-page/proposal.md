# Proposal

## Why

« Comment l’index est calculé » est aujourd'hui un bloc replié de sept lignes de texte, le même sur les pages Joueurs, Handicaps, Parcours et sur la page d'un round. Il dit la règle mais ne montre pas comment elle s'applique : pour savoir pourquoi son index vaut 12,4, un joueur doit refaire le calcul à la main. Le mainteneur a fait dessiner un écran dédié, « Calcul de l'index », dans le design « Golf handicap app redesign » (archive `Golf handicap app redesign (1).zip`, écran `Calcul de l'index.dc.html`, rattaché à `computation-help.tsx` dans la carte des écrans de l'archive). Cet écran déroule le calcul en cinq étapes avec les rounds du joueur choisi, et il faut maintenant le porter dans l'application.

Ce changement suit l'entrée 11 de `ROADMAP.md` (`visual-redesign`, faite et archivée), dont il prolonge le design. Ses dépendances (6 `handicap-index`, 9 `course-rating-sgt`, 11 `visual-redesign`) sont faites.

## What Changes

- **Nouvelle page `/handicaps/calcul`**, protégée par le code d'accès : lien de retour « Handicaps », titre « Comment l’index est calculé », onglets de joueur (le joueur dans l'adresse, `?joueur=<identifiant>`, comme la page Handicaps), puis « Chaque étape avec les rounds de {nom}. »
- **Cinq étapes numérotées**, chacune titrée et suivie de sa ligne de règle actuelle, puis illustrée avec les données du joueur :
  - **01 Score ajusté** : le dernier trou plafonné du joueur sur ses 20 derniers rounds (« TROU 4 · P4 », coups joués barrés, « MAX » par + 4), et le score ajusté sur le brut de son dernier round. Sans trou plafonné, une phrase remplace le trou.
  - **02 Différentiel** : la formule écrite avec le slope, le score ajusté et la rating de son dernier round, puis son différentiel dans la couleur du joueur.
  - **03 Différentiels retenus** : un escalier de 20 marches (nombre retenu selon le nombre de rounds), la marche du joueur en couleur, « {n} → {k} RETENU(S) », puis la liste des 20 derniers rounds (jour, mois, parcours, « {ajusté} ajusté · {rating} / {slope} », différentiel) où les rounds retenus portent la pastille du joueur.
  - **04 Ajustement** : quatre cases (3 rounds −2,0, 4 rounds −1,0, 6 rounds −1,0, autres 0), celle du joueur mise en valeur.
  - **05 Index** : la moyenne des différentiels retenus, l'ajustement, puis l'index actuel en grand.
- **Cas particuliers** : pastille « PROVISOIRE » et la règle de l'index provisoire ; pastille « SANS RATING », la règle de repli et un exemple tiré d'un round du joueur sans rating.
- **Joueur sans round** : les étapes et les cas particuliers en texte seul, sans valeur, avec « Pas encore d'index. Un premier round donne un index provisoire. »
- **Le bloc replié disparaît** des pages Joueurs, Handicaps, Parcours et de la page d'un round. À sa place, un lien « Comment l’index est calculé » mène à la nouvelle page ; depuis Handicaps, il garde le joueur choisi. Les sept lignes de règle gardent leur texte exact ; elles ne s'affichent plus que sur la nouvelle page, dans l'ordre des étapes.
- Les styles de texte et la couleur de texte d'explication que le design ajoute entrent dans `tokens.css` et `DESIGN.md`.

### Hors périmètre

- Aucun changement du calcul de l'index, des différentiels ni de l'historique : la page lit ce que `handicap-index` calcule et enregistre déjà.
- Aucune colonne ni table nouvelle : la rating et le slope montrés sont ceux qu'utilise le calcul (rating confirmée du parcours et des tees, sinon par du round et 113).
- Le reste de l'archive du design (autres écrans, police Sentient) : seul l'écran « Calcul de l'index » est porté.
- Aucun simulateur (« et si je jouais 85 ? ») ni lien de la page vers les rounds.

## Capabilities

### New Capabilities

Aucune : la page explique l'index, que la capacité `handicap-index` possède déjà.

### Modified Capabilities

- `handicap-index` : « Explication du calcul » et « Texte de l'explication du calcul » sont retirées, remplacées par « Lien vers le calcul de l'index » (le lien sur les quatre pages) et « Lignes de règle du calcul de l'index » (les sept lignes, même texte, sur la page, dans l'ordre des étapes) ; « Mise en page de la page Handicaps » finit par ce lien ; nouvelles exigences pour la page « Calcul de l'index » (accès, joueur choisi, chacune des cinq étapes, liste des 20 derniers rounds, cas particuliers, joueur sans round, mise en page).
- `rounds` : « Photo et explication sur la page d'un round » remplace le bloc replié par le lien.

## Impact

- Code : une route et une action de contrôleur (`/handicaps/calcul`), une query de lecture dans `app/Domain/Handicaps/Queries`, une page Inertia `handicaps/computation` et ses composants sous `resources/js/components/handicaps/`, `computation-help.tsx` réduit à un lien, `PlayerTabs` rendu réutilisable par la nouvelle page. Pages Joueurs, Handicaps, Parcours et round : le lien à la place du bloc.
- Thème : nouveaux styles de texte et une couleur dans `resources/css/tokens.css`, `DESIGN.md`, tests de thème (`theme-tokens`, `theme-contrast`).
- Tests : Pest pour la route, l'accès et les données de la page ; Vitest pour la page et le lien sur les quatre pages (les tests du bloc replié sont réécrits).
- Données, migrations, dépendances, CI, déploiement : rien.
- Confidentialité : la page montre les rounds d'un joueur du groupe, comme la page Handicaps, derrière la même session du code d'accès ; sans session, aucun nom ni score dans la réponse.
- Retour arrière : revenir au commit précédent ; aucune donnée n'est écrite.
- `ROADMAP.md` reçoit l'entrée 12 `index-computation-page` ; `CHANGELOG.md` une ligne dans la PR de code.
