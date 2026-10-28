# Spec Delta

## Purpose

Lire la photo entière d'une carte de score par un modèle de vision d'Anthropic, une fois par import, sous un plafond de dépense mensuel. L'IA rend tout le brouillon ; la vérification par l'utilisateur reste obligatoire avant l'enregistrement.

## ADDED Requirements

### Requirement: Une lecture par l'IA par import
Pour chaque import mis en file, le système SHALL demander le brouillon de la carte à l'IA, au plus une fois par tentative de lecture. Le modèle utilisé MUST être celui de `AI_MODEL`. Le système MUST NOT faire d'autre appel à l'IA pour cet import : ni cadrage, ni complétion.

#### Scenario: Lecture réussie
- **WHEN** un import de la carte de référence est lu et que l'IA rend un brouillon valide
- **THEN** le système a fait exactement un appel à l'IA pour cet import, et l'import est « Prêt à vérifier »

#### Scenario: Brouillon avec des cases vides
- **WHEN** l'IA rend un brouillon sans le score de Gab au trou 9
- **THEN** l'import est « Prêt à vérifier » après ce seul appel, avec la case vide

#### Scenario: Modèle configuré
- **GIVEN** `AI_MODEL=claude-opus-5-5`
- **WHEN** un import est lu
- **THEN** l'appel demande le modèle `claude-opus-5-5`

### Requirement: Données envoyées à l'IA
Pour une lecture, le système SHALL envoyer à Anthropic une seule image : la photo de l'import, remise droite, réduite à 1568 px au plus sur son grand côté, sans métadonnées, avec une consigne fixe. Il MUST NOT envoyer la photo à sa taille d'origine, ni aucune donnée de l'application : ni noms des joueurs, ni alias, ni rounds, ni index.

#### Scenario: Image envoyée
- **WHEN** une lecture est demandée pour une photo de 3000×2250 px
- **THEN** la requête contient une seule image, de 1568×1176 px, sans données EXIF, et aucune autre image

#### Scenario: Aucune donnée du groupe
- **GIVEN** les joueurs Gab, Manu et Sean et le round de référence existent
- **WHEN** une lecture est demandée
- **THEN** la requête ne contient ni « Gab », ni « Manu », ni « Sean », ni aucune valeur des rounds enregistrés

#### Scenario: Petite photo
- **WHEN** une lecture est demandée pour une photo de 1200×900 px
- **THEN** l'image envoyée mesure 1200×900 px

### Requirement: Lecture désactivée sans clé d'API
Sans clé d'API Anthropic configurée, le système SHALL désactiver l'envoi de photo : l'écran d'import affiche « Lecture des photos désactivée : aucune clé d'IA n'est configurée. » à la place des actions d'envoi, avec le lien « Saisie manuelle », et une demande d'import MUST être refusée sans créer d'import ni appeler l'IA. Les imports existants restent listés et vérifiables.

#### Scenario: Écran d'import sans clé
- **GIVEN** aucune clé d'API n'est configurée
- **WHEN** l'utilisateur ouvre `/import`
- **THEN** l'écran affiche « Lecture des photos désactivée : aucune clé d'IA n'est configurée. » et le lien « Saisie manuelle », sans « Prendre une photo » ni « Choisir une photo »

#### Scenario: Demande d'import sans clé
- **GIVEN** aucune clé d'API n'est configurée
- **WHEN** une demande d'import arrive avec une photo
- **THEN** le système la refuse, aucun import n'est créé et aucun appel n'est fait

#### Scenario: Clé retirée pendant qu'un import attend
- **GIVEN** un import « En attente » et aucune clé d'API configurée au moment de sa lecture
- **WHEN** le worker traite l'import
- **THEN** aucun appel n'est fait et l'import passe à « Échec » avec « Lecture des photos désactivée : aucune clé d'IA n'est configurée. »

### Requirement: Erreur de l'IA
Si l'appel ne donne pas de brouillon (erreur réseau ou de l'API après la nouvelle tentative, refus, sortie tronquée, réponse hors de la forme attendue), le système SHALL passer l'import en « Échec » avec « Lecture IA indisponible ». Une réponse qui donne un nombre de trous autre que 18 sur une ligne MUST compter comme hors de la forme attendue.

