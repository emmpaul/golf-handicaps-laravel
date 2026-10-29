import { Fragment } from 'react';
import { ScoreLegend, ScoreMark } from '@/components/scores/score-mark';
import { cn } from '@/lib/utils';

/** The card of a saved round, as RoundDetail sends it. */
export type SavedCard = {
    holes: { par: number; strokeIndex: number; distance: number | null }[];
    lines: {
        playerId: string;
        name: string;
        colorSlot: number;
        strokes: number[];
    }[];
};

const sum = (values: number[]) =>
    values.reduce((total, value) => total + value, 0);

/** Holes 1 to 9 (`from` 0) or 10 to 18 (`from` 9) of a row of 18 values. */
const nine = (values: number[], from: number) => values.slice(from, from + 9);

/** Cellule de score : 52 px au plus, resserrée jusqu'à la marque de 32 px à 320 px. */
const SCORE_CELL = 'w-[52px] min-w-8 p-0 text-center';

/** Rangée de sous-total : 40 px, fond `surface-1`, coins de 8 px. */
function SubtotalRow({ label, totals }: { label: string; totals: number[] }) {
    return (
        <tr className="h-10 font-mono text-meta-12">
            <th
                scope="row"
                colSpan={2}
                className="rounded-l-8 bg-surface-1 pl-2 text-left font-medium whitespace-nowrap text-ink-2"
            >
                {label}
            </th>
            {totals.map((total, i) => (
                <td
                    key={i}
                    className={cn(
                        SCORE_CELL,
                        'bg-surface-1 text-ink',
                        i === totals.length - 1 && 'rounded-r-8',
                    )}
                >
                    {total}
                </td>
            ))}
        </tr>
    );
}

/**
 * Carte de score d'un round (spec `rounds`, « Carte de score d'un round »,
 * « Mise en page de la page d'un round ») : une rangée de 44 px par trou,
 * numéro, « P4 · 8 », puis le score de chaque joueur dans l'ordre de la carte
 * avec sa marque ; « ALLER » après le trou 9, « RETOUR » et « TOTAL » après le
 * 18. L'en-tête donne les cinq premières lettres de chaque nom. À 320 px,
 * quatre joueurs tiennent sans défilement : les colonnes de score se
 * resserrent jusqu'à la marque. La légende suit la carte.
 */
export function RoundScorecard({ card }: { card: SavedCard }) {
    const pars = card.holes.map((hole) => hole.par);

    return (
        <section aria-label="Carte de score du round" className="flex flex-col">
            <table
                aria-label="Carte de score"
                className="w-full border-separate border-spacing-0 tabular-nums"
            >
                <thead>
                    <tr className="font-mono text-caption text-ink-2 uppercase">
                        <th
                            scope="col"
                            className="w-10 border-b border-rule pb-2.5 text-left font-medium"
                        >
                            Trou
                        </th>
                        <th
                            scope="col"
                            className="border-b border-rule pb-2.5 text-left font-medium whitespace-nowrap"
                        >
                            Par · idx
                        </th>
                        {card.lines.map((line) => (
                            <th
                                key={line.playerId}
                                scope="col"
                                className={cn(
                                    SCORE_CELL,
                                    'border-b border-rule pb-2.5 font-medium',
                                )}
                            >
                                {line.name.slice(0, 5).toLocaleUpperCase('fr')}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {card.holes.map((hole, i) => (
                        <Fragment key={i}>
                            <tr className="h-11">
                                <th
                                    scope="row"
                                    className="border-b border-rule-soft text-left font-mono text-num-m-17 font-medium"
                                >
                                    {i + 1}
                                </th>
                                <td className="border-b border-rule-soft font-mono text-meta-12 whitespace-nowrap text-ink-2">
                                    P{hole.par} · {hole.strokeIndex}
                                </td>
                                {card.lines.map((line) => (
                                    <td
                                        key={line.playerId}
                                        className={cn(
                                            SCORE_CELL,
                                            'border-b border-rule-soft font-mono text-num-m-16',
                                        )}
                                    >
                                        <span className="inline-flex justify-center">
                                            <ScoreMark
                                                strokes={line.strokes[i]}
                                                par={hole.par}
                                            />
                                        </span>
                                    </td>
                                ))}
                            </tr>
                            {i === 8 ? (
                                <SubtotalRow
                                    label={`ALLER · PAR ${sum(nine(pars, 0))}`}
                                    totals={card.lines.map((line) =>
                                        sum(nine(line.strokes, 0)),
                                    )}
                                />
                            ) : null}
                        </Fragment>
                    ))}
                    <SubtotalRow
                        label={`RETOUR · PAR ${sum(nine(pars, 9))}`}
                        totals={card.lines.map((line) =>
                            sum(nine(line.strokes, 9)),
                        )}
                    />
                    <SubtotalRow
                        label={`TOTAL · PAR ${sum(pars)}`}
                        totals={card.lines.map((line) => sum(line.strokes))}
                    />
                </tbody>
            </table>
            <div className="mt-4">
                <ScoreLegend />
            </div>
        </section>
    );
}
