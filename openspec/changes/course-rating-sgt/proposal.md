# Proposal

## Why

Depuis l'entrée 6 (`handicap-index`), un parcours n'a de course rating et de slope que si quelqu'un les recopie à la main sur `/courses`, en allant les chercher lui-même sur Simulator Golf Tour (SGT). Tant que personne ne le fait, l'index se calcule sur le par et le slope 113. Or presque tous les parcours GSPro ont leur rating et leur slope par tee sur l'image de carte publiée par SGT. L'application Next les cherchait seule, dans son worker, et ne les appliquait qu'après confirmation. Ce changement porte cette recherche ; il correspond à l'entrée 9 de `ROADMAP.md`.

Dépendances : l'entrée 6 (`handicap-index`) est faite et archivée : ce changement modifie ses capacités `course-ratings` et `handicap-index`, sa table `course_ratings` et ses Actions `SaveCourseRating` et `RemoveCourseRating`. L'entrée 8 (`scorecard-ai-import`) est proposée, pas encore implémentée : ce changement réutilise le Laravel AI SDK, la table `ai_usage`, le budget mensuel (`AiBudget`, `AiPricing`, `config/golf.php`), le garde-fou des faux dans les tests et le motif du job de lecture qu'elle introduit, sans les dupliquer. **L'apply attend que l'entrée 8 soit fusionnée et archivée** (tâche 1.1). L'entrée 7 n'est pas requise. L'entrée 11 (`visual-redesign`) vient après : elle restyle `/courses` ; ce changement livre le comportement dans le thème actuel, avec les libellés que son delta `course-ratings` attend (« Relancer la recherche », « Confirmé »).

## What Changes

- **Recherche sur SGT par le worker** : pour chaque parcours connu (nom et tees) sans ligne de rating, un job télécharge la liste des parcours de SGT (`simulatorgolftour.com/courses`, bloc JSON de la page), gardée 24 h en cache, et cherche le parcours de même nom (casse, accents, ponctuation et préfixe « The » ignorés) et de même par. Une correspondance unique est retenue ; plusieurs, ou des noms qui se contiennent seulement, sont proposées au choix ; aucune donne « Introuvable sur SGT ».
- **Lecture de la carte SGT par l'IA** : le parcours SGT retenu, le job télécharge son image de carte et la fait lire par le Laravel AI SDK en sortie structurée (rating et slope de chaque tee, par). Il propose ceux du tee qui porte le nom des tees du round, sans casse ni distance en fin (« White 6023yd » → « White »). Même clé, même plafond mensuel et même table `ai_usage` que la lecture des photos (8). Sans clé, budget atteint ou lecture impossible : saisie manuelle, avec la raison et le lien de la carte SGT.
- **Rien n'entre dans les calculs sans confirmation** : une proposition reste une proposition. Seules la confirmation d'une proposition, le choix d'un tee lu ou la saisie à la main écrivent la rating et le slope, et recalculent l'historique des joueurs du parcours.
- **Statuts de recherche** : « Recherche en cours », « Proposition à confirmer », « Parcours SGT à choisir », « Tee à choisir », « Saisie manuelle », « Introuvable sur SGT », « Recherche échouée », « Confirmé », avec une note (« SGT injoignable », « Budget IA du mois atteint »…).
- **Page Parcours** : état de la recherche et note, parcours SGT retenu (nom et lieu), liens « Carte SGT » et « The Course View », proposition à confirmer, tees lus à retenir, candidats SGT à choisir, « Relancer la recherche ». La page se rafraîchit tant qu'une recherche est en cours. La saisie et le retrait à la main de l'entrée 6 restent.
- **Mise en file** : à l'enregistrement ou à la correction d'un round qui introduit une paire (nom, tees) sans ligne, et, pour les rounds déjà enregistrés, par le service `migrate` à chaque déploiement (commande idempotente). Une paire déjà recherchée ne l'est plus sans action de l'utilisateur.
- **`SGT_LOOKUP=on|off`** (`on` par défaut) : à `off`, aucune requête vers SGT ; chaque recherche finit en « Saisie manuelle », « Recherche SGT désactivée ». Une autre valeur fait échouer le démarrage.
- **Worker borné** : chaque requête vers SGT abandonne après 20 s et un job de recherche après 120 s ; un SGT muet ne fige jamais le worker.
- **Schéma** : `course_ratings` gagne le statut, la source de la valeur confirmée (`sgt` ou `manual`), le parcours SGT retenu, les candidats, les tees lus, le tee proposé et la note ; `rating_t` et `slope` deviennent nuls tant que rien n'est confirmé. Les ratings déjà saisies passent « Confirmé », source `manual`.
- **Données de développement** : fabrique de `CourseRating` étendue d'un état par statut ; jeu de démonstration avec un parcours confirmé depuis SGT, une proposition à confirmer et un parcours introuvable, sans accès réseau ni job en file.
- **ADR** « Recherche des ratings sur Simulator Golf Tour ».

### Reporté à d'autres changements

| Sujet | Changement | Raison |
| --- | --- | --- |
| Lien vers `/courses` depuis la page Coups et rating du parcours choisi | `home-and-match-strokes` (7) | La page Coups n'existe pas encore. |
| Mise en page de `/courses` selon le design (pastilles, liens soulignés) | `visual-redesign` (11) | Ce changement livre le comportement dans le thème actuel ; 11 restyle. |
| Reprise des lignes `course_ratings` de la base SQLite, lignes `pending` remises en file | `sqlite-import-command` (10) | Le schéma suit celui de Next (statuts, colonnes) pour que 10 copie sans traduire. |

### Hors périmètre

