import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';

import { DistancesBlock } from '@/components/round-editor/distances-block';

// L11, DO42: the « Distances » block, folded under the grid (decision Q6),
// opens by itself when one of its distances is a problem, so the amber cell
// named by the save bar is in sight (an import read with a distance of 1200).
// Mutations: the block always folded (`open` dropped) → the first and third
// tests go red; open whenever any cell is invalid (not only `distance:*`) →
// the second test goes red.

const distances = Array.from({ length: 18 }, (_, hole) => 300 + hole);

function block(): HTMLDetailsElement {
    return screen
        .getByText('Distances')
        .closest('details') as HTMLDetailsElement;
}

describe('DistancesBlock', () => {
    it('opens by itself when a distance is invalid', () => {
        render(
            <DistancesBlock
                distances={distances}
                invalid={new Set(['distance:3'])}
                onCell={() => {}}
            />,
        );

        expect(block().open).toBe(true);
    });

    it('stays folded when only a cell of the grid is invalid', () => {
        render(
            <DistancesBlock
                distances={distances}
                invalid={new Set(['player0:3', 'par:3'])}
                onCell={() => {}}
            />,
        );

        expect(block().open).toBe(false);
    });

    it('opens when a distance becomes invalid, and folds again on a touch', async () => {
        const { rerender } = render(
            <DistancesBlock
                distances={distances}
                invalid={new Set()}
                onCell={() => {}}
            />,
        );
        expect(block().open).toBe(false);

        rerender(
            <DistancesBlock
                distances={distances}
                invalid={new Set(['distance:7'])}
                onCell={() => {}}
            />,
        );
        expect(block().open).toBe(true);

        await userEvent.click(screen.getByText('Distances'));
        expect(block().open).toBe(false);
    });
});
