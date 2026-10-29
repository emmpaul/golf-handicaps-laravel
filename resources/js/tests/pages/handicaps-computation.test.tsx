import { router } from '@inertiajs/react';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';

import { AppNav } from '@/components/app-nav';
import { RULE_LINES } from '@/components/handicaps/computation-help';
import { RETAINED_BY_ROUNDS } from '@/components/handicaps/computation/retained-step';
import HandicapsComputation from '@/pages/handicaps/computation';
import { GAB, MANU, ROUND, SEAN } from '@/tests/reference-round';
import { expectPlayerTabs } from '@/tests/player-tabs';

// Spec handicap-index (index-computation-page): « Page « Calcul de l'index » »,
// « Lignes de règle du calcul de l'index », « Étape 01 » to « Étape 05 »,
// « Liste des 20 derniers rounds de l'étape 03 », « Cas particuliers »,
// « Joueur sans round » and « Mise en page de la page « Calcul de l'index » ».
// The props are those of HandicapController::computation (design D4): the
// facts of PlayerIndexComputation, written by hand from the scenarios. Head
// needs the head manager of createInertiaApp and renders nothing here; usePage
// is simulated for the top row and the tab bar.

let url = '/handicaps/calcul';

afterEach(() => {
    url = '/handicaps/calcul';
});

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url, props: {} }),
}));

type Player = {
    id: string;
    name: string;
    indexT: number | null;
    provisional: boolean;
    deltaT: number | null;
    rounds: number;
    colorSlot: number;
};

const gab: Player = {
    id: GAB,
    name: 'Gab',
    indexT: 10,
    provisional: true,
    deltaT: null,
    rounds: 1,
    colorSlot: 0,
};
const manu: Player = {
    id: MANU,
    name: 'Manu',
    indexT: 120,
    provisional: true,
    deltaT: null,
    rounds: 1,
    colorSlot: 1,
};
const sean: Player = {
    id: SEAN,
    name: 'Sean',
    indexT: null,
    provisional: false,
    deltaT: null,
    rounds: 0,
    colorSlot: 2,
};

/** The reference card IMG_3064 for Gab: BETHPAGE BLACK / Red, no rating, 72 on par 71. */
const gabReference = {
    roundId: ROUND,
    playedOn: '2026-10-17',
    courseName: 'BETHPAGE BLACK',
    gross: 72,
    adjusted: 72,
    parTotal: 71,
    ratingT: null,
    slope: null,
    differentialT: 10,
    countsInCurrent: true,
};

/** Gab on the reference card alone, the facts of the scenarios « Round sans rating ». */
const gabAlone = {
    rounds: [gabReference],
    cappedHole: null,
    meanT: 10,
    adjustmentT: 0,
};

/** The seven lines of « Texte exact des lignes », in this order. */
const RULE_TEXTS = [
    'Score ajusté : chaque trou compte au plus par + 4.',
    'Différentiel d’un round : 113 / slope × (score ajusté − course rating), arrondi au dixième.',
    'Différentiels retenus : 1 de 3 à 5 rounds, 2 de 6 à 8, 3 de 9 à 11, 4 de 12 à 14, 5 à 15 ou 16, 6 à 17 ou 18, 7 à 19, 8 à 20.',
    'Ajustement : −2,0 à 3 rounds, −1,0 à 4 rounds et à 6 rounds, aucun sinon.',
    'Index : moyenne des meilleurs différentiels parmi les 20 derniers rounds, arrondie au dixième et plafonnée à 54,0.',
    'Index provisoire à 1 ou 2 rounds : moyenne de leurs différentiels, sans ajustement.',
    'Parcours sans rating : la course rating vaut le par du round et le slope 113.',
];

function ruleLines(): string[] {
    return screen
        .getAllByTestId('rule-line')
        .map((line) => line.textContent ?? '');
}