#### Scenario: Erreur de l'API
- **WHEN** l'API répond par une erreur aux deux tentatives de lecture d'un import
- **THEN** l'import est en « Échec » avec « Lecture IA indisponible »

#### Scenario: Refus
- **WHEN** l'IA refuse de lire la photo
- **THEN** l'import est en « Échec » avec « Lecture IA indisponible », après une seule tentative

#### Scenario: Ligne de 17 trous
- **WHEN** l'IA rend une ligne de joueur de 17 scores
- **THEN** l'import est en « Échec » avec « Lecture IA indisponible »

### Requirement: Coût de chaque appel compté
Le système SHALL calculer le coût de chaque appel à l'IA à partir des tokens d'entrée et de sortie facturés, renvoyés par l'API, et des tarifs du modèle, en micro-dollars, et l'ajouter au cumul du mois civil. Un appel facturé MUST être compté même si sa réponse est inexploitable.

#### Scenario: Appel compté
- **GIVEN** le modèle coûte 2 micro-dollars par token d'entrée et 10 par token de sortie
- **WHEN** une lecture facture 3 000 tokens d'entrée et 1 200 tokens de sortie
- **THEN** le cumul du mois augmente d'exactement 18 000 micro-dollars

#### Scenario: Réponse illisible facturée
- **WHEN** l'IA répond par un brouillon hors de la forme attendue, facturé 3 000 tokens d'entrée et 1 200 de sortie au même modèle
- **THEN** le cumul du mois augmente de 18 000 micro-dollars et l'import passe à « Échec »

### Requirement: Plafond de dépense mensuel
Avant chaque appel, si le cumul du mois plus le coût maximal d'un appel dépasse le plafond `AI_MONTHLY_BUDGET_USD` (5 $ par défaut), le système MUST NOT appeler l'IA et SHALL passer l'import en « Échec » avec « Budget IA du mois atteint ». Le coût maximal d'un appel est celui des bornes de tokens d'entrée et de sortie configurées.

#### Scenario: Plafond atteint
- **GIVEN** un plafond de 5 $, un coût maximal d'appel de 48 000 micro-dollars et un cumul du mois de 4 960 000 micro-dollars
- **WHEN** un import est lu
- **THEN** aucun appel n'est fait et l'import est en « Échec » avec « Budget IA du mois atteint »

#### Scenario: Plafond tout juste tenu
- **GIVEN** un plafond de 5 $, un coût maximal d'appel de 48 000 micro-dollars et un cumul du mois de 4 952 000 micro-dollars
- **WHEN** un import est lu
- **THEN** l'appel est fait

#### Scenario: Nouveau mois
- **WHEN** le plafond a été atteint en octobre et qu'une carte est importée en novembre
- **THEN** l'appel à l'IA est fait

#### Scenario: Saisie manuelle au-delà du plafond
- **GIVEN** le plafond du mois est atteint
- **WHEN** l'utilisateur ouvre l'écran de correction vide de l'import en échec, saisit la carte et enregistre
- **THEN** le round est enregistré

### Requirement: Mois civil du budget
Le cumul SHALL être compté par mois civil dans le fuseau horaire IANA de `AI_BUDGET_TIME_ZONE` (`America/Montreal` par défaut). Un fuseau inconnu MUST faire échouer le démarrage du web, du worker et des migrations avec un message qui nomme la variable.

#### Scenario: Fuseau par défaut
- **WHEN** `AI_BUDGET_TIME_ZONE` est absente et qu'un appel a lieu le 1er novembre 2026 à 02:30 UTC
- **THEN** son coût s'ajoute au cumul d'octobre 2026 (22:30 le 31 octobre à Montréal)

#### Scenario: Fuseau configuré
- **WHEN** `AI_BUDGET_TIME_ZONE=Europe/Paris` et qu'un appel a lieu le 1er novembre 2026 à 02:30 UTC
- **THEN** son coût s'ajoute au cumul de novembre 2026 (03:30 le 1er novembre à Paris)

#### Scenario: Fuseau inconnu
- **WHEN** le web ou le worker démarre avec `AI_BUDGET_TIME_ZONE=Mars/Olympus`
- **THEN** le démarrage échoue avec un message qui nomme `AI_BUDGET_TIME_ZONE`
