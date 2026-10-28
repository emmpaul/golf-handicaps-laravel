# 0003. OpenSpec, TDD et Makefile

## Statut

Acceptée, 2026-10-27 (changement OpenSpec `project-skeleton-and-quality-gates`).

## Contexte

Le port est écrit en grande partie par des agents, session après session. Sans cadre, chaque session invente ses commandes, ses tests et sa portée : la relecture de sessions antérieures a compté des dizaines d'appels directs à Pest et à `vp` qui recopiaient à la main les variables d'une cible, et des tests qui passaient quel que soit le code. Le mainteneur relit le résultat et doit retrouver, pour chaque comportement, la règle décidée et la preuve qu'elle tient (couches 4, 5 et 8 du squelette).

## Alternatives

- **Travail sans spécification** : rapide pour un petit correctif, mais aucune trace des critères d'acceptation pour la session suivante ni pour la personne qui relit.
- **Tests écrits après le code** : ils décrivent ce que le code fait, pas ce qu'il doit faire, et passent souvent même quand le code est faux.
- **Scripts Composer ou `package.json`** : deux interfaces concurrentes, chacune avec sa façon d'appeler Sail.
- **Taskfile ou Just** : un outil de plus à installer, sans gain sur `make`, déjà présent partout.

## Décision

- **OpenSpec pour chaque changement**, correctif et outillage compris : proposition, deltas de specs avec scénarios, design, tâches. La CLI est épinglée dans `package.json` et s'appelle par `make openspec ARGS="…"`. Une règle métier non tranchée, une tâche ambiguë ou un conflit avec `design.md` arrête l'apply jusqu'à la décision du mainteneur. Le code passe par une pull request, l'archivage par une seconde pull request de documentation après la fusion.
- **TDD orienté comportement** : RED, GREEN, REFACTOR. Un test mérite sa place s'il devient rouge quand le code de production est faux ; la mutation qui le fait échouer est nommée et observée. Valeurs attendues en littéraux tirés de la spec. Aucun test navigateur ou E2E versionné, aucun quota de couverture.
- **Makefile, interface unique** en local et en CI. Une cible lance ses commandes dans Sail sur le poste et directement sur le runner quand `CI=true`. Les cibles de contrôle ne modifient aucun fichier suivi. `make check` lance `lint`, `analyse`, `duplicates`, `test-backend` et `test-frontend` jusqu'au bout et écrit le log de chaque étape dans `reports/check/<étape>.log`. Les tests visés passent par `FILES=` ou `FILTER=`.
- **Consignes d'agent** : `AGENTS.md` est généré depuis `.ai/guidelines/` par `boost:update`. `tests/Feature/AgentGuidelinesTest.php` échoue si `AGENTS.md` prescrit une commande qui contourne le Makefile.

## Conséquences

- Chaque changement coûte une proposition, même petit. En échange, chaque décision et chaque critère d'acceptation ont une trace vérifiable.
- Une commande s'écrit une fois, dans le Makefile ; la CI ne la réécrit pas.
- Un contrôle rouge se corrige dans le code. Relever un seuil ou désactiver une règle demande une décision écrite.
- Après une mise à jour de Boost, le test de garde attrape une prescription réintroduite, pas une nouvelle consigne utile : le diff d'`AGENTS.md` se relit.
