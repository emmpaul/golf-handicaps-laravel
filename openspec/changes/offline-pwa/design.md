## Context

Voir `proposal.md` pour le pourquoi, et les specs `offline-mode`, `scorecard-import`, `loading-feedback` et `app-access` du changement pour le comportement attendu.

État actuel utile :

- `public/manifest.webmanifest` et les icônes existent (changement 11). Aucun service worker. `docker/Caddyfile` sert les fichiers de `public/` sans PHP, et `/build/*` avec `Cache-Control: public, max-age=31536000, immutable`.
- Inertia 3 sans rendu serveur : une ouverture à froid reçoit le HTML de `app.blade.php` (page initiale en JSON dans le document) ; une navigation demande la même adresse avec l'en-tête `X-Inertia: true` et reçoit du JSON. Les deux réponses ont la même adresse.
- `resources/js/lib/prefetch.ts` précharge les onglets et vide le cache Inertia après une écriture (changement 14). Ce cache est en mémoire, perdu à la fermeture.
- `ImportUpload` décode la photo dans le navigateur (`decodePhoto`, JPEG de 3000 px au plus), puis `router.post(store())` en `FormData`. `ImportController::store` appelle `AiReading::ensureEnabled()` puis `CreateImport`, et revient en arrière avec le flash `toast`.
- `RequirePasscode` répond 401 `{ "error": "Session requise" }` à une requête qui n'est pas Inertia, et redirige une visite Inertia vers `/login`. Il tourne avant `PreventRequestForgery`.
- Safari iOS (l'appareil principal du groupe) prend en charge les service workers, Cache Storage et IndexedDB avec des `Blob`, mais ni Background Sync ni Periodic Sync. Une application installée sur l'écran d'accueil n'est pas soumise à l'effacement des données après 7 jours sans visite.

## Goals / Non-Goals

**Goals:**

- Un seul chemin d'envoi des photos, en ligne comme hors ligne : toute photo passe par la file de l'appareil.
- Aucune perte silencieuse : une photo n'est effacée de l'appareil qu'après une réponse du serveur qui la crée ou la refuse pour elle-même, ou un « Retirer ».
- Aucune donnée du groupe gardée sur l'appareil après la fermeture de la session.
- Rien de neuf côté serveur au-delà de l'idempotence de l'envoi.

**Non-Goals:**

- Précharger à l'installation les pages que l'utilisateur n'a pas ouvertes (sauf `/import`).
- Résoudre des conflits d'écriture : seule la création d'un import est rejouée, et elle ne touche aucune ligne existante.
- Tester le service worker dans un navigateur réel en CI (pas d'E2E dans ce projet) : sa logique de décision est testée à part, son câblage à la passe navigateur.

## Decisions

### D1. Service worker écrit à la main, fichier statique `public/sw.js`

Un fichier JavaScript de quelques centaines de lignes, sans étape de build, servi par Caddy à la racine (portée `/` sans en-tête `Service-Worker-Allowed`). La logique de décision (quelle requête garder, quelle clé de cache, quelle réponse servir) est dans des fonctions pures du même fichier, exportées pour Vitest par un garde `typeof self.registration`.

- *Alternative* : `vite-plugin-pwa` / Workbox. Écartée : nouvelle dépendance, précache de tout le build (inutile, cf. D2), et une génération qui ne connaît ni les deux formes de réponse d'Inertia (D3) ni l'effacement à la déconnexion (D6), qu'il faudrait de toute façon écrire.
- *Alternative* : `resources/js/sw.ts` compilé par Vite. Écartée : `laravel-vite-plugin` écrit sous `/build/` avec une empreinte, ce qui donnerait une portée `/build/` ou demanderait un en-tête et une route stable ; le gain du typage ne vaut pas ce montage. Le fichier reste petit et ses fonctions pures sont testées.

`docker/Caddyfile` pose `Cache-Control: no-cache` sur `/sw.js` : le navigateur revérifie le fichier à chaque navigation et prend un nouveau service worker dès le déploiement suivant. Le service worker fait `skipWaiting()` et `clients.claim()` : il ne garde que des copies, sans état à migrer.

### D2. Assets : cache à la demande, pas de précache

`/build/*` est servi « cache d'abord » depuis le cache `assets`, rempli à la volée. Les noms portent une empreinte : une copie n'est jamais périmée. Pas de liste de précache : une page visitée en ligne a déjà chargé ses morceaux, et c'est la seule qu'on relit hors ligne (spec « Page visitée relue hors ligne »). Le cache est borné à 300 entrées, les plus anciennes effacées d'abord, pour que les versions successives ne s'accumulent pas.

Exception, `/import` (spec « Écran Importer disponible hors ligne ») : au premier chargement en ligne avec une session, l'application demande au service worker de garder `/import` sous ses deux formes (D3) ; le JSON Inertia de la réponse nomme le composant, et l'application précharge son morceau JavaScript par `import()` de `resources/js/pages/import/index.tsx`, ce qui le fait passer dans le cache `assets`.

### D3. Pages : « réseau d'abord », clé de cache par forme de réponse

Pour un `GET` de même origine hors `/build/`, `/login`, `/api/`, `/up` et des photos (`/rounds/*/photo`, `/imports/*/photo`) :

1. demander au réseau ;
2. sur une réponse `200` non redirigée, la garder dans le cache `pages`, puis la rendre ;
3. sur un échec réseau, rendre la copie gardée, ou à défaut : `/offline.html` (gardé à l'installation) pour un document, une réponse `503` avec l'en-tête `X-Offline: miss` pour une visite Inertia.

Une même adresse a deux réponses (HTML et JSON Inertia). La clé de cache ajoute donc la forme à l'adresse : `?__sw=inertia` pour une requête qui porte `X-Inertia`, l'adresse nue pour un document. La réponse gardée reçoit l'en-tête `X-Offline-Stored-At` (instant ISO 8601 de la copie).

Une réponse rendue depuis la copie porte en plus `X-Offline: hit`. Le service worker envoie aussi aux pages ouvertes le message `{ type: 'served-from-cache', url, storedAt }` : le bandeau (D5) l'écoute, car le client Inertia ne donne pas accès aux en-têtes d'une réponse, et le document d'une ouverture à froid non plus.

- *Alternative* : « cache d'abord, puis mise à jour » (stale-while-revalidate). Écartée : en ligne, l'index affiché doit être celui du serveur (spec « Pages visitées gardées sur l'appareil »), et le préchargement du changement 14 rend déjà la navigation rapide.
- *Alternative* : en-tête `Vary: X-Inertia` et appariement par `Vary` de Cache Storage. Écartée : Laravel ne pose pas `Vary` sur ces réponses, et l'appariement par `Vary` diffère selon les navigateurs ; une clé explicite se teste.

Un `409` d'Inertia (version des assets changée) n'est pas gardé (pas un `200`) ; le client recharge la page, qui repasse par le réseau.

### D4. Navigation vers une page sans copie, écritures hors ligne

`resources/js/lib/offline.ts` écoute les événements du routeur Inertia :

- une réponse `503` avec `X-Offline: miss` (événement `httpException`, où la réponse est lisible) annule l'affichage de l'erreur et montre le toast « Page indisponible hors ligne. » ;
- une visite qui n'est pas un `GET` et finit en `networkError` montre « Hors ligne : rien n'a été envoyé. ». Inertia ne touche pas l'état du formulaire sur une erreur réseau : la saisie reste à l'écran.

L'autosave de la correction d'un import (`autosave.ts`) garde son indicateur d'enregistrement actuel ; il n'est pas modifié par ce changement.

### D5. Bandeau « Hors ligne »

Composant `OfflineBanner` dans `app-layout.tsx`, sous la rangée du haut. Il s'affiche quand `navigator.onLine` est faux ou que la dernière page rendue vient de la copie (message `served-from-cache` dont l'`url` est la page courante). Il lit l'instant dans `storedAt` et le formate dans le fuseau d'affichage par une nouvelle fonction `formatStoredAt` de `format.ts` (« 30 oct. à 21:04 », jour et mois abrégé comme `formatDayMonthShort`). Il se cache à l'événement `online` et à la première réponse réseau d'une visite. Région `role="status"` pour l'annonce unique. Couleurs et typographie : tokens existants seulement (`DESIGN.md` ajoutera le bandeau et l'écran hors ligne).

`public/offline.html` est autonome : HTML et CSS en ligne, aux valeurs des tokens (fond `#060709`, encre `#F4F5F7`), sans police distante, sans donnée. Le test de `theme-sources.test.ts` ne parcourt que `resources/js` ; un test Pest compare le fichier aux littéraux de la spec.

### D6. Effacement des pages gardées

Deux déclencheurs, tous deux dans le service worker, qui vide le cache `pages` (le cache `assets` ne contient aucune donnée du groupe et reste) :

- la réponse de `POST /logout` passe par le service worker, qui vide `pages` dès qu'elle arrive ;
- toute réponse réseau qui redirige vers `/login` (document ou visite Inertia, qui reçoit `409` avec `X-Inertia-Location` ou une redirection), et toute réponse `401` `{ "error": "Session requise" }`, vident aussi `pages`.

Le service worker ne voit pas une session expirée tant qu'aucune requête n'atteint le serveur : hors ligne, une copie reste lisible jusqu'à la prochaine réponse réseau qui refuse la session. C'est la limite acceptée (Risques).

La file des photos (IndexedDB) n'est pas effacée (décision du mainteneur à la proposition : « garder et demander le code »).

### D7. File des photos dans IndexedDB, envoi par l'application

Base IndexedDB `golf-offline`, magasin `photos` : `{ key, blob, takenAt }` : `key` UUID tiré par `crypto.randomUUID()`, `blob` le JPEG préparé, `takenAt` l'instant ISO 8601 de la mise en file, indexé par `takenAt`. Module `resources/js/lib/photo-queue.ts` : `enqueue`, `list`, `remove`, `flush`, et un abonnement aux changements (`BroadcastChannel` entre onglets) pour la liste « EN ATTENTE D'ENVOI ».

`ImportUpload` met toute photo préparée en file, puis appelle `flush()`. En ligne, l'utilisateur voit le même parcours qu'avant (« Envoi de la photo… » puis le toast). L'envoi n'est fait que par la page, jamais par le service worker : Safari iOS n'a pas Background Sync, et la page sait afficher `/login`.

`flush()` est appelé à l'ouverture de l'application, à l'événement `online`, au retour au premier plan (`visibilitychange`), et après une connexion réussie (la page d'accueil après `/login` est une ouverture). Un verrou (`navigator.locks`, sinon un drapeau du module) empêche deux envois en parallèle entre onglets. Les photos partent une à une, de la plus ancienne à la plus récente :

| Réponse                                            | Effet                                                                    |
| -------------------------------------------------- | ------------------------------------------------------------------------ |
| `201` ou `200` (créé, ou clé déjà reçue)            | photo effacée de la file, suivante                                       |
| `422` (validation, ou lecture désactivée)          | photo effacée, refus du serveur affiché avec « Saisie manuelle », suivante |
| `401` Session requise                              | arrêt, photo gardée, `router.visit('/login')`                            |
| `419` (jeton CSRF périmé)                          | un seul nouvel essai après un `GET` de la page courante, qui renouvelle le témoin `XSRF-TOKEN` ; sinon comme une erreur réseau |
| erreur réseau, `5xx`, délai de 60 s dépassé         | arrêt, photo gardée                                                      |

Le refus « lecture désactivée » est aujourd'hui un `ImportReadingDisabled` (une `DomainException`), rendu en JSON hors Inertia par `422 { "error": message }` (`bootstrap/app.php`) : il tombe dans la ligne `422`.

L'envoi se fait par `fetch` en `FormData` (`photo`, `upload_key`), avec `Accept: application/json`, `X-Requested-With: XMLHttpRequest` et `X-XSRF-TOKEN` lu dans le témoin, sans `X-Inertia`. Après le dernier envoi réussi, l'application recharge la liste par `router.reload({ only: ['imports'] })` quand l'écran Importer est ouvert, et montre le toast « N photos envoyées. » (ou, pour un envoi unique depuis l'écran en ligne, le message actuel « Lecture en file d'attente. Tu peux envoyer la photo suivante. »).

`navigator.storage.persist()` est demandé à la première mise en file.

- *Alternative* : garder l'envoi Inertia en ligne et la file seulement hors ligne. Écartée : deux chemins, et `navigator.onLine` ment (vrai sur un Wi-Fi sans Internet) ; la file couvre aussi l'envoi qui échoue en route.
- *Alternative* : envoi par le service worker (`fetch` au retour du réseau). Écartée : rien ne réveille le service worker sur iOS, et il ne peut pas mener à `/login`.

### D8. Idempotence côté serveur : `imports.upload_key`

Migration : colonne `upload_key` `uuid` nullable, index unique. `StoreImportRequest` exige `upload_key` (`required`, `uuid`). `CreateImport::handle($photo, $uploadKey)` :

1. cherche un import de cette clé ; s'il existe, le rend sans rien écrire ;
2. sinon écrit la photo, insère l'import avec la clé, pousse le job après le commit comme aujourd'hui ;
3. une violation de l'unique (deux envois simultanés de la même clé) efface la photo écrite et rend l'import gagnant.

Un import retiré (supprimé) libère sa clé avec sa ligne : renvoyer la même clé recrée un import. Le client efface sa photo après la première réponse, donc ce cas ne vient que d'un rejeu très tardif ; accepté.

`ImportController::store` garde sa réponse Inertia (retour arrière et toast) pour une visite Inertia, et répond `201 { "id": … }` (`200` pour une clé déjà reçue) à une requête JSON. La photo d'une clé déjà reçue n'est pas décodée (pas de `WebpPhoto`) : la réponse est immédiate.

- *Alternative* : utiliser la clé du client comme `id` de l'import. Écartée : le client choisirait la clé primaire, et le nom du fichier photo en dépend.

`ImportFactory` reçoit l'état `withUploadKey()`. Le seeder `DemoImportsSeeder` reste sans clé (imports d'avant ce changement). Pas de nouveau modèle.

