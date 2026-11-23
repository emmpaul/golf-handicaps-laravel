# 0017. Alertes d'erreur par Sentry

## Statut

Acceptée, 2026-11-03 (changement OpenSpec `error-alerts`).

## Contexte

Une exception en production ou un import en échec ne prévient personne. Le mainteneur l'apprend en lisant `docker logs`, ou quand un joueur le lui dit.

Trois contraintes cadrent la décision :

- Pas d'abonnement mensuel. Le plan gratuit de Sentry, relevé sur sa page de tarifs et son écran de création de projet le 2026-11-03, coûte 0 $ sans carte : un utilisateur, 5 000 erreurs par mois après 14 jours de volume illimité, 30 jours d'historique, alertes par courriel.
- Une alerte ne doit contenir ni nom de joueur ni score. C'est la première sortie de données d'erreur vers un tiers. Le masquage de `mask_bindings_in_exception_messages` ne suffit pas : le texte que renvoie PostgreSQL, `Key (name)=(Manu) already exists`, garde la valeur.
- Trois échecs d'import ne lèvent aucune exception : réponse de l'IA inexploitable, budget du mois atteint, lecture désactivée. Ils doivent alerter aussi.

## Alternatives

- **Hook maison vers Telegram** : ni dépendance ni compte tiers, mais ni regroupement ni historique. Le mainteneur a choisi Sentry.
- **Sentry auto-hébergé** : un service de plus à faire tourner et à sauvegarder.
- **`send_default_pii=false` seul** : le message d'exception sort quand même.
- **Option `data_collection` du SDK** : ses clés non renseignées reprennent des défauts permissifs.
- **Filtrage côté serveur de Sentry** : il s'applique une fois la donnée sortie.
- **Assistant de configuration de Sentry et `sentry:publish`** : la commande écrit dans `.env`, et la configuration de cette application s'écrit à la main pour rester minimale.

## Décision

- **`sentry/sentry-laravel` (4.29, avec le SDK `sentry/sentry` 4.35), branché par `Integration::handles` dans `bootstrap/app.php`.** Il reçoit toute exception signalée et suit le cycle de vie d'Octane. Le handler de Laravel écarte déjà 404, 401, validation et CSRF, qui n'arrivent donc pas à Sentry.
- **`App\Integrations\Sentry\SentryEventFilter::beforeSend` est le seul contrôle de ce qui sort.** Il abandonne une `DomainException` et une tentative de job que la file réessaiera. Pour le reste, il construit un événement neuf et n'y copie que l'identifiant, l'horodatage, le niveau, l'environnement, le nom du SDK, la méthode et le chemin de la requête, puis soit les exceptions (type, cadres de pile réduits à la fonction, au fichier et à la ligne, type de mécanisme, drapeau `handled`), soit le message et l'empreinte d'un événement sans exception. Le message d'une exception part vide. Un champ qu'une future version du SDK ajouterait à l'événement reste derrière, puisque rien ne le copie.
- **`config/sentry.php` n'emploie `env()` que pour `SENTRY_LARAVEL_DSN`.** Aucune variable ne rouvre ce que le fichier ferme : traces, profils, journaux, métriques et fil d'Ariane sont coupés en dur. `before_send` y est un tableau, car une closure casserait `php artisan optimize`. Connexion 1 s et réponse 2 s au plus par envoi.
- **`App\Integrations\Sentry\RetriedJobExceptions` retient l'exception d'une tentative réessayée**, depuis l'événement `JobReleasedAfterException`, dans une `WeakMap`. Le journal garde cette tentative et Sentry ne la reçoit pas.
- **`ReadScorecardImport` appelle `ImportFailureAlert::send` dans ses trois chemins sans exception**, avec le message `Import en échec : <raison>` et l'empreinte `['import-failed', <raison>]`. L'appel reste dans le job : le domaine ne connaît pas Sentry (règle d'architecture), et `FailImport` sert aussi au chemin `failed()`, qui aurait envoyé une seconde alerte.
- **Tous les jobs alertent**, pas seulement ceux des imports. `LookUpCourseRating` en échec définitif donne une alerte. Une recherche finie en « Saisie manuelle » n'en donne pas.
- **Un DSN mal formé arrête le démarrage.** `AppServiceProvider` lève une `InvalidArgumentException` dont le message commence par `SENTRY_LARAVEL_DSN`, sans répéter la valeur. `migrate` échoue donc avant `web` et `worker`. Une valeur vide coupe l'envoi.
- **Canal et règles se règlent dans Sentry.** Le canal est le courriel du compte, seul inclus au plan gratuit. Le mainteneur active l'alerte sur la nouvelle issue et sur la réouverture d'une issue résolue. L'application n'envoie aucun courriel et ne limite rien.

## Conséquences

- Une alerte ne donne ni le message ni le contexte. Pour les lire, il faut ouvrir `docker logs` à l'heure de l'alerte (`docs/deployment.md`, « Alertes d'erreur »).
- Une issue laissée ouverte n'envoie plus de courriel. Le budget atteint en octobre reste silencieux en novembre si personne n'a marqué l'issue « Resolved ».
- `php artisan sentry:test` ne passe pas par le filtre : la commande construit son propre client, avec son propre `before_send`. Son événement prouve que le DSN et le courriel marchent, pas ce que le filtre laisse sortir. Il garde son message, le code source autour des cadres, le nom d'hôte et la liste des paquets. Ce texte est celui du SDK et ne contient aucune donnée de l'application.
- `web` fait désormais une requête sortante vers Sentry quand une erreur survient, 3 s au plus sur une requête déjà en échec. Une panne de Sentry ne change ni la réponse ni le résultat d'un job.
- Un job figé peut donner deux alertes, sous `TimeoutExceededException` et `MaxAttemptsExceededException`.
- Une rafale qui épuise le quota gratuit perd les événements en trop. L'application n'en souffre pas, et le premier courriel est déjà parti.
- Base arrêtée, `worker` redémarre en boucle et chaque démarrage envoie un événement : huit en une vingtaine de secondes à la mesure, puis l'écart augmente. Ces événements comptent dans le quota du plan gratuit.
- Un envoi vers Sentry en échec ne laisse aucune trace dans les journaux, car le SDK n'a pas de logger. `php artisan sentry:test` reste le moyen de contrôler l'envoi.
- Les erreurs JavaScript du navigateur, la disponibilité de l'hôte et les copies vers R2 restent sans alerte.
- Changer d'outil revient à remplacer `SentryEventFilter` et `ImportFailureAlert`, et à retirer le paquet.
- Retour arrière : retirer `SENTRY_LARAVEL_DSN` dans Dokploy et redéployer coupe tout envoi. Aucune migration.
