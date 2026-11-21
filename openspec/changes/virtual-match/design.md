# Design : partie virtuelle

## Context

Voir `proposal.md` pour le besoin. Ce qui existe déjà et que ce changement réutilise :

- `App\Domain\Handicaps\Queries\RoundLeaderboard` calcule le classement net d'un round : le brut et `index_before_t` viennent de `player_round_stats`, la rating du parcours et des tees se lit par `rating(Round)`, et le par total est celui du round. `NetRanking::rank()` calcule le handicap de jeu et le rang sur **un seul** `RatedCourse`.
- Rien n'est stocké pour le handicap de jeu ni pour le net : ils suivent la rating (ARCHITECTURE, « Lectures »).
- `KnownCourses` et `CourseOverview` listent les parcours connus. La page Coups (`StrokesController`) s'ouvre sur le premier.
- Front : `components/rounds/net-leaderboard.tsx`, `round-scorecard.tsx`, `components/scores/score-mark.tsx`, les primitives `components/ui` (dont `popover`) et `formatPlayedOn` / `formatDayMonthShort` de `lib/format.ts`.
- Mode hors ligne (spec `offline-mode`) : toute page `GET` lue en ligne est gardée par adresse.

Une partie virtuelle peut réunir des rounds dont le par total diffère (la spec `rounds` laisse chaque round garder ses pars). Le handicap de jeu de chaque joueur se calcule donc sur le par de **son** round, ce que `NetRanking::rank()` ne sait pas faire pour un groupe.

## Goals / Non-Goals

**Goals :**

- Une seule source pour le net : le même code donne le net d'un joueur sur la page de son round et dans une partie virtuelle.
- Aucune écriture, aucune migration, aucun modèle.
- Une adresse qui suffit à reconstruire la partie.

**Non-Goals :**

- Un calcul du classement dans le navigateur : la page Coups le fait parce qu'elle travaille hors ligne sur des index, mais ici le net vient de données serveur déjà calculées.
- Une API JSON : la page reste une page Inertia.

## Decisions

### D1. Lecture seule, sans ADR

La partie virtuelle est une lecture composée par des Queries, comme `RoundDetail`. Elle n'introduit ni nouveau motif, ni table, ni dépendance. Pas d'ADR : ce changement ne prend aucune décision d'architecture. L'autre option, une table `virtual_matches`, est écartée par le mainteneur (Q1) et reviendra avec `monthly-tournament`.

### D2. Le rang de compétition séparé du handicap

`NetRanking` gagne une méthode pure `competitionRanks(list<?int> $nets): list<?int>` : un net `null` n'est pas classé, des nets égaux partagent le rang, le rang suivant saute les places. `rank()` l'appelle au lieu de calculer le rang lui-même, et son comportement ne change pas (les tests existants de `NetRanking` et de `RoundLeaderboard` restent verts sans modification). La partie virtuelle calcule le handicap de chaque joueur-round avec `Whs::courseHandicap($indexBeforeT, new RatedCourse($ratingT, $slope, $parDuRound))`, puis classe les nets avec `competitionRanks()`.

Alternative écartée : appeler `rank()` une fois par round puis fusionner les rangs. On obtiendrait deux classements à recoller, avec un risque d'écart sur les ex æquo.

### D3. Deux Queries

- `App\Domain\Handicaps\Queries\VirtualMatch` : `for(string $courseName, string $tees, list<Pick> $picks)` rend le parcours retenu, sa rating, la liste d'ajout (tous les joueurs-rounds du parcours et des tees, du plus récent au plus ancien, avec nom, couleur, date, partenaires et brut), et les lignes choisies classées (brut, handicap de jeu, net, rang, scores des 18 trous). Elle rend aussi les pars et index du round le plus récent parmi les choisis et le drapeau `holesDiffer`. Elle filtre les choix en un passage :
    - elle ignore un `Pick` dont le round n'existe plus, qui n'est pas sur ce parcours et ces tees (sans casse ni espaces autour, comparés en SQL comme `RoundLeaderboard::rating`), ou qui n'a pas ce joueur ;
    - elle ignore un `Pick` dont le joueur est déjà pris par un `Pick` précédent.
  La rating se lit une fois pour la paire parcours et tees, et le par de chaque round se somme sur ses `round_holes`. Sans rating confirmée, `$ratingT` vaut le par de **chaque** round × 10 et `$slope` 113, comme `RoundLeaderboard::rating()` : un `$ratingT` commun pris sur un seul round donnerait, à pars différents, un handicap de jeu autre que celui de la page du round. Le tout tient en trois requêtes (joueurs-rounds du parcours, trous des rounds choisis, rating), sans N+1.
