# 0004. Stockage en UTC et fuseau d'affichage

## Statut

Acceptée, 2026-10-27 (changement OpenSpec `project-skeleton-and-quality-gates`).

## Contexte

Le groupe joue au Québec et lit chaque date et chaque heure à l'heure de Montréal, heure avancée comprise. Le serveur, les conteneurs et la CI tournent en UTC ; un poste de développement peut être dans n'importe quel fuseau.

Deux fois par an, le jour local ne dure pas 24 heures : le 2026-03-08 en compte 23, le 2026-11-01 en compte 25, et l'heure de 1 h à 2 h du matin y est répétée. Une partie jouée en soirée, après 19 h ou 20 h selon la saison, tombe déjà au lendemain en UTC. Un instant converti au mauvais endroit, ou une chaîne lue dans le mauvais fuseau, déplace donc une partie d'un jour (couche 2.8 du squelette).

## Alternatives

- **`app.timezone` en `America/Montreal`** : PHP et Eloquent écriraient de l'heure locale. L'heure répétée du retour à l'heure normale deviendrait ambiguë, et toute donnée déjà écrite en UTC serait relue de travers.
- **Un cast par modèle** : il convertit les attributs des modèles, mais pas les colonnes lues par une jointure ou une requête brute, ni les modèles des paquets.
- **Formatage côté client** : le résultat dépendrait du fuseau du navigateur, pas de celui du groupe, et deux personnes verraient deux jours différents pour la même partie.

## Décision

- `app.timezone` reste `UTC` dans tous les environnements. PHP, Eloquent et PostgreSQL écrivent, lisent et comparent de l'UTC.
- Une clé de configuration `app.display_timezone`, lue dans `APP_DISPLAY_TIMEZONE`, vaut `America/Montreal` par défaut. Changer ce réglage suffit à changer le fuseau de chaque date ou heure affichée.
- Une seule classe de conversion, `App\Domain\LocalTime` (finale, méthodes statiques, sans dépendance HTTP), par laquelle passe chaque instant envoyé à une page :
    - `format($instant, $pattern = 'Y-m-d H:i')` convertit l'instant vers le fuseau d'affichage avant de le formater ;
    - `date($instant)` en est le raccourci `Y-m-d` ;
    - `startOfDay('AAAA-MM-JJ')` rend minuit local du jour, exprimé en UTC.
- Une chaîne brute, lue d'une colonne sans fuseau, est lue comme de l'UTC (`CarbonImmutable::parse($raw, 'UTC')`) avant la conversion.
- Un filtre « ce jour-là » couvre de `startOfDay` du jour, inclus, à `startOfDay` du lendemain, exclu. On n'ajoute jamais 24 heures au début du jour.
- Les tests frontend tournent sous `TZ=America/Montreal` (`test.env.TZ` dans `vite.config.ts`), pour qu'une date lue au mauvais fuseau échoue sur tout poste et en CI, où le conteneur est en UTC.

## Conséquences

- Les tests d'affichage écrivent un instant UTC proche de minuit local et attendent la chaîne locale en littéral : `2026-11-02T04:30:00Z` s'affiche `2026-11-01 23:30`, `2026-07-15T03:30:00Z` s'affiche `2026-07-14 23:30`. `tests/Unit/LocalTimeTest.php` couvre aussi les bornes des jours de 25 heures (2026-11-01) et de 23 heures (2026-03-08) ; il devient rouge si `LocalTime` lit `app.timezone` au lieu de `app.display_timezone`.
- Formater un instant sans passer par `LocalTime` reste possible dans le code ; seule la revue l'attrape. Aucune règle d'architecture ne l'interdit pour l'instant.
- Côté frontend, une date `AAAA-MM-JJ` se lit par `parse` de `date-fns`, jamais par `new Date('2026-11-02')`, qui lit minuit UTC et recule d'un jour à l'ouest de Greenwich.
- Le fuseau d'affichage est unique pour toute l'application. Un groupe dans un autre fuseau demanderait un réglage par groupe, hors du périmètre actuel.
