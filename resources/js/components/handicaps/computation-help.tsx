/**
 * Les sept lignes de l'explication, dans l'ordre et au mot près de la spec
 * `handicap-index` (« Texte de l'explication du calcul ») : le même texte sur
 * les trois pages.
 */
const LINES = [
    'Score ajusté : chaque trou compte au plus par + 4.',
    'Différentiel d’un round : 113 / slope × (score ajusté − course rating), arrondi au dixième.',
    'Index : moyenne des meilleurs différentiels parmi les 20 derniers rounds, arrondie au dixième et plafonnée à 54,0.',
    'Différentiels retenus : 1 de 3 à 5 rounds, 2 de 6 à 8, 3 de 9 à 11, 4 de 12 à 14, 5 à 15 ou 16, 6 à 17 ou 18, 7 à 19, 8 à 20.',
    'Ajustement : −2,0 à 3 rounds, −1,0 à 4 rounds et à 6 rounds, aucun sinon.',
    'Index provisoire à 1 ou 2 rounds : moyenne de leurs différentiels, sans ajustement.',
    'Parcours sans rating : la course rating vaut le par du round et le slope 113.',
] as const;

/**
 * Explication repliée du calcul de l'index (spec `handicap-index`,
 * « Explication du calcul »), fermée à l'ouverture de la page. `<details>`
 * natif : le titre s'ouvre au clic, à Entrée et à Espace. Texte seul, sans
 * valeur calculée ; les lignes passent à la ligne à 320 px.
 */
export function ComputationHelp() {
    return (
        <details className="group text-sm max-w-2xl border-t pt-3">
            <summary className="w-fit cursor-pointer font-medium underline-offset-3 hover:underline max-md:min-h-11 max-md:content-center">
                Comment l’index est calculé
            </summary>
            <ul className="mt-2 flex list-disc flex-col gap-1 pl-5 [overflow-wrap:anywhere] text-muted-foreground">
                {LINES.map((line) => (
                    <li key={line}>{line}</li>
                ))}
            </ul>
        </details>
    );
}
