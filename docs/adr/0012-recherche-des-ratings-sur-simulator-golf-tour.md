# 0012. Recherche des ratings sur Simulator Golf Tour

## Statut

Acceptée, 2026-10-29 (changement OpenSpec `course-rating-sgt`).

## Contexte

L'index d'un joueur dépend de la rating et du slope de chaque parcours et tees qu'il a joués. Depuis l'ADR 0010, ces valeurs se tapent à la main sur `/courses` ; sans elles, le calcul prend le par et le slope 113. Les cartes du simulateur viennent de Simulator Golf Tour (SGT), qui publie la liste de ses parcours (page `/courses`, nom, lieu, par) et une image de la carte de chacun, où la ligne « Slope/Rating » donne les valeurs de chaque tee. L'application Next allait chercher cette image et la faisait lire par l'IA. Le port reprend la recherche, mais elle sort du réseau de la stack vers un site tiers, sans accord explicite de sa part, et chaque lecture coûte de l'argent sur le même plafond mensuel que la lecture des photos (ADR 0011). Une lecture fausse changerait les index de tout le groupe. L'import des données SQLite de Next (entrée 10 de la feuille de route) doit pouvoir copier les lignes de `course_ratings` sans les traduire.

## Alternatives

- **Saisie à la main seule (ADR 0010)** : aucune requête sortante, mais chaque parcours nouveau demande de trouver sa carte et de recopier deux nombres.
- **Table `course_rating_lookups` séparée**, `course_ratings` gardant les seules valeurs confirmées : aucune lecture de l'ADR 0010 ne changerait, mais deux lignes par paire à garder en accord, une jointure de plus sur la page, et un import SQLite qui scinderait chaque ligne de Next.
- **Recherche depuis le web ou au démarrage, comme Next** : le web joindrait SGT pendant une requête de l'utilisateur, et un démarrage relancerait le balayage à chaque processus.
- **Liste SGT gardée dans la mémoire du worker, comme Next** : `--max-time=3600` relance le processus toutes les heures et perdrait la liste.
- **Valeur lue appliquée sans confirmation** : une lecture fausse ou un homonyme au même par changerait les index sans que personne le voie.
- **Coût maximal de la lecture d'une photo (52 000 micro-dollars) pour la carte SGT** : la recherche serait bloquée bien avant le plafond, pour une image plus petite et une réponse plus courte.

## Décision

- **Source SGT, lien The Course View.** La page Parcours montre, pour chaque parcours trouvé, son nom et son lieu SGT, le lien « Carte SGT » vers l'image lue et le lien « The Course View » (slug du nom SGT), ouverts dans un nouvel onglet, pour que chacun vérifie la proposition contre la carte.
- **Le worker seul joint SGT.** Écrire un round (`CreateRound`, `UpdateRound`, import compris) et le service `migrate` (`golf:queue-rating-lookups`, après `golf:recompute-stats`) ne font qu'insérer une ligne `pending` par paire nouvelle et mettre `App\Jobs\LookUpCourseRating` en file après le commit. Le job (2 essais à 30 s, 120 s au plus) lit la liste des parcours, la garde 24 h dans le cache Laravel (le store en base en production), trouve le parcours au même par que le round (`SgtCourseMatcher`, pur, sans `Illuminate`), puis lit l'image de sa carte. Une requête dure 20 s au plus (connexion 10 s) et porte un `User-Agent` qui nomme le dépôt. SGT injoignable ou page illisible : la file réessaie une fois, puis la ligne finit « Recherche échouée ».
- **IA sur une image publique, sous le budget commun.** `App\Integrations\Ai\SgtScorecardReader`, agent du Laravel AI SDK sur le modèle de l'ADR 0011, lit le JPEG de SGT tel que servi (905×527, sans réencodage) et rend les tees lus, contrôlés en PHP (rating 40–90, slope 55–155 entier). Bornes `rating_max_input_tokens` 2 000 et `rating_max_output_tokens` 1 500, celles de Next : `AiBudget::canSpend()` reçoit le coût maximal de cette lecture, 19 000 micro-dollars au modèle par défaut, et le même plafond `AI_MONTHLY_BUDGET_USD` que les photos. L'usage facturé est compté dans `ai_usage` aussitôt après l'appel, même pour une lecture inexploitable. Sans clé ou sans budget, la recherche finit « Saisie manuelle » sans appel.
- **Aucune valeur sans confirmation.** La recherche écrit son issue (statut, parcours SGT, candidats, tees lus, tee proposé, note) sans jamais toucher à `rating_t`, `slope` ni `source`. Seuls « Confirmer », « Retenir » un tee ou la saisie à la main posent des valeurs et recalculent les index. « Retirer la rating » efface les valeurs et garde la recherche : la proposition, les tees lus ou les candidats reviennent. Une relance ne retire pas la valeur confirmée.
- **Schéma de Next gardé.** `course_ratings` gagne les colonnes et les huit statuts de Next (`pending`, `proposed`, `choose_course`, `choose_tee`, `manual`, `not_found`, `failed`, `confirmed`), `rating_t` et `slope` deviennent facultatifs, `source` vaut `sgt` ou `manual`. Une ligne par paire. Les lignes saisies avant la migration deviennent « Confirmé », source `manual`.
- **`SGT_LOOKUP`.** `on` par défaut ; `off` fait finir chaque recherche en « Saisie manuelle », « Recherche SGT désactivée », sans requête. Toute autre valeur fait refuser le démarrage par `AppServiceProvider` (message qui commence par `SGT_LOOKUP`) : `migrate` échoue et `web` et `worker` restent arrêtés, comme pour `AI_MODEL`.
- **Aucun test ne joint SGT ni Anthropic.** `phpunit.xml` pose `SGT_LOOKUP=off` ; les tests de la recherche activent `golf.sgt.lookup` et servent les fixtures copiées de Next (`tests/fixtures/sgt/`) par `Http::fake` ; `Http::preventStrayRequests()` et `SgtScorecardReader::fake()->preventStrayPrompts()` font échouer tout oubli. Règles d'architecture : `App\Domain` n'utilise pas `App\Integrations\Sgt`, que seul `App\Jobs` utilise.