### D9. ADR 0016

Nouvelle ADR « Mode hors ligne par service worker écrit à la main » : D1, D3, D6, D7 (envoi par la page, file IndexedDB) et D8. Ligne ajoutée à `docs/adr/README.md`.

## Risks / Trade-offs

- [Une copie reste lisible hors ligne après l'expiration de la session ou un changement du code, jusqu'à la prochaine réponse réseau] → accepté : même appareil, même groupe ; la déconnexion explicite efface tout de suite, et la première réponse en ligne qui refuse la session aussi.
- [Données du groupe gardées sur l'appareil] → mentionné dans la proposition ; aucune photo de round gardée ; cache `pages` borné à 100 entrées.
- [HTML neuf et JSON Inertia d'une ancienne version mélangés hors ligne après un déploiement] → le HTML gardé et le JSON d'une même page sont écrits ensemble à chaque visite ; une page dont le morceau JavaScript manque affiche l'écran de repli d'Inertia. Accepté : rare, et la visite en ligne suivante répare.
- [Un service worker défectueux bloque l'application chez tout le monde] → « réseau d'abord » pour les pages, `no-cache` sur `/sw.js`, et le `sw.js` de retrait documenté dans `docs/deployment.md` (Migration Plan).
- [`navigator.onLine` vrai sans Internet] → la décision ne repose jamais sur lui seul : l'échec de `fetch` fait foi ; `onLine` ne sert qu'au bandeau et au message « Photo gardée… » immédiat.
- [Photo gardée envoyée par un autre code d'accès ou un autre serveur] → même origine seulement ; IndexedDB est par origine.
- [Envoi interrompu par la fermeture de l'application au milieu d'un `fetch`] → la photo reste en file, la clé évite le doublon (D8).
- [Mode développement : Vite sert les assets depuis une autre origine] → l'application n'enregistre le service worker que hors serveur Vite (`import.meta.env.PROD`) ; la passe navigateur de ce changement se fait sur les assets compilés (`make build`, sans `make dev`).

## Migration Plan

1. Déploiement ordinaire : la migration ajoute la colonne nullable ; l'ancien code l'ignore.
2. Au premier chargement après le déploiement, chaque client installe le service worker ; aucune action du mainteneur.
3. Retour à l'image précédente : le service worker reste installé. L'ancien serveur ignore le champ `upload_key` et répond à l'envoi par une redirection, que `fetch` suit jusqu'à un `200` : l'envoi depuis la file marche encore, sans idempotence. Pour retirer le mode hors ligne : déployer un `public/sw.js` de retrait (`install` → `skipWaiting`, `activate` → vider tous les caches, `registration.unregister()`, recharger les clients), documenté dans `docs/deployment.md`.
