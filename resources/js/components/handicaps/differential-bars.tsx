import { PLAYER_BG } from '@/components/player-swatch';
import { formatTenths } from '@/lib/format';
import { useEntered } from '@/lib/motion';
import { cn } from '@/lib/utils';
import type { HandicapHistoryRow } from './round-history';

/** Les 20 derniers rounds dessinés (spec « Graphique des différentiels »). */
const SHOWN = 20;

/** Hauteur de la barre du plus grand différentiel, dans un graphique de 150 px. */
const TALLEST_PX = 110;

/** Hauteur d'une barre nulle ou négative. */
const FLOOR_PX = 4;

/** « 2 », « 5,5 », « 11 » : le dixième nul est omis au-dessus d'une barre. */
const barLabel = (differentialT: number) =>
    formatTenths(differentialT).replace(',0', '');

/** « 17/10 » pour `2026-10-17`. */
const dayMonth = (playedOn: string) => {
    const [, month, day] = playedOn.split('-').map(Number);

    return `${day}/${month}`;
};

/**
 * Graphique des différentiels de la page Handicaps (spec « Graphique des
 * différentiels », D5) : une barre par round pour les 20 derniers, du plus
 * ancien au plus récent, à l'échelle du plus grand différentiel du joueur
 * (110 px) ; 4 px pour un différentiel nul ou négatif. Une barre qui compte
 * prend la couleur du joueur, les autres `bar` : l'historique dit « Compte »
 * en texte (DO22). Les barres partent de 4 px et grandissent jusqu'à leur
 * hauteur en 600 ms (D8).
 */
export function DifferentialBars({
    history,
    colorSlot,
}: {
    /** Les rounds du joueur, du plus récent au plus ancien. */
    history: HandicapHistoryRow[];
    colorSlot: number;
}) {
    const largest = Math.max(
        10,
        ...history.map((round) => round.differentialT),
    );
    const bars = history.slice(0, SHOWN).reverse();
    const entered = useEntered();

    return (
        <section aria-labelledby="differentials-title" className="mt-[30px]">
            <div className="mb-2.5 flex justify-between font-mono text-section text-ink-2 uppercase">
                <h2 id="differentials-title" className="font-mono">
                    Différentiels
                </h2>
                <span aria-hidden="true" className="flex items-center gap-1.5">
                    <span
                        className={cn('size-2 rounded-2', PLAYER_BG[colorSlot])}
                    />
                    Compte
                </span>
            </div>
            <div className="relative">
                {/* Filet sous les barres, d'un bord à l'autre (les barres sont espacées de 6 px). */}
                <div
                    aria-hidden="true"
                    className="absolute inset-x-0 top-[150px] border-t border-rule"
                />
                <ol
                    aria-labelledby="differentials-title"
                    className="flex gap-1.5"
                >
                    {bars.map((round) => (
                        <li
                            key={round.roundId}
                            className="flex min-w-0 flex-1 flex-col items-center"
                        >
                            <span className="flex h-[150px] w-full flex-col items-center justify-end gap-1.5">
                                <span
                                    data-part="value"
                                    className="font-mono text-caption tracking-normal text-ink-2"
                                >
                                    {barLabel(round.differentialT)}
                                </span>
                                <span
                                    data-part="bar"
                                    className={cn(
                                        'w-full max-w-9 rounded-t-4 transition-[height,background-color] duration-(--dur-bar) ease-(--ease-design)',
                                        round.countsInCurrent
                                            ? PLAYER_BG[colorSlot]
                                            : 'bg-bar',
                                    )}
                                    style={{
                                        height: `${entered ? Math.max(FLOOR_PX, Math.round((Math.max(0, round.differentialT) / largest) * TALLEST_PX)) : FLOOR_PX}px`,
                                    }}
                                />
                            </span>
                            <span
                                data-part="date"
                                className="mt-2 font-mono text-caption-9 text-ink-2"
                            >
                                {dayMonth(round.playedOn)}
                            </span>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}
