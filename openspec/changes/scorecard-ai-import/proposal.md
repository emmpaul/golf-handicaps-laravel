# Proposal

## Why

Après l'entrée 5 (`players-and-rounds`), une carte de score se saisit case par case : 18 pars, 18 index, 18 distances et 18 scores par joueur, sur un téléphone, au simulateur. La section Importer sert encore « Section à venir ». L'application Next lisait la photo par OCR local, puis l'IA en repli ; le port retire l'OCR (`docs/PORT_TO_LARAVEL.md`, décisions 2 à 4 et 9) : la photo entière part à l'IA, qui rend le brouillon de la carte, et l'utilisateur le vérifie avant d'enregistrer. Ce changement correspond à l'entrée 8 de `ROADMAP.md`.

Dépendances : l'entrée 8 dépend de l'entrée 5 seule. `players-and-rounds` est en cours d'implémentation, non archivé : ce changement s'appuie sur son code et ses deltas (`RoundCard`, `RoundCardChecks`, `CreateRound`, `FindDuplicateRound`, `AssignAlias`, disque `photos`, `round-editor/*`). L'apply attend que l'entrée 5 soit fusionnée et archivée. L'entrée 6 (`handicap-index`) se propose en parallèle : ce changement n'en dépend pas (voir « Reporté »).

## What Changes

- **Écran d'import `/import`** : « Prendre une photo » sur écran tactile, « Choisir une photo », lien « Saisie manuelle », liste des 20 derniers imports avec leur état, rafraîchie toutes les 2 s tant qu'un import attend. Plus d'écran de cadrage.
- **Envoi et conversion** : le navigateur décode la photo et la réduit à 3000 px en JPEG (« Format de photo non lisible, exporte-la en JPEG » sinon) ; le serveur la redresse selon son orientation, retire ses métadonnées, la garde en WebP sur le disque `photos` et n'écrit jamais le fichier reçu tel quel.
- **Lecture en file** : un job de la file `database` lit chaque import ; statuts `queued`, `reading`, `ready`, `failed` (« En attente », « Lecture en cours », « Prêt à vérifier », « Échec »).
- **Lecture par l'IA** : un seul appel par import, par le Laravel AI SDK, en sortie structurée : la photo entière réduite à 1568 px, sans métadonnées. L'IA rend le brouillon de `src/scorecard/draft` (parcours, tees, date lue au format mois/jour/année, par, index, distance, lignes de joueur avec nom lu, 18 scores et totaux OUT, IN, TOT). Modèle choisi par `AI_MODEL`.
- **Budget mensuel** : table `ai_usage`, plafond `AI_MONTHLY_BUDGET_USD` (5 $ par défaut) compté par mois civil dans `AI_BUDGET_TIME_ZONE`. Au-delà, aucun appel et l'import passe en « Échec » avec « Budget IA du mois atteint ».
- **Sans clé d'API** : l'envoi de photo est désactivé, l'écran d'import propose la saisie manuelle seule.
- **Écran de correction `/imports/{id}`** : l'éditeur de round de l'entrée 5, avec la photo à côté, les noms lus (« lu « GAB » »), les totaux lus et leurs contrôles (« Somme aller 36 ≠ OUT 38 », « OUT + IN 71 ≠ TOT 72 »), les cases vides de la lecture surlignées dès l'ouverture, la sauvegarde automatique et sa reprise sur un autre appareil.
- **Enregistrement d'un import** : round créé par les mêmes contrôles et la même détection de doublon, avec la photo de l'import ; l'import est relié au round ; chaque nom lu associé à la main devient un alias du joueur (`AssignAlias`).
- **Reconnaissance des noms lus** : nom ou alias identique sans casse, sinon le seul joueur à un caractère près, sinon aucune association.
- **Suppression d'un import** non enregistré, confirmée ; refus pendant la lecture et après l'enregistrement. Suppression d'un round importé : son import disparaît avec lui.
- **Dépendances** : `laravel/ai` et `intervention/image` (driver Imagick), extension `imagick` dans l'image de production, limites d'envoi de PHP relevées. ADR « Lecture des cartes par l'IA et photos en WebP ».
- **Données de développement** : modèles `Import` et `AiUsage` avec fabriques, états et seeder de démonstration (imports dans chaque état, consommation du mois).

### Reporté à d'autres changements

| Sujet | Changement | Raison |
| --- | --- | --- |
| Recalcul des index après l'enregistrement d'un import ou la suppression d'un round importé | `handicap-index` (6) | L'enregistrement passe par `CreateRound`, la suppression par `DeleteRound` : le recalcul que 6 y ajoute s'applique tel quel. Rien n'est préparé ici si 8 est fusionné avant 6. |
| Mise en file de la recherche SGT d'un parcours nouveau | `course-rating-sgt` (9) | Même point d'entrée (`CreateRound`). 9 réutilise aussi `ai_usage` et le budget de ce changement. |
| Bloc « Imports à vérifier » de l'accueil | `home-and-match-strokes` (7) | Il lit la table `imports` de ce changement ; 7 l'ajoute si 8 est fusionné avant lui (Open Questions). |
| Reprise des imports, corrections et `ai_usage` de la base SQLite | `sqlite-import-command` (10) | La forme de la correction enregistrée change (identifiants UUID, plus de `aiCells`) : 10 la traduit. |

