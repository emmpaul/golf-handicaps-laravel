# Tasks

Changement de documentation seul : aucun test applicatif à écrire, aucun code. Les étapes de la procédure sont faites plus tard par le mainteneur (« Workflow follow-up »).

## 1. Procédure

- [x] 1.1 Réécrire la section « Bascule depuis Next » de `docs/deployment.md` (titre gardé, cité par le message « Base en WAL ») : phase parallèle sur `golf2.emmanuelpaul.com` (installation, `SGT_LOOKUP=off`, copie, import, effacement, comparaison, copie rafraîchie), déménagement (D4), retour arrière, « Reprendre après un arrêt » ; chaque étape marquée « Mainteneur », une commande ou un petit bloc avec ce qu'il faut lire. Vérifié par : `grep -c 'Mainteneur' docs/deployment.md` compte les 14 étapes et « Reprendre après un arrêt » (15), aucune mention du staging dans la section, et `make lint` vert.
- [x] 1.2 Mettre à jour `docs/PORT_TO_LARAVEL.md` (décision 12 et section Cutover) pour la phase parallèle. Vérifié par : `grep -n 'golf2' docs/PORT_TO_LARAVEL.md` montre la décision 12 et la section Cutover.

## 2. Décisions et suivi

- [x] 2.1 Mettre à jour l'ADR 0014 (statut « Modifiée », conséquence sur le cutover) et son statut dans `docs/adr/README.md`. Vérifié par : `grep -n 'parallel-run-cutover' docs/adr/0014-import-unique-de-la-base-sqlite-de-next.md` montre le statut et la conséquence, `grep -n '0014' docs/adr/README.md` montre « Acceptée, modifiée ».
- [x] 2.2 Mettre à jour `ROADMAP.md` (entrée 10 : tâches du mainteneur, déployer sur `golf2`, comparer, déménager) et ajouter une ligne en anglais sous `[Unreleased]` de `CHANGELOG.md`. Vérifié par : `make lint` vert.

## 3. Vérification

- [x] 3.1 Valider le change. Vérifié par : `make openspec ARGS="validate parallel-run-cutover --strict"` vert.

## Workflow follow-up

- Archiver le change (`openspec archive parallel-run-cutover --yes`), vérifier qu'aucun en-tête de delta ne reste dans `openspec/specs` et qu'aucune exigence ne dépasse 500 caractères, puis `make openspec ARGS="validate --all --strict"` et `make lint`.
- Mainteneur : installer la stack Laravel sur `golf2.emmanuelpaul.com`, importer, comparer, réimporter au besoin, puis déménager (`docs/deployment.md`, « Bascule depuis Next »).
