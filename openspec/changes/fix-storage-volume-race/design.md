# Design

## Context

La stack de `docker-compose.yml` (design D4 de `production-deployment`) monte le volume nommé `storage` sur `/app/storage/app` dans `web` et `worker`. `migrate` ne le monte pas. Dans l'image, `/app/storage/app` contient `.gitignore`, `private/.gitignore` et `public/.gitignore`, tous à `app:app` (uid 1000) : le `Dockerfile` fait `chown -R app:app storage`, et l'image tourne sous `USER app`.

Trois comportements de Docker et de Compose expliquent l'échec de l'issue #14. Ils ont été observés le 2026-10-28 sur Docker Engine 29.4.0 et Docker Compose 5.1.2, avec l'image `golf-handicaps-laravel:local`, sur une stack d'essai de trois services (`a`, puis `b` et `c` qui attendent la sortie en 0 de `a`, tous trois sur le même volume) :

1. **Copie à la création.** Quand un conteneur est créé avec un volume nommé vide, Docker copie dans le volume le contenu de l'image au point de montage, propriétaire et droits compris, y compris la racine du volume. La copie a lieu à `docker create`, avant tout démarrage : après un `docker compose create a` seul, un conteneur d'une autre image voit déjà `.gitignore`, `private` et `public` à l'uid 1000 dans le volume.
2. **Création de tous les conteneurs d'abord, dans l'ordre des dépendances.** `docker compose up` crée tous les conteneurs avant d'en démarrer un. Il crée un service après la création de ceux dont il dépend, et non après leur sortie : `a` créé, puis `b` et `c` créés 60 ms plus tard et à 5 ms l'un de l'autre, puis `a` démarré, et `b` et `c` démarrés seulement après la sortie de `a`.
3. **Pas d'exclusion entre deux copies.** Deux conteneurs créés en même temps sur le même volume vide font chacun leur copie. Quand le second `mkdir public` arrive après le premier, il échoue avec `file exists`, et Docker refuse de créer le conteneur. C'est l'échec de la CI : `web` et `worker` sont créés en parallèle, puisqu'aucun ne dépend de l'autre.

Le contenu du volume ne dépend que de la première copie : la même image, au même chemin, donne les mêmes fichiers quel que soit le conteneur qui la fait.

## Goals / Non-Goals

**Goals :**

- Une seule copie de l'image vers un volume `storage` neuf, faite avant la création de `web` et de `worker`.
- Le même contenu et les mêmes propriétaires dans le volume qu'aujourd'hui : `web` et `worker`, sous `app`, écrivent dans `public` et `private`.
- Aucun effet sur un volume `storage` déjà rempli (staging).

**Non-Goals :**

- Changer le `Dockerfile`, l'entrypoint ou l'utilisateur de l'image.
- Corriger la copie concurrente dans Docker lui-même, ou la contourner pour d'autres stacks.

## Decisions

### D1. `migrate` monte le volume `storage`

`migrate` reçoit la même ligne de volume que `web` et `worker` : `storage:/app/storage/app`. `web` et `worker` dépendent de `migrate` (`service_completed_successfully`). Par le comportement 2, Compose crée donc `migrate` avant eux, et attend la fin de cette création, copie comprise, avant de créer `web` et `worker`. Le volume n'est plus vide à leur création, et Docker n'y copie rien (comportement 1). Les deux copies concurrentes du comportement 3 ne peuvent plus se produire.

L'ordre ne repose que sur la création et non sur la sortie de `migrate` : même si `migrate` échoue, le volume est déjà rempli, et `web` et `worker` ne démarrent pas, comme aujourd'hui.

Un commentaire au-dessus du volume de `migrate` dit qu'il sert seulement à remplir le volume avant la création de `web` et `worker`, et renvoie à l'issue #14, pour que personne ne le retire comme inutile.

**Montage en lecture et écriture, pas en lecture seule.** Docker 29.4 fait la copie même pour un montage `:ro`, mais ce comportement n'est écrit nulle part dans la documentation de Docker, et la version de Docker du serveur Dokploy n'est pas fixée par le dépôt. Si une version ne copiait pas sur un montage en lecture seule, la course reviendrait sans bruit sur un volume neuf. `migrate` n'écrit aucun fichier : la lecture seule ne protégerait rien de réel. La même ligne sur les trois services est aussi plus facile à relire.

### D2. Alternative écartée : `nocopy` et dossiers créés par l'entrypoint

Monter `storage` avec `volume: { nocopy: true }` sur `web` et `worker`, et faire créer `storage/app/public` et `storage/app/private` par `docker/entrypoint.sh`. Écartée parce qu'elle ne marche pas avec l'image actuelle : sans copie, Docker laisse la racine d'un volume neuf à `root:root`, droits `755`. Essayé le 2026-10-28 avec l'image locale : sous l'uid 1000, `mkdir /app/storage/app/public` répond `Permission denied`. La faire marcher demanderait de démarrer l'entrypoint en root puis de changer d'utilisateur (`gosu` ou équivalent), ce qui défait l'utilisateur non root du requirement « Image unique sans secret », ou un service d'initialisation en root de plus. Les deux coûtent plus que D1 pour le même résultat.

### D3. Volume existant et volume neuf

