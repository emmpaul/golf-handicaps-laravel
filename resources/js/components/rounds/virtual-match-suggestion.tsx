import { Link } from '@inertiajs/react';
import { formatDayMonth } from '@/lib/format';
import { compareUrl, joinNames } from '@/lib/virtual-match';

/** A player to suggest, as RoundController::show sends it (design D6). */
export type SuggestedPlayer = {
    name: string;
    colorSlot: number;
    playedOn: string;
};

/** « Gab (18 nov.) a joué ce parcours. », « Gab (18 nov.) et Sean (20 nov.) ont joué ce parcours. » */
function suggestionText(suggested: SuggestedPlayer[]): string {
    const names = joinNames(
        suggested.map(
            (player) => `${player.name} (${formatDayMonth(player.playedOn)})`,
        ),
    );

    return `${names} ${suggested.length === 1 ? 'a' : 'ont'} joué ce parcours.`;
}

const LINK =
    'flex min-h-11 items-center self-start text-label-14 text-accent outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-solid';

/**
 * Suggestion de partie virtuelle de la page d'un round (spec `virtual-match`,
 * « Suggestion sur la page d'un round », « Lien de comparaison sans
 * suggestion ») : avec des joueurs suggérés, un seul encadré `surface-1` aux
 * coins de 12 px les nomme avec la date de leur round et mène à la partie
 * virtuelle ; sans suggéré, le lien « Comparer avec d'autres rounds ». Le lien
 * porte toujours le parcours, les tees et `picks`. Pas de bouton pour ignorer.
 */
export function VirtualMatchSuggestion({
    courseName,
    tees,
    suggested,
    picks,
}: {
    courseName: string;
    tees: string;
    suggested: SuggestedPlayer[];
    picks: string;
}) {
    const href = compareUrl(courseName, tees, picks);

    if (suggested.length === 0) {
        return (
            <Link href={href} className={LINK}>
                Comparer avec d'autres rounds
            </Link>
        );
    }

    return (
        <aside
            aria-label="Partie virtuelle"
            className="flex flex-col gap-1 rounded-12 bg-surface-1 px-4 pt-3.5 pb-1.5"
        >
            <p className="text-body">{suggestionText(suggested)}</p>
            <Link href={href} className={LINK}>
                Voir la partie virtuelle
            </Link>
        </aside>
    );
}
