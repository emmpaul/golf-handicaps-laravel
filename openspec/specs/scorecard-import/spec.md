# scorecard-import Specification

## Purpose
Transformer la photo d'une carte de score du simulateur en round enregistré : envoi et conservation de la photo, lecture en file, écran de correction de chaque valeur lue, puis enregistrement avec les contrôles de cohérence des rounds.

## Requirements

### Requirement: Envoi d'une photo
L'écran d'import SHALL accepter une photo choisie dans les fichiers ou prise au téléphone, dans un format que le navigateur sait afficher (JPEG, PNG, WebP, HEIC sur appareil Apple), et la mettre en file de lecture dès qu'elle est choisie, sans étape de cadrage. Un fichier que le navigateur ne sait pas lire MUST afficher « Format de photo non lisible, exporte-la en JPEG » et proposer la saisie manuelle.

#### Scenario: Photo JPEG
- **WHEN** l'utilisateur choisit la photo JPEG de la carte de référence `IMG_3064`
- **THEN** aucun écran de cadrage ne s'affiche, l'import apparaît « En attente » dans la liste des imports, et l'écran affiche « Lecture en file d'attente. Tu peux envoyer la photo suivante. »

#### Scenario: Fichier illisible par le navigateur
- **WHEN** l'utilisateur choisit un fichier que le navigateur ne sait pas décoder
- **THEN** l'écran affiche « Format de photo non lisible, exporte-la en JPEG » et le lien « Saisie manuelle », et aucun import n'est créé

#### Scenario: Fichier illisible par le serveur
- **WHEN** une demande d'import arrive avec un fichier qui n'est pas une image
- **THEN** le système refuse avec « Photo illisible », sans créer d'import ni écrire de fichier

#### Scenario: Envoi sans session
- **WHEN** une demande d'import arrive sans session ouverte par le code d'accès
- **THEN** le système la refuse sans créer d'import

### Requirement: Prise de photo depuis l'app
Sur un appareil à écran tactile, l'écran d'import SHALL proposer « Prendre une photo », qui ouvre l'appareil photo arrière sans passer par la photothèque. La photo prise MUST suivre le même parcours qu'une photo choisie dans les fichiers. Le choix d'un fichier SHALL rester disponible. Sur un appareil sans écran tactile, « Prendre une photo » MUST NOT s'afficher.

#### Scenario: Photo prise au téléphone
- **WHEN** sur iPhone, l'utilisateur touche « Prendre une photo » et prend la photo de la carte de référence
- **THEN** l'import apparaît « En attente » dans la liste des imports, comme une photo choisie dans les fichiers

#### Scenario: Prise de vue annulée
- **WHEN** l'utilisateur touche « Prendre une photo » puis annule dans l'appareil photo
- **THEN** l'écran d'import reste tel qu'il était, sans message d'erreur

#### Scenario: Ordinateur
- **WHEN** l'utilisateur ouvre l'écran d'import sur un ordinateur sans écran tactile
- **THEN** « Prendre une photo » ne s'affiche pas et le champ « Photo de la carte » reste disponible

### Requirement: Une action principale à l'import
Sur un appareil à écran tactile, l'écran d'import SHALL présenter « Prendre une photo » comme action principale et « Choisir une photo » comme action secondaire juste en dessous, sans libellé de champ entre les deux. Le champ de fichier MUST garder le nom accessible « Photo de la carte ».

#### Scenario: Écran d'import sur iPhone
- **WHEN** l'utilisateur ouvre l'écran d'import sur iPhone
- **THEN** « Prendre une photo » est le bouton plein en tête, « Choisir une photo » est un bouton secondaire juste en dessous, et aucun libellé « Photo de la carte » n'est affiché entre eux

#### Scenario: Champ toujours trouvable
- **WHEN** un test cherche le champ de fichier par son nom « Photo de la carte »
- **THEN** il le trouve

### Requirement: Conservation de la photo
Le système SHALL conserver la photo de chaque import au format WebP, redressée selon son orientation et sans ses métadonnées. Le fichier envoyé par le navigateur MUST NOT être écrit tel quel sur le serveur. La photo d'un import MUST n'être servie qu'avec une session ouverte par le code d'accès, avec le type `image/webp`, et devient celle du round enregistré depuis l'import.

