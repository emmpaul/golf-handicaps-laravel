---
name: release
description: 'Publie une version vX.Y.Z : PR de version, tag, image GHCR vérifiée, GitHub Release, déploiement guidé dans Dokploy.'
disable-model-invocation: true
argument-hint: '[X.Y.Z]'
---

# Publier une version

Le mainteneur lance ce skill, et lui seul. Chaque étape finit sur un critère vérifiable ; une étape dont le critère est faux arrête le skill, qui nomme ce qui manque. Un **arrêt** est une question posée par `AskUserQuestion` : sans réponse explicite du mainteneur, rien ne continue.

Références, à lire au lieu de les recopier : la règle des tags d'image dans la cible `image-tags` du `Makefile`, le job `publish` de `.github/workflows/ci.yml`, et `docs/deployment.md` pour l'image et Dokploy. Dans les commandes, `X.Y.Z` est la version retenue à l'étape 2.

## 1. Préconditions

- `git branch --show-current` répond `main`.
- Après `git fetch origin --tags`, `git rev-parse HEAD` égale `git rev-parse origin/main`, et `git status --porcelain` ne sort rien.
- `## [Unreleased]` de `CHANGELOG.md` contient au moins une ligne `- `.
- `gh run list --workflow ci.yml --branch main --event push --limit 1 --json conclusion,headSha` répond `success`.

Critère : les quatre points tiennent.

## 2. Version

- Avec un argument : il a la forme `X.Y.Z` et dépasse `version.txt`.
- Sans argument : proposer un saut depuis `version.txt` d'après les sous-sections de `## [Unreleased]`. `### Removed` ou une rupture donne un majeur, `### Added` ou `### Changed` un mineur, `### Fixed` seul un correctif. Sous `1.0.0`, une rupture donne un mineur. **Arrêt** : le mainteneur confirme ou corrige la version.

Puis le tag doit être libre : `git tag -l vX.Y.Z` et `git ls-remote --tags origin refs/tags/vX.Y.Z` ne sortent rien.

Critère : `X.Y.Z` retenu, tag absent en local et sur `origin`.

## 3. PR de version

1. `git switch -c chore/release-vX.Y.Z --no-track`.
2. Dans `CHANGELOG.md`, renommer `## [Unreleased]` en `## [X.Y.Z] - <date>`, la date de `TZ=America/Montreal date +%F`, et ouvrir au-dessus une section `## [Unreleased]` vide. Mettre `X.Y.Z` seul dans `version.txt`.
3. `make test-backend FILES=tests/Feature/VersionFileTest.php` passe.
4. `git add CHANGELOG.md version.txt`, puis `git commit -m "chore(release): vX.Y.Z"`, sans corps ni trailer.
5. Extraire les notes dans un fichier au chemin fixe, relu par les étapes 3 et 8 :

    ```sh
    awk '/^## \[X\.Y\.Z\] - / {f=1; next} /^## \[/ {f=0} f' CHANGELOG.md > "${TMPDIR:-/tmp}/release-notes-vX.Y.Z.md"
    ```

    Pas de `$0` dans ce skill : le chargeur des skills le remplace par l'argument. Le fichier n'est pas vide.

6. `git push -u origin chore/release-vX.Y.Z`, puis `gh pr create --base main --title "chore(release): vX.Y.Z" --body-file "${TMPDIR:-/tmp}/release-notes-vX.Y.Z.md"`.

Critère : la PR est ouverte, son diff ne touche que `CHANGELOG.md` et `version.txt`.

## 4. Fusion

1. `gh pr checks <pr> --required --watch --fail-fast` sort en 0.
2. **Arrêt** : fusionner la PR ?
3. `gh pr merge <pr> --rebase --match-head-commit <sha de la branche>`. Le dépôt n'accepte que le rebase et supprime la branche distante.
4. `git switch main`, `git pull --ff-only origin main`, `git branch -D chore/release-vX.Y.Z`. Le rebase a réécrit le commit, d'où `-D`.

Critère : `git log -1 --format=%s` répond `chore(release): vX.Y.Z` et `version.txt` vaut `X.Y.Z`.

## 5. Tag

1. **Arrêt** : poser et pousser `vX.Y.Z` sur `<sha de HEAD>` ? Un tag poussé est définitif.
2. `git tag -a vX.Y.Z -m "vX.Y.Z"`, puis `git push origin vX.Y.Z`.

Critère : `git ls-remote --tags origin refs/tags/vX.Y.Z` sort une ligne.

## 6. Publication

1. Trouver le run du tag, qui apparaît quelques secondes après le push : `gh run list --workflow ci.yml --branch vX.Y.Z --event push --limit 1 --json databaseId,status`.
2. `gh run watch <id> --exit-status`.
3. `gh run view <id> --json jobs --jq '.jobs[] | select(.name == "publish") | .conclusion'` répond `success`.

Critère : `publish` vert sur le run du tag.

