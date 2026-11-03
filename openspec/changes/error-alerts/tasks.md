# Tasks

Chaque test nomme, dans un commentaire d'en-tête, la mutation qui le fait passer au rouge, comme `tests/Feature/QueryExceptionLogTest.php`. Les tests lisent les événements par un transport Sentry de test branché sur un vrai client construit avec `config/sentry.php` : le filtre tourne pour de vrai.

## 1. Dépendance et configuration

- [x] 1.1 Installer `sentry/sentry-laravel` par Sail, et vérifier que la résolution accepte PHP 8.5 et Laravel 13 (`composer why-not` si elle refuse) et que `make test-backend` reste vert.
- [x] 1.2 Publier `config/sentry.php` et le réduire selon la décision D3 : seul le DSN lit `env()`. Poser `SENTRY_LARAVEL_DSN` vide et forcé dans `phpunit.xml`. Vérifier par `make artisan config:cache` (ou la cible équivalente) que la configuration se met en cache.
- [x] 1.3 Ajouter un helper de test qui branche un client Sentry avec la configuration du projet, un DSN factice et un transport qui garde les événements, et vérifier qu'une capture y arrive bien.

## 2. Filtre de confidentialité

- [x] 2.1 RED puis GREEN : `SentryEventFilter` vide le message d'exception. Test : `report()` d'une exception de message `Key (name)=(Manu) already exists`, type attendu en littéral et `Manu` absent de l'événement sérialisé. Mutation : garder le message.
- [x] 2.2 RED puis GREEN : une vraie requête `POST` en exception, avec query string, corps, témoin et en-tête témoins, ne laisse que la méthode et le chemin en littéraux. Couvrir `POST /login` avec `birdie-2026`. Mutation : garder le `query_string` ou les `data`.
- [x] 2.3 RED puis GREEN : variables de cadre, fil d'Ariane, utilisateur et `extra` vidés, avec un journal et une requête SQL émis avant l'exception et cherchés dans l'événement sérialisé. Mutation : retirer le vidage du fil d'Ariane du filtre tout en gardant `breadcrumbs` actif dans une configuration de test.
- [x] 2.4 Brancher `Integration::handles` dans `bootstrap/app.php` et `before_send` dans la configuration. Test : un refus `DomainException` rendu par `tests/Feature/DomainExceptionRenderingTest.php` garde son toast et n'envoie aucun événement, et une exception non prévue en envoie exactement un. Mutation : retirer le test `DomainException` du filtre.
- [x] 2.5 Test : sans DSN, une exception ne produit aucune requête sortante et la réponse reste 500. Test du journal : l'exception filtrée garde son message dans le journal (canal capturé). Mutation : retourner `false` du handler après la capture (`null` ne stoppe pas le journal).

## 3. Jobs

- [x] 3.1 RED puis GREEN : écouteur de `JobReleasedAfterException` et `RetriedJobExceptions`. Test avec le vrai worker (`queue:work --once` sur la connexion `database`, temps avancé après le `backoff`) : une erreur puis une réussite n'envoient aucun événement et l'erreur reste dans le journal. Mutation : retirer l'écouteur.
- [x] 3.2 Test avec le vrai worker : deux erreurs de `ReadScorecardImport` envoient exactement un événement et l'import finit en « Échec » avec « Lecture IA indisponible ». Mutation : abandonner aussi l'exception finale.
- [x] 3.3 Test avec le vrai worker : `LookUpCourseRating` sans réponse de SGT (`Http::fake`) envoie exactement un événement, et la ligne finit avec « SGT injoignable ». Une recherche finie en « Saisie manuelle » n'en envoie aucun. Mutation : abandonner tout événement pris dans un job de recherche.

## 4. Imports en échec sans exception

