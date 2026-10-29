import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { DifferentialBars } from '@/components/handicaps/differential-bars';

// V1, DO46: spec handicap-index « Étiquettes du graphique des
// différentiels ». Twenty bars leave 11,8 px a bar at 390 px (8,3 px at
// 320 px): a date « 17/10 » (27 px) and a value « −1,1 » (24 px) on each bar
// overlap. Up to 10 bars every bar is labelled; from 11 to 16, every other
// bar; from 17, one bar in three; always counted from the newest, and the
// others keep their value and date for screen readers (`sr-only`). Above 10
// bars the newest is aligned on the right edge of the chart, the oldest on
// its left edge, so that neither is cut.
// Mutations: every bar labelled whatever their number → the 20 bars test
// goes red; the thinning counted from the oldest → the newest « 20/10 » is
// hidden, red; every other bar up to 20 (no step of 3) → the 20 bars test
// goes red; the threshold at 20 instead of 10 → the 11 bars test goes red;
// the newest centred (`items-center`) → the edge test goes red.

/** `count` rounds a day apart in October 2026, newest first, as the page sends them. */
function history(count: number) {
    return Array.from({ length: count }, (_, position) => ({
        roundId: `0190a6b2-7c3e-7000-8000-${String(position).padStart(12, '0')}`,
        playedOn: `2026-10-${String(count - position).padStart(2, '0')}`,
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        differentialT: -11,
        indexAfterT: 0,
        countsInCurrent: false,
    }));
}

function bars(): HTMLElement[] {
    return within(
        screen.getByRole('list', { name: 'Différentiels' }),
    ).getAllByRole('listitem');
}

/** The dates written in sight, oldest first. */
function shownDates(): string[] {
    return bars()
        .map((bar) => bar.querySelector('[data-part="date"]')!)
        .filter((date) => !date.classList.contains('sr-only'))
        .map((date) => date.textContent ?? '');
}

describe('DifferentialBars labels', () => {
    it('writes every value and date with 10 bars', () => {
        render(<DifferentialBars history={history(10)} colorSlot={0} />);

        expect(shownDates()).toEqual([
            '1/10',
            '2/10',
            '3/10',
            '4/10',
            '5/10',
            '6/10',
            '7/10',
            '8/10',
            '9/10',
            '10/10',
        ]);
    });

    it('writes the value and the date of every other bar with 11 bars, the newest included', () => {
        render(<DifferentialBars history={history(11)} colorSlot={0} />);

        expect(shownDates()).toEqual([
            '1/10',
            '3/10',
            '5/10',
            '7/10',
            '9/10',
            '11/10',
        ]);
    });

    it('writes the value and the date of every other bar with 16 bars', () => {
        render(<DifferentialBars history={history(16)} colorSlot={0} />);

        expect(shownDates()).toEqual([
            '2/10',
            '4/10',
            '6/10',
            '8/10',
            '10/10',
            '12/10',
            '14/10',
            '16/10',
        ]);
    });

    it('writes 7 of 20 dates and values, one bar in three from the newest, all 20 kept for screen readers', () => {
        render(<DifferentialBars history={history(20)} colorSlot={0} />);

        expect(shownDates()).toEqual([
            '2/10',
            '5/10',
            '8/10',
            '11/10',
            '14/10',
            '17/10',
            '20/10',
        ]);
        const values = bars().map((bar) =>
            bar.querySelector('[data-part="value"]')!,
        );
        expect(values.map((value) => value.textContent)).toEqual(
            Array.from({ length: 20 }, () => '\u22121,1'),
        );
        expect(
            values.filter((value) => !value.classList.contains('sr-only')),
        ).toHaveLength(7);
    });

    it('keeps every bar centred with 10 bars', () => {
        render(<DifferentialBars history={history(10)} colorSlot={0} />);

        bars().forEach((bar) => expect(bar).toHaveClass('items-center'));
    });

    it('aligns the oldest bar on the left edge and the newest on the right edge with 20 bars', () => {
        render(<DifferentialBars history={history(20)} colorSlot={0} />);

        const all = bars();

        expect(all[0]).toHaveClass('items-start');
        expect(all[19]).toHaveClass('items-end');
        expect(all[10]).toHaveClass('items-center');
    });
});
