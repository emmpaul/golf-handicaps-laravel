import { EMPTY, MAX_PLAYERS, UNASSIGNED } from '@/lib/round-checks';
import type {
    CellValue,
    Problem,
    ReadTotals,
    RoundCard,
    RoundCardHole,
    RoundCardLine,
} from '@/lib/round-checks';

export const HOLES = 18;
export const MAX_LINES = MAX_PLAYERS;

/** Parcours connu (nom, tees) et ses valeurs de référence (spec `rounds`, KnownCourses). */
export type KnownCourse = {
    courseName: string;
    tees: string;
    pars: number[];
    strokeIndexes: number[];
    distances: (number | null)[];
};

/** Joueur proposé aux lignes, avec son rang de couleur (D6, PlayerList). */
export type PlayerOption = { id: string; name: string; colorSlot: number };

/**
 * Ligne de joueur de l'éditeur : la ligne de la carte et une `key` stable,
 * propre à l'onglet, qui suit la ligne quand une ligne précédente est retirée.
 */
export type EditorLine = RoundCardLine & { key: string };

/** État local de l'éditeur (design D9) : la carte, ses lignes avec leur `key`. */
export type EditorState = Omit<RoundCard, 'lines'> & { lines: EditorLine[] };

export type TextField = 'courseName' | 'tees' | 'playedOn';

/** Total imprimé sur la carte : OUT, IN ou TOT. */
export type TotalColumn = keyof ReadTotals;

/** Ligne de la grille : `par`, `strokeIndex`, `distance` ou `player<n>`. */
export type EditorAction =
    | { type: 'field'; field: TextField; value: string | null }
    | { type: 'cell'; row: string; hole: number; value: CellValue }
    | { type: 'total'; row: string; column: TotalColumn; value: CellValue }
    | { type: 'associate'; key: string; playerId: string | null }
    | { type: 'addLine' }
    | { type: 'removeLine'; key: string }
    | { type: 'course'; course: KnownCourse };

const emptyValues = (): CellValue[] => Array<CellValue>(HOLES).fill(null);

/**
 * Ligne de l'éditeur. Une ligne lue sur la carte garde son nom lu et ses
 * totaux imprimés ; une ligne saisie à la main n'en a pas.
 */
export function newLine(row: Partial<RoundCardLine> = {}): EditorLine {
    return {
        key: crypto.randomUUID(),
        playerId: row.playerId ?? null,
        strokes: row.strokes ? [...row.strokes] : emptyValues(),
        ...readParts(row),
    };
}

/** Nom et totaux lus d'une ligne, seulement quand la lecture les a donnés. */
function readParts({
    readName,
    totals,
}: Partial<RoundCardLine>): Pick<RoundCardLine, 'readName' | 'totals'> {
    return {
        ...(readName ? { readName } : {}),
        ...(totals ? { totals: { ...totals } } : {}),
    };
}

/** Saisie manuelle : une ligne de joueur, la date du jour donnée par le serveur, le reste vide. */
export function emptyEditorState(playedOn: string): EditorState {
    return {
        courseName: null,
        tees: null,
        playedOn,
        holes: Array.from({ length: HOLES }, () => ({
            par: null,
            strokeIndex: null,
            distance: null,
        })),
        lines: [newLine()],
    };
}

/** Correction : la carte du round enregistré, chaque ligne avec sa `key`. */
export function cardEditorState(card: RoundCard): EditorState {
    return {
        ...card,
        holes: card.holes.map((hole) => ({ ...hole })),
        lines: card.lines.map((line) => newLine(line)),
    };
}

/**
 * La carte envoyée et contrôlée : tout sauf la `key` des lignes. Les noms et
 * totaux lus d'un import n'y figurent que s'ils existent : une saisie manuelle
 * envoie la même carte qu'avant l'import.
 */
export function toRoundCard(state: EditorState): RoundCard {
    return {
        courseName: state.courseName,
        tees: state.tees,
        playedOn: state.playedOn,
        holes: state.holes,
        lines: state.lines.map((line) => ({
            playerId: line.playerId,
            strokes: line.strokes,
            ...readParts(line),
        })),
        ...(state.parTotals ? { parTotals: state.parTotals } : {}),
    };
}

const HOLE_ROWS = {
    par: 'par',
    strokeIndex: 'strokeIndex',
    distance: 'distance',
} as const satisfies Record<string, keyof RoundCardHole>;