### Hors périmètre

- Cadrage : détection du panneau, coins, `corners_source`, écran `/recadrer`, recadrage manuel (décisions 2 et 3).
- Fusion d'une lecture locale et d'une complétion, marque « complété par l'IA » sur chaque case, colonne `ai_cells` (décision 3 : l'IA lit tout).
- Conversion au démarrage des photos déjà stockées dans un autre format : aucune n'existe dans la base Laravel ; l'import SQLite (10) convertit les siennes.
- Relance de la lecture d'un import en « Échec » (Open Questions).

## Requirements des specs Next

| Spec Next | Requirement | Sort | Raison ou adaptation |
| --- | --- | --- | --- |
| `scorecard-import` | Envoi d'une photo | Porté | Sans cadrage, comme dans Next. |
| `scorecard-import` | Cadrage de la carte | Retiré | Décision 2. |
| `scorecard-import` | Lecture en tâche de fond | Porté | Sans « Panneau introuvable » ni « Recadrer la photo » ; l'échec propose l'écran vide et la suppression. |
| `scorecard-import` | Lecture OCR sans service externe | Remplacé | Par « Données envoyées à l'IA » (`scorecard-ai-completion`) et « Contenu lu sur la carte » (`scorecard-import`). |
| `scorecard-import` | Date au format du simulateur | Porté | La date lue par l'IA est convertie par le serveur. |
| `scorecard-import` | Contrôles de cohérence | Porté en partie | Les contrôles de l'entrée 5 restent dans `rounds` ; seuls ceux des totaux lus sont ajoutés ici. |
| `scorecard-import` | Correction manuelle avant enregistrement | Porté | Photo et nom lu en plus de l'éditeur de l'entrée 5. |
| `scorecard-import` | Enregistrement bloqué tant que la carte est incohérente | Déjà porté (5) | Les totaux lus s'y ajoutent. |
| `scorecard-import` | Saisie entièrement manuelle | Déjà porté (5) | — |
| `scorecard-import` | Détection de doublon | Porté | Le scénario « Même photo importée deux fois » s'ajoute à celui de l'entrée 5. |
| `scorecard-import` | Conservation de la photo | Porté | Sans « Lecture inchangée après conversion » : la photo envoyée à l'IA vient du WebP. |
| `scorecard-import` | Sauvegarde automatique de la correction d'un import | Porté | Sans les cases marquées IA. |
| `scorecard-import` | Reprise de la correction enregistrée | Porté | — |
| `scorecard-import` | Indicateur d'enregistrement | Porté | — |
| `scorecard-import` | Correction refusée hors vérification | Porté | — |
| `scorecard-import` | Nouvelle lecture après recadrage | Retiré | Décision 3. |
| `scorecard-import` | Prise de photo depuis l'app | Porté | — |
| `scorecard-import` | Choix de la date au calendrier | Déjà porté (5) | « Ouverture sur la date lue » se vérifie sur l'éditeur commun. |
| `scorecard-import` | Calendrier utilisable au clavier et au doigt | Déjà porté (5) | — |
| `scorecard-import` | Marques sur le champ de date | Porté en partie (5) | La marque de l'IA disparaît avec `ai_cells`. |
| `scorecard-import` | Suppression d'un import | Porté | — |
| `scorecard-import` | Conversion des photos déjà stockées | Retiré | Hors périmètre (voir plus haut). |
| `scorecard-import` | Lignes de joueur groupées sur téléphone | Porté | Le nom lu « lu « GAB » » s'ajoute au bloc de l'entrée 5. |
| `scorecard-import` | Une action principale à l'import | Porté | — |
| `scorecard-import` | Saisie manuelle neuve sans alerte | Porté | Le cas « Import lu par OCR » devient « Import lu par l'IA ». |
| `scorecard-import` | Grille de correction sur téléphone | Déjà porté (5) | — |
| `scorecard-ai-completion` | Déclenchement uniquement en repli | Remplacé | Par « Une lecture par l'IA par import ». |
| `scorecard-ai-completion` | Données envoyées | Réécrit | La photo entière réduite, plus le panneau redressé. |
| `scorecard-ai-completion` | Fusion avec la lecture locale | Retiré | Plus de lecture locale. |
| `scorecard-ai-completion` | Marquage des cases complétées par l'IA | Retiré | Décision 3 (`ai_cells`). |
| `scorecard-ai-completion` | Plafond de dépense mensuel | Porté | Plafond par défaut 5 $ ; au-delà, « Échec » au lieu de « Prêt à vérifier ». |
| `scorecard-ai-completion` | Fonction optionnelle et sans blocage | Réécrit | Sans clé, l'import est désactivé ; une erreur de l'IA fait échouer l'import, faute d'autre lecture. |
| `scorecard-panel-detection` | Les sept requirements | Retiré | Capacité supprimée (décision 2). Elle n'existe pas dans les specs Laravel : rien à retirer. |
| `players` | Reconnaissance des noms lus sur la carte | Porté | « Erreur d'OCR » devient « Erreur de lecture » ; un nom proche de deux joueurs n'est associé à personne, comme le code de Next. |
| `players` | Apprentissage des alias | Porté | Seulement à l'enregistrement d'un import. |
| `players` | Retrait d'un alias | Déjà porté (5) | Le scénario « à l'import suivant » se vérifie ici par la reconnaissance. |
| `rounds` | Suppression d'un round | Porté en partie | Scénario « Round importé supprimé » : l'import disparaît avec le round. |
| `loading-feedback` | Indicateur d'attente (« Envoi d'une photo cadrée ») | Porté | Sans cadrage : « Envoi de la photo… » à l'envoi. |
| `loading-feedback` | Attentes de l'import d'une photo | Porté en partie | « Préparation de la photo… » et « Envoi de la photo… » ; sans « Redressement de la photo… ». |
| `loading-feedback` | Imports en attente de lecture | Porté | — |
| `app-shell` | Cibles tactiles (« Lien d'action dans la liste des imports ») | Porté | Couvert par la règle générale des liens d'action de l'entrée 2. |

## Capabilities

### New Capabilities

- `scorecard-import` : envoi d'une photo, conversion, file de lecture et statuts, contenu lu, écran de correction d'un import, totaux lus, sauvegarde automatique, enregistrement, suppression d'un import.
- `scorecard-ai-completion` : réécrite, la lecture de la photo entière par l'IA, les données envoyées, le budget mensuel, le comportement sans clé et en cas d'erreur.

### Modified Capabilities

- `players` : reconnaissance des noms lus, apprentissage des alias à l'enregistrement d'un import.
- `loading-feedback` : attentes de l'envoi d'une photo, motif des imports en attente, suppression d'un import.
- `app-shell` : la section Importer a sa page.
- `deployment` : l'image de production lit et convertit les photos de carte envoyées.

## Impact

- **Code** : `app/Domain/Imports` (Actions, Queries, exceptions, brouillon), `app/Domain/AiUsage` (budget, coût), `App\Domain\Players\PlayerNameMatcher`, `app/Integrations/Ai` (agent du Laravel AI SDK, préparation de l'image), job `ReadScorecardImport`, modèles `Import` et `AiUsage`, `ImportController`, `ImportCorrectionController`, `ImportPhotoController`, `ImportRoundController`, Form Requests, page `import/index` et `imports/show`, composants `import/*`, `round-editor/autosave.ts`. Changements dans le code de l'entrée 5 : `RoundCard` et `RoundCardChecks` (totaux lus), `round-checks.ts` et sa table de cas, `round-editor/*` (photo, nom lu, totaux lus, autosave).
- **Base** : tables `imports` et `ai_usage`, en UUID. Aucune table existante modifiée ; `imports.round_id` référence `rounds` en cascade.
- **Configuration** : `config/ai.php` (publié par le SDK), `config/golf.php` (`ai.model`, `ai.monthly_budget_usd`, `ai.budget_time_zone`, prix par modèle, bornes de tokens). `.env.example` et `docs/deployment.md` : `ANTHROPIC_API_KEY`, `AI_MODEL`, `AI_MONTHLY_BUDGET_USD`, `AI_BUDGET_TIME_ZONE`.
- **Dépendances** : `laravel/ai`, `intervention/image` ; extension PHP `imagick` dans le `Dockerfile` (déjà dans le runtime Sail 8.5, à confirmer en 1.1 des tâches).
- **Confidentialité** : la photo d'une carte (noms lus et scores) part chez Anthropic, réduite et sans métadonnées, seulement si la clé d'API est configurée, et rien d'autre de l'application (ni la liste des joueurs, ni l'historique). Les photos sont servies par des routes protégées par le code d'accès, jamais par `storage/{path}`. Les tests n'appellent jamais l'API.
- **Coût** : au plus le plafond mensuel ; chaque appel facturé est compté, même sans réponse exploitable.
- **Retour arrière** : annuler la pull request de code, `php artisan migrate:rollback` des deux migrations, retirer les variables de l'IA. Les rounds créés depuis un import restent des rounds ordinaires ; leur photo reste sur le disque `photos`.
