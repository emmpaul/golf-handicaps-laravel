# Architecture (brouillon)

Monolithe Laravel 13 + Inertia 3 + React 19, PostgreSQL 18, UUID partout, monté selon le squelette Laravel du mainteneur. Les écarts au squelette sont listés dans `docs/PORT_TO_LARAVEL.md` et deviendront des ADR sous `docs/adr/`.

- Code métier sous `app/Domain/<Domaine>` (Actions, Queries, exceptions), indépendant de HTTP et du frontend.
- Intégrations tierces (IA, Simulator Golf Tour) sous `app/Integrations`.
- File en base et un worker dès le départ : l'import IA et la recherche SGT l'exigent.
- Accès par code partagé et session, sans comptes.
- Makefile comme interface unique, en local et en CI.

À compléter par le changement `project-skeleton-and-quality-gates`.