/** Case `row` du trou `hole` (1 à 18) remplacée par `value`. */
function setCell(
    state: EditorState,
    row: string,
    hole: number,
    value: CellValue,
): EditorState {
    if (row in HOLE_ROWS) {
        const field = HOLE_ROWS[row as keyof typeof HOLE_ROWS];

        return {
            ...state,
            holes: state.holes.map((current, i) =>
                i === hole - 1 ? { ...current, [field]: value } : current,
            ),
        };
    }

    const position = Number(row.replace('player', ''));

    return {
        ...state,
        lines: state.lines.map((line, i) =>
            i === position
                ? {
                      ...line,
                      strokes: line.strokes.map((strokes, h) =>
                          h === hole - 1 ? value : strokes,
                      ),
                  }
                : line,
        ),
    };
}

/**
 * Total lu `column` de la ligne `row` (`par` ou `player<n>`) remplacé par
 * `value` ; sans effet sur une ligne sans totaux lus, dont les totaux sont
 * calculés.
 */
function setTotal(
    state: EditorState,
    row: string,
    column: TotalColumn,
    value: CellValue,
): EditorState {
    const replace = (totals: ReadTotals | null | undefined) =>
        totals ? { ...totals, [column]: value } : totals;

    if (row === 'par') {
        return { ...state, parTotals: replace(state.parTotals) };
    }

    const position = Number(row.replace('player', ''));

    return {
        ...state,
        lines: state.lines.map((line, i) =>
            i === position ? { ...line, totals: replace(line.totals) } : line,
        ),
    };
}

/** Pré-remplit parcours, tees, pars, index et distances ; la date et les lignes restent. */
function applyCourse(state: EditorState, course: KnownCourse): EditorState {
    return {
        ...state,
        courseName: course.courseName,
        tees: course.tees,
        holes: course.pars.map((par, hole) => ({
            par,
            strokeIndex: course.strokeIndexes[hole] ?? null,
            distance: course.distances[hole] ?? null,
        })),
    };
}

export function editorReducer(
    state: EditorState,
    action: EditorAction,
): EditorState {
    switch (action.type) {
        case 'field':
            return { ...state, [action.field]: action.value };
        case 'cell':
            return setCell(state, action.row, action.hole, action.value);
        case 'total':
            return setTotal(state, action.row, action.column, action.value);
        case 'associate':
            return {
                ...state,
                lines: state.lines.map((line) =>
                    line.key === action.key
                        ? { ...line, playerId: action.playerId }
                        : line,
                ),
            };
        case 'addLine':
            return state.lines.length < MAX_LINES
                ? { ...state, lines: [...state.lines, newLine()] }
                : state;
        case 'removeLine':
            return state.lines.length > 1
                ? {
                      ...state,
                      lines: state.lines.filter(
                          (line) => line.key !== action.key,
                      ),
                  }
                : state;
        case 'course':
            return applyCourse(state, action.course);
    }
}

/** Texte d'une case vers sa valeur : un entier positif, sinon case vide. */
export function parseCell(text: string): CellValue {
    const trimmed = text.trim();

    return /^\d{1,5}$/.test(trimmed) ? Number(trimmed) : null;
}

export const courseLabel = ({
    courseName,
    tees,
}: {
    courseName: string;
    tees: string;
}) => `${courseName} / ${tees}`;

/**
 * Le joueur créé depuis une ligne, retrouvé dans les props rechargées : le nom
 * est unique sans tenir compte de la casse (spec `players`), le serveur le
 * garde tel que saisi, sans les espaces autour.
 */
export function createdPlayer(
    players: readonly PlayerOption[],
    name: string,
): PlayerOption | undefined {
    const wanted = name.trim().toLocaleLowerCase('fr');

    return players.find(
        (player) => player.name.toLocaleLowerCase('fr') === wanted,
    );
}

/**
 * Cases à marquer en erreur (spec « Saisie manuelle neuve sans alerte »). Un
 * contrôle en défaut marque toujours ses cases. Une case obligatoire vide (ou
 * une ligne sans joueur) ne l'est qu'une fois quittée (`touched`), sauf si
 * `flagUntouchedEmpty` (round enregistré) : elle l'est alors dès l'ouverture.
 * Le compte des problèmes, lui, ne change pas.
 */
export function shownInvalidCells(
    problems: readonly Problem[],
    {
        touched,
        flagUntouchedEmpty,
    }: { touched: ReadonlySet<string>; flagUntouchedEmpty: boolean },
): Set<string> {
    const shown = new Set<string>();

    for (const { message, cells } of problems) {
        const toFill = message === EMPTY || message === UNASSIGNED;

        for (const cell of cells) {
            if (!toFill || flagUntouchedEmpty || touched.has(cell)) {
                shown.add(cell);
            }
        }
    }

    return shown;
}
