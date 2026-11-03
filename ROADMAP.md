# Feuille de route

Ordre des prochains changements OpenSpec, de haut en bas. Le port depuis Next est terminé : sa feuille de route, entrées 0 à 16, est dans `docs/archive/ROADMAP-port-laravel.md`. Les numéros continuent à partir de 17, parce que les documents archivés citent les anciens. Le numéro d'une entrée l'identifie et ne donne pas son rang.

Chaque changement donne deux pull requests : le code, puis l'archivage. Les entrées 17 et suivantes sont des propositions. Leur périmètre se tranche dans la proposition OpenSpec de chacune.

## Reste du port

Les vérifications au mainteneur des entrées archivées. `golf2` étant retiré, celles prévues sur le staging se font en production.

- Restauration d'un dump dans une base vide sur le serveur, date notée dans `docs/deployment.md` (`production-deployment`, tâche 8.3). L'essai n'a été fait que sur la stack locale.
- Retour à l'image précédente, à essayer à la prochaine version publiée et à noter dans `docs/deployment.md`. La doc parle encore de `v0.1.1`, qui n'a jamais existé.
- Ajout de `image` aux contrôles requis de `main`, si ce n'est pas fait (`production-deployment`, tâche 5.5).
- Import des trois cartes de test avec une clé d'API (`scorecard-ai-import`, tâche 9.3).
- Visite de `/`, `/handicaps` et `/strokes`, et confirmation des propositions de rating sur `/courses`.
- Zones sûres et rangée du haut à 59 px sur un vrai iPhone, en application d'écran d'accueil.
- Relecture des captures de `reports/captures/visual-redesign/`.
- Trou plafonné de Manu : `make demo`, puis `/handicaps/calcul` sur Manu.
- Issue #28 : polices absentes en `make dev` après une exécution de Vitest.
- Next et son volume `golf-data` : à supprimer à partir du 2026-11-28.

## Fiabilité

### 17. `offsite-backups` (terminée le 2026-11-03)

Réglée dans Dokploy, sans changement OpenSpec ni code : la base et le volume `storage`, donc les photos, partent vers un bucket Cloudflare R2 privé par des sauvegardes planifiées. Une première exécution de chacune a déposé dans le bucket un fichier de la taille attendue.

Écarts avec le périmètre prévu :

- les copies ne sont pas chiffrées avant l'envoi : Dokploy les dépose en gzip et en tar ;
- `backup` ne devient pas malade quand une copie échoue : ces sauvegardes tournent hors de ce service ;
- aucune restauration n'a été essayée à partir de ces copies.

### 18. `error-alerts`

Aucun outil de suivi d'erreurs n'est installé : une exception en production ou un job d'import en échec ne prévient personne. Envoyer une alerte au mainteneur pour ces deux cas. L'alerte ne contient ni nom de joueur ni score. Reste à choisir l'outil et le canal.

### 19. `ai-reading-accuracy`

**Dépend de** : 8 (archivée).

La table `imports` garde la lecture de l'IA (`draft`) et ce que l'utilisateur en a fait (`correction`). Comparer les deux pour savoir quels champs l'IA lit mal et combien de cases il faut corriger par carte, avant de toucher au prompt ou au modèle.

## Usage quotidien

### 20. `remembered-player`

Sans compte, chaque téléphone retient « C'est moi » : `/handicaps` et `/handicaps/calcul` s'ouvrent sur ce joueur, et l'accueil met sa ligne en avant. Le choix reste sur l'appareil, et le code d'accès partagé ne change pas. Reste à décider si la déconnexion l'efface.

### 21. `monthly-tournament`

**Dépend de** : le classement net d'un round (archivé).

Le groupe joue un tournoi par mois : tout le monde joue le même parcours le même jour, et le meilleur net gagne. Marquer un round comme tournoi du mois, afficher le gagnant sur la page du round et sur l'accueil, et garder la liste des gagnants mois par mois avec le nombre de victoires de chacun. Le classement reprend le rang net existant.

À trancher dans la proposition :

- un ou plusieurs rounds par tournoi, puisqu'une carte porte 4 joueurs au plus ;
- le sort des ex æquo, que le classement net laisse aujourd'hui tous deux « 1ER » ;
- le mois d'un tournoi joué en retard.

### 22. `course-page`

**Dépend de** : 9 (archivée).

Une page par parcours et tees, ouverte depuis `/courses` : rounds joués, meilleur score de chaque joueur, trous les plus durs du groupe et rating. Elle dit aussi quel parcours le groupe n'a pas rejoué depuis longtemps, ce qui aide à choisir celui du prochain tournoi.

### 23. `round-recap-share`

**Dépend de** : 21.

Une image de résumé d'un round ou d'un tournoi (parcours, classement net, gagnant), envoyée au groupe par le partage du téléphone. Le partage sort des noms et des scores de l'accès par code : il part seulement du geste d'un joueur, et aucune page publique n'est créée.

### 24. `personal-insights`

**Dépend de** : 20.

Sur la page Handicaps, ce que le joueur peut travailler : aller contre retour, coups perdus selon l'index de trou, écarts de score d'un parcours à l'autre. Ne garder que ce qui reste lisible avec peu de rounds.

## Plus tard

Écartés pour l'instant, à reprendre si le besoin vient :

- rivalités : bilan de chaque paire de joueurs, en brut et en net ;
- formats de match sur un round : Stableford net, skins, match play ;
- saisons : classement annuel par points, qui peut partir des tournois de 21 ;
- records : meilleur round, meilleur différentiel, eagles, séries ;
- rounds de 9 trous : le modèle n'en connaît que 18, donc le changement touche la saisie, la lecture de la carte et l'index ;
- plafonds WHS (soft cap et hard cap) : absents des specs, ils changeraient des index existants.

Hors du projet : comptes par joueur, application native et saisie en direct pendant la partie. La photo de la carte couvre déjà la saisie, et le code partagé suffit à un groupe d'amis.

## Définition de terminé

1. spec à jour, critères vérifiables, ambiguïtés résolues avant l'implémentation ;
2. ADR créée ou mise à jour si une décision d'architecture est prise ;
3. tests comportementaux écrits avant le code ;
4. accès par code vérifié pour toute nouvelle page ; confidentialité respectée pour toute sortie ;
5. `make check` vert ;
6. documentation à jour, changement archivé, aucun code hors périmètre ;
7. tout modèle ajouté a sa fabrique, ses états et son seeder de démonstration.