describe('Index computation page, header', () => {
    // Mutations: the back link without ?joueur= (the Handicaps page would open
    // on Gab), the tabs pointing at /handicaps (handicapsIndex kept in hrefFor),
    // or `aria-current` on every tab → this test goes red.
    it('links back to /handicaps?joueur=<Manu>, titles the page and shows one tab per player to /handicaps/calcul', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={MANU}
                computation={gabAlone}
            />,
        );

        expect(
            screen
                .getByRole('link', { name: 'Handicaps' })
                .getAttribute('href'),
        ).toBe(`/handicaps?joueur=${MANU}`);
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Comment l’index est calculé',
        );

        expectPlayerTabs([
            `/handicaps/calcul?joueur=${GAB}`,
            `/handicaps/calcul?joueur=${MANU}`,
            `/handicaps/calcul?joueur=${SEAN}`,
        ]);
        expect(screen.getByTestId('computation-intro').textContent).toBe(
            'Chaque étape avec les rounds de Manu.',
        );
    });

    it('opens on Gab with « Chaque étape avec les rounds de Gab. »', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={gabAlone}
            />,
        );

        const intro = screen.getByTestId('computation-intro');

        expect(intro.textContent).toBe('Chaque étape avec les rounds de Gab.');
        expect(intro.className).toContain('text-body-13');
        expect(within(intro).getByText('Gab').className).toContain('text-ink');
    });

    // Spec « Choix d'un joueur », design D4: the visit keeps the page and
    // reloads only the chosen player and the computation of that player.
    // Mutation: `only` left at the Handicaps value (history, scoringStats) →
    // red.
    it('reloads only selectedPlayerId and computation when a tab is chosen', () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={gabAlone}
            />,
        );

        fireEvent.click(screen.getByRole('link', { name: /Manu/ }));

        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit.mock.calls[0][1]).toMatchObject({
            only: ['selectedPlayerId', 'computation'],
            preserveState: true,
            preserveScroll: true,
        });
        visit.mockRestore();
    });

    // Spec « Texte exact des lignes ». Mutation: the special cases rendered
    // before step 05, or one step reading RULE_LINES at the wrong position → red.
    it('shows the seven rule lines in the order of the steps, in ink-soft at 15 px / 1,45', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={gabAlone}
            />,
        );

        expect(ruleLines()).toEqual(RULE_TEXTS);
        expect(screen.getAllByTestId('rule-line')[0].className).toContain(
            'text-prose',
        );
        expect(screen.getAllByTestId('rule-line')[0].className).toContain(
            'text-ink-soft',
        );
    });

    // Spec « Libellé d'étape ». Mutation: the number in ink-2 like the label,
    // or the steps renumbered → red.
    it('heads each step with its number in the colour of the player over a rule', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={gabAlone}
            />,
        );

        const headings = screen.getAllByRole('heading', { level: 2 });

        expect(headings.map((heading) => heading.textContent)).toEqual([
            '01Score ajusté',
            '02Différentiel',
            '03Différentiels retenus',
            '04Ajustement',
            '05Index',
            'Cas particuliers',
        ]);
        expect(headings[1].className).toContain('text-section');
        expect(headings[1].className).toContain('text-ink-2');
        expect(headings[1].className).toContain('uppercase');
        expect(headings[1].className).toContain('border-rule');
        expect(within(headings[1]).getByText('02').className).toContain(
            'text-player-1',
        );
    });

    // Spec « Aucun joueur ». Mutation: the steps left out without player → red.
    it('shows « Aucun joueur. » without tabs, then the seven rule lines', () => {
        render(
            <HandicapsComputation
                players={[]}
                selectedPlayerId={null}
                computation={null}
            />,
        );

        expect(screen.getByText('Aucun joueur.')).toBeInTheDocument();
        expect(
            screen.queryByRole('navigation', { name: 'Choisir un joueur' }),
        ).toBeNull();
        expect(screen.queryByTestId('computation-intro')).toBeNull();
        expect(
            screen
                .getByRole('link', { name: 'Handicaps' })
                .getAttribute('href'),
        ).toBe('/handicaps');
        expect(ruleLines()).toEqual(RULE_TEXTS);
    });
});

/** Manu on the reference card with 10 at hole 1 (par 4): 91 gross, 89 adjusted. */
const manuCapped = {
    rounds: [
        {
            ...gabReference,
            gross: 91,
            adjusted: 89,
            differentialT: 180,
        },
    ],
    cappedHole: { hole: 1, par: 4, strokes: 10 },
    meanT: 180,
    adjustmentT: 0,
};

function step(number: string): HTMLElement {
    return screen.getByTestId(`step-${number}`);
}

