# Feuille de route

Ordre des changements OpenSpec du port, de haut en bas. Le numéro d'une entrée l'identifie dans les autres documents et ne donne pas son rang : 8 passe avant 7, et 11 avant 10. Le détail de chacun et les décisions qui le cadrent sont dans `docs/PORT_TO_LARAVEL.md`. Chaque changement donne deux pull requests : le code, puis l'archivage.

## 0. Bootstrap (hors OpenSpec)

**Statut** : fait. Étapes 1 à 5 du squelette : projet Laravel, Sail (`laravel.test` + `pgsql`), OpenSpec épinglé, Boost, commitlint et hook `commit-msg`.

## 1. `project-skeleton-and-quality-gates`

**Dépend de** : 0. **Statut** : fait.

Makefile, Pint, Larastan 8, Pest sur PostgreSQL, Vitest, jscpd, tests d'architecture, UUID, fuseau d'affichage `America/Montreal`, CI GitHub Actions, route de version. Version tenue à la main dans `version.txt` et `CHANGELOG.md`, tags `vX.Y.Z` posés par le mainteneur : pas de release-please (ADR 0005).

**Terminé lorsque** : `make check` est vert en local et en CI, et les capacités `quality-gates`, `local-development-environment`, `persistence-identifiers`, `date-time-presentation`, `app-version` et `ci-pipeline` sont spécifiées.

## 2. `visual-theme-port`

**Dépend de** : 1. **Statut** : fait.

Thème scoreboard clair et sombre, polices, primitives `components/ui`, shell de l'application, retours de chargement.

## 3. `app-access`

**Dépend de** : 2. **Statut** : fait.

Code d'accès partagé, `/login`, déconnexion, session chiffrée de 90 jours, limiteur, en-têtes de sécurité, `TRUSTED_PROXIES`.

`/api/version` reste hors du middleware du code d'accès : la route se lit sans session ni code (capacité `app-version`).

## 4. `production-deployment`

**Dépend de** : 3. **Statut** : en cours. Le changement est archivé. `v0.1.0` est publiée et tourne sur `golf2.emmanuelpaul.com`, qui tient lieu de staging : installation, `/api/version`, HSTS, témoin `Secure` et limiteur vérifiés le 2026-10-29. Restent l'essai de restauration (tâche 8.3) et l'essai du retour à l'image précédente à `v0.1.1`.

Image unique sur GHCR, stack docker-compose Dokploy (`web`, `worker`, `migrate`, `pgsql`), sauvegardes, staging. Remplit la couche 12 du squelette.

## 5. `players-and-rounds`

**Dépend de** : 4. **Statut** : fait.

Joueurs, alias, rounds, saisie manuelle, données de démonstration et cibles `migrate` / `fresh` / `demo`.

## 6. `handicap-index`

**Dépend de** : 5. **Statut** : fait.

Calcul WHS, table `player_round_stats`, ratings et slopes saisis à la main.

## 8. `scorecard-ai-import`

**Dépend de** : 5. **Statut** : fait. Le changement est archivé ; reste au mainteneur la passe sur le staging avec une clé d'API (tâche 9.3).

Photo, job de file, lecture par l'IA, écran de correction, budget mensuel.

## 7. `home-and-match-strokes`

**Dépend de** : 6, 8. **Statut** : fait. Le changement est archivé ; reste au mainteneur la visite de `/`, `/handicaps` et `/strokes` sur le staging après le déploiement.

Tableau de bord, dont le bloc « Imports à vérifier » qui lit les imports de 8, page des handicaps, coups rendus en match.

## 9. `course-rating-sgt`

**Dépend de** : 6, 8. **Statut** : fait. Le changement est archivé ; reste au mainteneur, après le déploiement sur le staging, la lecture de « N recherches de rating mises en file. » dans le log de `migrate` et la confirmation des propositions sur `/courses`.

Recherche des ratings sur Simulator Golf Tour par le worker, confirmation, recalcul.

## 11. `visual-redesign`

**Dépend de** : 6, 7, 8, 9. **Statut** : fait. Le changement est archivé ; reste au mainteneur la relecture des captures locales de `reports/captures/visual-redesign/`, la vérification des zones sûres sur un vrai iPhone (scénarios sans preuve automatique) et l'issue #28 (polices en `make dev`).

Refonte visuelle selon le design « Golf handicap app redesign » du mainteneur : thème sombre, Geist et Geist Mono, barre d'onglets flottante et bouton Importer, toutes les pages recomposées, animations du design. Remplace la décision 7 de `docs/PORT_TO_LARAVEL.md` pour le thème et les tokens, et une partie de l'ADR 0006.

## 10. `sqlite-import-command`

**Dépend de** : 9, 11. **Statut** : fait. Le changement est archivé. Pas de bascule franche (changement `parallel-run-cutover`) ; restent au mainteneur, dans l'ordre (`docs/deployment.md`, « Bascule depuis Next ») :

