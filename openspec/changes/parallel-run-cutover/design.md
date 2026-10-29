# Design

## Context

Voir `proposal.md` pour la demande du mainteneur. La procédure actuelle (`docs/deployment.md`, « Bascule depuis Next ») a sept étapes : répétition sur le staging, arrêt de Next, copie `VACUUM INTO` et des photos, import (essai à blanc puis réel), bascule du domaine, effacement des copies, retour arrière, plus « Reprendre après un arrêt ». Les commandes de copie et d'import restent bonnes ; seul l'enchaînement change.

Ce que la doc et le compose lient au domaine, relevé dans le dépôt :

- `APP_URL` (`docker-compose.yml`, passé à `migrate`, `web` et `worker` ; défaut `http://localhost`) : adresse publique en `https`. Elle ne sert ni aux témoins ni aux redirections de l'application, qui suivent l'hôte de la requête ; changer une variable demande un redéploiement (« Déployer une version »).
- L'onglet Domains de Dokploy (« Première installation », étape 5) : hôte, service `web`, port `8080`, HTTPS par Let's Encrypt ; Traefik termine le TLS. Rien dans le compose ne nomme un hôte (aucun label Traefik, aucun port publié).
- `TRUSTED_PROXIES` : sous-réseau du réseau de Traefik, indépendant du nom d'hôte.
- Le témoin de session : `SESSION_DOMAIN` n'est pas réglé, le témoin vaut pour l'hôte seul. Une session ouverte sur `golf2` ne vaut pas sur le domaine principal : chacun ressaisit le code après le déménagement.
- HSTS : `max-age=31536000` sans `includeSubDomains` (`AddSecurityHeaders`), donc sans effet sur les autres sous-domaines.
- DNS : aucune mention dans le dépôt. L'enregistrement de `golf2.emmanuelpaul.com` est hors du dépôt.
- Le nom du domaine principal de Next n'est écrit nulle part dans le dépôt : la procédure l'appelle « le domaine principal » et le fait relever dans Dokploy.

Le titre « Bascule depuis Next » est cité par le message d'erreur « Base en WAL : copier par VACUUM INTO (docs/deployment.md, Bascule depuis Next). » de `golf:import-sqlite` et par son test : il est gardé.

## Goals / Non-Goals

**Goals :** une procédure en deux phases, chaque étape du mainteneur en une commande ou un petit bloc avec ce qu'il faut lire ; la spec `deployment` à jour.

**Non-Goals :** code, variables nouvelles, outil de comparaison automatique, gestion DNS.

## Decisions

### D1. La stack de `golf2` est celle qui deviendra la production

On n'installe pas une seconde stack jetable : la stack Laravel installée pour `golf2` déménage ensuite sur le domaine principal par un changement d'`APP_URL` et de domaine dans Dokploy. Alternative écartée : une stack temporaire puis une nouvelle installation de production, qui referait la première installation et perdrait l'essai fait sur `golf2`. La phase parallèle remplace la répétition sur le staging, qui devient inutile pour la bascule : elle essaie les mêmes commandes sur le même hôte que Next.

### D2. Rien n'est saisi sur `golf2`, on réimporte

Chaque import passe `--force` et vide les tables métier : toute saisie faite sur `golf2` disparaîtrait au réimport suivant, et ne serait jamais dans Next. Le groupe continue de tout saisir dans Next. Pour comparer avec des rounds récents, le mainteneur suit « Reprendre après un arrêt », puis la copie et l'import, sans arrêter Next (`VACUUM INTO` lit une image cohérente d'une base en service). Alternative écartée : laisser saisir sur `golf2` et fusionner, que la commande ne sait pas faire (elle ne fusionne jamais, `legacy-import`).

### D3. `SGT_LOOKUP=off` pendant la phase parallèle, `on` au déménagement avant le dernier import

