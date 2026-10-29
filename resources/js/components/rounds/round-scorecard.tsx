import { ScoreMark } from '@/components/scores/score-mark';
import { withTotals } from '@/lib/round-checks';
import type { HoleRow } from '@/lib/round-checks';
import { cn } from '@/lib/utils';

/** The card of a saved round, as RoundDetail sends it. */
export type SavedCard = {
    holes: { par: number; strokeIndex: number; distance: number | null }[];
    lines: { playerId: string; name: string; strokes: number[] }[];
};

type Column = number | 'out' | 'in' | 'total';

const FRONT: Column[] = [...Array.from({ length: 9 }, (_, i) => i), 'out'];
const BACK: Column[] = [
    ...Array.from({ length: 9 }, (_, i) => i + 9),
    'in',
    'total',
];
/** Grille large, à partir de 768 px : les 18 trous sur une seule rangée. */
const ALL: Column[] = [...FRONT, ...BACK];
const totals = { out: 'OUT', in: 'IN', total: 'TOT' };

type Row = { key: string; label: string; values: HoleRow; scores?: boolean };

/**
 * Une table de la carte : en-tête sur le bandeau d'encre, rangées Par et Index
 * en encre secondaire, une rangée par joueur en chiffres condensés avec les
 * marques de score.
 * - `wide` : grille large (≥ 768 px), libellés collants dans un cadre qui défile ;
 * - sinon : bloc empilé (aller ou retour) qui tient dans la largeur de l'écran,
 *   marques resserrées.
 */
function ScorecardTable({
    label,
    columns,
    rows,
    par,
    wide = false,
}: {
    label: string;
    columns: Column[];
    rows: Row[];
    par: HoleRow;
    wide?: boolean;
}) {
    const labelClass = cn(
        'px-2 text-left font-medium',
        wide
            ? 'sticky left-0 z-10 w-24 max-w-24 min-w-24 truncate'
            : 'w-11 pr-1 pl-1',
    );
    const cellClass = wide ? 'min-w-11 px-1' : 'px-0';
    // Totaux du bloc empilé : un filet de 2 px couleur page et 4 px de chaque
    // côté, pour que « 42 » (IN) et « 77 » (TOT) ne se lisent jamais comme un
    // seul nombre à 320 px.
    const totalClass = wide ? 'border-l' : 'border-l-2 px-1';
    // Bloc empilé : cercle et carré resserrés sous la taille de la case (22 px
    // au lieu de 32, 16 px sous 360 px), pour que deux marques voisines gardent
    // un blanc entre elles.
    const markClass = cn(
        'inline-flex justify-center',
        !wide && '[&>span]:size-[1.375rem] max-[22.5rem]:[&>span]:size-4',
    );

    return (
        <table
            aria-label={label}
            className="w-full border-separate border-spacing-0 text-center tabular-nums"
        >
            <thead className="bg-band text-band-ink">
                <tr>
                    <th
                        scope="col"
                        className={cn(labelClass, 'bg-band text-sm py-1.5')}
                    >
                        Trou
                    </th>
                    {columns.map((column) => (
                        <th
                            key={column}
                            scope="col"
                            className={cn(
                                cellClass,
                                'font-heading text-base py-1.5 font-bold',
                                // Sous 360 px, « 13 14 15 » resserrés : un cran plus petit.
                                !wide && 'max-[22.5rem]:text-sm',
                                typeof column !== 'number' &&
                                    cn(totalClass, 'border-l-band text-sm'),
                            )}
                        >
                            {typeof column === 'number'
                                ? column + 1
                                : totals[column]}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr
                        key={row.key}
                        className={
                            row.scores ? undefined : 'text-muted-foreground'
                        }
                    >
                        <th
                            scope="row"
                            className={cn(
                                labelClass,
                                'text-sm border-b bg-background py-1',
                                row.scores &&
                                    'font-heading text-base font-semibold text-foreground',
                            )}
                        >
                            {row.label}
                        </th>
                        {columns.map((column) => {
                            const hole = typeof column === 'number';
                            const value = hole
                                ? row.values.holes[column]
                                : row.values[column];
                            const holePar = hole ? par.holes[column] : null;

                            return (
                                <td
                                    key={column}
                                    className={cn(
                                        cellClass,
                                        'font-heading text-base border-b py-1',
                                        row.scores
                                            ? 'font-semibold'
                                            : 'text-sm',
                                        !hole &&
                                            cn(
                                                totalClass,
                                                'border-l-background bg-muted font-extrabold',
                                            ),
                                    )}
                                >
                                    {row.scores &&
                                    value !== null &&
                                    holePar !== null ? (
                                        <span className={markClass}>
                                            <ScoreMark
                                                strokes={value}
                                                par={holePar}
                                            />
                                        </span>
                                    ) : (
                                        (value ?? '')
                                    )}
                                </td>
                            );
                        })}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/**
 * Carte de score d'un round, objet principal de la page (spec `rounds`). Sous
 * 768 px, l'aller (1 à 9, OUT) au-dessus du retour (10 à 18, IN, TOT), sans
 * défilement de côté ; à partir de 768 px, une seule rangée de 18 trous, dans
 * un cadre qui défile s'il le faut. Une disposition est masquée en CSS
 * (`display: none`), donc absente de l'arbre d'accessibilité.
 */
export function RoundScorecard({ card }: { card: SavedCard }) {
    const par = withTotals(card.holes.map((hole) => hole.par));
    const rows: Row[] = [
        { key: 'par', label: 'Par', values: par },
        {
            key: 'index',
            label: 'Index',
            values: {
                holes: card.holes.map((hole) => hole.strokeIndex),
                out: null,
                in: null,
                total: null,
            },
        },
        ...card.lines.map((line, i) => ({
            key: `player-${i}`,
            label: line.name,
            values: withTotals(line.strokes),
            scores: true,
        })),
    ];

    return (
        <section
            aria-labelledby="scorecard-title"
            className="flex min-w-0 flex-col gap-2"
        >
            <h2
                id="scorecard-title"
                className="font-heading text-xl font-extrabold"
            >
                Carte de score
            </h2>
            <div className="flex flex-col gap-4 md:hidden">
                <ScorecardTable
                    label="Carte de score, aller"
                    columns={FRONT}
                    rows={rows}
                    par={par}
                />
                <ScorecardTable
                    label="Carte de score, retour"
                    columns={BACK}
                    rows={rows}
                    par={par}
                />
            </div>
            <div className="max-md:hidden">
                {/* Focusable so that keyboard users can scroll the table,
                    until the rows of the redesign replace it (task 5.2). */}
                <section
                    aria-label="Défilement de la carte de score"
                    tabIndex={0}
                    className="max-w-full min-w-0 overflow-x-auto"
                >
                    <ScorecardTable
                        label="Carte de score"
                        columns={ALL}
                        rows={rows}
                        par={par}
                        wide
                    />
                </section>
            </div>
        </section>
    );
}
