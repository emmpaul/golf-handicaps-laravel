# Spec Delta

## Purpose

Donner sur la page d'accueil l'état du groupe d'un coup d'œil : imports à vérifier, classement des index et dernier round en net.

## ADDED Requirements

### Requirement: Mise en page de l'accueil
À 390 px, l'accueil SHALL suivre le design : rangée « Golf » et « Se déconnecter », lien des imports à vérifier, classement « INDEX », puis « DERNIER ROUND ». Cette exigence est réconciliée par la tâche 1.2 avec celles que l'entrée 7 écrit pour l'accueil.

#### Scenario: Imports à vérifier
- **GIVEN** deux imports prêts à vérifier
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** 12 px sous la rangée du haut, une rangée de 44 px montre la pastille « 2 » (Geist Mono 600, 11 px, ambre sur ambre à 15 %), « imports à vérifier » en Geist 500, 14 px, ambre, et « → » à droite ; elle ouvre `/import`

#### Scenario: Un seul import
- **GIVEN** un import prêt à vérifier
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la rangée dit « 1 » et « import à vérifier »

#### Scenario: Aucun import
- **GIVEN** aucun import prêt à vérifier
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la rangée des imports n'apparaît pas

### Requirement: Classement des index de l'accueil
Sous « INDEX », l'accueil SHALL lister les joueurs par index croissant, les joueurs sans index à la fin : rang (« – » sans index), pastille et nom en Geist 600, 17 px, ligne d'état en Geist Mono 500, 11 px, gris, en capitales, et l'index à droite en Geist Mono 500, 44 px, sur 76 px de haut au moins. Chaque ligne ouvre la page Handicaps sur ce joueur.

#### Scenario: Lignes d'état
- **GIVEN** Gab à 2,5 sur 8 rounds dont le dernier l'a fait baisser de 1,5, Manu à 16,5 stable sur 8 rounds, Sean à 19,0 provisoire, Alex sans round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** les lignes sont, dans l'ordre, « 1 », Gab, « ▼ 1,5 · 8 ROUNDS », « 2,5 » ; « 2 », Manu, « = STABLE · 8 ROUNDS », « 16,5 » ; « 3 », Sean, « PROVISOIRE », « 19,0 » ; « – », Alex, « PAS ENCORE D'INDEX », « — »

#### Scenario: Ouverture d'un joueur
- **WHEN** l'utilisateur touche la ligne de Manu
- **THEN** la page Handicaps s'ouvre sur Manu

### Requirement: Dernier round de l'accueil
L'accueil SHALL montrer le dernier round joué : « DERNIER ROUND » et sa date « 17 OCT. », le parcours en Geist 600, 22 px, suivi de « · Red » gris, une ligne par joueur classé en net (rang, pastille et nom, « 72 − 4 », net en Geist Mono 500, 24 px), puis « Net · » et le résultat. Le bloc ouvre la page du round.

#### Scenario: Vainqueur seul
- **GIVEN** le dernier round, où Gab fait 68 net et Sean, deuxième, 70
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la dernière ligne dit « Net · Gab gagne de 2 coups »

#### Scenario: Écart d'un coup
- **GIVEN** le dernier round, où Gab fait 70 net et Manu 71
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la dernière ligne dit « Net · Gab gagne de 1 coup »

#### Scenario: Égalité
- **GIVEN** le dernier round, où Gab et Manu font 71 net
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la dernière ligne dit « Net · Égalité : Gab, Manu »

#### Scenario: Joueur seul
- **GIVEN** le dernier round n'a qu'un joueur classé en net, Gab
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** la dernière ligne dit « Net · Gab seul en jeu »

#### Scenario: Aucun round
- **GIVEN** une base sans round
- **WHEN** l'utilisateur ouvre l'accueil
- **THEN** le bloc « DERNIER ROUND » n'apparaît pas
