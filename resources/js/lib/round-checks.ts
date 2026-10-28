/**
 * The coherence checks of a round card (spec rounds, « Contrôles de cohérence »), run by the
 * editor at each keystroke. App\Domain\Rounds\RoundCardChecks replays them before any write;
 * tests/fixtures/round-checks.json keeps the two aligned.
 */

/** A cell: an integer, or `null` while it is empty. */
export type CellValue = number | null;

export type RoundCardHole = {
    par: CellValue;
    strokeIndex: CellValue;
    distance: CellValue;
};

export type RoundCardLine = {
    playerId: string | null;
    /** The 18 strokes, hole 1 first. */
    strokes: CellValue[];
};

/** The card as the editor sends it to the server. */
export type RoundCard = {
    courseName: string | null;
    tees: string | null;
    /** `yyyy-mm-dd`. */
    playedOn: string | null;
    /** The 18 holes, hole 1 first. */
    holes: RoundCardHole[];
    /** The lines of players in the order of the card. */
    lines: RoundCardLine[];
};

/**
 * A problem and the cells to highlight. Cell keys: `courseName`, `tees`, `playedOn`;
 * `<row>:<hole 1..18>` with `<row>` = `par`, `strokeIndex`, `distance` or `player<n>`
 * (n = position of the line, from 0); `player<n>:player` for the player of a line.
 */
export type Problem = { message: string; cells: string[] };

/** 18 values with their OUT, IN and TOT totals, `null` when a hole is empty. */
export type HoleRow = {
    holes: CellValue[];
    out: CellValue;
    in: CellValue;
    total: CellValue;
};

export const MAX_PLAYERS = 4;
/** Message of a required cell left empty. */
export const EMPTY = 'Case vide';
/** Message of a line without a player. */
export const UNASSIGNED = 'Ligne sans joueur';

type Range = { min: number; max: number; label: string };
const PAR_RANGE: Range = { min: 3, max: 5, label: 'Par' };
const SCORE_RANGE: Range = { min: 1, max: 15, label: 'Score' };
const INDEX_RANGE: Range = { min: 1, max: 18, label: 'Index' };
const DISTANCE_RANGE: Range = { min: 1, max: 999, label: 'Distance' };

const holeKey = (row: string, hole: number) => `${row}:${hole + 1}`;

const outOfRange = (value: number, range: Range): Problem['message'] | null =>
    value < range.min || value > range.max
        ? `${range.label} ${value} hors de ${range.min} à ${range.max}`
        : null;

/** Required cells: empty or out of bounds, one problem per cell. */
function cellProblems(
    row: string,
    values: readonly CellValue[],
    range: Range,
): Problem[] {
    return values.flatMap((value, hole): Problem[] => {
        const cells = [holeKey(row, hole)];

        if (value === null) {
            return [{ message: EMPTY, cells }];
        }

        const message = outOfRange(value, range);

        return message === null ? [] : [{ message, cells }];
    });
}

/** The distance is optional: only a filled cell out of 1 to 999 is a problem. */
function distanceProblems(distances: readonly CellValue[]): Problem[] {
    return distances.flatMap((value, hole): Problem[] => {
        const message =
            value === null ? null : outOfRange(value, DISTANCE_RANGE);

        return message === null
            ? []
            : [{ message, cells: [holeKey('distance', hole)] }];
    });
}

/** The indexes form a permutation of 1 to 18: each duplicate highlights all its cells. */
function strokeIndexProblems(indexes: readonly CellValue[]): Problem[] {
    const problems = cellProblems('strokeIndex', indexes, INDEX_RANGE);
    const holesByIndex = new Map<number, number[]>();

    indexes.forEach((value, hole) => {
        if (value !== null) {
            holesByIndex.set(value, [...(holesByIndex.get(value) ?? []), hole]);
        }
    });

    for (const [value, holes] of holesByIndex) {
        if (holes.length > 1) {
            problems.push({
                message: `Index ${value} en double`,
                cells: holes.map((hole) => holeKey('strokeIndex', hole)),
            });
        }
    }

    return problems;
}

type TextField = 'courseName' | 'tees' | 'playedOn';

function fieldProblems(
    card: RoundCard,
    fields: readonly TextField[],
): Problem[] {
    return fields
        .filter((field) => !card[field]?.trim())
        .map((field) => ({ message: EMPTY, cells: [field] }));
}

function playerCountProblems(count: number): Problem[] {
    if (count === 0) {
        return [{ message: 'Aucune ligne de joueur', cells: [] }];
    }

    if (count > MAX_PLAYERS) {
        return [{ message: `${MAX_PLAYERS} joueurs au plus`, cells: [] }];
    }

    return [];
}

function unassignedLineProblems(lines: readonly RoundCardLine[]): Problem[] {
    return lines.flatMap(({ playerId }, position) =>
        playerId === null
            ? [{ message: UNASSIGNED, cells: [`player${position}:player`] }]
            : [],
    );
}

function duplicatePlayerProblems(lines: readonly RoundCardLine[]): Problem[] {
    const positionsByPlayer = new Map<string, number[]>();

    lines.forEach(({ playerId }, position) => {
        if (playerId !== null) {
            positionsByPlayer.set(playerId, [
                ...(positionsByPlayer.get(playerId) ?? []),
                position,
            ]);
        }
    });

    return [...positionsByPlayer.values()]
        .filter((positions) => positions.length > 1)
        .map((positions) => ({
            message: 'Joueur en double',
            cells: positions.map((position) => `player${position}:player`),
        }));
}

/**
 * The problems of the card, in the order the editor lists them; empty when it can be saved.
 */
export function checkRoundCard(card: RoundCard): Problem[] {
    return [
        ...fieldProblems(card, ['courseName', 'tees']),
        ...cellProblems(
            'par',
            card.holes.map((hole) => hole.par),
            PAR_RANGE,
        ),
        ...strokeIndexProblems(card.holes.map((hole) => hole.strokeIndex)),
        ...distanceProblems(card.holes.map((hole) => hole.distance)),
        ...playerCountProblems(card.lines.length),
        ...card.lines.flatMap((line, position) =>
            cellProblems(`player${position}`, line.strokes, SCORE_RANGE),
        ),
        ...fieldProblems(card, ['playedOn']),
        ...unassignedLineProblems(card.lines),
        ...duplicatePlayerProblems(card.lines),
    ];
}

const sum = (values: readonly CellValue[]): CellValue =>
    values.every((value) => value !== null)
        ? values.reduce<number>((total, value) => total + (value ?? 0), 0)
        : null;

/** A row of 18 values with its OUT, IN and TOT totals, `null` when a hole is empty. */
export function withTotals(holes: readonly CellValue[]): HoleRow {
    const out = sum(holes.slice(0, 9));
    const back = sum(holes.slice(9));

    return { holes: [...holes], out, in: back, total: sum([out, back]) };
}

/** Label of the save button and of the server refusal: « 1 problème à corriger ». */
export function problemCountLabel(count: number): string {
    return `${count} ${count > 1 ? 'problèmes' : 'problème'} à corriger`;
}
