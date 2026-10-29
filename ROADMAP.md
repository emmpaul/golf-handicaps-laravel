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

**Dépend de** : 3. **Statut** : en cours. Le changement est archivé ; restent le staging (groupe 8 des tâches) et la première `/release` `v0.1.0`. Il n'est fait qu'une fois les deux passés.

Image unique sur GHCR, stack docker-compose Dokploy (`web`, `worker`, `migrate`, `pgsql`), sauvegardes, staging. Remplit la couche 12 du squelette.

## 5. `players-and-rounds`

**Dépend de** : 4. **Statut** : fait.

Joueurs, alias, rounds, saisie manuelle, données de démonstration et cibles `migrate` / `fresh` / `demo`.

## 6. `handicap-index`

**Dépend de** : 5. **Statut** : fait.

Calcul WHS, table `player_round_stats`, ratings et slopes saisis à la main.

## 8. `scorecard-ai-import`

**Dépend de** : 5. **Statut** : à proposer.

Photo, job de file, lecture par l'IA, écran de correction, budget mensuel.

## 7. `home-and-match-strokes`

**Dépend de** : 6, 8. **Statut** : à proposer.

Tableau de bord, dont le bloc « Imports à vérifier » qui lit les imports de 8, page des handicaps, coups rendus en match.

## 9. `course-rating-sgt`

**Dépend de** : 6, 8. **Statut** : à proposer.

Recherche des ratings sur Simulator Golf Tour par le worker, confirmation, recalcul.

## 11. `visual-redesign`

**Dépend de** : 6, 7, 8, 9. **Statut** : proposé.

Refonte visuelle selon le design « Golf handicap app redesign » du mainteneur : thème sombre, Geist et Geist Mono, barre d'onglets flottante et bouton Importer, toutes les pages recomposées, animations du design. Remplace la décision 7 de `docs/PORT_TO_LARAVEL.md` pour le thème, les tokens et recharts, et une partie de l'ADR 0006.

## 10. `sqlite-import-command`

**Dépend de** : 9, 11. **Statut** : à proposer.

Commande `golf:import-sqlite`, puis cutover.

## Définition de terminé

1. spec à jour, critères vérifiables, ambiguïtés résolues avant l'implémentation ;
2. ADR créée ou mise à jour si une décision d'architecture est prise ;
3. tests comportementaux écrits avant le code ;
4. accès par code vérifié pour toute nouvelle page ; confidentialité respectée pour toute sortie ;
5. `make check` vert ;
6. documentation à jour, changement archivé, aucun code hors périmètre ;
7. tout modèle ajouté a sa fabrique, ses états et son seeder de démonstration.