#### Scenario: Photo stockée en WebP
- **WHEN** l'utilisateur envoie la photo JPEG d'une carte
- **THEN** le serveur ne garde de cette photo qu'un fichier WebP, et la photo de l'import est servie avec le type `image/webp`

#### Scenario: Photo tournée par son orientation
- **WHEN** l'utilisateur envoie une photo enregistrée de côté avec une orientation EXIF qui la remet droite
- **THEN** la photo servie est droite et ne contient plus de données EXIF

#### Scenario: Photo sans session
- **WHEN** un visiteur sans session demande l'adresse de la photo d'un import
- **THEN** le système le redirige vers `/login` sans envoyer l'image

#### Scenario: Consultation de la photo d'un round importé
- **WHEN** l'utilisateur ouvre le round enregistré depuis l'import de la carte de référence
- **THEN** la page du round affiche l'image « Carte du round », la photo de l'import

### Requirement: Lecture en tâche de fond
Une fois la photo envoyée, le système SHALL mettre sa lecture en file et rendre la main tout de suite. Chaque import affiche son état : « En attente », « Lecture en cours », « Prêt à vérifier » ou « Échec ». L'utilisateur peut quitter la page et revenir. Plusieurs imports peuvent attendre en même temps.

#### Scenario: Plusieurs photos d'un coup
- **WHEN** l'utilisateur envoie 3 photos à la suite
- **THEN** les 3 imports apparaissent dans la liste des imports avec leur état, et chacun passe à « Prêt à vérifier » quand sa lecture est terminée

#### Scenario: Retour après fermeture
- **WHEN** l'utilisateur ferme l'app pendant une lecture puis la rouvre
- **THEN** l'import apparaît avec son état à jour, et son écran de correction s'ouvre s'il est « Prêt à vérifier »

#### Scenario: Liste qui se met à jour
- **GIVEN** la liste des imports affiche un import « En attente »
- **WHEN** sa lecture se termine pendant que la page est ouverte
- **THEN** l'import passe à « Prêt à vérifier » sans que l'utilisateur recharge la page

### Requirement: Liste des imports
L'écran d'import SHALL lister les 20 imports les plus récents, du plus récent au plus ancien, avec leur état et leur action : « Vérifier » pour un import « Prêt à vérifier » sans round, « Ouvrir l'écran de correction vide » et « Relancer la lecture » pour un import en « Échec », « Voir le round » pour un import enregistré, et « Supprimer » pour un import « En attente », « Prêt à vérifier » ou « Échec » sans round. Un import en « Échec » MUST afficher la raison de l'échec.

#### Scenario: Import prêt
- **WHEN** la liste contient un import « Prêt à vérifier » sans round
- **THEN** sa ligne propose « Vérifier » et « Supprimer »

#### Scenario: Import enregistré
- **WHEN** la liste contient un import dont le round est enregistré
- **THEN** sa ligne propose « Voir le round », qui ouvre la page du round, et ne propose pas « Supprimer »

#### Scenario: Import en échec
- **WHEN** la liste contient un import en « Échec » faute de budget
- **THEN** sa ligne affiche « Budget IA du mois atteint » et propose « Ouvrir l'écran de correction vide », « Relancer la lecture » et « Supprimer »

#### Scenario: Import en lecture
- **WHEN** la liste contient un import « Lecture en cours »
- **THEN** sa ligne ne propose aucune action

### Requirement: Échec de la lecture
Quand la lecture d'un import ne donne pas de brouillon, le système SHALL passer l'import en « Échec » avec la raison de l'échec. Une erreur passagère (réseau, service surchargé) MUST être réessayée une fois avant l'« Échec ». Une réponse facturée mais inexploitable MUST mener à l'« Échec » sans nouvelle tentative.

#### Scenario: Deux erreurs passagères
- **WHEN** la lecture d'un import échoue deux fois de suite sur une erreur réseau
- **THEN** l'import passe à « Échec » et propose l'écran de correction vide

#### Scenario: Une erreur passagère puis une lecture
- **WHEN** la première tentative échoue sur une erreur réseau et la seconde rend un brouillon
- **THEN** l'import est « Prêt à vérifier » avec ce brouillon

