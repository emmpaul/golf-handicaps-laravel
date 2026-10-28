// The reference round, card IMG_3064 (tasks.md, preamble): BETHPAGE BLACK / Red of
// 2026-10-17, par 71, Gab 72 (OUT 38, IN 34) and Manu 88. Hand-written literals, the
// expected values of the round page and round editor tests.

export const GAB = '0190a6b2-7c3e-7000-8000-000000000001';
export const MANU = '0190a6b2-7c3e-7000-8000-000000000002';
export const SEAN = '0190a6b2-7c3e-7000-8000-000000000003';
export const ROUND = '0190a6b2-7c3e-7000-8000-0000000000aa';

export const PARS = [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4];
export const STROKE_INDEXES = [
    8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15,
];
export const DISTANCES = [
    420, 344, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401,
    423, 173, 337,
];
export const GAB_STROKES = [
    5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3,
];
export const MANU_STROKES = [
    7, 5, 3, 7, 6, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5,
];

export const HOLES = PARS.map((par, hole) => ({
    par,
    strokeIndex: STROKE_INDEXES[hole],
    distance: DISTANCES[hole],
}));

/** The card as RoundController@edit sends it (RoundCard::toArray). */
export const REFERENCE_CARD = {
    courseName: 'BETHPAGE BLACK',
    tees: 'Red',
    playedOn: '2026-10-17',
    holes: HOLES,
    lines: [
        { playerId: GAB, strokes: GAB_STROKES },
        { playerId: MANU, strokes: MANU_STROKES },
    ],
};

export const PLAYERS = [
    { id: GAB, name: 'Gab' },
    { id: MANU, name: 'Manu' },
    { id: SEAN, name: 'Sean' },
];
