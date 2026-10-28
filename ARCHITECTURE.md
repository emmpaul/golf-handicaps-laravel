# Architecture

Monolithe Laravel 13 + Inertia 3 + React 19, PostgreSQL 18, monté selon le squelette Laravel du mainteneur. Chaque décision d'architecture a son ADR sous `docs/adr/` (index dans `docs/adr/README.md`) ; les écarts au squelette sont dans l'ADR 0005.

## Structure (ADR 0001)

- Code métier sous `app/Domain/<Domaine>`, indépendant de HTTP, d'Inertia et des intégrations :
    - `Actions/` : une classe par écriture, méthode `handle()`, qui ouvre sa transaction ;
    - `Queries/` : une classe par lecture complexe ;
    - `Exceptions/` : sous-classes de `App\Domain\DomainException`, avec un message pour la personne qui utilise l'application. Elles sont rendues au même endroit pour toute l'application, dans `bootstrap/app.php`. La classe et son rendu arrivent avec la première Action qui lève une erreur métier.
- Un dossier de domaine naît avec son premier code, jamais vide d'avance.
- Intégrations tierces (IA, Simulator Golf Tour) sous `app/Integrations`, sans dépendance vers `App\Http` ni Inertia ; le domaine ne les appelle pas.
- Contrôleurs minces : Form Request, puis une Action ou une Query, puis la réponse.
- Les frontières sont des règles d'architecture Pest (`tests/Arch/ArchitectureTest.php`, `make arch-test`).

## Données (ADR 0002)

- PostgreSQL partout : local, tests (base `testing`) et CI.
- Chaque modèle de `App\Models` utilise `HasUuids` (UUID v7) ; chaque clé étrangère applicative est un `foreignUuid` avec contrainte. Les tables techniques du framework gardent leurs identifiants.
- Aucune table de comptes. Sessions, cache et files en base, sans Redis. Un worker de file tourne dès le départ : l'import IA et la recherche SGT l'exigent.

## Heures et fuseau (ADR 0004)

- `app.timezone` reste `UTC`. Chaque instant envoyé à une page passe par `App\Domain\LocalTime`, qui le convertit vers `app.display_timezone` (`America/Montreal`).
- Les bornes d'un jour se calculent en heure locale (`LocalTime::startOfDay`) puis se ramènent en UTC.

## Accès

- Code d'accès partagé et session, sans comptes ni rôles (`app-access`). `GET /api/version` reste lisible sans session ni code.

## Outillage (ADR 0003)

- Chaque changement passe par OpenSpec, en TDD.
- Le Makefile est l'interface unique, en local et en CI : `make` liste les cibles, `make check` lance tous les contrôles.
- Version tenue à la main dans `version.txt`, changements notés dans `CHANGELOG.md` par chaque pull request de code (ADR 0005).