describe('Index computation page, step 01', () => {
    // Spec « Trou plafonné ». Mutations: MAX written as the strokes played, the
    // played strokes not struck, or « DERNIER ROUND » reading gross / adjusted
    // → red.
    it('shows « Trou 1 · P4 », « 10 » struck, « Max » « 8 » and « 89 / 91 » for Manu', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={MANU}
                computation={manuCapped}
            />,
        );

        const hole = within(step('01')).getByTestId('capped-hole');
        const played = within(step('01')).getByTestId('capped-strokes');

        expect(hole.textContent).toBe('Trou 1 · P4');
        expect(hole.className).toContain('uppercase');
        expect(played.textContent).toBe('10');
        expect(played.tagName).toBe('S');
        expect(played.className).toContain('text-kpi');
        expect(within(step('01')).getByText('Max')).toBeInTheDocument();
        expect(within(step('01')).getByTestId('capped-max').textContent).toBe(
            '8',
        );
        expect(
            within(step('01')).getByText('Dernier round'),
        ).toBeInTheDocument();
        expect(within(step('01')).getByTestId('latest-round').textContent).toBe(
            '89 / 91',
        );
        expect(
            within(step('01')).queryByText(
                'Aucun trou plafonné sur les 20 derniers rounds.',
            ),
        ).toBeNull();
    });

    // Spec « Aucun trou plafonné ». Mutation: the hole block shown with an
    // empty hole, or the sentence left out → red.
    it('shows « Aucun trou plafonné sur les 20 derniers rounds. » and « 72 / 72 » for Gab, without « Trou » nor « Max »', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={gabAlone}
            />,
        );

        expect(
            within(step('01')).getByText(
                'Aucun trou plafonné sur les 20 derniers rounds.',
            ),
        ).toBeInTheDocument();
        expect(within(step('01')).getByTestId('latest-round').textContent).toBe(
            '72 / 72',
        );
        expect(within(step('01')).queryByTestId('capped-hole')).toBeNull();
        expect(within(step('01')).queryByText('Max')).toBeNull();
    });
});

/** Manu on the reference card, BETHPAGE BLACK / Red confirmed at 71,4 / 144: 88 adjusted, 13,0. */
const manuRated = {
    rounds: [
        {
            ...gabReference,
            gross: 88,
            adjusted: 88,
            ratingT: 714,
            slope: 144,
            differentialT: 130,
        },
    ],
    cappedHole: null,
    meanT: 130,
    adjustmentT: 0,
};

function formulaValues(): string[] {
    return within(step('02'))
        .getAllByTestId('formula-value')
        .map((value) => value.textContent ?? '');
}

describe('Index computation page, step 02', () => {
    // Spec « Round sans rating » (and « Rating seulement proposée », which the
    // server sends with ratingT and slope null). Mutations: the slope shown
    // empty instead of 113, the rating as « 71,0 » or the course rating of
    // another round, the differential in ink → red.
    it('writes « 113 / 113 × ( 72 − 71 ) », « BETHPAGE BLACK · 17 OCT » and « 1,0 » in the colour of Gab', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={gabAlone}
            />,
        );

        const differential = within(step('02')).getByTestId(
            'latest-differential',
        );

        expect(formulaValues()).toEqual(['113', '113', '72', '71']);
        expect(
            within(step('02'))
                .getAllByTestId('formula-label')
                .map((label) => label.textContent),
        ).toEqual(['Slope', 'Ajusté', 'Rating']);
        expect(within(step('02')).getByTestId('formula').textContent).toBe(
            '113/113Slope× (72Ajusté−71Rating)',
        );
        expect(
            within(step('02')).getByTestId('latest-course').textContent,
        ).toBe('BETHPAGE BLACK · 17 OCT');
        expect(differential.textContent).toBe('1,0');
        expect(differential.className).toContain('text-player-1');
        expect(differential.className).toContain('text-num-36');
    });

    // Spec « Round avec rating ». Mutation: the par shown instead of the
    // confirmed rating, or 113 kept for the slope → red.
    it('writes « 113 / 144 × ( 88 − 71,4 ) » and « 13,0 » for Manu on a confirmed rating', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={MANU}
                computation={manuRated}
            />,
        );

        expect(formulaValues()).toEqual(['113', '144', '88', '71,4']);
        expect(
            within(step('02')).getByTestId('latest-differential').textContent,
        ).toBe('13,0');
        expect(
            within(step('02')).getByTestId('latest-differential').className,
        ).toContain('text-player-2');
    });
});

