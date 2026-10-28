# Golf Handicaps

Ces règles priment sur les consignes génériques de Boost.

## Sources de vérité

- Lire `PRD.md`, `ARCHITECTURE.md`, `DESIGN.md`, `ROADMAP.md` et `docs/PORT_TO_LARAVEL.md` avant de planifier un changement.
- L'application est le port de l'application Next.js `golf-handicaps`. Ses specs OpenSpec sont la référence de comportement, sauf ce que `docs/PORT_TO_LARAVEL.md` retire.

## OpenSpec

- Chaque changement, correctif ou mise à jour d'outillage compris, passe par un changement OpenSpec.
- La CLI est épinglée dans `package.json` : `vendor/bin/sail pnpm exec openspec …` (ou `make openspec ARGS="…"` une fois le Makefile en place). Jamais d'installation globale.
- Une règle métier non tranchée, une tâche ambiguë ou un conflit avec `design.md` arrête l'apply jusqu'à la décision du mainteneur.

## ADR

- Une décision d'architecture s'écrit en ADR numérotée sous `docs/adr/`, dans le même lot que le code qu'elle décide, avec sa ligne dans `docs/adr/README.md`.

## Tests

- TDD : RED, GREEN, REFACTOR. Un test mérite sa place s'il devient rouge quand le code de production est faux. Nommer la mutation qui le fait échouer.
- Valeurs attendues en littéraux tirés de la spec, jamais recalculées par le code testé. Comparaisons par valeur.
- Aucun test navigateur ou E2E versionné.

## Makefile

- Le Makefile est l'interface unique, en local et en CI. Tests visés : `make test-backend FILES=…` ou `FILTER=…`. Après un `make check` rouge, lire `reports/check/<étape>.log`.
- Le Makefile arrive avec le changement `project-skeleton-and-quality-gates`. D'ici là, passer par `vendor/bin/sail`.

## Données

- Tout nouveau modèle arrive avec sa fabrique, ses états et son seeder de démonstration.
- Identifiants UUID partout (`HasUuids`, `foreignUuid`).

## Accès

- Un code d'accès partagé et une session protègent l'application. Pas de comptes, pas de rôles, pas de Fortify.

## Heures et fuseau

- `app.timezone` reste `UTC`. Chaque instant envoyé à une page passe par la classe de conversion vers `app.display_timezone` (`America/Montreal`).

## PNPM

- PNPM seulement, dans le conteneur : `vendor/bin/sail pnpm …`. Jamais npm.

## Messages de commit

- Conventional Commits : `type(scope): sujet`, sujet sans majuscule initiale ni point final. Scope facultatif, pris dans la liste fermée de `commitlint.config.js`.
- Corps : 100 caractères au plus par ligne, coupés à la main. C'est la première cause de rejet par le hook.
- Branches `<type>/<change>`, créées avec `git switch -c <nom> --no-track`. Jamais `--no-verify`.

## Langue

- Prose en français : interface, documentation, artefacts OpenSpec, ADR. Identifiants de code en anglais.

## Interface

- Le port reprend le thème scoreboard de l'application Next.js : tokens clairs et sombres, primitives possédées par le projet dans `components/ui`.

## Boost et fichiers générés

- `AGENTS.md` est généré par `vendor/bin/sail artisan boost:update` depuis `.ai/guidelines/`. Ne jamais le modifier à la main. Pas de `CLAUDE.md` dans le dépôt.

## Passe navigateur

- En fin d'apply, après `make check` : `make up`, `make dev`, `make browser`, puis le skill `browser-use` sur `http://localhost`. Lire la console, rapporter ce qui a été vu, finir par `make dev-stop`.
