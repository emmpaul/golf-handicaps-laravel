# Proposal

## Why

Une exception en production ou un import en échec ne prévient personne : le mainteneur ne l'apprend qu'en lisant `docker logs` ou quand un joueur le lui dit. C'est l'entrée 18 de `ROADMAP.md`, sans dépendance. L'outil et le canal y restaient à choisir, sans abonnement mensuel à payer : ce sera le plan gratuit de Sentry, avec une alerte par courriel.

## What Changes

- Envoyer à Sentry les exceptions non prévues du web et du worker, ainsi que les jobs tombés en échec définitif.
- Alerter aussi sur les trois échecs d'import qui ne lèvent aucune exception : réponse de l'IA inexploitable, budget du mois atteint, lecture désactivée.
- Ne rien envoyer pour un refus métier (le toast d'erreur) ni pour une tentative de job qui sera réessayée.
- Filtrer chaque événement avant l'envoi : ni message d'exception, ni corps de requête, ni query string, ni témoin, ni variable locale, ni fil d'Ariane. Les journaux sur la sortie d'erreur gardent le détail.
- Ajouter la variable facultative `SENTRY_LARAVEL_DSN` à la stack. Sans elle, rien n'est envoyé et aucune erreur n'apparaît.
- Documenter le réglage dans `docs/deployment.md` et la décision dans une ADR.

**Hors périmètre :**

- erreurs JavaScript du navigateur : le SDK navigateur enverrait des données depuis les téléphones et toucherait aux en-têtes de sécurité ;
- suivi de performance, journaux et métriques envoyés à Sentry ;
- surveillance de disponibilité : si l'hôte est arrêté, l'application ne peut rien envoyer ;
- échec des copies vers R2 de l'entrée 17 : elles tournent dans Dokploy, hors de l'application ;
- page ou réglage dans l'interface, courriel envoyé par l'application.

## Capabilities

### New Capabilities

- `error-alerts` : ce qui déclenche une alerte au mainteneur, ce qui n'en déclenche pas, et ce qu'une alerte a le droit de contenir.

### Modified Capabilities

- `deployment` : nouvelle variable d'environnement facultative du suivi d'erreurs, lue par `migrate`, `web` et `worker`.

## Impact

- **Dépendance** : `sentry/sentry-laravel` et le SDK PHP qu'il tire. Plan gratuit de Sentry selon sa page de tarifs et son écran de création de projet le 2026-11-03 : 0 $ sans carte, un utilisateur, 5 000 erreurs par mois après 14 jours de volume illimité, 30 jours d'historique, alertes par courriel. Seul le suivi d'erreurs est activé.
- **Code** : `bootstrap/app.php`, `config/sentry.php`, `AppServiceProvider`, `App\Jobs\ReadScorecardImport`, nouveau dossier `app/Integrations/Sentry`. Domaines touchés : imports (par le job seulement). Le domaine ne connaît pas Sentry.
- **Stack** : `docker-compose.yml`, `.env.example`, `phpunit.xml`. `web` et `worker` font désormais une requête sortante vers Sentry quand une erreur survient, ce que `web` ne faisait vers aucun service jusque-là.
- **Confidentialité** : c'est la première sortie de données d'erreur vers un tiers. Une alerte ne contient ni nom de joueur ni score ; la spec `error-alerts` le fixe et un test le vérifie sur le vrai parcours d'envoi. Elle contient le type de l'exception, la pile d'appels, la méthode et le chemin de la requête. Un chemin peut porter un UUID, qui ne nomme personne.
- **Accès** : aucune nouvelle page, le code d'accès partagé ne change pas.
- **Dépendances de la feuille de route** : aucune.
- **Retour arrière** : retirer `SENTRY_LARAVEL_DSN` dans Dokploy et redéployer coupe tout envoi. Revenir à l'image précédente retire le code. Aucune migration.
