# Spec Delta

## MODIFIED Requirements

### Requirement: Indicateur dans les deux thèmes
Le motif de l'indicateur SHALL s'afficher dans sa variante sombre, lisible sur le fond `oklch(0.13 0.005 260)` et sur les surfaces, quel que soit le réglage de l'appareil (thème sombre seul, spec `visual-theme`). Son texte MUST prendre le style des métas du design : Geist Mono 500, 12 px, gris `oklch(0.68 0.01 260)`.

#### Scenario: Mode sombre
- **WHEN** l'indicateur s'affiche sur un téléphone réglé en mode clair puis en mode sombre
- **THEN** le motif s'affiche les deux fois dans sa variante sombre, et le texte « Enregistrement du joueur… » en Geist Mono 500, 12 px, gris
