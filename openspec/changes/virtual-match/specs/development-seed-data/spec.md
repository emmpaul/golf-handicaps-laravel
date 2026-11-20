# Spec Delta

## ADDED Requirements

### Requirement: Partie virtuelle de démonstration
`make demo` SHALL écrire, en plus du jeu existant, un cas de partie virtuelle : un round d'un seul joueur, puis le lendemain un round de deux autres joueurs, sur le même parcours et les mêmes tees, sans autre round de ce parcours et de ces tees dans les 7 jours. Ces rounds MUST être déterministes et passer les contrôles de cohérence, comme le reste du jeu.

#### Scenario: Suggestion visible
- **WHEN** on lance `make demo` puis qu'on ouvre le round des deux joueurs
- **THEN** l'encadré de suggestion nomme le joueur seul de la veille, et son lien ouvre une partie virtuelle de trois joueurs classés

#### Scenario: Jeu toujours déterministe
- **WHEN** on lance `make demo` deux fois
- **THEN** les deux bases ont les mêmes rounds de partie virtuelle (parcours, tees, date, joueurs et scores)