- The Course View comme source automatique : le contrôle anti-robot de Vercel bloque tout appel serveur (HTTP 429). Elle reste un lien d'aide.
- Lecture locale de la carte SGT (OCR) : retirée du port (décision 2 de `docs/PORT_TO_LARAVEL.md`).
- Fusion des tees « Blue » et « Blue 6212yd » en un seul parcours, rating figée à la date d'un round, règles WHS absentes de Next (comme l'entrée 6).

## Requirements des specs Next

Référence : le changement `add-course-rating` de Next (non archivé, implémenté dans `src/server/courses`, `src/jobs/course-rating.ts`, `src/ai/rating-reader.ts`), capacité `course-rating`, et `openspec/specs/worker-health`. L'entrée 6 a porté la partie manuelle.

| Spec Next | Requirement | Sort | Raison ou adaptation |
| --- | --- | --- | --- |
| `course-rating` | Rating et slope d'un parcours | Porté (6), complété | « Seules des valeurs confirmées » s'ajoute au requirement de 6. |
| `course-rating` | Recherche sur Simulator Golf Tour | Porté | Mêmes règles de nom, de par et de candidats. |
| `course-rating` | Proposition lue sur la carte SGT | Porté | Laravel AI SDK au lieu du SDK Anthropic ; budget et `ai_usage` de 8. |
| `course-rating` | Page Parcours | Porté | État, note, liens, proposition, tees, candidats, relance ; rafraîchissement ajouté. Lien depuis la page Coups reporté (7). |
| `course-rating` | Confirmation, choix et saisie manuelle | Porté | Saisie de 6 inchangée ; confirmation, choix d'un tee, choix d'un candidat et relance ajoutés. |
| `course-rating` | Rounds déjà enregistrés | Porté | Le balayage passe du démarrage du web au service `migrate`. |
| `handicap-index` (`add-course-rating`) | Recalcul complet | Porté | La confirmation et le choix d'un tee s'ajoutent aux écritures qui recalculent. |
| `worker-health` | Signal de vie, worker malade quand il se fige | Déjà porté (4) | Recherche bornée dans le temps ajoutée : un SGT muet ne fige pas le worker. |
| — | Retrait d'une rating | Adapté | Requirement de 6 (absent de Next) : le retrait garde la recherche faite, la proposition revient. |
| — | `SGT_LOOKUP` invalide | Ajouté | Next validait la variable dans `src/lib/env.ts` ; ici le démarrage échoue comme pour `AI_MODEL` (8). |

## Capabilities

### New Capabilities

Aucune.

### Modified Capabilities

- `course-ratings` : recherche sur SGT, proposition lue par l'IA, statuts et notes, confirmation, choix d'un tee ou d'un candidat, relance, mise en file des parcours nouveaux et existants, `SGT_LOOKUP` ; « Rating et slope d'un parcours », « Retrait d'une rating », « Page Parcours » et « Champs nommés par parcours » modifiés.
- `handicap-index` : le différentiel utilise la rating confirmée ; la confirmation et le choix d'un tee recalculent l'historique.
- `worker-health` : une recherche SGT est bornée dans le temps et ne fige pas le worker.
- `deployment` : le service `migrate` met en file la recherche des parcours sans ligne ; `SGT_LOOKUP` est lue dans l'environnement du worker.
- `loading-feedback` : attentes des actions de la recherche et recherche en cours.
- `development-seed-data` : ratings de démonstration dans plusieurs états, sans réseau ni job en file.

## Impact

- **Code** : `app/Domain/Courses` (correspondance SGT pure, Actions de mise en file, de fin de recherche, de confirmation, de choix, de relance ; `SaveCourseRating`, `RemoveCourseRating`, `CourseOverview` et `CourseRounds` étendues), `app/Integrations/Sgt` (client HTTP, lecture de la page), `app/Integrations/Ai/SgtScorecardReader` (agent du SDK), job `LookUpCourseRating`, commande `golf:queue-rating-lookups`, `CreateRound` et `UpdateRound` (mise en file), `RoundLeaderboard` (rating nulle), contrôleur `CourseRatingLookupController` et ses routes, page `courses/index` et composants `courses/*`, `config/golf.php`, `AppServiceProvider` (contrôle de `SGT_LOOKUP`), `docker-compose.yml` (commande de `migrate`, variable du worker), `.env.example`, `phpunit.xml`.
- **Base** : une migration qui modifie `course_ratings` (colonnes nouvelles, `rating_t` et `slope` nullables, contraintes). Aucune table nouvelle.
- **Dépendances** : aucune nouvelle (`laravel/ai` vient de 8 ; client HTTP de Laravel).
- **Réseau** : requêtes sortantes du worker seul vers `simulatorgolftour.com` (liste des parcours, au plus une fois par 24 h, et une image de carte par parcours recherché), avec un `User-Agent` qui nomme l'application.
- **Confidentialité** : rien du groupe ne part chez SGT : la liste se télécharge entière et la correspondance se fait sur le serveur ; l'image demandée l'est par son identifiant. Anthropic reçoit seulement l'image publique de SGT, ni nom de joueur, ni score, ni nom de parcours saisi. La page et ses actions restent derrière le code d'accès.
- **Coût** : environ 2 ¢ par parcours lu au modèle par défaut, dans le plafond mensuel de 8.
- **Documents touchés** : `ROADMAP.md` (entrée 9), `docs/deployment.md` (`SGT_LOOKUP`, commande de `migrate`, requêtes sortantes), `ARCHITECTURE.md` (section « Index et ratings »), `docs/adr/` et son index, `CHANGELOG.md` (PR de code).
- **Retour arrière** : redéployer l'image précédente, puis `php artisan migrate:rollback --step=1` : les lignes sans valeur confirmée sont effacées, les ratings confirmées restent. Les jobs de recherche restés en file échouent sur l'ancienne image, sans effet sur les données.