#### Scenario: Réponse inexploitable
- **WHEN** l'IA répond par un brouillon qui ne respecte pas la forme attendue
- **THEN** l'import passe à « Échec » après cette seule tentative

### Requirement: Relance de la lecture
Pour un import en « Échec » sans round, « Relancer la lecture » SHALL remettre l'import « En attente » avec la même photo enregistrée, sans nouvel envoi, et mettre sa lecture en file. La relance suit le parcours d'une première lecture : un seul appel à l'IA par tentative, coût compté et plafond vérifié avant l'appel (spec `scorecard-ai-completion`). La raison de l'échec et la correction enregistrée sur l'écran vide sont effacées, et l'écran affiche « Lecture relancée. ».

#### Scenario: Relance après une erreur de l'IA
- **GIVEN** un import en « Échec » avec « Lecture IA indisponible »
- **WHEN** l'utilisateur touche « Relancer la lecture » et que l'IA rend le brouillon de la carte de référence, facturé 3 000 tokens d'entrée et 1 200 de sortie
- **THEN** l'import passe « En attente » puis « Prêt à vérifier », sa photo est le même fichier qu'avant la relance, et le cumul du mois augmente de 18 000 micro-dollars

#### Scenario: Relance au-delà du plafond
- **GIVEN** un import en « Échec » et le plafond du mois atteint
- **WHEN** l'utilisateur touche « Relancer la lecture »
- **THEN** le système refuse avec « Budget IA du mois atteint », aucun appel n'est fait, et l'import reste en « Échec » avec sa raison

#### Scenario: Relance au mois suivant
- **GIVEN** un import en « Échec » avec « Budget IA du mois atteint » en octobre
- **WHEN** l'utilisateur touche « Relancer la lecture » en novembre
- **THEN** l'import passe « En attente » et sa lecture est faite

### Requirement: Relance refusée
Le système MUST refuser une relance, sans rien changer, pour un import « En attente » ou « Lecture en cours » avec « Lecture en cours », pour un import enregistré en round avec « Cette carte est déjà enregistrée », pour un import « Prêt à vérifier » avec « Cet import n’est pas en échec », et sans clé d'API avec « Lecture des photos désactivée : aucune clé d’IA n’est configurée. ». Sans clé d'API, la liste des imports MUST NOT proposer « Relancer la lecture ».

#### Scenario: Import prêt
- **WHEN** une demande de relance arrive pour un import « Prêt à vérifier »
- **THEN** le système refuse avec « Cet import n’est pas en échec » et l'import ne change pas

#### Scenario: Import en lecture
- **WHEN** une demande de relance arrive pour un import « Lecture en cours »
- **THEN** le système refuse avec « Lecture en cours » et l'import ne change pas

#### Scenario: Sans clé d'API
- **GIVEN** aucune clé d'API n'est configurée et un import en « Échec »
- **WHEN** l'utilisateur ouvre `/import`
- **THEN** la ligne de l'import ne propose pas « Relancer la lecture », et une demande de relance est refusée avec « Lecture des photos désactivée : aucune clé d’IA n’est configurée. »

### Requirement: Contenu lu sur la carte
Pour une carte GSPro, la lecture SHALL donner : le nom du parcours, les tees, la date, pour chaque trou le par, l'index et la distance, les totaux OUT, IN et TOT du par et de la distance, et pour chaque ligne de joueur non vide le nom lu, les 18 scores et les totaux OUT, IN et TOT imprimés. Une case que l'IA ne lit pas MUST rester vide, jamais devinée par le système.

#### Scenario: Carte de référence IMG_3064
- **GIVEN** l'IA lit la carte de référence `IMG_3064` comme le brouillon de `tests/fixtures/scorecards/gspro-img3064.draft.json`
- **WHEN** l'utilisateur ouvre l'écran de correction de l'import
- **THEN** l'écran est pré-rempli avec le parcours « BETHPAGE BLACK », les tees « Red », la date « 17 octobre 2026 », 2 lignes de joueur lues « GAB » et « MANU » et les pars 4,4,3,5,4,4,5,3,4,4,4,4,5,3,4,4,3,4

#### Scenario: Case non lue
- **WHEN** l'IA ne lit pas le score de la ligne 1 au trou 9
- **THEN** cette case est vide sur l'écran de correction

