# Design

## Context

Voir `proposal.md` pour le besoin et `specs/` pour les comportements. État constaté dans le code :

- `App\Domain\DomainException` a un rendu dans `bootstrap/app.php`, mais rien ne l'exclut du signalement : chaque refus métier passe par `report()`.
- `Worker::runJob` signale l'exception de chaque tentative. Pour un job réessayé, `JobReleasedAfterException` part juste avant ce signalement (`Worker::handleJobException`).
- `ReadScorecardImport` a `tries = 2`. Trois chemins appellent `FailImport` sans lever d'exception : brouillon inexploitable, budget atteint, lecture désactivée.
- La connexion `pgsql` a `mask_bindings_in_exception_messages` (`tests/Feature/QueryExceptionLogTest.php`) : les valeurs liées ne figurent pas dans le SQL d'un `QueryException`. Le texte que renvoie PostgreSQL n'est pas couvert : `Key (name)=(Manu) already exists` y garde la valeur.
- File et cache sont en base. `docker/entrypoint.sh` lance `php artisan optimize` au démarrage : la configuration est mise en cache et ne peut pas contenir de closure.
- `php.ini-production` (image) met `zend.exception_ignore_args` à `On` : les arguments ne figurent pas dans les piles.

## Goals / Non-Goals

**Goals :**

- un seul point de contrôle de ce qui sort vers Sentry, testé sur le vrai parcours d'envoi ;
- aucune table, aucun job, aucune dépendance à la base pour alerter ;
- comportement inchangé sans DSN.

**Non-Goals :**

- regrouper ou limiter les alertes dans l'application : Sentry regroupe les événements en issues et n'envoie un courriel qu'à la création de l'issue ou à sa réouverture ;
- associer une alerte à un import précis : l'écran des imports et les journaux le disent déjà.

## Decisions

### D1. SDK officiel `sentry/sentry-laravel`, branché par `Integration::handles`

Son `reportable` reçoit toutes les exceptions signalées, et il gère déjà le cycle de vie d'Octane (`EventHandler::subscribeOctaneEvents`). Le handler interne de Laravel écarte déjà 404, 401, validation et CSRF, et ces exceptions n'arrivent jamais à Sentry.

Écarté : un hook maison vers Telegram, sans dépendance ni compte tiers, mais sans regroupement ni historique, et le mainteneur a choisi Sentry. Écarté : Sentry auto-hébergé, service de plus à faire tourner et sauvegarder.

L'assistant de configuration de Sentry pour agents n'est pas utilisé : la configuration de cette application est écrite à la main pour rester minimale.

### D2. Un seul filtre, `before_send`, par liste d'autorisation

`App\Integrations\Sentry\SentryEventFilter::beforeSend(Event, ?EventHint): ?Event` est le seul point de contrôle. Il abandonne l'événement si l'exception est une `DomainException` ou une exception de tentative réessayée (D4). Sinon, il construit un événement neuf, qui reçoit seulement :

- l'identifiant, l'horodatage, le niveau, l'environnement, le nom et la version du SDK ;
- la méthode et le chemin de la requête, sans query string, corps, en-têtes ni témoins ;
- pour un événement avec exception, le type de chaque exception, un message vide, le type de mécanisme et le drapeau `handled`, et les cadres de pile réduits à la fonction, au fichier, à la ligne et à `in_app` ;
- pour un événement sans exception, le message sans `params` ni `formatted`, et l'empreinte.

Tout le reste reste derrière : fil d'Ariane, utilisateur, `extra`, étiquettes, tous les contextes (`os` et `runtime` compris), nom d'hôte, version de l'application, liste des paquets.

La liste autorise plutôt qu'elle n'interdit : une donnée ajoutée par une future version du SDK ne sort pas toute seule. Le message d'exception est retiré malgré le masquage SQL, à cause du texte de PostgreSQL et des messages de tiers. Le prix : pour lire le message, il faut ouvrir les journaux à l'heure de l'alerte. L'exception y reste, puisque le filtre ne touche que l'envoi à Sentry.

Écarté : `send_default_pii=false` seul, qui laisse le message d'exception. Écarté : `data_collection`, dont les clés non renseignées reprennent des défauts permissifs. Écarté : le filtrage côté serveur de Sentry, qui s'applique une fois la donnée sortie.

`before_send` est donné par un tableau `[SentryEventFilter::class, 'beforeSend']` : une closure casserait la mise en cache de la configuration.

### D3. Configuration figée, sans variable pour rouvrir l'envoi

`config/sentry.php`, publié puis réduit, n'emploie `env()` que pour le DSN. Valeurs écrites en dur : `send_default_pii=false`, `traces_sample_rate=null`, `profiles_sample_rate=null`, `enable_logs=false`, `enable_metrics=false`, toutes les clés de `breadcrumbs` à `false`, `http_connect_timeout=1`, `http_timeout=2`. Ces réglages limitent ce que le SDK collecte. Le filtre de D2 reste le contrôle, et son test tourne avec cette configuration.

### D4. Tentatives réessayées écartées sans toucher aux journaux

