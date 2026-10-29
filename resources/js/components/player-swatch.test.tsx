import { render } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { PlayerSwatch } from '@/components/player-swatch';

// Spec visual-theme « Couleur de joueur »; design D5, D6: the colorSlot of a
// player (0 to 5, rank of creation) picks --color-player-{slot + 1}; the
// swatch is decoration, hidden from screen readers, the name always beside it.
// Mutation: slot shifted by one (`player-${slot + 2}`) → the slot tests go red.

function swatch(element: React.ReactElement): HTMLElement {
    const { container } = render(element);

    return container.firstElementChild as HTMLElement;
}

describe('PlayerSwatch', () => {
    it.each([
        [0, 'bg-player-1'],
        [1, 'bg-player-2'],
        [2, 'bg-player-3'],
        [3, 'bg-player-4'],
        [4, 'bg-player-5'],
        [5, 'bg-player-6'],
    ])('paints slot %i with %s', (slot, token) => {
        expect(swatch(<PlayerSwatch colorSlot={slot} />)).toHaveClass(token);
    });

    it.each([
        [6, 'size-1.5', 'rounded-2'],
        [8, 'size-2', 'rounded-2'],
        [10, 'size-2.5', 'rounded-3'],
    ] as const)('measures %i px with its radius', (size, sizeClass, radius) => {
        expect(swatch(<PlayerSwatch colorSlot={0} size={size} />)).toHaveClass(
            sizeClass,
            radius,
        );
    });

    it('is hidden from screen readers', () => {
        expect(swatch(<PlayerSwatch colorSlot={0} />)).toHaveAttribute(
            'aria-hidden',
            'true',
        );
    });
});