/**
 * Rounds of BETHPAGE BLACK without rating, one a day from 2026-10-01, given
 * oldest first as the scenarios write them and sent newest first as the
 * server does; `counted` lists the differentials (tenths) of the current index.
 */
function series(differentials: number[], counted: number[]) {
    return differentials
        .map((differentialT, position) => ({
            ...gabReference,
            roundId: `0190a6b2-7c3e-7000-8000-${String(position + 1).padStart(12, '0')}`,
            playedOn: `2026-10-${String(position + 1).padStart(2, '0')}`,
            differentialT,
            countsInCurrent: counted.includes(differentialT),
        }))
        .reverse();
}

/** Gab with 3, 5 or 6 rounds, Sean with 2, Gab at the cap (scenarios of steps 03 to 05). */
const gabThree = {
    rounds: series([170, 120, 200], [120]),
    cappedHole: null,
    meanT: 120,
    adjustmentT: -20,
};
const gabFive = {
    rounds: series([170, 120, 200, 150, 140], [120]),
    cappedHole: null,
    meanT: 120,
    adjustmentT: 0,
};
const gabSix = {
    rounds: series([170, 120, 200, 150, 140, 180], [120, 140]),
    cappedHole: null,
    meanT: 130,
    adjustmentT: -10,
};
const seanTwo = {
    rounds: series([170, 120], [170, 120]),
    cappedHole: null,
    meanT: 145,
    adjustmentT: 0,
};
/** Manu with 21 rounds: the server sends the 20 latest, the 8 best counting. */
const manuTwenty = {
    rounds: series(
        Array.from({ length: 20 }, (_, position) => 100 + position * 10),
        [100, 110, 120, 130, 140, 150, 160, 170],
    ),
    cappedHole: null,
    meanT: 135,
    adjustmentT: 0,
};

const gabEstablished = (indexT: number, rounds: number) => ({
    ...gab,
    indexT,
    provisional: false,
    rounds,
});
const seanProvisional = {
    ...sean,
    indexT: 145,
    provisional: true,
    rounds: 2,
};

function stairs(): HTMLElement[] {
    return within(step('03')).getAllByTestId('stair');
}

function currentStairs(): HTMLElement[] {
    return stairs().filter((stair) => stair.dataset.current === 'true');
}

function listRows(): HTMLElement[] {
    return within(
        within(step('03')).getByRole('list', { name: /^20 derniers rounds/ }),
    ).getAllByRole('listitem');
}

