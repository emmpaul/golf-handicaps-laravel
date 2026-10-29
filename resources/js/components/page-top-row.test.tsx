import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';

import { PageTopRow } from '@/components/page-top-row';

// Spec app-shell « Rangée du haut des pages » (change reduce-mobile-padding).
// jsdom computes neither env() nor CSS variables: the test checks the class
// the row sets. Only usePage is simulated.
// Mutations: the 10 px added back under the safe area
// (calc(var(--safe-top)+10px)) → the « Golf » test goes red; the back-link
// row given the 54 px minimum → the back-link test goes red.

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url: '/handicaps', props: {} }),
}));

function row(container: HTMLElement) {
    return container.querySelector('[data-slot="page-top-row"]')!;
}

describe('PageTopRow', () => {
    it('starts the « Golf » row at 54 px or at the bottom of the safe area, with no margin added', () => {
        const { container } = render(<PageTopRow />);
        const classes = [...row(container).classList];

        expect(classes).toContain('pt-[max(54px,var(--safe-top))]');
        expect(classes.filter((name) => name.includes('+10px'))).toEqual([]);
    });

    it('starts the back-link row at 44 px or at the bottom of the safe area', () => {
        const { container } = render(
            <PageTopRow back={{ href: '/rounds', label: 'Rounds' }} />,
        );

        expect([...row(container).classList]).toContain(
            'pt-[max(44px,var(--safe-top))]',
        );
    });
});