### Requirement: Date au format du simulateur
Le système SHALL lire la date de la carte au format mois/jour/année (« DATE: 10/17/2026 » donne le 17 octobre 2026). Si la date est absente ou n'est pas une date du calendrier, l'écran de correction SHALL proposer la date du jour à l'heure de Montréal, modifiable.

#### Scenario: Date américaine
- **WHEN** la carte affiche « DATE: 03/04/2026 »
- **THEN** la date proposée est le 4 mars 2026

#### Scenario: Date impossible
- **GIVEN** il est 23 h 30 le 17 octobre 2026 à Montréal
- **WHEN** la date lue est « 13/45/2026 »
- **THEN** la date proposée est le 17 octobre 2026

### Requirement: Correction manuelle d'un import
L'écran de correction d'un import SHALL être l'éditeur de round, pré-rempli par la lecture, avec la photo de l'import visible à côté de la grille. Chaque valeur lue est modifiable, et les contrôles de cohérence des rounds s'y appliquent comme à une saisie manuelle.

#### Scenario: Photo à côté de la grille
- **WHEN** l'utilisateur ouvre l'écran de correction de l'import de la carte de référence
- **THEN** l'écran montre la photo de la carte et la grille pré-remplie

#### Scenario: Ajout d'une ligne oubliée
- **WHEN** la lecture n'a donné que 2 lignes alors que Sean jouait aussi
- **THEN** l'utilisateur peut ajouter une ligne, l'associer à Sean et saisir ses 18 scores

#### Scenario: Import en échec
- **WHEN** l'utilisateur ouvre l'écran de correction vide d'un import en « Échec »
- **THEN** l'écran montre la photo de l'import, la raison de l'échec, une ligne de joueur et la date du jour, toutes les autres cases vides

### Requirement: Repère de lecture par l'IA
En tête de l'écran de correction d'un import dont la lecture a donné un brouillon, le système SHALL afficher « Carte lue par l’IA, vérifie chaque case » dans la couleur du token `--color-ai`, que l'écran parte de la lecture ou d'une correction enregistrée. L'écran vide d'un import en « Échec » et la saisie manuelle MUST NOT l'afficher.

#### Scenario: Import lu par l'IA
- **WHEN** l'utilisateur ouvre l'écran de correction de l'import de la carte de référence, « Prêt à vérifier »
- **THEN** l'écran affiche en tête « Carte lue par l’IA, vérifie chaque case », dans la couleur `--color-ai`

#### Scenario: Correction reprise
- **GIVEN** une correction enregistrée sur l'import de la carte de référence
- **WHEN** l'utilisateur rouvre son écran de correction
- **THEN** l'écran affiche toujours « Carte lue par l’IA, vérifie chaque case »

#### Scenario: Import en échec
- **WHEN** l'utilisateur ouvre l'écran de correction vide d'un import en « Échec »
- **THEN** « Carte lue par l’IA, vérifie chaque case » n'est pas affiché

#### Scenario: Saisie manuelle
- **WHEN** l'utilisateur ouvre « Saisie manuelle »
- **THEN** « Carte lue par l’IA, vérifie chaque case » n'est pas affiché

### Requirement: Nom lu sur chaque ligne
Sur l'écran de correction d'un import, chaque ligne de joueur lue SHALL afficher le nom lu sur la carte, « lu « GAB » », sur la rangée de son numéro de ligne. Une ligne ajoutée à la main n'affiche pas de nom lu.

#### Scenario: Ligne à 390 px
- **WHEN** l'écran de correction du round de référence est ouvert sur un écran de 390 px
- **THEN** la liste « Joueur de la ligne 1 » et son bouton de retrait sont sur la même rangée, sous « Ligne 1 » et « lu « GAB » »

#### Scenario: Ligne ajoutée
- **WHEN** l'utilisateur ajoute une ligne sur l'écran de correction
- **THEN** la nouvelle ligne n'affiche aucun « lu « … » »