- **Volume neuf** (CI, premier déploiement de la production au cutover, staging remonté de zéro) : la copie a lieu une fois, à la création de `migrate`. Le volume reçoit `.gitignore`, `private/` et `public/` à `app:app`, exactement comme aujourd'hui quand la course ne se produit pas.
- **Volume existant** (staging, puis production après le cutover) : Docker ne copie que dans un volume vide. Le volume `storage` du staging contient déjà au moins les fichiers de la première copie : monter le volume dans `migrate` ne copie rien, ne change aucun propriétaire et n'ajoute aucun fichier. Redéployer la stack avec ce correctif est un redéploiement ordinaire dans Dokploy, sans geste sur le volume.
- **Volume existant mais vide** (cas théorique : quelqu'un a vidé le volume à la main) : il se comporte comme un volume neuf, et c'est encore `migrate` qui le remplit.

### D4. Vérification

L'échec est intermittent : un `make image-smoke` vert ne prouve rien seul. La vérification a trois parties :

1. **Contrôle déterministe de l'ordre.** Sur la stack de fumée, `docker compose … create migrate` seul, puis un conteneur d'une autre image (`postgres`, déjà présente dans la stack) qui monte le volume `golf-handicaps-smoke_storage` sur un chemin vide de son image et liste son contenu : `.gitignore`, `private` et `public` à l'uid 1000 doivent y être. Avec la mutation « volume retiré de `migrate` », le volume n'existe pas encore ou reste vide après `create migrate` : le contrôle rougit. Ce contrôle prouve que le volume est rempli avant que `web` et `worker` existent, ce qui rend la course impossible.
2. **Cinq `make image-smoke` de suite** en local, chacun retirant la stack et ses volumes (`down -v`), donc cinq lancements sur un volume neuf.
3. **CI** : le job `image` vert sur la PR de code, relancé deux fois (trois exécutions vertes).

Avant le correctif, une tentative de reproduction (`create` de la stack puis `down -v`, vingt fois de suite) note si la course se montre en local. Son résultat est rapporté tel quel : une course qui ne se montre pas en local ne bloque pas le correctif, le contrôle déterministe en tient lieu.

## Risks / Trade-offs

- [Une version future de Compose crée les services sans attendre la création de leurs dépendances] → La course reviendrait. Le contrôle déterministe de D4 est décrit dans `docs/deployment.md` pour être refait après une mise à jour majeure de Docker ou de Compose sur le serveur, et le job `image` de la CI lance la stack à chaque PR de code.
- [`migrate` voit les fichiers du stockage] → Même image, même utilisateur, même code que `web` et `worker`, qui les voient déjà. Aucune commande de `migrate` ne lit ni n'écrit sous `storage/app`.
- [Un commentaire seul protège le montage] → Le contrôle déterministe de D4 et le scénario « Lancement sur un volume de stockage neuf » de la spec `deployment` disent pourquoi il existe. Un test Pest qui lirait `docker-compose.yml` est écarté (décisions par défaut, point 3).

## Migration Plan

1. Fusionner la PR de code.
2. Staging : redéployer la stack dans Dokploy sur l'image `:main`. Vérifier que `migrate` sort en 0, que `web` et `worker` sont sains, et qu'un fichier déjà présent dans le volume `storage` est toujours là (D3, volume existant).
3. Production : rien à faire avant le cutover. Le premier déploiement sur un volume neuf profite du correctif.

Retour arrière : annuler la PR de code et redéployer. Le volume ne change pas.

## Décisions par défaut (orchestrateur, nuit du 2026-10-28)

Le mainteneur dormait pendant la rédaction de cette proposition. Chaque question ouverte a été tranchée par l'option recommandée ci-dessous. Chacune peut être contestée à la revue de la PR ; la changer modifie `tasks.md` en conséquence.

1. **Correctif retenu : monter `storage` dans `migrate` (D1)** plutôt que `nocopy` et l'entrypoint (D2), qui échoue sous l'utilisateur non root.
2. **Montage en lecture et écriture dans `migrate`**, pas `:ro` (D1) : la copie sur un montage en lecture seule n'est pas un comportement documenté de Docker.
3. **Pas de test Pest sur `docker-compose.yml`.** Un test qui lirait le YAML pour y chercher la ligne de volume ne prouverait pas l'absence de course, seulement la présence d'une ligne. La preuve est le contrôle déterministe de D4, avec sa mutation nommée, rapporté dans le compte rendu de l'apply.
4. **Pas de nouvelle cible Makefile** pour répéter le test de fumée ou pour le contrôle déterministe : une boucle shell sur `make image-smoke` et trois commandes `docker compose` notées dans `docs/deployment.md` suffisent pour un contrôle fait une fois et refait après une mise à jour de Docker.
5. **Cinq lancements locaux de suite et trois exécutions vertes du job `image` en CI** comme seuil de vérification (D4).
6. **Pas d'entrée dans `ROADMAP.md`.** La feuille de route ordonne les changements du port ; ce correctif répare la stack de l'entrée 4 sans en ajouter une, et ne bloque ni ne débloque aucune entrée.
7. **Pas d'ADR.** Le déploiement reste celui de l'ADR 0008 ; seul le montage d'un volume change, sans nouvelle décision d'architecture.
8. **Passe navigateur réduite** : la consigne du dépôt demande une passe navigateur en fin d'apply. Aucune page ne change ; la passe se limite à `/login` sur l'environnement local, pour constater que l'application répond toujours.
