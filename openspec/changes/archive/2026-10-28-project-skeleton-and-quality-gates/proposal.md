# Proposal

## Why

Le bootstrap (entrée 0 de `ROADMAP.md`, fait) a livré un projet Laravel qui tourne sous Sail, mais sans interface de commandes, sans contrôle de qualité automatisé ni CI : `AGENTS.md` prescrit déjà `make check`, `make test-backend` ou `make browser`, qui n'existent pas. Aucun changement métier ne peut se livrer proprement tant que ces garde-fous, les identifiants UUID et la règle du fuseau d'affichage ne sont pas posés. C'est l'entrée 1 de `ROADMAP.md` ; sa seule dépendance, le bootstrap, est terminée.

## What Changes

- **Makefile** à la racine, interface unique en local et en CI (couche 5 du squelette) : `help`, `setup`, `up`, `down`, `dev`, `dev-stop`, `browser`, `build`, `migrate`, `openspec`, `lint`, `format`, `analyse`, `duplicates`, `arch-test`, `test-backend` (`FILES`, `FILTER`, `SHARD`, `JUNIT`), `test-frontend`, `test`, `update-shards`, `check`, `commitlint`, `changed-code`, `wayfinder`. Les cibles `fresh` et `demo` arrivent avec `players-and-rounds`.
- **Contrôles de qualité** : Pint, Larastan porté du niveau 7 du squelette au niveau 8 pour vérifier les types nullables, jscpd seuil 0, Pest en parallèle sur PostgreSQL (Paratest), suite `Arch`, Vitest (`vp test`) sous `TZ=America/Montreal` avec un premier test qui vérifie ce fuseau, `TestCase::seed()` avec `ANALYZE`.
- **Tests d'architecture** de départ (preset php, helpers de débogage, UUID des modèles, couches du domaine et des intégrations) et **test de garde** d'`AGENTS.md` contre les prescriptions qui contournent le Makefile.
- **Identifiants UUID** : règle d'architecture `HasUuids` sur chaque modèle de `App\Models`. **Suppression des comptes du kit** : table `users`, table `password_reset_tokens`, colonne `sessions.user_id`, `App\Models\User`, `UserFactory`, garde `web` sur le provider Eloquent.
- **Fuseau d'affichage** : `app.timezone` reste `UTC`, nouvelle clé `app.display_timezone` = `America/Montreal`, une seule classe de conversion `App\Domain\LocalTime`.
- **Version de l'application** : fichier `version.txt` à la racine, tenu à la main, et route `GET /api/version` qui renvoie `{ "version", "commit" }` avec `Cache-Control: no-store`, sans session ni base.
- **`CHANGELOG.md`** tenu à la main, en français : chaque pull request de code ajoute ses lignes sous « Non publié ». Le mainteneur pose les tags `vX.Y.Z` à la main. Pas de release-please. La règle entre dans les consignes d'agent.
- **CI GitHub Actions** (couche 6, sans matrice) : jobs `changes`, `static`, `test-frontend`, `test-backend`, `report`, action composite `setup`, workflow zizmor, Dependabot pour les actions.
- **Composer** : `@php artisan boost:update --ansi` ajouté à `post-update-cmd` ; dépendances de développement `brianium/paratest`, `jscpd`, `jsdom`.
- **ADR 0001 à 0005** : monolithe modulaire ; PostgreSQL et UUID ; workflow OpenSpec + TDD + Makefile ; stockage UTC et fuseau d'affichage ; écarts au squelette de `docs/PORT_TO_LARAVEL.md`, suppression des comptes et versionnement manuel compris.
- Consignes d'agent (`.ai/guidelines/golf-handicaps.md`), `ARCHITECTURE.md` et `docs/PORT_TO_LARAVEL.md` (défaut « Versionnement ») mis à jour, puis `AGENTS.md` régénéré par `boost:update`.

### Hors périmètre