- `App\Domain\Rounds\Queries\VirtualMatchSuggestion` : `for(Round $round)` rend les joueurs absents de la carte qui ont un round sur le même parcours et les mêmes tees, avec `abs(played_on − round.played_on) ≤ 7` jours et un `round_id` différent. Pour chaque joueur, elle garde le round le plus proche en date, puis le plus récent par `played_on`, `created_at` et `id`. Le résultat est trié par date de jeu, puis par nom. C'est une seule requête : `distinct on (player_id)` ordonné par l'écart de dates puis `played_on desc, created_at desc, id desc`. `played_on` est une date, donc il n'y a pas de fuseau en jeu.

`Pick` est une valeur `final readonly` (`roundId`, `playerId`) du domaine `Handicaps`.

### D4. Adresse : `GET /rounds/compare?course=…&tees=…&picks=…`

- `picks` est une liste `roundUuid.playerUuid` séparée par des virgules, dans l'ordre du choix. L'adresse ne porte ni nom de joueur ni score.
- La route est nommée `rounds.compare`. Elle est déclarée avant `rounds/{round}`, qui est déjà contrainte par `whereUuid` : `compare` n'est pas un UUID et ne lui est jamais pris.
- Une Form Request (`CompareRoundsRequest`) ne fait que lire la forme. Les UUID mal formés et les paires illisibles sont écartés, jamais refusés en 422, puisque la spec demande d'ignorer sans erreur.
- Sans `course` ou avec un parcours inconnu, on prend le premier parcours connu (ordre de `KnownCourses`) et on ignore les `picks`.
- `RoundController::compare` est un contrôleur mince : Form Request, `VirtualMatch`, `Inertia::render('rounds/compare')`.
- Les liens se construisent côté front avec Wayfinder (`rounds.compare({ query })`).

### D5. Chaque changement de choix est une visite Inertia `GET`

Ajouter ou retirer un joueur-round, ou changer de parcours, fait un `router.get(rounds.compare.url({ query }), {}, { preserveScroll: true, preserveState: true, replace: true })`. Le serveur reste la seule source du classement, l'adresse suit sans code à part, et la page de chaque choix est gardée hors ligne comme toute page lue. Le dépli de la carte est un état local React, sans requête, comme le demande la spec. `preserveState` garde la carte dépliée d'un choix à l'autre.

Alternative écartée : envoyer tous les joueurs-rounds du parcours avec leurs scores et classer dans le navigateur. Le classement serait écrit en double (PHP et TS) pour un gain hors ligne minime.

### D6. Suggestion portée par la page du round

`RoundController::show` ajoute la prop `virtualMatch: { suggested: list<{ name, colorSlot, playedOn }>, picks: string }`. `picks` contient les joueurs de la carte dans l'ordre, puis les suggérés dans l'ordre de la liste. Le front choisit le texte :

- avec des suggérés, l'encadré (« Gab (18 nov.) a joué ce parcours. » ou « … et … ont joué ce parcours. ») et son lien « Voir la partie virtuelle » ;
- sans suggéré, le lien « Comparer avec d'autres rounds ».

Le lien porte toujours le parcours, les tees et `picks`.

### D7. Front et maquette

Page `resources/js/pages/rounds/compare.tsx`, composants sous `resources/js/components/rounds/` :

- `virtual-match-winner.tsx` : l'encadré du gagnant ;
- `virtual-match-picks.tsx` : les pastilles et « + Ajouter », avec la liste d'ajout dans le `popover` existant, défilante, ses lignes de 52 px ;
- `virtual-match-suggestion.tsx`.