Un écouteur de `JobReleasedAfterException` retient l'exception dans `App\Integrations\Sentry\RetriedJobExceptions`, un singleton à `WeakMap`. Le filtre abandonne l'événement de cette exception-là. Le journal sur la sortie d'erreur reste intact, ce que la spec `deployment` demande.

Écarté : `Worker::$reportJobExceptions = false`, qui retirerait aussi les tentatives des journaux. Écarté : capturer depuis `Queue::failing` en excluant tout le worker, qui traiterait à part les exceptions signalées hors d'un job.

Le signalement de `TimeoutExceededException`, juste avant que Laravel tue le worker, passe à Sentry même si une tentative reste : un job de 120 s figé mérite l'alerte. Le job peut donc donner deux alertes s'il se fige deux fois, sous deux types distincts au plus (`TimeoutExceededException`, `MaxAttemptsExceededException`).

### D5. Échecs sans exception signalés par le job

`ReadScorecardImport` appelle `App\Integrations\Sentry\ImportFailureAlert::send(string $reason)` dans ses trois chemins, à côté de `FailImport`. Le message est la raison telle qu'affichée, `Import en échec : <raison>`, et l'empreinte est fixée à `['import-failed', <raison>]`. Niveau `error`. Les raisons sont les constantes `ReadingFailure` et `ImportReadingDisabled::MESSAGE`, sans donnée de joueur.

Écarté : l'appel dans `FailImport`. L'action reste libre d'intégration (architecture du domaine), et `failed()` passe aussi par elle : un échec par exception donnerait deux alertes.

Toute erreur de l'envoi est absorbée par le SDK, en silence : `config/sentry.php` ne pose aucune option `logger`, et rien n'arrive au journal. Un test le confirme avec un transport qui échoue (spec « Service d'alerte injoignable »).

### D6. Tous les jobs, pas seulement ceux des imports

L'entrée 18 parle des jobs d'import. Le filtre ne distingue pas les jobs, donc `LookUpCourseRating` en échec définitif alerte aussi : une panne de SGT reste une issue unique et l'exclure demanderait du code. Ses issues « Saisie manuelle » ne lèvent rien et n'alertent pas.

### D7. DSN mal formé : arrêt au démarrage

`AppServiceProvider` valide le DSN dans `boot`, à côté de `ensureAiConfiguration` et `ensureSgtConfiguration`, avec `Sentry\Dsn::createFromString`. Le SDK ne lève pas sur `pas-un-dsn` : il écarte la valeur et n'envoie rien. L'application lève donc une `InvalidArgumentException` neuve, dont le message commence par `SENTRY_LARAVEL_DSN` et ne répète pas la valeur. Valeur vide ou absente : aucune vérification. `migrate` reçoit la variable pour échouer avant `web` et `worker`.

Écarté : couper l'envoi en silence sur une valeur invalide, ce qui laisserait croire à des alertes actives.

### D8. Canal et règles d'alerte réglés dans Sentry

Le canal est le courriel du compte Sentry, seul inclus au plan gratuit. Le mainteneur active l'alerte sur la nouvelle issue et sur la réouverture d'une issue résolue. `docs/deployment.md` le décrit, avec la consigne de marquer une issue « Resolved » une fois corrigée : sinon sa prochaine occurrence n'envoie pas de courriel.

## Risks / Trade-offs

- [Issue laissée ouverte, plus aucun courriel] → consigne de D8. Par exemple, le budget atteint en octobre reste silencieux en novembre si l'issue n'a pas été résolue.
- [Quota gratuit épuisé par une rafale] → les événements en trop sont perdus, l'application n'en souffre pas, et le premier courriel est déjà parti.
- [Plan gratuit modifié par Sentry] → retirer `SENTRY_LARAVEL_DSN` coupe tout. `SentryEventFilter` et `ImportFailureAlert` sont les seuls fichiers à remplacer pour changer d'outil.
- [Serveur Sentry lent pendant une erreur] → 3 s au plus par envoi d'après D3, sur une requête déjà en échec.
- [Champ ajouté par le SDK] → l'événement neuf ne le copie pas, le test du filtre cherche des valeurs témoins dans tout l'événement sérialisé, et une mise à jour du SDK le rejoue.
- [Message absent de l'alerte, diagnostic plus long] → heure de l'alerte et type de l'exception pour retrouver la ligne dans `docker logs`.

## Migration Plan

1. Déployer l'image sans DSN : aucun changement visible.
2. Créer le projet Sentry (« Set up manually », PHP/Laravel), poser `SENTRY_LARAVEL_DSN` dans Dokploy, redéployer.
3. Lancer `php artisan sentry:test` dans `worker`, puis contrôler la réception et le courriel. Cette commande prouve l'envoi et le courriel, pas le contenu : `TestCommand` construit son propre client, et son événement ne passe pas par le filtre.
4. Contrôler le contenu avec la commande `tinker` de `docs/deployment.md`, qui signale une vraie exception.

Retour arrière : retirer la variable et redéployer. Pas de migration de base.
