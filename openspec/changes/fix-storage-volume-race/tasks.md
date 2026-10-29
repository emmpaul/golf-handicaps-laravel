# Tasks

Avant de commencer : relire l'issue #14, `proposal.md`, `design.md` (D1 à D4 et les décisions par défaut du 2026-10-28) et le delta de la spec `deployment`. Les commandes Docker de l'hôte passent par `make image` et `make image-smoke` ; seuls les contrôles de 1.1 et 2.2 appellent `docker compose` en direct, sur le projet de fumée (`-p golf-handicaps-smoke`, fichiers `docker-compose.yml` et `docker-compose.smoke.yml`, `--env-file docker/smoke.env`), et finissent par `down -v`. Noter chaque commande et sa sortie dans le compte rendu de l'apply.

## 1. Constat avant le correctif

- [ ] 1.1 `make image`, puis tenter de reproduire la course : vingt fois de suite, `docker compose … create` de toute la stack de fumée puis `down -v`, en arrêtant la boucle au premier `file exists` ; vérifier et noter le nombre d'essais et si l'erreur est apparue (une course absente en local ne bloque pas la suite, design D4)
- [ ] 1.2 Sur la stack de fumée sans correctif, faire le contrôle déterministe de D4 : `create migrate` seul, puis `docker run --rm --entrypoint sh -v golf-handicaps-smoke_storage:/mnt <image postgres de la stack> -c 'ls -lan /mnt'`, puis `down -v` ; vérifier que le volume est absent ou vide : c'est la mutation « volume retiré de `migrate` » vue rouge avant le code

## 2. Volume `storage` monté par `migrate`

- [ ] 2.1 Ajouter `storage:/app/storage/app` aux volumes de `migrate` dans `docker-compose.yml`, en lecture et écriture, avec le commentaire de D1 (remplit le volume avant la création de `web` et `worker`, issue #14) ; vérifier que `docker compose -f docker-compose.yml -f docker-compose.smoke.yml --env-file docker/smoke.env config` liste le volume sur `migrate`, `web` et `worker`
- [ ] 2.2 Refaire le contrôle déterministe de 1.2 avec le correctif ; vérifier que le listing montre `.gitignore`, `private` et `public` à l'uid 1000, puis `down -v` ; mutation nommée : la ligne retirée de `migrate` redonne le listing vide de 1.2
- [ ] 2.3 Scénario « Redéploiement sur un volume de stockage existant » : stack de fumée lancée à la main (`up --wait`, sans `down`), écrire `public/keep.txt` depuis `worker` et retirer le `.gitignore` de la racine du volume, puis `up --wait --force-recreate` ; vérifier que `web` lit `public/keep.txt` avec le même contenu et que le `.gitignore` de la racine n'est pas revenu, puis `down -v`
- [ ] 2.4 Scénario « Lancement sur un volume de stockage neuf » : lancer `make image-smoke` cinq fois de suite (`for i in 1 2 3 4 5; do make image-smoke || break; done`) ; vérifier cinq sorties en 0, chacune avec `GET /up : 200` et `GET /login : 200` ; sur une sixième stack lancée à la main, vérifier que `web` et `worker` écrivent chacun un fichier dans `storage/app/public` et `storage/app/private`, puis `down -v`
- [ ] 2.5 Mettre à jour `docs/deployment.md` : la phrase « Ils partagent le volume `storage` » devient « `migrate`, `web` et `worker` montent le volume `storage` », avec la raison en une phrase (remplir un volume neuf une seule fois, avant la création de `web` et `worker`) ; ajouter le contrôle déterministe de 2.2 (trois commandes) à refaire après une mise à jour majeure de Docker ou de Compose sur le serveur ; mettre à jour la ligne `volumes:` de la topologie dans `docs/PORT_TO_LARAVEL.md` (`storage (photos, monté sur migrate, web et worker)`) ; ajouter sous `### Fixed` de `## [Unreleased]` dans `CHANGELOG.md` une ligne en anglais sur la course à la création du volume `storage` ; vérifier que les commandes copiées depuis la doc donnent le listing de 2.2

## 3. Vérification finale

- [ ] 3.1 Lancer `make check` et le voir vert ; en cas d'échec, lire `reports/check/<étape>.log` et corriger
- [ ] 3.2 Passe navigateur réduite (décisions par défaut, point 8) : `make up`, `make dev` en arrière-plan, `make browser`, puis le skill `browser-use` sur `http://localhost/login` ; vérifier que la page s'affiche et que la console est sans erreur ; `make dev-stop`
- [ ] 3.3 Ouvrir la PR de code ; vérifier le job `image` vert, puis le relancer deux fois (« Re-run jobs ») et vérifier trois exécutions vertes, liens notés dans la PR
- [ ] 3.4 Relire les deux nouveaux scénarios du delta `deployment` et noter pour chacun la tâche qui le prouve (2.3, 2.4) ; finir par `make openspec ARGS="validate --all --strict"` vert

## Workflow follow-up

- Après la fusion, redéployer le staging dans Dokploy sur `:main` et vérifier `migrate` sorti en 0, `web` et `worker` sains, et un fichier déjà présent dans le volume `storage` toujours là (design, Migration Plan) ; commenter le résultat sur l'issue #14 puis la fermer.
- Archiver le changement dans une PR de documentation séparée.
