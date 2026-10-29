# Proposal

## Why

Le test de fumée de l'image (`make image-smoke`) a échoué une fois dans le job `image` de la CI, sur la PR #13 (issue #14) : Docker a refusé de créer le conteneur `web` avec `failed to mkdir …/golf-handicaps-smoke_storage/_data/public: file exists`. `web` et `worker` montent tous deux le volume nommé `storage` sur `storage/app`, et Compose crée les deux conteneurs presque en même temps. À la création du premier conteneur qui monte un volume vide, Docker y copie le contenu de l'image à ce chemin (`.gitignore`, `private/`, `public/`). Deux créations simultanées font deux copies, et la seconde échoue.

Ce n'est pas propre à la CI : le premier déploiement d'une stack sur un volume `storage` neuf (production au cutover, staging remonté) court le même risque. Le job `image` doit rester un contrôle fiable : une PR ne peut pas rougir au hasard. Ce correctif se rattache à l'entrée 4 de `ROADMAP.md` (`production-deployment`, archivée), dont il corrige la stack.

## What Changes

- Le service `migrate` monte aussi le volume `storage` sur `storage/app`, avec la même ligne que `web` et `worker`. Compose crée `migrate` avant `web` et `worker`, qui en dépendent : la copie du contenu de l'image dans un volume neuf a lieu une seule fois, à la création de `migrate`, et le volume n'est plus vide quand `web` et `worker` sont créés. Docker ne copie plus rien pour eux.
- Un commentaire de `docker-compose.yml` dit pourquoi `migrate`, qui n'écrit aucun fichier, monte ce volume.
- `docs/deployment.md` et la topologie de `docs/PORT_TO_LARAVEL.md` disent que `migrate`, `web` et `worker` montent le volume `storage`.
- Le correctif est vérifié par cinq `make image-smoke` de suite en local, par un contrôle déterministe de l'ordre de remplissage du volume, puis par le job `image` de la CI.

### Hors périmètre

- Désactiver la copie (`volume: { nocopy: true }`) et créer les dossiers dans l'entrypoint : écarté (design D2).
- Les volumes `pgdata` et `backups` : chacun n'est monté que par un service, aucune course possible.
- Un test Pest qui lit `docker-compose.yml` : écarté (design, décisions par défaut).

## Capabilities

### New Capabilities

Aucune.

### Modified Capabilities

- `deployment` : le requirement « Données conservées entre les déploiements » gagne deux scénarios, le lancement sur un volume de stockage neuf, qui réussit à chaque essai, et le redéploiement sur un volume existant, dont le contenu ne change pas.

## Impact

- **Code** : `docker-compose.yml` (volume `storage` sur `migrate`, commentaire), `docs/deployment.md`, `docs/PORT_TO_LARAVEL.md`, `CHANGELOG.md` (`### Fixed`). Ni le `Dockerfile`, ni `docker/entrypoint.sh`, ni le code PHP ne changent.
- **Base** : aucune migration.
- **Dépendances** : aucune.
- **Confidentialité** : aucun effet. `migrate` voit les fichiers du stockage (photos à venir) mais n'en lit ni n'en écrit aucun, et tourne avec la même image et le même utilisateur que `web` et `worker`.
- **Volumes existants** (staging) : Docker ne copie le contenu de l'image que dans un volume vide. Sur le volume `storage` déjà rempli du staging, monter le volume dans `migrate` ne copie rien et ne change aucun fichier (design D3).
- **Retour arrière** : annuler la pull request de code. Le volume `storage` reste tel quel ; le retour ramène seulement le risque de course sur un volume neuf.
