import { Fragment } from 'react';
import type { CellValue } from '@/lib/round-checks';
import { cn } from '@/lib/utils';
import { parseCell } from './form-state';
import type { TotalColumn } from './form-state';

/**
 * Une ligne de la carte dans la grille ; `key` est le préfixe des clés de case
 * des contrôles (`par`, `strokeIndex`, `player<n>`). `cellLabel` préfixe le
 * nom accessible des cases (`label` par défaut) : il reste stable quand le nom
 * affiché change, par exemple « Ligne 1 » pour la ligne de Gab. `headClass` :
 * la couleur du nom en tête de colonne. `totals` : OUT, IN et TOT calculés, en
 * lecture seule (spec `rounds`, « Totaux calculés ») ; lus sur la carte d'un
 * import, modifiables (`readTotals`, spec « Contrôle des totaux lus »).
 */
export type GridRow = {
    key: string;
    label: string;
    cellLabel?: string;
    headClass?: string;
    holes: CellValue[];
    totals?: { out: CellValue; in: CellValue; total: CellValue };
    readTotals?: boolean;
};

/** Noms accessibles des totaux, gardés de la grille d'avant (« Ligne 1 OUT »). */
const TOTAL_NAMES = { out: 'OUT', in: 'IN', total: 'TOT' } as const;

const cellKey = (row: GridRow, column: number | TotalColumn) =>
    `${row.key}:${column}`;

const cellName = (row: GridRow, column: number | TotalColumn) =>
    typeof column === 'number'
        ? `${row.cellLabel ?? row.label} trou ${column}`
        : `${row.cellLabel ?? row.label} ${TOTAL_NAMES[column]}`;

/** Focus au clavier : contour d'encre de 2 px décalé de 2 px (spec visual-theme). */
const FOCUS =
    'outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid focus-visible:outline-ring';
/** Case de 44 px, rayon 8 px, Geist Mono 16 px. */
const CELL = cn(
    FOCUS,
    'h-11 min-w-0 rounded-8 border px-0.5 text-center font-mono text-num-m-16 transition-[background-color,border-color] duration-(--dur-press)',
);
/** Case en erreur (spec « Mise en page de l'éditeur ») : fond et bordure ambre. */
const CELL_ERROR = 'border-accent/60 bg-accent/8';

/** Champ d'une case : texte saisi lu par `parseCell`. */
export function GridInput({
    label,
    value,
    invalid,
    readOnly = false,
    onChange,
    onBlur,
    className,
}: {
    label: string;
    value: CellValue;
    invalid: boolean;
    readOnly?: boolean;
    onChange: (value: CellValue) => void;
    onBlur: () => void;
    className?: string;
}) {
    return (
        <input
            aria-label={label}
            aria-invalid={invalid || undefined}
            inputMode="numeric"
            autoComplete="off"
            readOnly={readOnly}
            value={value ?? ''}
            onChange={(event) => onChange(parseCell(event.currentTarget.value))}
            onBlur={onBlur}
            className={className}
        />
    );
}

type GridProps = {
    /** Par, index, puis une ligne par joueur. */
    rows: GridRow[];
    invalid: ReadonlySet<string>;
    onCell: (row: string, hole: number, value: CellValue) => void;
    /** Total lu modifié (lignes à `readTotals`). */
    onTotal?: (row: string, column: TotalColumn, value: CellValue) => void;
    onLeave?: (cell: string) => void;
};

/** Rangées des sous-totaux : après le trou 9, puis après le trou 18. */
const TOTAL_ROWS: { after: number; column: TotalColumn; label: string }[] = [
    { after: 9, column: 'out', label: 'ALL.' },
    { after: 18, column: 'in', label: 'RET.' },
    { after: 18, column: 'total', label: 'TOT.' },
];

/**
 * Grille de saisie (spec `rounds`, « Grille de saisie sur téléphone »,
 * « Mise en page de l'éditeur ») : une rangée par trou, numéro, par, index
 * (cases à contour `rule`, DO23), puis le score de chaque ligne de joueur ;
 * « ALL. » après le trou 9, « RET. » et « TOT. » après le 18. La rangée des
 * noms reste collée en haut pendant le défilement, chaque nom dans la couleur
 * de son joueur. Une seule disposition à toute largeur : quatre lignes tiennent
 * à 320 px, les cases de score se partagent la largeur restante. Une case de
 * score sous le par prend le fond `signal` ; une case en erreur, l'ambre.
 */