describe('Index computation page, step 03', () => {
    // Design D2: the 20 steps are a constant of the page, written from the
    // rule line. Mutation: 6 rounds → 1 retained (or 19 → 8) → red.
    it('retains, for 1 to 20 rounds, the numbers of the rule lines', () => {
        expect(RETAINED_BY_ROUNDS).toEqual([
            1, 2, 1, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 4, 5, 5, 6, 6, 7, 8,
        ]);

        // « 1 de 3 à 5 rounds, 2 de 6 à 8, … 5 à 15 ou 16, … 8 à 20. »
        const fromRule = new Map<number, number>();

        for (const [, retained, from, to, at, or] of RULE_LINES[2].matchAll(
            /(\d+) (?:de (\d+) à (\d+)|à (\d+)(?: ou (\d+))?)/g,
        )) {
            const first = Number(from ?? at);
            const last = Number(to ?? or ?? at);

            for (let rounds = first; rounds <= last; rounds++) {
                fromRule.set(rounds, Number(retained));
            }
        }

        expect(fromRule.size).toBe(18);
        expect(
            RETAINED_BY_ROUNDS.slice(2).map((retained, position) => [
                position + 3,
                retained,
            ]),
        ).toEqual([...fromRule.entries()]);
    });

    // Spec « Trois rounds ». Mutation: the step of the player chosen by
    // index (rounds − 1 → rounds) or every step coloured → red.
    it('colours step 3 for Gab with 3 rounds, labelled « 1 », and shows « 3 → 1 retenu »', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(100, 3), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabThree}
            />,
        );

        const [current] = currentStairs();

        expect(stairs()).toHaveLength(20);
        expect(currentStairs()).toHaveLength(1);
        expect(current.dataset.rounds).toBe('3');
        expect(
            current.querySelector('[data-part="retained"]')?.textContent,
        ).toBe('1');
        expect(current.querySelector('[data-part="bar"]')?.className).toContain(
            'bg-player-1',
        );
        expect(
            stairs()
                .filter((stair) => stair.dataset.current !== 'true')
                .map(
                    (stair) =>
                        stair.querySelector('[data-part="bar"]')?.className,
                ),
        ).not.toContainEqual(expect.stringContaining('bg-player'));
        expect(
            within(step('03')).getByTestId('retained-summary').textContent,
        ).toBe('3 → 1 retenu');
    });

    // Steps are proportional to the number retained, 8 at 82 % (design).
    // Mutation: heights over 20 instead of 8 → red.
    it('draws step 20 at 82 % and step 3 at 10,25 % of the height', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(100, 3), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabThree}
            />,
        );

        const height = (rounds: number) =>
            (
                stairs()[rounds - 1].querySelector(
                    '[data-part="bar"]',
                ) as HTMLElement
            ).style.height;

        expect([height(3), height(6), height(20)]).toEqual([
            '10.25%',
            '20.5%',
            '82%',
        ]);
    });

    it('labels step 6 « 2 » and shows « 6 → 2 retenus » for Gab with 6 rounds', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(120, 6), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabSix}
            />,
        );

        const [current] = currentStairs();

        expect(current.dataset.rounds).toBe('6');
        expect(
            current.querySelector('[data-part="retained"]')?.textContent,
        ).toBe('2');
        expect(
            within(step('03')).getByTestId('retained-summary').textContent,
        ).toBe('6 → 2 retenus');
    });

    // Spec « Index provisoire ». Mutation: 2 rounds read as 1 retained → red.
    it('colours step 2 in the colour of Sean, labelled « 2 », and shows « 2 → 2 retenus »', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, seanProvisional]}
                selectedPlayerId={SEAN}
                computation={seanTwo}
            />,
        );

        const [current] = currentStairs();

        expect(current.dataset.rounds).toBe('2');
        expect(current.querySelector('[data-part="bar"]')?.className).toContain(
            'bg-player-3',
        );
        expect(
            current.querySelector('[data-part="retained"]')?.textContent,
        ).toBe('2');
        expect(
            within(step('03')).getByTestId('retained-summary').textContent,
        ).toBe('2 → 2 retenus');
    });

    // Spec « Plus de 20 rounds »: the server sends the 20 latest.
    it('lists 20 rounds and shows « 20 → 8 retenus » for Manu with 21 rounds', () => {
        render(
            <HandicapsComputation
                players={[gab, { ...manu, indexT: 135, rounds: 21 }, sean]}
                selectedPlayerId={MANU}
                computation={manuTwenty}
            />,
        );

        expect(currentStairs()[0].dataset.rounds).toBe('20');
        expect(
            currentStairs()[0].querySelector('[data-part="retained"]')
                ?.textContent,
        ).toBe('8');
        expect(
            within(step('03')).getByTestId('retained-summary').textContent,
        ).toBe('20 → 8 retenus');
        expect(within(step('03')).getByTestId('rounds-label').textContent).toBe(
            '20 derniers rounds · 20',
        );
        expect(listRows()).toHaveLength(20);
    });

    // Spec « Liste des 20 derniers rounds », « Six rounds ». Mutations: the
    // list oldest first, « Retenu » on every row, the rows not retained in
    // ink → red.
    it('lists the 6 rounds of Gab newest first, « Retenu » on 12,0 and 14,0 only', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(120, 6), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabSix}
            />,
        );

        expect(within(step('03')).getByTestId('rounds-label').textContent).toBe(
            '20 derniers rounds · 6',
        );
        expect(
            listRows().map((row) => [
                within(row).getByTestId('row-differential').textContent,
                within(row).queryByRole('img', { name: 'Retenu' }) !== null,
            ]),
        ).toEqual([
            ['18,0', false],
            ['14,0', true],
            ['15,0', false],
            ['20,0', false],
            ['12,0', true],
            ['17,0', false],
        ]);
        expect(listRows()[0].className).toContain('text-ink-2');
        expect(listRows()[1].className).toContain('text-ink');
        expect(listRows()[1].className).not.toContain('text-ink-2');
        expect(
            within(listRows()[1]).getByRole('img', { name: 'Retenu' })
                .className,
        ).toContain('bg-player-1');
        expect(
            within(listRows()[1]).getByTestId('row-differential').className,
        ).toContain('text-num-20');
    });

    // Spec « Ligne d'un round sans rating ». Mutation: « par » left out, or the
    // slope empty → red.
    it('shows « 17 », « OCT », « BETHPAGE BLACK », « 72 ajusté · par 71 / 113 » and « 1,0 » for a round without rating', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={gabAlone}
            />,
        );

        const [row] = listRows();

        expect(
            [
                'row-day',
                'row-month',
                'row-course',
                'row-detail',
                'row-differential',
            ].map((part) => within(row).getByTestId(part).textContent),
        ).toEqual([
            '17',
            'OCT',
            'BETHPAGE BLACK',
            '72 ajusté · par 71 / 113',
            '1,0',
        ]);
        expect(within(row).getByTestId('row-course').className).toContain(
            'uppercase',
        );
    });

    // Spec « Ligne d'un round avec rating ».
    it('shows « 72 ajusté · 71,4 / 144 » and « 0,5 » for a round on a confirmed rating', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={{
                    ...gabAlone,
                    rounds: [
                        {
                            ...gabReference,
                            ratingT: 714,
                            slope: 144,
                            differentialT: 5,
                        },
                    ],
                }}
            />,
        );

        const [row] = listRows();

        expect(within(row).getByTestId('row-detail').textContent).toBe(
            '72 ajusté · 71,4 / 144',
        );
        expect(within(row).getByTestId('row-differential').textContent).toBe(
            '0,5',
        );
    });
});

