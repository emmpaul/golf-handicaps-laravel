# 0007. Accès par code partagé

## Statut

Acceptée, 2026-10-28 (changement OpenSpec `app-access`).

## Contexte

L'application sert un groupe d'amis, sans comptes ni rôles (ADR 0005). L'application Next.js réserve tout au groupe par un code partagé saisi sur `/login`, puis par un cookie valable 90 jours fixes. Le port Laravel doit fermer de la même façon chaque page et chaque écriture à venir (joueurs, rounds, photos de cartes), et le déploiement sur staging a besoin d'une page protégée avant d'être exposé sur Internet.

Trois questions restaient ouvertes après Next. Comment une route ajoutée plus tard reste fermée sans que son auteur y pense ? Que devient une session quand le code change ? Que fait l'application quand le code manque dans l'environnement ? Le mainteneur a tranché les deux décisions de comportement le 2026-10-28 : session glissante, et fin de toutes les sessions au changement de code.

## Alternatives

- **Un groupe de routes protégées dans `routes/web.php`** : une route posée hors du groupe serait ouverte par oubli. Le test des routes la verrait, mais l'application serait ouverte par défaut.
- **Un booléen `access.granted` en session** : plus simple, mais un code changé laisserait entrer tous les appareils déjà connectés. Retirer l'accès à une personne deviendrait impossible.
- **Le code lui-même en session** : la session est chiffrée en base, mais le secret y serait recopié sur chaque ligne.
- **90 jours fixes depuis la saisie, comme Next** : un joueur régulier devrait ressaisir le code tous les trois mois sans raison.
- **Lever l'erreur de code manquant dans `AppServiceProvider::boot()`** : elle casserait `composer install`, `config:cache` et toute commande artisan lancée sans le secret, dont le build de l'image.
- **Fortify ou un garde d'authentification Laravel** : ils supposent un modèle d'utilisateur, que l'ADR 0002 a supprimé.

## Décision

- **Fermé par défaut.** `App\Http\Middleware\RequirePasscode` est ajouté au groupe `web` dans `bootstrap/app.php`. Seules `GET /login` et `POST /login` s'en retirent, par `withoutMiddleware()`. `GET /api/version` (groupe `api`) et `/up` restent hors du groupe `web` et sans session. Une route de repli renvoie 404 aux adresses inconnues ; elle passe par le groupe `web`, donc une adresse inconnue sans session redirige vers `/login`. Les adresses sous `api/` restent hors du repli et répondent 404 sans session.
- **Garde-fou par test.** `tests/Feature/ProtectedRoutesTest.php` compare à une liste littérale les routes dont la pile résolue ne contient pas `RequirePasscode`. Une route ouverte de plus fait échouer `make test-backend`.
- **Empreinte du code en session.** `App\Support\Passcode` calcule `hash_hmac('sha256', APP_PASSCODE, APP_KEY)`. À la connexion, la session reçoit cette empreinte sous `access.passcode`, sous un nouvel identifiant de session. `RequirePasscode` compare l'empreinte stockée à celle du code configuré par `hash_equals`. Quand `APP_PASSCODE` ou `APP_KEY` change, aucune session ne passe plus. Le code n'est jamais en session, ni dans le flash d'un formulaire refusé (`dontFlash('passcode')`).
- **Contrôle avant le CSRF.** `RequirePasscode` tourne après `StartSession` et avant `PreventRequestForgery`, par `appendToPriorityList`. Après une déconnexion, une écriture qui porte l'ancien jeton reçoit 401, pas 419.
- **Refus.** Une lecture de page et une visite Inertia sans session sont redirigées vers `/login`. Une autre écriture, ou une requête qui attend du JSON, reçoit 401 avec `{ "error": "Session requise" }`. L'action de la route ne tourne pas.
- **Durée glissante de 90 jours.** `config/session.php` met `lifetime` à 129600 minutes par défaut. Le gestionnaire `database` compare `last_activity` à cette durée et renvoie le cookie à chaque réponse : chaque requête repart pour 90 jours, sans code propre au projet.
- **Sessions chiffrées.** `encrypt` vaut `true` par défaut, sans dépendre d'un `.env` complet.
- **Limiteur.** `LoginRequest` accepte 10 essais par minute et par IP, sous la clé `login:<ip>`. Un essai réussi compte aussi, comme dans Next.
- **Refus de servir sans code.** `App\Http\Middleware\EnsurePasscodeConfigured`, premier contrôle de la pile globale, juste après `AddSecurityHeaders` qui pose les en-têtes de sécurité sur ce 500 aussi, lève une exception qui nomme `APP_PASSCODE` quand la variable est vide. Toute requête HTTP répond 500, `/up` compris, et le healthcheck du déploiement tombe. Les commandes artisan ne passent pas par cette pile et tournent sans le secret.
- **Déconnexion par appareil.** `POST /logout` invalide la session de ce navigateur seulement. Les autres appareils gardent la leur.

## Conséquences

- Une nouvelle page est protégée sans rien déclarer. Ouvrir une route demande un `withoutMiddleware()` explicite et la mise à jour de la liste de `ProtectedRoutesTest`, que la revue voit.
- Changer `APP_PASSCODE` est le seul moyen de retirer l'accès à quelqu'un, et il déconnecte tout le groupe. Changer `APP_KEY` a le même effet.
- Qui connaît le code entre. Aucun journal ne dit qui s'est connecté.
- Le limiteur compte par IP. Derrière un proxy mal déclaré dans `TRUSTED_PROXIES`, tout le groupe partage l'IP du proxy et se bloque après 10 essais. `production-deployment` fixe la valeur de production.
- Le tri des middlewares par priorité peut changer d'une version de Laravel à l'autre. Le test « jeton CSRF périmé », 401 et pas 419, le surveille.
- Un environnement sans `APP_PASSCODE` ne sert rien. La variable doit être posée dans Dokploy avant le premier déploiement de ce changement.
- Ajouter des comptes un jour demandera son propre changement OpenSpec et une ADR qui remplace celle-ci.
