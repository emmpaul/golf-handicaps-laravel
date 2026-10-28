# 0001. Monolithe modulaire

## Statut

Acceptée, 2026-10-27 (changement OpenSpec `project-skeleton-and-quality-gates`).

## Contexte

L'application sert un groupe d'amis : quelques personnes, un serveur, une base. Ses règles métier (calcul de l'index WHS, coups rendus, contrôle d'un brouillon de carte de score) doivent se tester sans requête HTTP ni rendu de page. Dans la structure Laravel par défaut, ces règles finissent dans les contrôleurs et les modèles, où elles ne se testent qu'à travers une requête. L'application parle aussi à des tiers (l'IA qui lit les cartes, Simulator Golf Tour), dont le code ne doit pas se mêler au domaine (couche 2.3 du squelette).

## Alternatives

- **Structure Laravel par défaut** : rien à apprendre, mais les règles se dispersent dans les contrôleurs et ne se testent qu'en Feature.
- **API séparée et SPA** : deux déploiements, une couche de sérialisation et une authentification d'API, sans besoin démontré. Inertia donne des pages React servies par Laravel.
- **Microservices** : réseau, déploiements et cohérence entre services pour une poignée d'utilisateurs.
- **DDD complet** (repositories génériques, CQRS, event sourcing) : des couches au-dessus d'Eloquent qui ne rapportent rien à cette taille.

## Décision

- Un seul projet Laravel, Inertia et React, rangé par domaine métier :
    - `app/Domain/<Domaine>/Actions/` : une classe par écriture, méthode `handle()`, qui ouvre sa transaction ;
    - `app/Domain/<Domaine>/Queries/` : une classe par lecture complexe ;
    - `app/Domain/<Domaine>/Exceptions/` : sous-classes de `App\Domain\DomainException`, dont le message s'adresse à la personne qui utilise l'application ;
    - à côté, les enums et les calculs purs du domaine.
- `app/Integrations/<Tiers>/` : adaptateurs vers les sites et API tiers.
- Contrôleurs minces : un Form Request valide, une Action ou une Query fait le travail, le contrôleur rend la réponse.
- Dépendances : `App\Domain` ne dépend ni de `Illuminate\Http`, ni d'`App\Http`, ni d'Inertia, ni d'`App\Integrations`. `App\Integrations` ne dépend ni d'`App\Http` ni d'Inertia. Les dépendances entre domaines vont dans un seul sens.
- Ces frontières sont des règles d'architecture Pest (`tests/Arch/ArchitectureTest.php`, `make arch-test`) : un import interdit rend la suite rouge.
- Un dossier de domaine naît avec son premier code, jamais vide d'avance. `App\Domain\LocalTime` est le premier fichier de `App\Domain`.

## Conséquences

- Les règles métier se testent en tests unitaires, sans HTTP ni rendu.
- Ce changement fixe les conventions et les règles, pas leur code : aucune classe `Action` ni `Query` n'existe encore. `App\Domain\DomainException` et son rendu unique dans `bootstrap/app.php` (JSON 422 pour une requête JSON, toast Inertia sinon) arrivent avec la première Action qui lève une erreur métier (`players-and-rounds`) ; le toast vient de `visual-theme-port`.
- Tant que `App\Integrations` n'existe pas, ses règles passent à vide. Elles deviennent rouges au premier import interdit, ce qui est le but.
- Chaque nouveau sens interdit entre deux domaines s'ajoute comme règle d'architecture dans le changement qui crée le second domaine.