function adjustmentCells(): HTMLElement[] {
    return within(step('04')).getAllByRole('listitem');
}

function appliedCells(): string[] {
    return adjustmentCells()
        .filter((cell) => cell.getAttribute('aria-current') === 'true')
        .map(
            (cell) =>
                within(cell).getByTestId('adjustment-label').textContent ?? '',
        );
}

describe('Index computation page, step 04', () => {
    // Spec « Étape 04, ajustement », « Trois rounds ». Mutations: the cell
    // chosen by index (3 rounds → « 4 rounds »), the text « appliqué » left
    // out, or the border of the player on every cell → red.
    it('shows the four cells of the rule and applies « 3 rounds » « −2,0 » alone for Gab with 3 rounds', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(100, 3), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabThree}
            />,
        );

        expect(
            adjustmentCells().map((cell) => [
                within(cell).getByTestId('adjustment-label').textContent,
                within(cell).getByTestId('adjustment-value').textContent,
            ]),
        ).toEqual([
            ['3 rounds', '−2,0'],
            ['4 rounds', '−1,0'],
            ['6 rounds', '−1,0'],
            ['Autres', '0'],
        ]);
        expect(appliedCells()).toEqual(['3 rounds']);

        const [applied, other] = adjustmentCells();

        expect(applied.className).toContain('border-player-1');
        expect(
            within(applied).getByTestId('adjustment-label').className,
        ).toContain('text-player-1');
        expect(within(applied).getByText('appliqué').className).toContain(
            'sr-only',
        );
        expect(other.className).toContain('border-rule');
        expect(within(other).queryByText('appliqué')).toBeNull();
        expect(
            within(applied).getByTestId('adjustment-value').className,
        ).toContain('text-num-20');
    });

    it('applies « 6 rounds » alone for Gab with 6 rounds', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(120, 6), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabSix}
            />,
        );

        expect(appliedCells()).toEqual(['6 rounds']);
    });

    // Spec « Cinq rounds ». Mutation: no cell applied without adjustment → red.
    it('applies « Autres » alone for Gab with 5 rounds', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(120, 5), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabFive}
            />,
        );

        expect(appliedCells()).toEqual(['Autres']);
    });

    // A provisional index takes no adjustment: « Autres ».
    it('applies « Autres » alone for Sean with 2 rounds', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, seanProvisional]}
                selectedPlayerId={SEAN}
                computation={seanTwo}
            />,
        );

        expect(appliedCells()).toEqual(['Autres']);
    });
});