export function ScoreGrid({
    rows,
    invalid,
    onCell,
    onTotal,
    onLeave,
}: GridProps) {
    const [par, strokeIndex, ...players] = rows;

    function holeCell(row: GridRow, hole: number, className: string) {
        const key = cellKey(row, hole);

        return (
            <GridInput
                label={cellName(row, hole)}
                value={row.holes[hole - 1]}
                invalid={invalid.has(key)}
                onChange={(value) => onCell(row.key, hole, value)}
                onBlur={() => onLeave?.(key)}
                className={className}
            />
        );
    }

    function scoreClass(row: GridRow, hole: number) {
        const strokes = row.holes[hole - 1];
        const holePar = par.holes[hole - 1];

        if (invalid.has(cellKey(row, hole))) {
            return CELL_ERROR;
        }

        return strokes !== null && holePar !== null && strokes < holePar
            ? 'border-signal/50 bg-signal/22'
            : 'border-rule bg-surface-1';
    }

    function totalCell(row: GridRow, column: TotalColumn, className: string) {
        const value = row.totals?.[column];

        if (value === undefined) {
            return <span className={className} />;
        }

        const key = cellKey(row, column);
        const error = invalid.has(key);

        return (
            <GridInput
                label={cellName(row, column)}
                value={value}
                invalid={error}
                readOnly={!row.readTotals}
                onChange={(next) => onTotal?.(row.key, column, next)}
                onBlur={() => onLeave?.(key)}
                className={cn(
                    className,
                    row.readTotals
                        ? cn(
                              CELL,
                              error ? CELL_ERROR : 'border-rule bg-surface-1',
                          )
                        : cn(FOCUS, 'min-w-0 bg-transparent text-center'),
                    !row.readTotals && error && 'text-accent',
                )}
            />
        );
    }

    return (
        <section aria-label="Carte à corriger" className="mt-[26px] min-w-0">
            <div
                data-testid="grid-head"
                className="sticky top-(--safe-top) z-10 flex items-center gap-1.5 border-b border-rule bg-bg pt-2 pb-2 font-mono text-caption text-ink-2 uppercase"
            >
                <span className="w-[30px] flex-none">Trou</span>
                <span className="w-11 flex-none text-center">Par</span>
                <span className="w-11 flex-none text-center">Idx</span>
                {players.map((row) => (
                    <span
                        key={row.key}
                        data-slot="grid-line-name"
                        className={cn(
                            'min-w-0 flex-1 truncate text-center',
                            row.headClass,
                        )}
                    >
                        {row.label}
                    </span>
                ))}
            </div>
            {Array.from({ length: 18 }, (_, i) => i + 1).map((hole) => (
                <Fragment key={hole}>
                    <div
                        data-slot="grid-row"
                        className="flex items-center gap-1.5 border-b border-rule-soft py-1"
                    >
                        <span
                            data-slot="grid-row-label"
                            className="w-[30px] flex-none font-mono text-num-m-15"
                        >
                            {hole}
                        </span>
                        {[par, strokeIndex].map((row) => (
                            <Fragment key={row.key}>
                                {holeCell(
                                    row,
                                    hole,
                                    cn(
                                        CELL,
                                        'w-11 flex-none text-ink-2',
                                        invalid.has(cellKey(row, hole))
                                            ? CELL_ERROR
                                            : 'border-rule bg-transparent',
                                    ),
                                )}
                            </Fragment>
                        ))}
                        {players.map((row) => (
                            <Fragment key={row.key}>
                                {holeCell(
                                    row,
                                    hole,
                                    cn(
                                        CELL,
                                        'flex-1 text-ink',
                                        scoreClass(row, hole),
                                    ),
                                )}
                            </Fragment>
                        ))}
                    </div>
                    {TOTAL_ROWS.filter(({ after }) => after === hole).map(
                        ({ column, label }) => (
                            <div
                                key={column}
                                data-slot="grid-row"
                                // Totaux en 16 px : une case lue, même en lecture seule, ne fait pas zoomer iOS.
                                className={cn(
                                    'flex items-center gap-1.5 py-1 font-mono text-num-m-16 text-ink',
                                    column === 'total' ? 'min-h-10' : 'min-h-9',
                                )}
                            >
                                <span
                                    data-slot="grid-row-label"
                                    className="w-[30px] flex-none text-section tracking-normal text-ink-2"
                                >
                                    {label}
                                </span>
                                {totalCell(
                                    par,
                                    column,
                                    'w-11 flex-none text-ink-2',
                                )}
                                <span className="w-11 flex-none" />
                                {players.map((row) => (
                                    <Fragment key={row.key}>
                                        {totalCell(row, column, 'flex-1')}
                                    </Fragment>
                                ))}
                            </div>
                        ),
                    )}
                </Fragment>
            ))}
        </section>
    );
}
