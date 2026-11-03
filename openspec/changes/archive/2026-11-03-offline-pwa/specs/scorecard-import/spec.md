## MODIFIED Requirements

### Requirement: Envoi d'une photo
L'écran d'import SHALL accepter une photo choisie dans les fichiers ou prise au téléphone, dans un format que le navigateur sait afficher (JPEG, PNG, WebP, HEIC sur appareil Apple), la garder sur l'appareil dès qu'elle est préparée, puis l'envoyer pour la mettre en file de lecture, sans étape de cadrage. Un fichier que le navigateur ne sait pas lire MUST afficher « Format de photo non lisible, exporte-la en JPEG » et proposer la saisie manuelle.

#### Scenario: Photo JPEG
- **WHEN** l'utilisateur choisit en ligne la photo JPEG de la carte de référence `IMG_3064`
- **THEN** aucun écran de cadrage ne s'affiche, l'import apparaît « En attente » dans la liste des imports, et l'écran affiche « Lecture en file d'attente. Tu peux envoyer la photo suivante. »

#### Scenario: Fichier illisible par le navigateur
- **WHEN** l'utilisateur choisit un fichier que le navigateur ne sait pas décoder
- **THEN** l'écran affiche « Format de photo non lisible, exporte-la en JPEG » et le lien « Saisie manuelle », et aucun import n'est créé ni aucune photo gardée sur l'appareil

#### Scenario: Fichier illisible par le serveur
- **WHEN** une demande d'import arrive avec un fichier qui n'est pas une image
- **THEN** le système refuse avec « Photo illisible », sans créer d'import ni écrire de fichier

#### Scenario: Envoi sans session
- **WHEN** une demande d'import arrive sans session ouverte par le code d'accès
- **THEN** le système la refuse sans créer d'import

## ADDED Requirements

### Requirement: Photo gardée hors ligne
Sans réseau, une photo choisie ou prise SHALL être gardée sur l'appareil et l'écran MUST afficher « Photo gardée sur le téléphone. Elle partira au retour du réseau. ». La photo gardée MUST survivre à la fermeture de l'application et au redémarrage du téléphone.

#### Scenario: Photo prise en mode avion
- **GIVEN** l'appareil est en mode avion sur l'écran Importer
- **WHEN** l'utilisateur prend la photo de la carte de référence
- **THEN** l'écran affiche « Photo gardée sur le téléphone. Elle partira au retour du réseau. » et aucun import n'est créé sur le serveur

#### Scenario: Application fermée
- **GIVEN** une photo est gardée hors ligne
- **WHEN** l'utilisateur ferme l'application, redémarre le téléphone et rouvre l'écran Importer
- **THEN** la photo est toujours listée « En attente d'envoi »

### Requirement: Liste des photos en attente d'envoi
Tant qu'au moins une photo est gardée sans être envoyée, l'écran d'import SHALL afficher, au-dessus de « IMPORTS », la section « EN ATTENTE D'ENVOI » avec leur nombre et, pour chaque photo, sa miniature, l'heure de la prise dans le fuseau d'affichage et l'action « Retirer ». « Retirer » MUST effacer la photo de l'appareil sans rien envoyer. Sans photo en attente, la section MUST NOT s'afficher.

#### Scenario: Deux photos hors ligne
- **GIVEN** deux photos ont été prises hors ligne à 21:04 puis 21:06
- **WHEN** l'utilisateur ouvre l'écran Importer
- **THEN** la section « EN ATTENTE D'ENVOI » affiche 2 et les deux photos, la plus ancienne en premier, chacune avec « Retirer »

#### Scenario: Photo retirée
- **GIVEN** une photo est en attente d'envoi
- **WHEN** l'utilisateur touche « Retirer » sur cette photo
- **THEN** la photo disparaît de la liste et, au retour du réseau, aucun import n'est créé pour elle

### Requirement: Envoi au retour du réseau
Quand l'application est ouverte et que le réseau revient, ou à l'ouverture de l'application avec du réseau, les photos en attente SHALL partir seules, une à la fois, de la plus ancienne à la plus récente. Une photo envoyée MUST quitter la liste « En attente d'envoi » et apparaître « En attente » dans la liste des imports. Après le dernier envoi, l'écran SHALL afficher « N photos envoyées. » (« 1 photo envoyée. » pour une seule).

