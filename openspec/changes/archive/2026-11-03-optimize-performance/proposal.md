## Why

L'application tourne en production sur `golf.emmanuelpaul.com` depuis la bascule du 2026-10-29. Une mesure en lecture seule faite le même jour (détail dans `design.md`, « Mesure de référence ») montre qu'aucune page n'est lente à cause de ses données : les réponses Inertia font moins de 1,5 Ko. Le temps part ailleurs :

- **Polices chargées deux fois.** La page précharge 10 fichiers WOFF2 (≈ 98 Ko), puis le navigateur télécharge en plus les fichiers WOFF (≈ 85 Ko, non compressés). Le plugin de polices de `laravel-vite-plugin` 3.2.0 écrit une règle `@font-face` par fichier et par plage : la règle WOFF, écrite après la WOFF2, l'emporte. Les préchargements ne servent pas, la police utilisée arrive tard et le texte change de police sous les yeux.
- **Chaque navigation attend le serveur.** Passer d'un onglet à l'autre prend 150 à 320 ms, tout entiers passés à attendre la réponse Inertia puis le code de la page. Aucun lien ne précharge.
- **Chaque requête reconstruit Laravel.** FrankenPHP tourne en mode classique (ADR 0008, design D1) : une page coûte 80 à 150 ms de plus qu'un fichier statique servi par le même Caddy derrière Cloudflare. `/up`, qui ne lit presque rien, coûte déjà 33 ms de plus.
- **Petites fuites.** `/favicon.ico` n'existe pas : la demande passe par PHP, ouvre une session en base et répond par une redirection vers `/login`. Cloudflare injecte son script d'analyse (`static.cloudflareinsights.com`), qui contredit l'exigence « aucune requête vers un tiers » de `visual-theme`.

Il n'existe pas non plus de moyen de refaire la mesure après un déploiement pour comparer.

## What Changes

- **Mesure reproductible.** Une commande lance la même mesure en lecture seule contre n'importe quelle adresse : un seul envoi du code d'accès, puis des GET seulement, avec la médiane et le 90e centile de chaque page. Elle lit le code dans l'environnement ou le demande sans l'afficher. Le code n'est jamais écrit dans le dépôt ni dans le rapport. `docs/performance.md` garde la mesure de référence du 2026-10-29 et la mesure prise après le déploiement de ce changement.
- **Temps du serveur visible.** Chaque réponse de l'application porte un en-tête `Server-Timing` (temps de l'application, temps et nombre des requêtes SQL), que la mesure lit pour séparer le serveur du réseau.
- **Polices chargées une fois.** WOFF2 seulement, une règle par graisse, sous-ensemble latin seulement. Le préchargement se limite aux fichiers que la page utilise, et une police de repli aux métriques ajustées évite le saut à l'arrivée de Geist.
- **Navigation instantanée.** Les onglets de la barre flottante et les liens de liste préchargent leur page. Une page déjà préchargée s'affiche aussitôt puis se met à jour en arrière-plan. Toute écriture réussie vide le cache des pages préchargées.
- **Serveur web en mode worker.** FrankenPHP garde Laravel en mémoire entre les requêtes, par Laravel Octane. **Nouvelle dépendance** (`laravel/octane`) et nouvelle ADR qui remplace la décision D1 de l'ADR 0008. Rien d'une requête (session, flash, code d'accès, erreurs de validation) ne passe à la suivante.
- **`/favicon.ico` statique.** Servi par Caddy, sans PHP ni session.
- **Script tiers de Cloudflare.** Le mainteneur coupe l'injection de Cloudflare Web Analytics sur la zone. C'est un réglage hors du dépôt, documenté.

Hors périmètre : cache HTTP des pages (elles sont privées), CDN ou cache applicatif des requêtes (les données sont trop petites pour en profiter, et `config.yaml` écarte le cache prématuré), changement de pilote de session, de cache ou de file (à rouvrir seulement si le `Server-Timing` montre que la session en base pèse), compression Brotli précalculée des assets, découpage du paquet JavaScript commun (122 Ko compressé, en cache un an), index SQL supplémentaires, emplacement de l'hôte.

## Capabilities

### New Capabilities

- `performance-measurement` : la mesure reproductible en lecture seule et l'en-tête `Server-Timing` qui la nourrit.

### Modified Capabilities

- `visual-theme` : chaque fichier de police est téléchargé une fois au plus, au format WOFF2, et seuls les fichiers utilisés sont préchargés.
- `app-shell` : navigation préchargée entre sections et pages de liste, données à jour après une écriture.
- `deployment` : serveur web en mode worker sans fuite d'état entre requêtes, `/favicon.ico` servi sans PHP.

## Impact

- Code : `vite.config.ts` (polices), `resources/js` (liens préchargés et vidage du cache après écriture : `nav-link.tsx`, `app-nav.tsx`, `app.tsx`, liens des listes de rounds, d'imports et de l'historique, sauvegarde automatique de l'écran de correction), un middleware `Server-Timing`, une commande artisan de mesure, `public/favicon.ico`, `docker/Caddyfile`, `Dockerfile`, `docker-compose.yml` (commande du service `web` retirée, donnée par l'image), `config/octane.php`, `Makefile` (cible de mesure).
- Dépendances : ajout de `laravel/octane`, à approuver par le mainteneur à la relecture de cette proposition.
- Docs : ADR 0015 (mode worker) et sa ligne dans `docs/adr/README.md`, ADR 0008 marquée remplacée sur D1, `docs/performance.md` (nouveau), `docs/deployment.md` (mode worker, réglage Cloudflare, relancer la mesure), `ARCHITECTURE.md`, `ROADMAP.md` (nouvelle entrée 14), `CHANGELOG.md`.
- Dépendances de feuille de route : 4 `production-deployment` (en cours, la stack tourne en production) et 11 `visual-redesign` (fait). Pas de lien avec le changement en cours `index-computation-page`, sauf que sa future page `/handicaps/calcul` suivra la même règle de préchargement.
- Vie privée : la commande de mesure ne lit que des pages déjà servies au groupe, n'écrit rien et ne garde ni le code ni le témoin de session. `Server-Timing` donne des durées, aucune donnée. Couper le script Cloudflare réduit ce qui part chez un tiers.
- Retour arrière : polices, préchargement, favicon et `Server-Timing` se défont en revenant au commit. Le mode worker se défait en redéployant l'image précédente (`docs/deployment.md`, « Retour arrière »), sans migration.