`net-leaderboard.tsx` gagne une prop facultative `playedOn` par ligne, rendue sous « {brut} − {hcp} ». `round-scorecard.tsx` gagne une sous-ligne facultative de date par colonne et un défilement horizontal dans son cadre au-delà de la largeur. Les pages existantes ne passent pas ces props et ne changent pas.

Tous les textes de l'encadré du gagnant (singulier, pluriel, égalité, deuxièmes ex æquo) sortent d'une fonction pure `winnerSummary()` dans `resources/js/lib/virtual-match.ts`, testée par Vitest. Couleurs, tailles et rayons viennent des tokens.

Maquette retenue (variante B du prototype) :

```
┌──────────────────────────────────────┐
│ ‹ Rounds                             │
│ Partie virtuelle                     │
│ PARCOURS                             │
│ [ BETHPAGE BLACK / Red          ▾ ]  │
│ rating 71,4 · slope 144              │
│ ┌──────────────────────────────────┐ │
│ │ AURAIT GAGNÉ                     │ │
│ │ ● Manu                           │ │
│ │ 65 net · 3 coups devant Sean     │ │
│ └──────────────────────────────────┘ │
│ (● Gab 18 nov.) (● Manu 19 nov.)     │
│ (● Sean 19 nov.) ( + Ajouter )       │
│ CLASSEMENT NET            BRUT − HCP │
│ 1  ● Manu  1ER                    65 │
│    88 − 23 · 19 nov. 2026            │
│ 2  ● Sean                         68 │
│    84 − 16 · 19 nov. 2026            │
│ 3  ● Gab                          72 │
│    74 − 2 · 18 nov. 2026             │
│ [ Voir la carte combinée ]           │
│   (dépliée : carte trou par trou,    │
│    colonnes GAB / MANU / SEAN        │
│    avec leur date, ALLER/RETOUR/     │
│    TOTAL, note si les pars diffèrent)│
└──────────────────────────────────────┘
```

Le prototype jetable des trois variantes est resté hors du dépôt. La variante B l'a emporté parce qu'elle donne la réponse (« qui aurait gagné ») avant le détail.

### D8. Démonstration

`DemoRoundsSeeder` ajoute deux rounds à scores fixes sur « PAYNES VALLEY / Blue », après le round de référence et sans autre round de ce parcours dans les 7 jours :

- Gab seul le 2026-10-24 ;
- Manu et Sean le 2026-10-25.

Les littéraux de `tests/Feature/Seeders/DemoDatabaseSeederTest.php` que ces rounds changent (nombre de rounds, dernier round, historique de Manu) sont réécrits à la main depuis les nouvelles valeurs, sans rien recalculer par le code testé. Le round de référence `IMG_3064` du 2026-10-17 et son index d'avant ne bougent pas, puisque les nouveaux rounds viennent après lui.

## Risks / Trade-offs

- [Une visite serveur par ajout ou retrait] → Les requêtes sont légères (trois requêtes SQL) et `preserveState` évite le clignotement. Hors ligne, seuls les choix déjà vus se rouvrent. Ce n'est pas un usage visé : on compare après coup, en ligne.
- [Beaucoup de joueurs-rounds sur un parcours populaire] → La liste d'ajout ne porte que le nom, la date, les partenaires et le brut. Les scores trou par trou ne viennent que pour les choisis.
- [Le texte de la suggestion grandit avec le nombre de joueurs] → Le groupe compte quelques joueurs : la phrase se joint par « , » et « et » et passe à la ligne, sans troncature.
- [Une rating saisie après coup change les nets] → C'est déjà le cas sur la page d'un round. La partie virtuelle reste alignée sur elle, ce que vérifie le scénario « Même net que la page du round ».
- [Le refactor de `NetRanking`] → Il est couvert par les tests existants de `NetRanking` et de `RoundLeaderboard`, qui ne changent pas. Un test unitaire de `competitionRanks()` nomme sa mutation : un rang dense (1, 2, 2, 3) au lieu de 1, 2, 2, 4.

## Migration Plan

Pas de migration : déployer l'image suffit. Retour arrière : revenir à l'image précédente, puisqu'aucune donnée n'a été écrite.
