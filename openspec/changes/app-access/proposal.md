# Proposal

## Why

L'application Laravel est ouverte à tous : l'accueil et les cinq pages de section se lisent sans rien saisir. L'application Next.js réserve tout au groupe par un code d'accès partagé, et chaque donnée à venir (joueurs, rounds, photos de cartes) suppose cette barrière. Le déploiement sur staging (`production-deployment`, entrée 4) a aussi besoin d'une page protégée pour être exposé sur Internet. Ce changement correspond à l'entrée 3 de `ROADMAP.md`. Ses dépendances, les entrées 1 et 2, sont faites et archivées.

Il absorbe aussi la couche 2.7 du squelette (sécurité des requêtes), que l'ADR 0005 a fusionnée dans `app-access` : en-têtes de sécurité, mandataires de confiance, sessions chiffrées et limiteur de connexion.

## What Changes

- **Code d'accès partagé** : `APP_PASSCODE` lu par `config('app.passcode')`. Toute route applicative exige une session ouverte par ce code. Restent ouvertes sans session : `/login`, `GET`/`HEAD /api/version`, `/up`, et les fichiers statiques de `public/` (manifest, icônes, assets compilés).
- **Page `/login`** hors du cadre commun : marque, titre « Handicaps golf », champ « Code d'accès », bouton « Entrer », attente « Connexion… », ligne de version en bas. Un code faux affiche « Code incorrect ». Un visiteur déjà connecté qui ouvre `/login` est renvoyé à l'accueil.
- **Session de 90 jours glissante** (décision du mainteneur, 2026-10-28) : `SESSION_LIFETIME=129600`, le compteur repart à chaque requête. Next comptait 90 jours fixes depuis la saisie.
- **Changement de code = fin de toutes les sessions** (décision du mainteneur, 2026-10-28) : la session garde une empreinte du code. Quand `APP_PASSCODE` change, chaque appareil doit ressaisir le nouveau code. Next gardait les sessions ouvertes.
- **Déconnexion** : bouton « Se déconnecter » en bas de l'accueil. Il ferme la session de cet appareil seulement.
- **Refus sans session** : une page lue redirige vers `/login`, une adresse inconnue aussi. Une écriture est rejetée sans rien enregistrer : 401 pour un client qui n'est pas Inertia, renvoi vers `/login` pour une visite Inertia.
- **Démarrage refusé sans code** : sans `APP_PASSCODE`, toute requête HTTP, `/up` compris, échoue avec un message qui nomme la variable. Le healthcheck du déploiement tombe, au lieu de laisser l'application ouverte. Les commandes artisan continuent de tourner.
- **Limiteur** : 10 essais par minute et par IP sur `/login`, puis « Trop d'essais. Réessaie dans une minute. », comme dans Next.
- **En-têtes de sécurité** sur toute réponse, pages d'erreur, `/up` et JSON compris : `X-Frame-Options: DENY`, `Content-Security-Policy: frame-ancestors 'none'`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.
- **Mandataires de confiance** : `config/trustedproxy.php` lit `TRUSTED_PROXIES` (vide = aucun mandataire, jamais `'*'`), pour que le limiteur voie l'IP du client derrière le proxy de Dokploy.
- **Tests existants** : les tests qui ouvrent `/` ou une section passent par un assistant qui ouvre la session du code.
- **ADR 0007** (accès par code partagé), `ARCHITECTURE.md`, `.env.example`, `phpunit.xml` et `CHANGELOG.md` mis à jour.

### Hors périmètre

- Comptes, rôles, Fortify, confirmation de mot de passe, fin des sessions d'un compte (ADR 0005).
- HSTS et témoin de session `Secure` : ils dépendent du TLS de production et arrivent avec `production-deployment`.
- Tableau de bord d'accueil (`home-and-match-strokes`) : l'accueil reste la page d'attente actuelle, plus le bouton de déconnexion.
- Liste des sessions ouvertes, déconnexion des autres appareils depuis l'interface.
- Journal des tentatives de connexion.

## Requirements des specs Next

| Spec Next | Requirement | Sort | Raison ou adaptation |
| --- | --- | --- | --- |
| `app-access` | Accès protégé par code partagé | Porté | Les fichiers d'installation sont des fichiers statiques de `public/`, servis sans Laravel ; `/up` s'ajoute aux adresses ouvertes. Une écriture Inertia sans session est renvoyée vers `/login` au lieu d'un 401 brut, que le client Inertia afficherait dans une fenêtre d'erreur. `/api/version/x` et `/api/versions` répondent 404 sans donnée au lieu de 401 : il n'existe aucune autre route d'API, et un test garde que toute route applicative exige la session. |
| `app-access` | Ouverture de session | Porté | Session glissante au lieu de 90 jours fixes ; changement de code ajouté (décisions du mainteneur). |
| `app-access` | Démarrage sans code configuré | Porté | « Démarrage » devient « toute requête HTTP » : PHP démarre l'application à chaque requête, et les commandes artisan du build de l'image doivent tourner sans le secret. |
| `app-access` | Fermeture de session | Porté | — |
| `loading-feedback` | Autres attentes de l'app | Porté en partie | « Connexion… » ici ; « Enregistrement du joueur… » et « Suppression du round… » restent reportés à `players-and-rounds`. |

## Capabilities

### New Capabilities

- `app-access` : réserver l'application au groupe par un code partagé et une session, ouverture, refus, fermeture et rotation du code.
- `web-request-security` : en-têtes de sécurité, mandataires de confiance, chiffrement des sessions et limiteur de connexion.

### Modified Capabilities

- `loading-feedback` : ajoute l'attente « Connexion… » pendant l'ouverture de session.

## Impact

- **Code** : middleware du code d'accès et des en-têtes, contrôleur de `/login` et de la déconnexion, Form Request, `bootstrap/app.php`, `config/app.php`, `config/session.php`, nouveau `config/trustedproxy.php`, `routes/web.php` (groupe protégé, route de repli), pages `login.tsx` et `home.tsx`.
- **Tests** : nouveaux tests Pest d'accès, de session, de limiteur et d'en-têtes ; `HomePageTest`, `SectionPlaceholderTest`, `SessionStorageTest`, `DocumentHeadTest` et `PageAssetsTest` passent par la session du code. Tests Vitest de la page de connexion.
- **Configuration** : `APP_PASSCODE` et `SESSION_LIFETIME=129600` dans `.env.example` et `phpunit.xml`. Une installation locale existante doit ajouter `APP_PASSCODE` à son `.env`.
- **Dépendances** : aucune.
- **Confidentialité** : le code n'est jamais écrit dans un journal, une session ni une réponse ; la session garde une empreinte HMAC dérivée d'`APP_KEY`. Les lignes de `sessions` gardent l'IP et l'agent, comme aujourd'hui, sans identifiant de personne.
- **Retour arrière** : annuler la pull request rouvre l'application à tous ; aucune migration, aucune donnée à reprendre.
