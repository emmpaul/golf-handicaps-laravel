# Décisions d'architecture

Une ADR par décision d'architecture, numérotée, écrite dans le même lot que le code qu'elle décide. Gabarit : `Statut`, `Contexte`, `Alternatives`, `Décision`, `Conséquences`. Une ADR remplacée garde son fichier et passe à « Remplacée par NNNN ».

| N°                                                            | Décision                                               | Statut                       |
| ------------------------------------------------------------- | ------------------------------------------------------ | ---------------------------- |
| [0001](0001-monolithe-modulaire.md)                           | Monolithe modulaire                                    | Acceptée                     |
| [0002](0002-postgresql-et-uuid.md)                            | PostgreSQL et UUID                                     | Acceptée                     |
| [0003](0003-openspec-tdd-makefile.md)                         | OpenSpec, TDD et Makefile                              | Acceptée                     |
| [0004](0004-utc-et-fuseau-affichage.md)                       | Stockage en UTC et fuseau d'affichage                  | Acceptée                     |
| [0005](0005-ecarts-au-squelette.md)                           | Écarts au squelette                                    | Acceptée                     |
| [0006](0006-systeme-de-design.md)                             | Système de design                                      | Remplacée en partie par 0013 |
| [0007](0007-acces-par-code-partage.md)                        | Accès par code partagé                                 | Acceptée                     |
| [0008](0008-deploiement-image-unique-dokploy.md)              | Déploiement par image unique et stack Dokploy          | Remplacée en partie par 0015 |
| [0009](0009-modele-des-rounds-et-controles.md)                | Modèle des rounds et contrôles de cohérence            | Acceptée                     |
| [0010](0010-index-en-dixiemes-et-table-derivee.md)            | Calcul de l'index en dixièmes entiers et table dérivée | Acceptée                     |
| [0011](0011-lecture-des-cartes-par-ia-et-photos-webp.md)      | Lecture des cartes par l'IA et photos en WebP          | Acceptée                     |
| [0012](0012-recherche-des-ratings-sur-simulator-golf-tour.md) | Recherche des ratings sur Simulator Golf Tour          | Acceptée                     |
| [0013](0013-refonte-visuelle.md)                              | Refonte visuelle                                       | Acceptée                     |
| [0014](0014-import-unique-de-la-base-sqlite-de-next.md)       | Import unique de la base SQLite de Next                | Acceptée, modifiée           |
| [0015](0015-mode-worker-octane-frankenphp.md)                 | Mode worker par Laravel Octane et FrankenPHP           | Acceptée                     |
