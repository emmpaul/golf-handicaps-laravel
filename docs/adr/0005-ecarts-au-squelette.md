# 0005. Écarts au squelette

## Statut

Acceptée, 2026-10-27 (changement OpenSpec `project-skeleton-and-quality-gates`).

## Contexte

Le projet est monté selon le squelette Laravel du mainteneur (`SKELETON.md`). Le squelette vise une application avec comptes, rôles et personnel ; celle-ci sert un groupe d'amis qui partage un code d'accès, importe des cartes de score par l'IA et se déploie sur un seul serveur. `docs/PORT_TO_LARAVEL.md` liste les écarts tranchés le 2026-10-27, et les décisions du mainteneur dans la proposition de `project-skeleton-and-quality-gates` en ajoutent trois. Cette ADR les rassemble pour qu'un écart ne passe pas pour un oubli.

## Alternatives

- **Suivre le squelette à la lettre** : des tables, des paquets et un pipeline de publication que rien n'utilise.
- **Noter les écarts seulement dans `docs/PORT_TO_LARAVEL.md`** : ce brief sert à écrire les propositions et vieillit avec elles ; une décision d'architecture a sa place dans une ADR.

## Décision

- **Pas de Fortify, de comptes ni de rôles.** Un code d'accès partagé et une session suffisent (`app-access`). Les tables du kit `users` et `password_reset_tokens`, la colonne `sessions.user_id`, `App\Models\User` et sa fabrique sont supprimés (ADR 0002). Les couches 2.4 (autorisation), 2.5 (espace `/admin`) et 11 (audit) ne sont pas reprises ; la couche 2.7 (sécurité des requêtes) passe dans `app-access`.
- **Pas de Mailpit** : l'application n'envoie aucun courriel. `compose.yaml` ne lance que `laravel.test` et `pgsql`.
- **Worker de file dès le départ** en production : l'import par l'IA et la recherche sur Simulator Golf Tour tournent en jobs. Sessions, cache et files restent en base, sans Redis.
- **Pas de tests navigateur** : `make check` réunit Pint, Larastan, jscpd, Pest et Vitest. La passe `browser-use` de fin d'apply reste un outil d'agent, pas une suite versionnée.
- **Larastan au niveau 8 au lieu du niveau 7 du squelette**, décidé le 2026-10-27 : le niveau 8 signale une valeur nullable utilisée là où la signature ne l'admet pas, ce que le scénario « Erreur de type PHP » de `quality-gates` exige.
- **Couche 12 remplie par le projet**, après le premier déploiement réel (`production-deployment`).
- **Versionnement manuel, sans release-please.** La version est dans `version.txt`, tenu à la main (`0.0.0` jusqu'à la première version). Chaque pull request de code ajoute ses lignes sous « Non publié » dans `CHANGELOG.md`, en français, au format Keep a Changelog. Pour publier, le mainteneur renomme la section en `## [X.Y.Z] - AAAA-MM-JJ`, met `X.Y.Z` dans `version.txt`, fusionne, puis pose le tag `vX.Y.Z` sur `main`. `version.txt` compte comme documentation pour `make changed-code`.
- **Route de version en 405** pour une méthode autre que `GET` ou `HEAD`, le comportement natif de Laravel, au lieu du 401 de l'application Next.js.

## Conséquences

- Aucune table, aucun paquet ni aucun workflow mort dans le dépôt.
- La suppression de `sessions.user_id` demande un gestionnaire de session `database` propre au projet (ADR 0002), à relire à chaque version majeure de Laravel.
- Sans release-please, aucune pull request de version ne s'ouvre seule : un agent qui oublie sa ligne de `CHANGELOG.md` n'est arrêté que par la revue. `tests/Feature/VersionFileTest.php` vérifie seulement le format de `version.txt` et la présence de la section de chaque version publiée.
- Le tag `vX.Y.Z` déclenchera la publication de l'image quand `production-deployment` l'ajoutera.
- Reprendre plus tard une couche sautée (comptes, audit) demandera son propre changement OpenSpec et une ADR qui remplace la partie concernée de celle-ci.