/** Gab at the cap: 63,0, 70,0 and 65,0, the index 54,0. */
const gabCapped = {
    rounds: series([630, 700, 650], [630]),
    cappedHole: null,
    meanT: 630,
    adjustmentT: -20,
};

function indexStep(): string[] {
    return [
        'mean-label',
        'mean-value',
        'adjustment-label',
        'adjustment-applied',
        'index-label',
        'index-value',
    ].map((part) => within(step('05')).getByTestId(part).textContent ?? '');
}

describe('Index computation page, step 05', () => {
    // Spec « Six rounds ». Mutations: the retained differentials listed in the
    // order of the rounds (14,0 · 12,0), the mean recomputed in the page, or
    // the index of the player replaced by mean + adjustment → red.
    it('shows « Moyenne des 2 meilleurs (12,0 · 14,0) » « 13,0 », « −1,0 », then « Index Gab » « 12,0 » for 6 rounds', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(120, 6), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabSix}
            />,
        );

        expect(indexStep()).toEqual([
            'Moyenne des 2 meilleurs (12,0 · 14,0)',
            '13,0',
            'Ajustement',
            '−1,0',
            'Index Gab',
            '12,0',
        ]);

        const index = within(step('05')).getByTestId('index-value');

        expect(index.className).toContain('text-display-xl');
        expect(index.className).toContain('font-mono');
        expect(
            within(step('05')).getByTestId('index-label').className,
        ).toContain('uppercase');
    });

    it('shows « Meilleur différentiel » « 12,0 », « −2,0 », then « 10,0 » for 3 rounds', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(100, 3), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabThree}
            />,
        );

        expect(indexStep()).toEqual([
            'Meilleur différentiel',
            '12,0',
            'Ajustement',
            '−2,0',
            'Index Gab',
            '10,0',
        ]);
    });

    // Spec « Sans ajustement ». Mutation: « 0,0 » instead of « aucun » → red.
    it('shows « Meilleur différentiel » « 12,0 », « aucun », then « 12,0 » for 5 rounds', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(120, 5), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabFive}
            />,
        );

        expect(indexStep()).toEqual([
            'Meilleur différentiel',
            '12,0',
            'Ajustement',
            'aucun',
            'Index Gab',
            '12,0',
        ]);
    });

    // Spec « Index provisoire ». Mutation: « meilleurs » for a provisional
    // index → red.
    it('shows « Moyenne des 2 différentiels (12,0 · 17,0) » « 14,5 », « aucun », then « 14,5 » for Sean', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, seanProvisional]}
                selectedPlayerId={SEAN}
                computation={seanTwo}
            />,
        );

        expect(indexStep()).toEqual([
            'Moyenne des 2 différentiels (12,0 · 17,0)',
            '14,5',
            'Ajustement',
            'aucun',
            'Index Sean',
            '14,5',
        ]);
    });

    // Spec « Plafond »: the index of PlayerStandings, capped by the server.
    it('shows « 63,0 », « −2,0 », then « 54,0 » at the cap', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(540, 3), manu, sean]}
                selectedPlayerId={GAB}
                computation={gabCapped}
            />,
        );

        expect(indexStep()).toEqual([
            'Meilleur différentiel',
            '63,0',
            'Ajustement',
            '−2,0',
            'Index Gab',
            '54,0',
        ]);
    });
});

const seanWithout = {
    rounds: [],
    cappedHole: null,
    meanT: null,
    adjustmentT: 0,
};