- déployer la stack Laravel sur `golf2.emmanuelpaul.com`, avec `SGT_LOOKUP=off`, et y importer une copie de la base Next, Next restant en production : fait le 2026-10-29 ;
- comparer, pour chaque joueur, l'index et les rounds (liste, scores, différentiels) entre Next et `golf2`, en réimportant une copie fraîche au besoin : comparaison bonne le 2026-10-29 ;
- déménager : arrêter les saisies sur Next, dernier import, domaine principal passé à la stack Laravel, `golf2` retiré, Next gardé arrêté 30 jours : fait le 2026-10-29, sans dernier import (Next et la stack Laravel avaient les mêmes comptes après l'arrêt de Next). Next et son volume `golf-data` restent arrêtés jusqu'au 2026-11-28, puis sont supprimés.

Commande `golf:import-sqlite`, puis cutover, fait à la main par le mainteneur.

## 12. `reduce-mobile-padding`

**Dépend de** : 11. **Statut** : fait. Le changement est archivé ; reste au mainteneur, après le déploiement, la vérification sur un vrai iPhone des scénarios « en application d'écran d'accueil » (rangée du haut à 59 px, sans preuve automatique).

Marges resserrées sur téléphone, suite de la vérification des zones sûres sur iPhone laissée par 11 : la rangée « Golf » commence au bas de la zone sûre sans les 10 px ajoutés (`max(54px, safe-top)`), et les marges latérales du contenu passent de 20 à 16 px, alignées sur la barre flottante.

## 13. `index-computation-page`

**Dépend de** : 6, 9 et 11. **Statut** : fait. Le changement est archivé ; reste au mainteneur le trou plafonné de démonstration de Manu, pas vu dans le navigateur parce que la base de développement avait été remplie avant le changement du seeder : lancer `make demo`, puis ouvrir `/handicaps/calcul` sur Manu.

Page « Calcul de l'index » (`/handicaps/calcul`), dessinée dans le design « Golf handicap app redesign » du mainteneur : le calcul déroulé en cinq étapes (score ajusté, différentiel, différentiels retenus, ajustement, index) avec les rounds du joueur choisi, puis les cas particuliers. Le bloc replié « Comment l’index est calculé » des pages Joueurs, Handicaps, Parcours et d'un round devient un lien vers cette page.

## 14. `optimize-performance`

**Dépend de** : 4 et 11. **Statut** : en cours. `Server-Timing`, `make perf-probe`, polices en WOFF2 latin seul, préchargement des onglets avec vidage après écriture, `/favicon.ico` statique et mode worker sont faits et mesurés sur la stack locale (médiane de `app` sur `/rounds` : 15,1 ms avant, 6,4 ms après). Restent :

- le préchargement des liens de liste (tâche 4.2), en attente d'une décision du mainteneur : avec Inertia 3.9, `prefetch={['hover', 'click']}` ne part pas au toucher ;
- au mainteneur, la mesure « avant » en production (tâche 2.5) avant tout déploiement, puis, après le déploiement par Dokploy : la mesure « après » et la passe navigateur en production dans `docs/performance.md`, la vérification à deux navigateurs et la mémoire de `web` dans `docker stats`, l'arrêt de Cloudflare Web Analytics avec sa vérification, le signalement à `laravel/vite-plugin` des règles WOFF qui l'emportent sur les WOFF2, puis l'archivage.

Mesure reproductible des pages, en-tête `Server-Timing`, polices chargées une seule fois, pages préchargées, `/favicon.ico` hors du routeur, et FrankenPHP en mode worker par Laravel Octane (ADR 0015).

## 15. `offline-pwa`

**Dépend de** : 8, 11 et 14. **Statut** : en cours. Tout le code est écrit et testé : envoi sans doublon côté serveur, file des photos, service worker, écran `/offline.html`, écran Importer, bandeau « Hors ligne », messages des pages sans copie et des écritures. `make image image-smoke` passe (tâche 4.4 cochée). Restent au mainteneur :

- la passe navigateur (tâche 6.3), sautée à ce lot sur décision du mainteneur : les ports 80 et 5433 étaient pris par un autre projet local ;
- après le déploiement par Dokploy, sur un vrai iPhone avec l'application installée : prendre une photo en mode avion, fermer l'application, redémarrer le téléphone, rouvrir avec du réseau et vérifier l'import ; vérifier le bandeau et l'écran « Hors ligne » ;
- puis l'archivage.

Application utilisable sans réseau, que `polish-mobile-ui` avait écarté côté Next : un service worker écrit à la main garde les pages visitées et les relit hors ligne avec un bandeau « Hors ligne », les photos de cartes restent sur le téléphone jusqu'au retour du réseau, un envoi rejoué ne crée pas de second import (`imports.upload_key`), et la déconnexion efface les pages gardées (ADR 0016).

## 16. `courses-tab-replaces-strokes`

**Dépend de** : 7, 9 et 11. **Statut** : en cours.

L'onglet « Parcours » (`/courses`) remplace « Coups » dans la barre flottante ; la page Coups reste à `/strokes`, devient une sous-page de Handicaps ouverte par le lien « Coups à donner », et la page Rounds perd son lien « Parcours ».

## Définition de terminé

1. spec à jour, critères vérifiables, ambiguïtés résolues avant l'implémentation ;
2. ADR créée ou mise à jour si une décision d'architecture est prise ;
3. tests comportementaux écrits avant le code ;
4. accès par code vérifié pour toute nouvelle page ; confidentialité respectée pour toute sortie ;
5. `make check` vert ;
6. documentation à jour, changement archivé, aucun code hors périmètre ;
7. tout modèle ajouté a sa fabrique, ses états et son seeder de démonstration.
