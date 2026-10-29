# Proposal

## Why

`docs/deployment.md` (« Bascule depuis Next ») décrit une bascule franche : répétition sur le staging, puis le jour J, arrêt de Next, import et passage du domaine sur la stack Laravel dans la même fenêtre. Le mainteneur ne veut pas de bascule franche : « we wont do a hard cutover, ill run the laravel app on golf2.emmanuelpaul.com temp while i check that the scores are the same as the nextjs one and then move ». Il veut faire tourner l'application Laravel quelque temps sur `golf2.emmanuelpaul.com`, à côté de Next qui reste en production, vérifier que les index et les rounds sont les mêmes, puis seulement déménager. Ce changement suit l'entrée 10 de `ROADMAP.md` (`sqlite-import-command`, faite et archivée) : il ne touche que la procédure qui reste au mainteneur.

## What Changes

- **Phase parallèle** : Next reste en production sur son domaine actuel et reste la seule source de vérité. La stack Laravel est installée sur `golf2.emmanuelpaul.com` (`APP_URL`, domaine dans l'onglet Domains de Dokploy, certificat Let's Encrypt de Traefik) et reçoit une copie de la base Next par la procédure existante : copie `VACUUM INTO`, essai à blanc, import `golf:import-sqlite`. Cette phase remplace la répétition sur le staging.
- **Rien n'est saisi sur `golf2`** pendant la phase parallèle : chaque réimport vide la base Laravel (`--force`), toute saisie y serait perdue. Pour comparer avec des rounds récents, le mainteneur réimporte une copie fraîche avec `--force` (« Reprendre après un arrêt », puis copie et import), sans arrêter Next.
- **`SGT_LOOKUP=off` sur `golf2` pendant la phase parallèle** : la recherche des ratings y ferait des requêtes vers Simulator Golf Tour et dépenserait le budget IA pour des données qui seront réimportées. Elle repasse à `on` au déménagement, avant le dernier import, pour que les recherches de Next en cours soient reprises.
- **Comparaison par le mainteneur**, une étape à la fois : rapport de l'import (statistiques identiques), puis, pour chaque joueur, index actuel, nombre de rounds, et pour les derniers rounds date, parcours, brut et différentiel, entre Next et `golf2`. Ce qui doit être identique et ce qui peut différer (rounds saisis dans Next après la copie, recherches de rating) est écrit.
- **Déménagement** : arrêt des saisies sur Next (web arrêté, file vidée, worker arrêté), dernière copie fraîche, `SGT_LOOKUP=on` et `APP_URL` du domaine principal, import `--force`, domaine principal retiré de Next et relié au service `web` Laravel, `golf2` retiré, Next gardé arrêté avec son volume 30 jours. Retour arrière : domaine principal rendu à Next, Next redémarré.
- **Chaque étape est marquée « Mainteneur »**, une commande ou un petit bloc à la fois, avec ce qu'il faut lire dans la sortie.
- ADR 0014, `docs/PORT_TO_LARAVEL.md` (décision 12 et section Cutover), `ROADMAP.md` et `CHANGELOG.md` suivent.

### Hors périmètre

- Aucun code, aucune variable nouvelle, aucun changement de `docker-compose.yml` : `APP_URL` et le domaine se règlent déjà dans Dokploy.
- L'enregistrement DNS de `golf2.emmanuelpaul.com` : il se fait chez le fournisseur DNS du domaine, hors du dépôt ; la procédure dit seulement de vérifier qu'il pointe vers le serveur.
- Une comparaison automatique entre Next et Laravel en dehors de celle que fait déjà `golf:import-sqlite` (statistiques recalculées comparées à celles de Next).
- Le staging de `production-deployment` (groupe 8 de ses tâches) : il reste à faire, indépendamment.

## Capabilities

### New Capabilities

Aucune.

### Modified Capabilities

- `deployment` : l'exigence « Bascule depuis l'application Next documentée » décrit désormais la mise en route en parallèle sur un domaine temporaire, la comparaison, puis le déménagement, au lieu d'une bascule franche après une répétition sur le staging.

`legacy-import` n'a aucune exigence sur la forme de la bascule (seul le message « Base en WAL » nomme la section « Bascule depuis Next », dont le titre est gardé) : pas de delta.

## Impact

- Documentation : `docs/deployment.md` (section « Bascule depuis Next » réécrite, titre gardé), `docs/adr/0014-import-unique-de-la-base-sqlite-de-next.md`, `docs/PORT_TO_LARAVEL.md`, `ROADMAP.md`, `CHANGELOG.md`.
- Code, image, CI : rien.
- Confidentialité : `golf2` porte une copie complète des données du groupe ; il est derrière le même code d'accès `APP_PASSCODE` que la production. La copie sur l'hôte et dans le conteneur est effacée après chaque import, comme avant.
- Retour arrière du changement : rétablir l'ancienne section par `git revert` ; aucune donnée en jeu. Retour arrière du déménagement : décrit dans la procédure.