describe('Index computation page, special cases', () => {
    // Spec « Exemple sans rating ». Mutations: the example taken from the
    // course rating, or « PROVISOIRE » in the neutral variant → red.
    it('shows « PROVISOIRE » in amber, « SANS RATING » neutral and « Ex. BETHPAGE BLACK → rating 71 · slope 113 »', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={gabAlone}
            />,
        );

        const cases = screen.getByTestId('special-cases');

        expect(within(cases).getByText('PROVISOIRE').className).toContain(
            'text-accent',
        );
        expect(within(cases).getByText('SANS RATING').className).toContain(
            'bg-surface-3',
        );
        expect(within(cases).getByText('SANS RATING').className).toContain(
            'text-ink-2',
        );
        expect(within(cases).getByTestId('special-example').textContent).toBe(
            'Ex. BETHPAGE BLACK → rating 71 · slope 113',
        );
        expect(
            within(cases)
                .getAllByTestId('rule-line')
                .map((line) => line.className),
        ).toEqual([
            expect.stringContaining('text-ink-soft'),
            expect.stringContaining('text-ink-soft'),
        ]);
        expect(
            within(cases).getAllByTestId('rule-line')[0].className,
        ).toContain('text-body');
    });

    // Spec « Tous les parcours notés ». Mutation: the example shown for a
    // rated round (« rating 71,4 · slope 144 ») → red.
    it('shows « SANS RATING » and its line without « Ex. » when every round has a confirmed rating', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={GAB}
                computation={{ ...gabAlone, rounds: manuRated.rounds }}
            />,
        );

        const cases = screen.getByTestId('special-cases');

        expect(within(cases).getByText('SANS RATING')).toBeInTheDocument();
        expect(
            within(cases).getByText(
                'Parcours sans rating : la course rating vaut le par du round et le slope 113.',
            ),
        ).toBeInTheDocument();
        expect(within(cases).queryByTestId('special-example')).toBeNull();
        expect(within(cases).queryByText(/^Ex\./)).toBeNull();
    });

    // The example comes from the latest round without rating. Mutation: the
    // oldest one (`findLast`) → red.
    it('takes the example from the latest round without confirmed rating', () => {
        render(
            <HandicapsComputation
                players={[gabEstablished(100, 3), manu, sean]}
                selectedPlayerId={GAB}
                computation={{
                    ...gabThree,
                    rounds: [
                        { ...gabThree.rounds[0], ratingT: 714, slope: 144 },
                        {
                            ...gabThree.rounds[1],
                            courseName: 'PAYNES VALLEY',
                            parTotal: 72,
                        },
                        gabThree.rounds[2],
                    ],
                }}
            />,
        );

        expect(screen.getByTestId('special-example').textContent).toBe(
            'Ex. PAYNES VALLEY → rating 72 · slope 113',
        );
    });
});

describe('Index computation page, player without round', () => {
    // Spec « Sean sans round ». Mutations: the list, the summary or the latest
    // round shown empty, a step or a cell in colour, or the steps left out →
    // red.
    it('shows the seven lines and « Pas encore d’index. », nothing in colour, nor « Dernier round », « 20 derniers rounds » or « Index Sean »', () => {
        render(
            <HandicapsComputation
                players={[gab, manu, sean]}
                selectedPlayerId={SEAN}
                computation={seanWithout}
            />,
        );

        expect(ruleLines()).toEqual(RULE_TEXTS);
        expect(
            screen.getByText(
                "Pas encore d'index. Un premier round donne un index provisoire.",
            ),
        ).toBeInTheDocument();
        expect(stairs()).toHaveLength(20);
        expect(currentStairs()).toEqual([]);
        expect(adjustmentCells()).toHaveLength(4);
        expect(appliedCells()).toEqual([]);
        expect(
            [...document.querySelectorAll('[class*="bg-player"]')].filter(
                (element) => element.closest('[data-testid^="step-"]') !== null,
            ),
        ).toEqual([]);
        expect(screen.queryByText('Dernier round')).toBeNull();
        expect(screen.queryByTestId('rounds-label')).toBeNull();
        expect(screen.queryByTestId('retained-summary')).toBeNull();
        expect(screen.queryByTestId('formula')).toBeNull();
        expect(screen.queryByTestId('index-label')).toBeNull();
        expect(screen.queryByText(/Index Sean/)).toBeNull();
        expect(screen.queryByTestId('special-example')).toBeNull();
        expect(screen.queryByTestId('mean-label')).toBeNull();
    });
});

describe('Index computation page, tab bar', () => {
    // Spec « Page « Calcul de l'index » »: the Handicaps tab stays current
    // (design D1, NavLink marks the sub-pages of /handicaps). Mutation:
    // inSection matching the exact path only (`path === prefix`) → red.
    it('marks Handicaps as the current section on /handicaps/calcul', () => {
        url = `/handicaps/calcul?joueur=${MANU}`;
        render(<AppNav />);

        expect(
            within(screen.getByRole('navigation', { name: 'Sections' }))
                .getAllByRole('link')
                .filter((link) => link.getAttribute('aria-current') === 'page')
                .map((link) => link.getAttribute('aria-label')),
        ).toEqual(['Handicaps']);
    });
});