- Playwright, Pest Browser, Dusk et tout test navigateur versionné (décision 8 du port).
- release-please, PR de release automatique, publication de release GitHub.
- Ligne de version dans l'interface : elle demande le shell de l'application (`visual-theme-port`).
- Image Docker, label OCI `org.opencontainers.image.revision`, publication GHCR et test de fumée compose : `production-deployment`.
- Cibles `fresh` et `demo`, `DemoSeeder`, test de découverte des fabriques, règle d'architecture des seeders : `players-and-rounds`.
- Thème, polices `fontsource`, primitives `components/ui`, mode sombre : `visual-theme-port`.
- Code d'accès, en-têtes de sécurité, `TRUSTED_PROXIES` : `app-access`.
- Classes `Action`, `Query` et `DomainException` avec son rendu : les conventions sont fixées ici (ADR 0001, `ARCHITECTURE.md`, règles d'architecture), le code arrive avec la première Action métier (`players-and-rounds`).
- Matrice de partitions de la CI, `tests/.pest/shards.json` : quand `test-backend` dépassera 5 minutes en CI.
- Service worker de file en développement : il arrive avec le premier job (`scorecard-ai-import`).
- Skill d'agent `babysit-pr` (étape 11 du squelette).
- Test de frontière d'adresse `/admin` (couches 2.5 et 4) et `skipUnlessFortifyHas` : sans objet, l'application n'a ni comptes ni Fortify.

## Capabilities

### New Capabilities

- `quality-gates` : cibles de contrôle, non-mutation, `make check` en une passe, tests visés, partitions, tests d'architecture, garde des consignes d'agent, messages de commit, classement documentation ou code.
- `local-development-environment` : installation sur un clone neuf, Sail avec PostgreSQL, base de test, Vite avec HMR, arrêt propre, navigateur jetable pour la passe de fin d'apply.
- `persistence-identifiers` : clés primaires et étrangères applicatives en UUID v7, tables techniques exclues, aucune table de comptes.
- `date-time-presentation` : stockage en UTC, conversion de chaque instant affiché vers `America/Montreal`, bornes de jour.
- `app-version` : version tenue à la main, route de version lisible sans session, journal des changements (la ligne dans l'interface viendra avec `visual-theme-port`).
- `ci-pipeline` : contrôles lancés selon les fichiers touchés, rapport de tests, annulation des exécutions périmées, audit des workflows, mises à jour des actions.

### Modified Capabilities

Aucune : `openspec/specs/` est vide.

## Décisions du mainteneur (2026-10-27)

Les règles laissées ouvertes par le brief ont été posées au mainteneur et tranchées avant l'apply :

1. **Comptes du kit** : tout supprimer. Plus de table `users` ni `password_reset_tokens`, plus de colonne `sessions.user_id`, plus de `User` ni de `UserFactory`.
2. **Méthode refusée sur la route de version** : 405, le comportement natif de Laravel, au lieu du 401 de la référence Next.
3. **Releases** : pas de release-please. Tags `vX.Y.Z` posés à la main, `CHANGELOG.md` tenu par les agents dans chaque pull request de code.
4. **Source de la version** : `version.txt` à la racine, `0.0.0` jusqu'à la première release, puis `0.1.0`.
5. **Fichiers de version** : `version.txt` compte comme documentation pour `changed-code` ; `CHANGELOG.md` l'est déjà (`.md` à la racine). Une PR de version seule saute les tests.
6. **Étape 11 du squelette** : ce changement reprend le test de garde d'`AGENTS.md`, `make browser` et `make dev-stop`. Le skill `babysit-pr` est laissé de côté.
7. **Premier test Vitest** : un test qui vérifie le fuseau fixe, plutôt que `passWithNoTests`.
8. **Niveau de Larastan** : 8 au lieu du 7 du squelette, pour que le scénario « Erreur de type PHP » (une méthode déclarée `string` qui renvoie `?string`) fasse échouer `make analyse`.

## Impact

- Fichiers nouveaux : `Makefile`, `.jscpd.json`, `version.txt`, `CHANGELOG.md`, `.github/` (workflows `ci.yml` et `zizmor.yml`, action `setup`, `dependabot.yml`, `zizmor.yml`), `routes/api.php`, `app/Domain/LocalTime.php`, `tests/Arch/`, tests Feature et Unit, premier test Vitest, `docs/adr/0001` à `0005`.
- Fichiers supprimés : `app/Models/User.php`, `database/factories/UserFactory.php`, `resources/js/types/auth.ts` s'il ne sert plus.
- Fichiers modifiés : `phpstan.neon`, `composer.json`, `composer.lock`, `package.json`, `pnpm-lock.yaml`, `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php`, `vite.config.ts`, `config/app.php`, `config/auth.php`, `bootstrap/app.php`, migration initiale des utilisateurs, `database/seeders/DatabaseSeeder.php`, `.env.example`, `.ai/guidelines/golf-handicaps.md`, `AGENTS.md` (généré), `ARCHITECTURE.md`, `docs/PORT_TO_LARAVEL.md`, `docs/adr/README.md`, `ROADMAP.md`.
- API : nouvelle route publique `GET /api/version`, sans donnée du groupe.
- Domaines : aucun domaine métier ; `App\Domain` naît avec `LocalTime`.
- Vie privée : la route de version ne révèle que la version et le sha du commit, déjà publics sur GitHub. La suppression de `sessions.user_id` retire le seul lien entre une session et une personne.
- Base : la migration initiale est réécrite en place, ce qui reste permis tant qu'aucune donnée réelle n'existe. En local, `make migrate` ne suffit pas : `migrate:fresh` une fois.
- Retour arrière : revenir sur le commit de fusion. Aucune donnée réelle n'est en jeu ; une base locale se recrée par `migrate:fresh`.
