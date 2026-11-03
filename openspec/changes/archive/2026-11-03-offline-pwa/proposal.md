## Why

Les cartes se photographient au simulateur, souvent avec un réseau faible ou absent. Aujourd'hui, sans réseau, l'application installée sur l'écran d'accueil n'ouvre rien : l'écran Importer ne s'affiche pas, une photo prise ne part pas et se perd (une photo prise par « Prendre une photo » n'est pas gardée dans la photothèque d'iOS). Les pages consultées la veille (index, coups à rendre avant un match) ne se relisent pas non plus. Le manifest existe depuis le changement 11, mais aucun service worker : l'application est installable, pas utilisable hors ligne.

Aucune entrée de `ROADMAP.md` ne la prévoit : Next l'avait écartée (« Pas de service worker ni de mode hors ligne », changement `polish-mobile-ui`). Ce changement ouvre l'entrée 15. Ses dépendances (8 pour l'import, 11 pour le manifest et le shell, 14 pour le préchargement des pages) sont faites ; l'entrée 14 est encore « en cours » pour des mesures de production au mainteneur, sans effet sur ce changement.

## What Changes

- **Service worker.** Un service worker statique, servi par le serveur web sans PHP ni session, garde les assets compilés et la dernière version de chaque page visitée en ligne.
- **Pages relisibles hors ligne.** Hors ligne, une page déjà visitée s'affiche depuis la copie gardée, avec un bandeau « Hors ligne » qui donne l'heure de cette copie. Une page jamais visitée affiche un message clair sans quitter la page courante ; ouverte à froid, l'application affiche un écran « Hors ligne » qui mène à l'écran Importer. L'écran Importer est gardé dès la première ouverture en ligne avec une session, même sans y être allé.
- **Photos gardées sur le téléphone.** Une photo choisie ou prise est d'abord gardée sur l'appareil, puis envoyée. Sans réseau, elle y reste et l'écran Importer la liste « En attente d'envoi » ; elle part seule au retour du réseau, ou à la prochaine ouverture de l'application si celle-ci était fermée. Les photos partent une à une, dans l'ordre de la prise.
- **Envoi sans doublon.** Chaque photo gardée porte un identifiant tiré sur l'appareil. Un envoi rejoué (réponse perdue, application fermée au mauvais moment) ne crée pas un second import. **Nouvelle colonne** `imports.upload_key`, unique.
- **Session expirée.** Une photo refusée faute de session reste sur l'appareil ; l'application affiche `/login`, et l'envoi reprend après la saisie du code.
- **Écritures hors ligne refusées clairement.** Hors ligne, toute autre écriture (saisie manuelle, joueurs, ratings, correction d'un import) échoue avec un message, sans rien garder ni perdre en silence.
- **Déconnexion.** « Se déconnecter » efface les pages gardées sur l'appareil. Les photos en attente d'envoi restent, et partent après la prochaine saisie du code.

Hors périmètre : mise en file hors ligne de la saisie manuelle d'un round et des autres écritures, synchronisation en arrière-plan quand l'application est fermée (Background Sync, absent de Safari iOS), notifications, photos des rounds et des imports relues hors ligne, préchargement de toutes les pages à l'installation, invite d'installation personnalisée, mise à jour forcée du service worker par une invite « Nouvelle version ».

## Capabilities

### New Capabilities

- `offline-mode` : service worker, pages visitées relisibles hors ligne, bandeau « Hors ligne », écran hors ligne à froid, pages jamais visitées et écritures hors ligne, effacement des pages gardées à la déconnexion.

### Modified Capabilities

- `scorecard-import` : la photo choisie est gardée sur l'appareil puis envoyée ; liste « En attente d'envoi », envoi au retour du réseau, reprise après la saisie du code, envoi sans doublon.
- `loading-feedback` : l'attente de l'envoi d'une photo distingue l'envoi en cours de la photo gardée hors ligne.
- `app-access` : la fermeture de session efface aussi les pages gardées sur l'appareil.

## Impact

- Code : `public/sw.js` (nouveau), `docker/Caddyfile` (`Cache-Control: no-cache` de `/sw.js`), `resources/js` (enregistrement du service worker, file des photos en IndexedDB et son envoi, bandeau hors ligne, `import-upload.tsx`, liste « En attente d'envoi », message d'une page indisponible, effacement à la déconnexion), `public/offline.html` (nouveau), migration `imports.upload_key`, `StoreImportRequest`, `CreateImport`, `ImportController::store` (réponse JSON hors Inertia), `ImportFactory` (état avec clé).
- Dépendances : aucune. Le service worker est écrit à la main (pas de Workbox ni de `vite-plugin-pwa`).
- Confidentialité : les pages visitées (noms des joueurs, scores, index) et les photos en attente sont gardées sur l'appareil, dans le stockage du navigateur de l'origine, jusqu'à la déconnexion pour les pages et jusqu'à l'envoi pour les photos. Rien ne part vers un tiers. Les photos des rounds ne sont pas gardées.
- Docs : ADR 0016 (mode hors ligne par service worker écrit à la main) et sa ligne dans `docs/adr/README.md`, `ARCHITECTURE.md` (section Frontend), `DESIGN.md` (bandeau et écran hors ligne), `ROADMAP.md` (entrée 15), `CHANGELOG.md`.
- Retour arrière : revenir à l'image précédente laisse le service worker installé chez les clients. Retirer le mode hors ligne demande donc de déployer un `public/sw.js` de retrait, qui vide ses caches et se désinscrit (contenu et procédure dans `docs/deployment.md`) ; les photos en attente sur un appareil y restent jusqu'à leur envoi par une version qui les connaît ; la colonne `upload_key`, nullable, est ignorée par l'ancien code.
