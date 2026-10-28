# Journal des changements

Les changements visibles de l'application, version par version. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et les versions suivent le [versionnage sémantique](https://semver.org/lang/fr/). La version en cours est dans `version.txt`.

## [Non publié]

### Ajouté

- Makefile à la racine, interface unique des commandes en local et en CI : `make help` liste chaque cible.
- Contrôles de qualité : Pint, Larastan niveau 8, jscpd, tests Pest en parallèle sur PostgreSQL, tests d'architecture et Vitest sous le fuseau `America/Montreal`, lancés en une passe par `make check`.
- Intégration continue GitHub Actions, audit des workflows par zizmor et mises à jour des actions par Dependabot.
- Identifiants UUID v7 imposés à chaque modèle par une règle d'architecture.
- Fuseau d'affichage `America/Montreal`, distinct du stockage en UTC, et classe de conversion `App\Domain\LocalTime`.
- Route `GET /api/version`, qui renvoie la version et le sha du commit construit, sans session ni accès à la base.
- Fichier `version.txt` et ce journal des changements, tenus à la main.
- ADR 0001 à 0005 sous `docs/adr/`.

### Retiré

- Comptes du kit de démarrage : tables `users` et `password_reset_tokens`, colonne `sessions.user_id`, modèle `User` et sa fabrique.