#### Scenario: Retour du réseau
- **GIVEN** deux photos sont en attente d'envoi et l'écran Importer est ouvert
- **WHEN** l'appareil retrouve le réseau
- **THEN** les deux imports apparaissent « En attente », dans l'ordre de la prise, la section « EN ATTENTE D'ENVOI » disparaît et l'écran affiche « 2 photos envoyées. »

#### Scenario: Application rouverte avec du réseau
- **GIVEN** une photo a été gardée hors ligne puis l'application fermée
- **WHEN** l'utilisateur rouvre l'application sur l'accueil avec du réseau
- **THEN** la photo part sans autre geste et l'import apparaît « En attente » dans la liste des imports

### Requirement: Envoi interrompu
Un envoi interrompu par la perte du réseau, une erreur du serveur ou une réponse qui n'arrive pas SHALL garder la photo en attente d'envoi, et arrêter l'envoi des suivantes jusqu'au prochain retour du réseau ou à la prochaine ouverture de l'application.

#### Scenario: Serveur en erreur
- **GIVEN** une photo est en attente d'envoi
- **WHEN** le serveur répond par une erreur 500 à son envoi
- **THEN** la photo reste « En attente d'envoi » et repart au prochain retour du réseau ou à la prochaine ouverture

#### Scenario: Réseau perdu pendant l'envoi
- **GIVEN** deux photos sont en attente d'envoi
- **WHEN** le réseau tombe pendant l'envoi de la première
- **THEN** les deux photos restent « En attente d'envoi »

### Requirement: Envoi sans doublon
Chaque photo gardée SHALL porter une clé unique tirée sur l'appareil, envoyée avec la photo. Le serveur MUST NOT créer un second import, écrire une seconde photo ni lancer une seconde lecture pour une clé déjà reçue : il répond comme au premier envoi.

#### Scenario: Réponse perdue
- **GIVEN** le serveur a créé l'import d'une photo mais sa réponse n'est pas arrivée sur le téléphone
- **WHEN** le téléphone renvoie la même photo avec la même clé
- **THEN** la liste des imports ne contient qu'un import pour cette photo, et une seule lecture est mise en file

#### Scenario: Deux photos différentes
- **WHEN** deux photos gardées partent avec deux clés différentes
- **THEN** deux imports sont créés

### Requirement: Reprise de l'envoi après la saisie du code
Quand le serveur refuse l'envoi d'une photo faute de session (session expirée, code changé, déconnexion), la photo SHALL rester en attente d'envoi et l'application MUST afficher `/login`. Après une saisie du code réussie, les photos en attente SHALL partir comme au retour du réseau.

#### Scenario: Code changé avant l'envoi
- **GIVEN** une photo a été gardée hors ligne et le code configuré a changé depuis
- **WHEN** le réseau revient
- **THEN** l'application affiche `/login`, la photo n'est pas perdue, et après la saisie du nouveau code l'import apparaît « En attente »

#### Scenario: Déconnexion avec une photo en attente
- **GIVEN** une photo est en attente d'envoi
- **WHEN** l'utilisateur se déconnecte puis saisit à nouveau le code en ligne
- **THEN** la photo part et son import apparaît « En attente »

### Requirement: Photo refusée à l'envoi
Quand le serveur refuse une photo gardée pour elle-même (photo illisible, trop lourde, lecture des photos désactivée), la photo SHALL quitter la liste « En attente d'envoi » et l'écran MUST afficher le refus du serveur avec le lien « Saisie manuelle ». Les photos suivantes MUST continuer de partir.

#### Scenario: Lecture désactivée entre-temps
- **GIVEN** deux photos sont en attente et la clé d'IA a été retirée du serveur
- **WHEN** le réseau revient
- **THEN** l'écran affiche le refus « Lecture des photos désactivée : aucune clé d’IA n’est configurée. » avec « Saisie manuelle », et aucune photo ne reste en attente