### Requirement: Contrôle des totaux lus
Quand les totaux d'une ligne viennent de la lecture, l'écran de correction SHALL les afficher dans des cases modifiables et vérifier que la somme des trous 1 à 9 égale OUT, que celle des trous 10 à 18 égale IN, et que OUT + IN égale TOT, pour le par et pour chaque ligne de joueur. Un total lu vide MUST compter comme une case vide. La distance n'est pas contrôlée. Le serveur MUST rejouer ces contrôles avant d'enregistrer.

#### Scenario: Total qui ne correspond pas
- **WHEN** la lecture donne pour Gab les trous 1 à 9 = 5,5,3,5,4,4,4,3,3 avec OUT = 38
- **THEN** la ligne aller de Gab et son OUT sont surlignés avec « Somme aller 36 ≠ OUT 38 »

#### Scenario: Correction d'un score
- **GIVEN** le surlignage « Somme aller 36 ≠ OUT 38 » est affiché
- **WHEN** l'utilisateur remplace le score « 3 » du trou 9 de Gab par « 5 »
- **THEN** le surlignage disparaît

#### Scenario: Total général faux
- **WHEN** la lecture donne pour Gab OUT 38, IN 34 et TOT 73, et des trous qui donnent bien 38 et 34
- **THEN** les cases OUT, IN et TOT de Gab sont surlignées avec « OUT + IN 72 ≠ TOT 73 »

#### Scenario: Distances arrondies
- **WHEN** la lecture donne des distances aller qui font 3138 avec OUT 3139
- **THEN** aucun problème n'est signalé sur la distance

#### Scenario: Ligne ajoutée à la main
- **WHEN** l'utilisateur ajoute une ligne et saisit ses 18 scores
- **THEN** les totaux de cette ligne sont calculés et ne se saisissent pas

#### Scenario: Demande incohérente reçue par le serveur
- **WHEN** une demande d'enregistrement d'un import arrive avec pour Gab une somme aller de 36 et un OUT de 38
- **THEN** le système refuse sans rien écrire et signale « Somme aller 36 ≠ OUT 38 »

### Requirement: Cases vides de la lecture surlignées dès l'ouverture
Sur l'écran de correction d'un import lu, une case obligatoire que la lecture a laissée vide SHALL être surlignée en erreur dès l'ouverture, sans attendre que l'utilisateur la quitte. Sur l'écran vide d'un import en « Échec », les cases suivent la règle de la saisie manuelle neuve.

#### Scenario: Import lu par l'IA
- **WHEN** la lecture n'a pas donné le score du trou 9 de la ligne 1
- **THEN** cette case est surlignée dès l'ouverture de l'écran de correction

#### Scenario: Import en échec
- **WHEN** l'utilisateur ouvre l'écran de correction vide d'un import en « Échec »
- **THEN** aucune case n'est marquée en erreur avant d'être quittée vide

### Requirement: Sauvegarde automatique de la correction d'un import
Sur l'écran de correction d'un import, le système SHALL enregistrer sur le serveur chaque modification sans action de l'utilisateur : au plus tard 2 s après la dernière modification, et aussi quand la page passe en arrière-plan ou se ferme. L'état enregistré couvre le parcours, les tees, la date, toutes les cases, les totaux lus et les lignes de joueur avec leur nom lu et leur joueur associé. La lecture d'origine MUST rester inchangée.

#### Scenario: Joueur choisi et case corrigée, puis rechargement
- **WHEN** sur l'import de la carte de référence où la ligne 1 a été lue « JULIEN », l'utilisateur associe la ligne 1 à Gab, remplace le score 7 de Manu au trou 1 par 6, attend « Enregistré » puis recharge la page
- **THEN** la ligne 1 est associée à Gab et la case de Manu au trou 1 vaut 6

#### Scenario: Date modifiée, puis rechargement
- **WHEN** l'utilisateur remplace la date 2026-10-17 lue sur la carte par 2026-10-13, attend « Enregistré » puis recharge la page
- **THEN** la date affichée est « 13 octobre 2026 »

#### Scenario: Page fermée juste après une modification
- **WHEN** l'utilisateur remplace le score de Gab au trou 9 par 4 puis ferme l'onglet moins d'une seconde après
- **THEN** à la réouverture de l'import, la case de Gab au trou 9 vaut 4

