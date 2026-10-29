# 0013. Refonte visuelle

## Statut

Acceptée, 2026-10-29 (changement OpenSpec `visual-redesign`). Remplace en partie l'ADR 0006.

## Contexte

Le port avait repris l'interface de l'application Next.js (décision 7 de `docs/PORT_TO_LARAVEL.md`, ADR 0006) : thème scoreboard clair et sombre selon le système, Sofia Sans Condensed et IBM Plex Sans, en-tête collé avec la marque dessinée, navigation en haut sur ordinateur et barre d'onglets en bas sur téléphone. Le mainteneur a fait dessiner une nouvelle interface, « Golf handicap app redesign » : une maquette de téléphone de 390 × 844 px, sombre, typographique, en Geist et Geist Mono, avec une barre d'onglets flottante, un bouton d'import toujours visible, des couleurs de joueur et un bloc de statistiques. Elle ne définit aucun thème clair ni écran large. Le groupe découvrira l'application Laravel au cutover ; la refonte passe avant la reprise des données de Next (entrée 10 de la feuille de route), pour qu'il ne change pas deux fois d'interface.

L'archive du design contient aussi la famille Sentient, sous ITF Free Font License 2.0. Le HTML final ne la nomme nulle part. Le dépôt est public sur GitHub et l'image GHCR embarque `public/build`.

## Alternatives

- **Garder le scoreboard** et n'appliquer que la navigation du design : deux langages visuels dans une même application, et un design commandé laissé de côté.
- **Inventer un thème clair** à partir de la palette sombre : le design n'en dessine aucun, chaque écran demanderait des valeurs que personne n'a validées, et les tests de contraste doubleraient.
- **Versionner Sentient** pour le jour où le design s'en servirait : la licence interdit de rendre les fichiers disponibles à d'autres, dépôt compris ; versionner les woff2 dans un dépôt public, ou dans l'image publique, serait une redistribution.
- **Une colonne de couleur en base** pour les joueurs : migration, fabrique et seeder pour une donnée que le rang de création donne déjà.
- **Statistiques stockées** dans `player_round_stats` : la table appartient au calcul de l'index et à la comparaison de l'import SQLite ; une requête d'agrégats sur quelques centaines de rounds suffit.
- **Relever les trois paires sous les seuils de contraste** (contour de champ en `ink-3`, case décochée en `bg`, texte sombre sur le bouton rouge) : écarté par le mainteneur, qui veut le design au pixel près.

## Décision

- **Thème sombre seul.** `resources/css/tokens.css` déclare la palette du design (`bg` `oklch(0.13 0.005 260)`, trois surfaces, filets, quatre encres, `accent`, `signal`, `info`, `ok`, six couleurs de joueur), ses rayons, son ombre unique, son échelle typographique et ses courbes. La palette reste fermée. Plus de bloc `prefers-color-scheme` ; `color-scheme: dark`, une seule `theme-color` `#060709`, barre d'état `black`.
- **Geist et Geist Mono** par `fontsource()`, Sofia Sans Condensed et IBM Plex Sans retirées. Sentient n'est pas livrée.
- **Cadre du design.** Une colonne de 390 px centrée à toute largeur, une barre d'onglets flottante à quatre sections et le bouton rond Importer, une rangée de page qui défile au lieu de l'en-tête collé, « Golf » en texte au lieu de la marque dessinée. La navigation du haut et `ScrollFrame` disparaissent. Les écrans de l'éditeur masquent la barre.
- **Couleur de joueur** calculée à la lecture : rang de création modulo 6 (`Player::withColorSlot()`), sans colonne.
- **Bloc STATS** calculé à la lecture par la Query `PlayerScoringStats`, une requête d'agrégats, arrondis en entiers côté PHP.
- **Barres CSS** pour les différentiels, sans bibliothèque de graphiques.
- **Animations du design** toutes reprises, la règle « jamais largeur, hauteur ou position » levée pour les seules propriétés que le design anime ; état final dès la première image sous mouvement réduit.
- **Compromis d'accessibilité, décision du mainteneur (Q4).** Trois paires gardent la valeur du design sous les seuils : contour de champ `oklch(0.27 0.008 260)` à 1,34:1 contre le fond, case décochée `oklch(0.4 0.01 260)` à 2,18:1, texte blanc sur le bouton rouge à 2,88:1. Le prix est une moins bonne visibilité des champs, des cases et du bouton de suppression pour les personnes malvoyantes. Aucune autre exception n'est permise : `theme-contrast.test.ts` déclare ces trois paires en liste littérale avec leurs ratios et échoue sur toute quatrième paire sous le seuil.
- **Ce que garde l'ADR 0006** : les primitives base-ui possédées par le projet, le garde-fou `theme-sources`, les icônes statiques générées par `app:icons`, le toast par le flash d'Inertia.

## Conséquences

- L'ADR 0006 passe à « Remplacée en partie par 0013 » pour la palette, le mode sombre selon le système, les polices et la marque d'en-tête. La décision 7 de `docs/PORT_TO_LARAVEL.md` est remplacée pour le thème et les tokens.
- `DESIGN.md` décrit le nouveau design ; c'est la référence de toute nouvelle page.
- Un appareil réglé en mode clair affiche le thème sombre. Ajouter un thème clair demandera un design, son propre changement OpenSpec et une ADR.
- Les trois exceptions de contraste sont permanentes tant que le design ne change pas. Les relever demande une décision du mainteneur et la mise à jour de la liste du test.
- Une police qui reviendrait sous licence restrictive demanderait un dépôt et une image privés, ou une livraison au build hors du dépôt.
- La couleur d'un joueur dépend de son rang de création : supprimer un joueur décalerait les suivants. Aucune page ne supprime de joueur aujourd'hui.
- Les icônes, le favicon et le manifest sont redessinés sur le fond `#060709` ; les tests Pest comparent aux littéraux du design.