Sur `golf2`, l'import remet en file les recherches de rating « en cours » de Next, et `migrate` met en file celles des parcours sans ligne : avec `on`, le worker de `golf2` interroge Simulator Golf Tour et fait lire les cartes par l'IA, sous le budget mensuel, pour des lignes que le réimport suivant remplace. Avec `off`, chaque recherche finit en « Saisie manuelle » (« Recherche SGT désactivée »), sans requête ni coût. Au déménagement, `SGT_LOOKUP=on` est remis et la stack redéployée avant le dernier import : l'import remet alors en file les recherches en cours de Next, que le worker traite pour de bon. Décision par défaut, prise sans le mainteneur ; à contester au besoin. Conséquence pour la comparaison : la page Parcours de `golf2` peut montrer « Saisie manuelle » là où Next montre une recherche en cours ou une proposition ; les ratings confirmées, elles, sont identiques. `ANTHROPIC_API_KEY` peut rester réglée sur `golf2` : sans saisie ni recherche SGT, rien n'appelle l'IA.

### D4. Ordre du déménagement

1. Arrêter les saisies sur Next (web arrêté, file du worker vidée, worker arrêté), comme l'ancienne étape 2.
2. Copier (`VACUUM INTO`, photos).
3. Dans Dokploy, stack Laravel : `APP_URL=https://<domaine principal>` et `SGT_LOOKUP=on`, puis Deploy. Un seul redéploiement pour les deux variables ; `APP_URL` ne change rien à l'hôte servi, `golf2` répond encore. `migrate` ne met en file que les parcours sans ligne : après un import avec `off`, chaque parcours a déjà sa ligne.
4. Importer avec `--force` (essai à blanc puis réel), lire le rapport.
5. Domaine : retirer le domaine principal de l'application web de Next, le relier au service `web` Laravel, retirer `golf2` du service `web`. Vérifier `/api/version` et les pages.
6. Effacer les copies.
7. Garder Next arrêté avec son volume 30 jours.

Alternative écartée : changer les variables après l'import. Les recherches en cours de Next seraient remises en file avec `off` et finiraient en « Saisie manuelle » ; il faudrait les relancer à la main sur la page Parcours.

### D5. Comparaison

`golf:import-sqlite` compare déjà `player_round_stats` colonne par colonne à celle de Next et refuse tout écart : « Statistiques identiques : N / N » est la preuve sur la copie. La comparaison du mainteneur vérifie ce que les pages affichent, à partir de la même copie : pour chaque joueur, l'index actuel (`/handicaps` des deux côtés), le nombre de rounds et les derniers rounds (date, parcours, brut, différentiel). Seuls les rounds saisis dans Next après la copie peuvent manquer sur `golf2`, et les recherches de rating en cours peuvent différer (D3). Tout autre écart arrête la procédure : on ne déménage pas, on le signale.

### D6. Spec

L'exigence `deployment` « Bascule depuis l'application Next documentée » est remplacée en entier (MODIFIED), titre gardé. Les scénarios « Copie d'une base en WAL » et « Base qui a déjà les joueurs initiaux » restent ; « Retour arrière » nomme le domaine principal ; s'ajoutent domaine temporaire, saisie, recherche SGT, copie rafraîchie, comparaison et déménagement. `legacy-import` ne parle pas de bascule franche : pas de delta. ADR 0014 reçoit une mention « Modifiée » dans son statut et sa conséquence sur le cutover, selon la convention de l'ADR 0006 (une ADR modifiée en partie garde son fichier et le dit dans son statut).

## Risks / Trade-offs

- [Quelqu'un saisit sur `golf2`] → la saisie est perdue au réimport. La procédure le dit en tête de la phase parallèle ; le mainteneur prévient le groupe, ou ne donne pas l'adresse.
- [Les sessions ne suivent pas le changement de domaine] → chacun ressaisit le code une fois après le déménagement ; c'est écrit.
- [Un écran ajouté au domaine temporaire sur un téléphone] → il pointe sur `golf2`, qui ne répond plus après le déménagement ; ajouter l'écran d'accueil depuis le domaine principal.
- [L'ordre « domaine retiré de Next, puis relié à Laravel » dans Dokploy] → coupure de quelques minutes, pendant que Traefik obtient le certificat ; acceptée, Next est déjà arrêté.
- [Comportement de Dokploy quand un domaine passe d'une application à l'autre] → non essayé ; la procédure vérifie par `curl` que le domaine principal répond la `version` de la stack Laravel.

## Migration Plan

Changement de documentation seul : fusion, puis le mainteneur suit la procédure. Retour arrière du changement : `git revert`.