#### Scenario: Lecture d'origine conservée
- **WHEN** l'utilisateur a corrigé des cases d'un import et que la correction est enregistrée
- **THEN** la lecture de l'import garde les valeurs lues, et seule la correction enregistrée porte les valeurs modifiées

### Requirement: Reprise de la correction enregistrée
À l'ouverture de l'écran de correction d'un import, le système SHALL afficher la dernière correction enregistrée pour cet import, quel que soit l'appareil qui l'a faite. Sans correction enregistrée, ou si elle est illisible, l'écran part de la lecture ; pour un import en « Échec », de l'écran vide. Un joueur associé qui n'existe plus MUST laisser sa ligne sans joueur.

#### Scenario: Autre appareil
- **WHEN** l'utilisateur associe la ligne 2 à Sean sur son téléphone, voit « Enregistré », puis ouvre le même import sur son ordinateur
- **THEN** la ligne 2 est associée à Sean sur l'ordinateur

#### Scenario: Import en échec corrigé à la main
- **WHEN** sur l'écran de correction vide d'un import en « Échec », l'utilisateur saisit le parcours « BETHPAGE BLACK » et la date 2026-10-13, attend « Enregistré » puis recharge la page
- **THEN** le parcours vaut « BETHPAGE BLACK » et la date « 13 octobre 2026 », au lieu d'un écran vide daté du jour

#### Scenario: Correction enregistrée illisible
- **WHEN** la correction enregistrée d'un import ne respecte pas le format attendu
- **THEN** l'écran s'ouvre pré-rempli par la lecture, sans erreur

#### Scenario: Deux modifications concurrentes
- **WHEN** deux membres du groupe corrigent le même import en même temps
- **THEN** la dernière correction reçue par le serveur est celle qu'affiche la prochaine ouverture

### Requirement: Indicateur d'enregistrement
L'écran de correction d'un import SHALL afficher l'état de la sauvegarde automatique : « Enregistrement… » pendant l'envoi, « Enregistré » une fois la correction acceptée, « Modifications non enregistrées » après un échec. Cet état MUST être annoncé aux lecteurs d'écran sans interrompre la saisie. Un échec MUST NOT bloquer la correction ni l'enregistrement du round, et le système SHALL réessayer.

#### Scenario: Enregistrement réussi
- **WHEN** l'utilisateur remplace un score et que le serveur accepte la correction
- **THEN** l'indicateur passe de « Enregistrement… » à « Enregistré »

#### Scenario: Serveur injoignable
- **WHEN** l'utilisateur remplace un score alors que le serveur ne répond pas
- **THEN** l'indicateur affiche « Modifications non enregistrées », les cases restent modifiables, et la correction est envoyée à nouveau quand le serveur répond

#### Scenario: Écran ouvert sans modification
- **WHEN** l'utilisateur ouvre l'écran de correction d'un import sans rien modifier
- **THEN** aucune sauvegarde n'est envoyée et l'indicateur n'affiche rien

### Requirement: Correction refusée hors vérification
Le système SHALL accepter une correction enregistrée seulement pour un import « Prêt à vérifier » ou en « Échec » qui n'a pas de round. Pour un import inconnu, il SHALL répondre « Import introuvable ». Pour un import en attente, en lecture ou déjà enregistré en round, il MUST refuser sans rien modifier ; l'écran SHALL afficher « Cette carte est déjà enregistrée » quand un round existe, et arrêter la sauvegarde.

#### Scenario: Round déjà enregistré par un autre membre
- **WHEN** Gab enregistre le round d'un import pendant que Manu a encore l'écran de correction de cet import ouvert, puis que Manu modifie une case
- **THEN** la correction de Manu est refusée, son écran affiche « Cette carte est déjà enregistrée », et le round enregistré par Gab ne change pas

#### Scenario: Import en lecture
- **WHEN** une correction est envoyée pour un import « En attente » ou « Lecture en cours »
- **THEN** le système la refuse et l'import ne change pas

#### Scenario: Import inconnu
- **WHEN** une correction est envoyée pour un identifiant qui ne désigne aucun import
- **THEN** le système répond 404 avec « Import introuvable »