## Écarts relevés pendant l'implémentation

- `{courseRating}` des quatre routes de la recherche n'a pas de `whereUuid()` : un `POST` sur un identifiant qui n'est pas un uuid recevrait 405 de la route de repli, qui ne répond qu'à `GET`. Sans contrainte, le contrôle d'uuid de `HasUuids` rend 404 avant toute requête.
- Une lecture `found` vraie sans aucun tee est inexploitable (« Lecture IA impossible ») : elle finirait sinon en « Tee à choisir » sans tee à retenir.
- La note de la recherche reste en base après une confirmation, mais la page ne la montre pas sur une ligne confirmée.
- Mesure locale du 2026-10-29 (`claude-sonnet-5-5`, clé réelle, une seule lecture, relance de BETHPAGE BLACK / Red sur la base de `make demo`) : le worker a lu la liste SGT (2 752 parcours, gardée en cache) puis `scorecard_30.jpg`, et l'IA a rendu les cinq tees de la carte (Blue 77,5 / 155, White 74,2 / 148, Red 71,4 / 144, Junior 71,0 / 120, PAR3 54,0 / 120), tee Red proposé à 71,4 / 144. Usage : 1 239 tokens d'entrée (62 % de la borne de 2 000) et 128 de sortie (9 % de 1 500), 3 758 micro-dollars. Sous 80 % des deux bornes : `rating_max_input_tokens` et `rating_max_output_tokens` ne changent pas.
- `migrate:rollback` ne défait la migration qu'avec la nouvelle image : l'ancienne n'a pas son fichier et la passe (« Migration not found »). Le retour arrière de `docs/deployment.md` arrête le worker, défait la migration depuis le web encore sur la nouvelle image, puis redéploie l'ancienne.

## Conséquences

- La stack fait des requêtes sortantes vers `simulatorgolftour.com`, depuis le worker seul : une liste par 24 h et une image par parcours nouveau. Si SGT le demande, `SGT_LOOKUP=off` les coupe sans redéployer d'image.
- Un changement de format de la page SGT fait finir les recherches en « Recherche échouée », une carte mal lue en « Saisie manuelle » ; la saisie à la main reste toujours possible.
- Les lectures de cartes SGT et de photos partagent le plafond mensuel : une recherche peut être refusée faute de budget après des imports, et l'inverse.
- Chaque parcours nouveau demande une confirmation sur `/courses` avant de compter dans les index ; après le déploiement de cette version, chaque parcours déjà joué attend la sienne.
- Le retour arrière perd les recherches non confirmées ; le balayage de `migrate` les relance au redéploiement suivant.
