## Purpose

Garder l'application utilisable sans réseau : relire hors ligne les pages déjà visitées, signaler l'état hors ligne, et refuser clairement ce qui ne peut pas partir, sans garder de données du groupe au-delà de la session.

## ADDED Requirements

### Requirement: Pages visitées gardées sur l'appareil
Chaque page de l'application lue avec succès en ligne, avec une session ouverte, SHALL être gardée sur l'appareil, dans sa dernière version. En ligne, une page MUST toujours être demandée au serveur d'abord : la copie gardée ne sert que sans réseau. La page `/login`, les redirections, les réponses en erreur, les écritures et les photos des rounds et des imports MUST NOT être gardées.

#### Scenario: Page relue en ligne
- **GIVEN** l'utilisateur a visité `/handicaps` en ligne la veille
- **WHEN** il ouvre `/handicaps` en ligne après l'ajout d'un round
- **THEN** la page affiche l'index recalculé, pas celui de la veille

#### Scenario: Page de connexion
- **WHEN** un visiteur sans session ouvre `/login` en ligne
- **THEN** rien de la réponse n'est gardé sur l'appareil

#### Scenario: Photo d'un round
- **WHEN** l'utilisateur ouvre en ligne la page d'un round avec sa photo
- **THEN** la page est gardée et la photo ne l'est pas

### Requirement: Page visitée relue hors ligne
Sans réseau, une page déjà gardée SHALL s'afficher depuis sa copie, par un onglet, un lien ou à l'ouverture de l'application, avec ses données telles qu'à la dernière visite en ligne. La page MUST rester lisible et naviguable vers les autres pages gardées.

#### Scenario: Coups à rendre hors ligne
- **GIVEN** l'utilisateur a visité `/strokes` en ligne
- **WHEN** il ouvre l'application en mode avion et touche l'onglet des coups
- **THEN** `/strokes` s'affiche avec les index de la dernière visite et le calcul des coups d'une partie fonctionne

#### Scenario: Ouverture à froid sur une page gardée
- **GIVEN** l'utilisateur a visité `/` en ligne
- **WHEN** il ouvre l'application installée depuis l'écran d'accueil en mode avion
- **THEN** l'accueil s'affiche depuis sa copie

### Requirement: Bandeau hors ligne
Tant que l'appareil est hors ligne, ou qu'une page affichée vient d'une copie gardée, chaque page SHALL afficher sous la rangée du haut le bandeau « Hors ligne », suivi pour une copie de « · page du <jour> à <heure> », heure de la copie dans le fuseau d'affichage. Le bandeau MUST disparaître au retour du réseau sans recharger la page, et MUST être annoncé une fois par le lecteur d'écran.

#### Scenario: Copie affichée
- **GIVEN** `/rounds` a été gardé en ligne le 30 octobre 2026 à 21 h 04, heure de Montréal
- **WHEN** l'utilisateur l'ouvre hors ligne
- **THEN** la page affiche « Hors ligne · page du 30 oct. à 21:04 »

#### Scenario: Retour du réseau
- **GIVEN** le bandeau « Hors ligne » est affiché
- **WHEN** l'appareil retrouve le réseau
- **THEN** le bandeau disparaît sans rechargement de la page

#### Scenario: En ligne
- **WHEN** l'utilisateur ouvre une page en ligne
- **THEN** aucun bandeau « Hors ligne » ne s'affiche

### Requirement: Page jamais visitée hors ligne
Sans réseau, aller vers une page qui n'a pas de copie gardée SHALL laisser la page courante affichée et montrer le message éphémère « Page indisponible hors ligne. ». Ouverte à froid sans réseau sur une adresse sans copie, l'application SHALL afficher un écran « Hors ligne » aux couleurs de l'application, avec le texte « Cette page n'a pas encore été ouverte sur ce téléphone. » et un lien « Importer une carte » vers `/import`.

#### Scenario: Lien vers un round jamais ouvert
- **GIVEN** l'utilisateur est hors ligne sur `/rounds`, gardé en ligne
- **WHEN** il touche un round qu'il n'a jamais ouvert
- **THEN** `/rounds` reste affiché et le message « Page indisponible hors ligne. » apparaît

#### Scenario: Ouverture à froid sans copie
- **GIVEN** aucune page de `/courses` n'a été gardée
- **WHEN** l'utilisateur ouvre `/courses` sans réseau
- **THEN** l'écran « Hors ligne » s'affiche avec le lien « Importer une carte »

### Requirement: Écran Importer disponible hors ligne
Dès que l'application s'ouvre en ligne avec une session, l'écran `/import` SHALL être gardé sur l'appareil, même si l'utilisateur n'y est pas allé. Hors ligne, il MUST permettre de prendre ou de choisir une photo.

#### Scenario: Importer sans visite préalable
- **GIVEN** l'utilisateur a ouvert l'accueil en ligne puis fermé l'application, sans aller sur `/import`
- **WHEN** il rouvre l'application en mode avion et touche le bouton Importer
- **THEN** l'écran Importer s'affiche avec « Prendre une photo » et « Choisir une photo »

### Requirement: Écritures hors ligne refusées
Sans réseau, toute écriture autre que l'envoi d'une photo (saisie ou modification d'un round, suppression, joueurs, alias, ratings, correction d'un import, relance d'une lecture) SHALL échouer avec le message éphémère « Hors ligne : rien n'a été envoyé. ». Le formulaire MUST garder ce qui a été saisi, et rien ne MUST être gardé pour un envoi plus tard.

#### Scenario: Round saisi hors ligne
- **GIVEN** l'utilisateur a rempli une carte sur `/rounds/new` puis perdu le réseau
- **WHEN** il touche « Enregistrer »
- **THEN** le message « Hors ligne : rien n'a été envoyé. » s'affiche, la carte remplie reste à l'écran, et aucun round n'est créé au retour du réseau

### Requirement: Service worker servi sans session
Le service worker et l'écran « Hors ligne » SHALL être servis sans session, sans redirection, sans en-tête `Set-Cookie`, et ne contenir aucune donnée du groupe. Le service worker MUST être servi avec `Cache-Control: no-cache`, pour qu'un déploiement le remplace à la visite suivante.

#### Scenario: Client sans cookie
- **WHEN** un client sans cookie demande `/sw.js`
- **THEN** il reçoit 200, du JavaScript, `Cache-Control: no-cache` et aucun en-tête `Set-Cookie`

#### Scenario: Écran hors ligne
- **WHEN** un client sans cookie demande `/offline.html`
- **THEN** il reçoit 200, du HTML en français, sans nom de joueur ni score

### Requirement: Pages gardées effacées à la fermeture de session
Les pages gardées sur l'appareil SHALL être effacées quand la session de ce navigateur se ferme : par « Se déconnecter », ou quand le serveur refuse la session (code changé, session expirée). Après l'effacement, aucune page de l'application MUST NOT s'afficher hors ligne avant une nouvelle saisie du code en ligne.

#### Scenario: Déconnexion puis mode avion
- **GIVEN** l'utilisateur a visité `/handicaps` en ligne
- **WHEN** il se déconnecte, passe en mode avion et ouvre `/handicaps`
- **THEN** l'écran « Hors ligne » s'affiche, sans index de joueur

#### Scenario: Code changé sur le serveur
- **GIVEN** un téléphone a gardé `/rounds` et le code configuré change
- **WHEN** le téléphone ouvre l'accueil en ligne et le serveur le renvoie vers `/login`
- **THEN** les pages gardées sont effacées