### Requirement: Enregistrement d'un import
Depuis l'écran de correction, l'utilisateur SHALL enregistrer le round avec les mêmes contrôles de cohérence et la même détection de doublon qu'une saisie manuelle. Le round enregistré MUST garder la photo de l'import, et l'import MUST être relié à ce round. Après l'enregistrement, la liste des rounds s'ouvre avec « Round enregistré. ». L'enregistrement d'un import qui a déjà un round MUST être refusé avec « Cette carte est déjà enregistrée », sans rien écrire.

#### Scenario: Carte de référence enregistrée
- **WHEN** l'utilisateur associe les lignes à Gab et Manu sur l'import de la carte de référence et enregistre
- **THEN** la liste des rounds s'ouvre avec « Round enregistré. », le round montre Gab 72 et Manu 88 avec sa photo, et l'import propose « Voir le round »

#### Scenario: Même photo importée deux fois
- **GIVEN** le round de la carte de référence est enregistré
- **WHEN** l'utilisateur importe une deuxième fois la carte `IMG_3064`, vérifie et enregistre
- **THEN** le système affiche « Ce round semble déjà enregistré » avec un lien vers le round existant, et n'enregistre rien

#### Scenario: Import enregistré depuis deux onglets
- **GIVEN** l'écran de correction d'un import est ouvert dans deux onglets, et le round est enregistré depuis le premier
- **WHEN** l'utilisateur enregistre le même import depuis le second onglet
- **THEN** le système refuse avec « Cette carte est déjà enregistrée », sans « Ce round semble déjà enregistré », aucun second round n'est créé, aucun alias n'est appris, et l'import reste relié au premier round

#### Scenario: Import ouvert par l'adresse après l'enregistrement
- **GIVEN** le round d'un import est enregistré
- **WHEN** l'utilisateur ouvre l'adresse de l'écran de correction de cet import
- **THEN** la page du round s'ouvre

### Requirement: Suppression d'un import
Le système SHALL permettre de supprimer, après une confirmation, un import sans round « En attente », « Prêt à vérifier » ou « Échec ». La suppression MUST retirer l'import de la liste, effacer sa photo du serveur et empêcher sa lecture. Un import « Lecture en cours » ou enregistré en round MUST NOT être supprimé.

#### Scenario: Import en échec supprimé
- **WHEN** l'utilisateur supprime un import en « Échec » et confirme
- **THEN** l'import disparaît de la liste des imports, et l'adresse de sa photo répond 404 avec « Photo introuvable »

#### Scenario: Import en attente supprimé
- **WHEN** l'utilisateur supprime un import « En attente » et confirme
- **THEN** l'import disparaît de la liste, sa photo n'est jamais envoyée à l'IA et aucun coût n'est compté

#### Scenario: Suppression annulée
- **WHEN** l'utilisateur demande la suppression d'un import puis annule
- **THEN** l'import reste dans la liste avec son état, sa photo et sa correction

#### Scenario: Import en lecture
- **WHEN** une demande de suppression arrive pour un import « Lecture en cours »
- **THEN** le système refuse avec « Lecture en cours » et l'import ne change pas

#### Scenario: Import déjà enregistré en round
- **WHEN** une demande de suppression arrive pour un import enregistré en round
- **THEN** le système refuse avec « Cette carte est déjà enregistrée », et l'import, le round et la photo ne changent pas

### Requirement: Import retiré avec son round
La suppression d'un round enregistré depuis un import SHALL retirer aussi cet import de la liste des imports. La suppression d'un round saisi à la main MUST laisser la liste des imports inchangée.

#### Scenario: Round importé supprimé
- **WHEN** l'utilisateur supprime le round enregistré depuis l'import de la carte de référence et confirme
- **THEN** l'import n'apparaît plus dans la liste des imports (il ne revient pas « Prêt à vérifier »), et la photo n'est plus servie

#### Scenario: Round saisi à la main supprimé
- **WHEN** l'utilisateur supprime un round saisi à la main
- **THEN** la liste des imports ne change pas

### Requirement: Import introuvable
Une adresse d'import dont l'identifiant n'est pas un UUID ou ne désigne aucun import SHALL répondre 404, pour l'écran de correction, la photo, la correction enregistrée, l'enregistrement et la suppression.

#### Scenario: Ancienne adresse numérique
- **WHEN** l'utilisateur ouvre `/imports/12`
- **THEN** le système répond 404
