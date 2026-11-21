import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { RoundScorecard } from '@/components/rounds/round-scorecard';
import {
    GAB,
    GAB_STROKES,
    HOLES,
    MANU,
    MANU_STROKES,
} from '@/tests/reference-round';

// Spec virtual-match « Carte combinée »: each column carries the first five
// letters of the name in capitals and the short date of the round. The page
// of a round sends no date and keeps its header (covered by rounds-show).
// Mutation: the date dropped from the header, or written with the year
// (« 19 nov. 2026 ») → this test goes red.

describe('RoundScorecard', () => {
    it('shows « 19 nov. » under « MANU » when the line has a date', () => {
        render(
            <RoundScorecard
                card={{
                    holes: HOLES,
                    lines: [
                        {
                            playerId: GAB,
                            name: 'Gab',
                            colorSlot: 0,
                            strokes: GAB_STROKES,
                            playedOn: '2026-11-18',
                        },
                        {
                            playerId: MANU,
                            name: 'Manu',
                            colorSlot: 1,
                            strokes: MANU_STROKES,
                            playedOn: '2026-11-19',
                        },
                    ],
                }}
            />,
        );

        const headers = within(
            screen.getByRole('table', { name: 'Carte de score' }),
        )
            .getAllByRole('columnheader')
            .slice(2)
            .map((header) =>
                Array.from(header.children).map((part) => part.textContent),
            );

        expect(headers).toEqual([
            ['GAB', '18 nov.'],
            ['MANU', '19 nov.'],
        ]);
    });
});