- [x] 4.1 RED puis GREEN : `ImportFailureAlert` et son appel dans `ReadScorecardImport`. Trois tests, réponse inexploitable, budget atteint, lecture désactivée : un événement, message exact en littéral, empreinte `['import-failed', <raison>]`, import en « Échec » avec la raison. Mutation : retirer l'appel d'un des trois chemins, ou changer le texte du message.
- [x] 4.2 Test : deux imports au-delà du budget donnent deux événements de même empreinte. Mutation : inclure l'identifiant de l'import dans l'empreinte.
- [x] 4.3 Test : avec un transport qui échoue ou lève, le budget atteint laisse l'import en « Échec » et le job finit sans exception. Mutation : retirer l'absorption si le SDK ne la fait pas ; sinon, relever dans le test que l'erreur vient bien du transport.
- [x] 4.4 Ajouter à `tests/Arch/ArchitectureTest.php` la règle : `App\Domain` n'utilise ni `Sentry` ni `App\Integrations\Sentry`. Vérifier le rouge en ajoutant temporairement un `use` dans `FailImport`.

## 5. Stack

- [x] 5.1 RED puis GREEN : validation du DSN dans `AppServiceProvider`. Tests : `pas-un-dsn` lève une `InvalidArgumentException` dont le message commence par `SENTRY_LARAVEL_DSN`, un DSN vide et un DSN valide passent. Mutation : retirer la validation.
- [x] 5.2 Ajouter `SENTRY_LARAVEL_DSN: ${SENTRY_LARAVEL_DSN:-}` au bloc d'environnement commun de `docker-compose.yml` et un commentaire à `.env.example`. Vérifier par `make image image-smoke` que `web` et `worker` deviennent sains sans la variable.
- [x] 5.3 Vérifier sur la stack de fumée avec un DSN factice que l'échec du `sentry:test` ou d'une exception ne casse ni `web` ni `worker`, et que le temps de réponse d'une exception reste sous 5 s. Vérifier avec `SENTRY_LARAVEL_DSN=pas-un-dsn` que `migrate` sort en erreur et que `web` et `worker` restent sur `Created`.

## 6. Documentation

- [x] 6.1 Écrire `docs/adr/0017-alertes-d-erreur-par-sentry.md` (D1, D2, D8 et les alternatives écartées) et sa ligne dans `docs/adr/README.md`. Vérifier que le lien du tableau s'ouvre.
- [x] 6.2 Ajouter à `docs/deployment.md` la ligne `SENTRY_LARAVEL_DSN` du tableau des variables et une section « Alertes d'erreur » : création du projet, règles d'alerte, `php artisan sentry:test`, consigne « Resolved », retrouver le message dans `docker logs`, limites (hôte arrêté, navigateur, copies R2). Vérifier que chaque commande a été lancée sur la stack de fumée.
- [x] 6.3 Ajouter le suivi d'erreurs à la section production d'`ARCHITECTURE.md` et remplacer, dans l'entrée 18 de `ROADMAP.md`, « Reste à choisir l'outil et le canal » par le choix fait. Inscrire les changements sous `## [Unreleased]` de `CHANGELOG.md`, en anglais, avec la dépendance. Vérifier par `tests/Feature/VersionFileTest.php`.

## 7. Vérification d'ensemble

- [x] 7.1 Lancer `make check` : vert, sans règle affaiblie.
- [x] 7.2 Passe navigateur avec browser-use (`make up`, `make dev`, `make browser`, puis `make dev-stop`) : connexion, erreur de validation sous le champ pour un joueur en double, toast d'un refus du domaine (`POST /imports/{id}/reading` : « Cet import n’est pas en échec »), liste des imports. Rien ne change à l'écran. Captures dans `reports/captures/error-alerts/`.
- [x] 7.3 Relire chaque scénario de `specs/error-alerts/spec.md` et `specs/deployment/spec.md`, et citer le test ou le contrôle qui le couvre. Signaler ceux qui restent à vérifier en production.

## Workflow follow-up

- Le mainteneur crée le projet Sentry (« Set up manually », PHP/Laravel), active les deux règles d'alerte, pose `SENTRY_LARAVEL_DSN` dans Dokploy et redéploie.
- Le mainteneur lance `php artisan sentry:test` dans `worker` et dans `web`, ce qui prouve l'envoi et le courriel : `TestCommand` construit son propre client, et son événement ne passe pas par le filtre. Il contrôle ensuite le contenu avec la commande `tinker` de `docs/deployment.md`, qui signale une vraie exception. Noter la date dans `docs/deployment.md`.
- Archiver le changement dans une seconde pull request.