## 7. Image publiée

Les trois tags de `ghcr.io/emmpaul/golf-handicaps-laravel` ont un seul digest, `<sha>` étant le sha complet de `git rev-parse "vX.Y.Z^{commit}"` :

```sh
for t in vX.Y.Z latest <sha>; do
  docker buildx imagetools inspect "ghcr.io/emmpaul/golf-handicaps-laravel:$t" --format '{{.Manifest.Digest}}'
done
```

Puis l'image répond sa version. `/api/version` ne lit ni base ni session, mais toute requête exige `APP_KEY` et `APP_PASSCODE`. Les valeurs ci-dessous ne servent qu'à ce conteneur jetable. `--platform` est là parce que l'image n'existe qu'en `linux/amd64`.

```sh
docker run -d --rm --name golf-release-check --platform linux/amd64 -p 127.0.0.1:18080:8080 \
  -e APP_KEY="base64:$(openssl rand -base64 32)" -e APP_PASSCODE=release-check \
  ghcr.io/emmpaul/golf-handicaps-laravel:vX.Y.Z
curl -fsS --retry 30 --retry-all-errors --retry-delay 1 http://127.0.0.1:18080/api/version
docker stop golf-release-check
```

Critère : trois digests identiques, et la réponse vaut `{"version":"X.Y.Z","commit":"<sha>"}`.

## 8. GitHub Release

Le corps de la release est un en-tête de déploiement, puis `## Changes` et les notes de l'étape 3. Il s'écrit en anglais, comme `CHANGELOG.md`, dans `${TMPDIR:-/tmp}/release-body-vX.Y.Z.md`. `<prev>` est la version publiée précédente : `git describe --tags --abbrev=0 "vX.Y.Z^"`. Sans tag précédent, c'est la première version, et chaque ligne prend sa variante « première version ».

En-tête, une ligne par point :

- **Image** : `ghcr.io/emmpaul/golf-handicaps-laravel:vX.Y.Z`, aussi `latest` et `<sha>`, `linux/amd64`, et le digest de l'étape 7.
- **Commit** : `<sha>` court, lié à `https://github.com/emmpaul/golf-handicaps-laravel/commit/<sha>`.
- **Deploy** : `IMAGE_TAG=vX.Y.Z` dans Dokploy, puis « Déployer une version » de `docs/deployment.md`. Première version : « Première installation ».
- **Migrations** : les fichiers de `git diff --name-only --diff-filter=A <prev> vX.Y.Z -- database/migrations`, ou « none ». S'il y en a, rappeler de forcer un dump avant le déploiement et de lire leur `up()` avant un retour arrière. Première version : leur nombre, toutes appliquées par `migrate`.
- **Environment** : les variables de `docker-compose.yml` ajoutées ou retirées depuis `<prev>`, ou « no change ». Les comparer par `git show <tag>:docker-compose.yml | grep -oE '\$\{[A-Z_]+' | sort -u` aux deux tags. Première version : les obligatoires (`${NOM:?…}` dans le fichier), `APP_PASSCODE`, et le renvoi à « Renseigner les variables » de `docs/deployment.md`.
- **Rollback** : `IMAGE_TAG=<prev>`, puis « Retour arrière » de `docs/deployment.md`. Première version : aucune version à laquelle revenir.
- **Full changelog** : `https://github.com/emmpaul/golf-handicaps-laravel/compare/<prev>...vX.Y.Z`. Première version : `https://github.com/emmpaul/golf-handicaps-laravel/blob/vX.Y.Z/CHANGELOG.md`.

Puis `gh release create vX.Y.Z --verify-tag --title vX.Y.Z --notes-file "${TMPDIR:-/tmp}/release-body-vX.Y.Z.md"`.

Critère : `gh release view vX.Y.Z` montre l'en-tête, avec le digest de l'étape 7, puis les notes de la section `## [X.Y.Z]`.

## 9. Déploiement

**Arrêt** : quelle cible ? La production, si sa stack existe dans Dokploy ; le staging, épinglé par `IMAGE_TAG=vX.Y.Z` ; ou aucune.

Pour une cible, suivre « Déployer une version » de `docs/deployment.md` avec `IMAGE_TAG=vX.Y.Z`. Donner une étape à la fois au mainteneur, avec ce qu'il doit lire dans la sortie, et attendre son retour avant la suivante.

Critère : `GET /api/version` de la cible répond `X.Y.Z` et `<sha>`, ou le mainteneur a choisi « aucune ».

## Échec après le tag

Un tag poussé reste où il est : jamais déplacé, jamais supprimé.

- Échec passager (réseau, registre, runner) : **arrêt**, puis `gh run rerun <id> --failed` et reprise à l'étape 6.
- Vrai échec (test, image, `publish` qui refuse le tag) : le skill s'arrête, nomme le job et l'erreur, et propose une version corrective `vX.Y.(Z+1)`, publiée par `/release` une fois le correctif fusionné.
