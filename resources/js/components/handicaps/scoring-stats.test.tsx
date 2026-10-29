import { act, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import {
    ScoringStats,
    type ScoringStatsData,
} from '@/components/handicaps/scoring-stats';

// Spec handicap-index (visual-redesign) « Statistiques de jeu d'un joueur »,
// « Répartition des trous », « Moyennes par par », « Mise en page du bloc
// STATS »; design D13. The props are the literal values of
// PlayerScoringStatsTest (task 8.5), as the server sends them.
// Mutations: the sign « + » on a negative delta (`deltaT <= 0 ? '+'`) → the
// par 5 box reads « +0,7 » and the boxes test goes red; the « Eagle ou mieux »
// row rendered at 0 (filter removed) → the one-round rows test goes red; an
// ASCII « - » instead of « − » (U+2212) → the boxes test goes red; the tints
// mapped to the wrong tokens (`neutral` → `text-signal`) → the tints test goes
// red.

/** Gab on the reference card IMG_3064 alone. */
const gabOneRound: ScoringStatsData = {
    rounds: 1,
    holes: 18,
    averageGrossT: 720,
    bestGross: 72,
    parOrBetterPercent: 78,
    classes: [0, 3, 11, 4, 0],
    classPercents: [0, 17, 61, 22, 0],
    pars: [
        { par: 3, holes: 4, averageT: 30, deltaT: 0, tint: 'good' },
        { par: 4, holes: 11, averageT: 43, deltaT: 3, tint: 'good' },
        { par: 5, holes: 3, averageT: 43, deltaT: -7, tint: 'good' },
    ],
};

/** Gab on the reference card corrected with 1 on hole 3. */
const gabHoleInOne: ScoringStatsData = {
    ...gabOneRound,
    averageGrossT: 700,
    bestGross: 70,
    classes: [1, 3, 10, 4, 0],
    classPercents: [6, 17, 56, 22, 0],
};

/** Gab on the reference card and a second round with Manu's strokes. */
const gabTwoRounds: ScoringStatsData = {
    rounds: 2,
    holes: 36,
    averageGrossT: 800,
    bestGross: 72,
    parOrBetterPercent: 56,
    classes: [0, 4, 16, 11, 5],
    classPercents: [0, 11, 44, 31, 14],
    pars: [
        { par: 3, holes: 8, averageT: 31, deltaT: 1, tint: 'good' },
        { par: 4, holes: 22, averageT: 47, deltaT: 7, tint: 'neutral' },
        { par: 5, holes: 6, averageT: 53, deltaT: 3, tint: 'neutral' },
    ],
};

/** Manu on the reference card alone (averages by par only). */
const manuPars: ScoringStatsData['pars'] = [
    { par: 3, holes: 4, averageT: 33, deltaT: 3, tint: 'good' },
    { par: 4, holes: 11, averageT: 51, deltaT: 11, tint: 'bad' },
    { par: 5, holes: 3, averageT: 63, deltaT: 13, tint: 'bad' },
];

/** The row of a class of the distribution, by its name. */
function row(name: string): HTMLElement {
    return screen.getByText(name).closest('li') as HTMLElement;
}

/** The box of a par, by its label « Par 3 ». */
function parBox(label: string): HTMLElement {
    return screen.getByText(label).closest('li') as HTMLElement;
}

/** The value written above a label of the three values. */
function valueOf(label: string): string | null {
    return screen.getByText(label).nextElementSibling?.textContent ?? null;
}

describe('ScoringStats', () => {
    it('heads the block with « STATS » and the number of holes played', () => {
        render(<ScoringStats stats={gabOneRound} />);

        const heading = screen.getByRole('heading', { name: 'Stats' });

        expect(heading.parentElement).toHaveClass('uppercase');
        expect(screen.getByText('18 trous')).toBeTruthy();
    });

    it('writes the three values under their labels', () => {
        render(<ScoringStats stats={gabOneRound} />);

        expect(valueOf('Moy. brut')).toBe('72,0');
        expect(valueOf('Meilleur brut')).toBe('72');
        expect(valueOf('Par ou mieux')).toBe('78%');
        expect(screen.getByText('Moy. brut')).toHaveClass('uppercase');
    });

    it('lists the classes of one round without « Eagle ou mieux »', () => {
        render(<ScoringStats stats={gabOneRound} />);

        const rows = screen
            .getAllByRole('listitem')
            .filter((item) => item.dataset.part === 'class')
            .map((item) => [
                item.querySelector('[data-part="name"]')?.textContent,
                item.querySelector('[data-part="percent"]')?.textContent,
                item.querySelector('[data-part="count"]')?.textContent,
            ]);

        expect(rows).toEqual([
            ['Birdies', '17%', '3'],
            ['Pars', '61%', '11'],
            ['Bogeys', '22%', '4'],
            ['Double bogey +', '0%', '0'],
        ]);
        expect(screen.queryByText('Eagle ou mieux')).toBeNull();
    });

    it('shows « Eagle ou mieux » when it counts a hole', () => {
        render(<ScoringStats stats={gabHoleInOne} />);

        expect(within(row('Eagle ou mieux')).getByText('1')).toBeTruthy();
        expect(within(row('Eagle ou mieux')).getByText('6%')).toBeTruthy();
        expect(valueOf('Moy. brut')).toBe('70,0');
    });

    // The segments grow from a null width over two frames (D8,
    // motion.test.tsx): their widths are read once grown.
    it('draws the segments of the bar at the share of each class', async () => {
        render(<ScoringStats stats={gabOneRound} />);

        const widths = () =>
            Array.from(
                document.querySelectorAll<HTMLElement>('[data-part="segment"]'),
            ).map((segment) => segment.style.width);

        await waitFor(() =>
            expect(widths()).toEqual(['16.7%', '61.1%', '22.2%', '0%']),
        );
    });

    // M5, DO37: the stats come as a deferred prop, after the page. The bar
    // must still grow from a null width when they arrive. Mutation:
    // `useEntered` called in ScoringStats itself, before `stats === null`
    // returns (its frames run out while the prop is missing) → the segments
    // arrive at their final widths, this test goes red.
    it('grows the segments from 0 when the stats arrive after the block', async () => {
        const { rerender } = render(<ScoringStats stats={null} />);

        await act(
            () =>
                new Promise<void>((resolve) =>
                    requestAnimationFrame(() =>
                        requestAnimationFrame(() => resolve()),
                    ),
                ),
        );
        rerender(<ScoringStats stats={gabOneRound} />);

        const widths = () =>
            Array.from(
                document.querySelectorAll<HTMLElement>('[data-part="segment"]'),
            ).map((segment) => segment.style.width);

        expect(widths()).toEqual(['0%', '0%', '0%', '0%']);
        await waitFor(() =>
            expect(widths()).toEqual(['16.7%', '61.1%', '22.2%', '0%']),
        );
    });

    it('writes each par with its average and its signed delta', () => {
        render(<ScoringStats stats={gabOneRound} />);

        expect(within(parBox('Par 3')).getByText('3,0')).toBeTruthy();
        expect(within(parBox('Par 3')).getByText('+0,0')).toBeTruthy();
        expect(within(parBox('Par 4')).getByText('+0,3')).toBeTruthy();
        expect(within(parBox('Par 5')).getByText('4,3')).toBeTruthy();
        expect(within(parBox('Par 5')).getByText('−0,7')).toBeTruthy();
        expect(screen.getByText('Par 5')).toHaveClass('uppercase');
    });

    it('tints a delta good, neutral or bad with the ok, ink-2 and signal tokens', () => {
        const { unmount } = render(<ScoringStats stats={gabTwoRounds} />);

        expect(within(parBox('Par 3')).getByText('+0,1')).toHaveClass(
            'text-ok',
        );
        expect(within(parBox('Par 4')).getByText('+0,7')).toHaveClass(
            'text-ink-2',
        );
        unmount();

        render(<ScoringStats stats={{ ...gabOneRound, pars: manuPars }} />);

        expect(within(parBox('Par 5')).getByText('+1,3')).toHaveClass(
            'text-signal',
        );
    });

    it('has no box for a par never played', () => {
        render(
            <ScoringStats
                stats={{ ...gabOneRound, pars: gabOneRound.pars.slice(0, 2) }}
            />,
        );

        expect(screen.getByText('Par 4')).toBeTruthy();
        expect(screen.queryByText('Par 5')).toBeNull();
    });

    it('renders nothing without round', () => {
        const { container } = render(<ScoringStats stats={null} />);

        expect(container.innerHTML).toBe('');
    });
});
