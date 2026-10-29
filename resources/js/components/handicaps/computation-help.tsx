import { Link } from '@inertiajs/react';
import { computation } from '@/actions/App/Http/Controllers/HandicapController';

/**
 * Les sept lignes de règle du calcul de l'index, au mot près de la spec
 * `handicap-index` (« Lignes de règle du calcul de l'index »), dans l'ordre
 * des étapes : 01 score ajusté, 02 différentiel, 03 retenus, 04 ajustement,
 * 05 index, puis les deux cas particuliers. Seule source du texte (design D6
 * d'`index-computation-page`) : la page « Calcul de l'index » et ses tests la
 * lisent.
 */
export const RULE_LINES = [
    'Score ajusté : chaque trou compte au plus par + 4.',
    'Différentiel d’un round : 113 / slope × (score ajusté − course rating), arrondi au dixième.',
    'Différentiels retenus : 1 de 3 à 5 rounds, 2 de 6 à 8, 3 de 9 à 11, 4 de 12 à 14, 5 à 15 ou 16, 6 à 17 ou 18, 7 à 19, 8 à 20.',
    'Ajustement : −2,0 à 3 rounds, −1,0 à 4 rounds et à 6 rounds, aucun sinon.',
    'Index : moyenne des meilleurs différentiels parmi les 20 derniers rounds, arrondie au dixième et plafonnée à 54,0.',
    'Index provisoire à 1 ou 2 rounds : moyenne de leurs différentiels, sans ajustement.',
    'Parcours sans rating : la course rating vaut le par du round et le slope 113.',
] as const;

/**
 * Lien « Comment l’index est calculé » vers la page « Calcul de l'index »
 * (spec `handicap-index`, « Lien vers le calcul de l'index », design D6) :
 * `/handicaps/calcul`, avec `?joueur=<id>` quand un joueur est donné (la page
 * Handicaps garde ainsi le joueur choisi). Geist 500, 13 px, gris `ink-2`,
 * souligné au survol, 44 px de haut.
 */
export function ComputationLink({ playerId }: { playerId?: string }) {
    return (
        <Link
            href={computation(
                playerId === undefined
                    ? undefined
                    : { query: { joueur: playerId } },
            )}
            className="flex min-h-11 w-fit items-center text-link-13 text-ink-2 underline-offset-3 hover:underline"
        >
            Comment l’index est calculé
        </Link>
    );
}
