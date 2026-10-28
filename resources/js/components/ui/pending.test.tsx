import { render, screen } from '@testing-library/react';
import type { ThinkingOrbProps } from 'thinking-orbs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';

import { Pending } from '@/components/ui/pending';

// Spec loading-feedback « Indicateur d'attente », « Indicateur dans les deux
// thèmes », « Mouvement réduit ». The orb is simulated to read its props: it
// paints on a canvas jsdom cannot draw.
// Mutations: `theme="light"` in PendingOrb → the theme test goes red;
// `paused` not passed to ThinkingOrb → the reduced-motion test goes red.

const orbProps = vi.hoisted(() => ({ last: null as ThinkingOrbProps | null }));

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: (props: ThinkingOrbProps) => {
        orbProps.last = props;
        const {
            state: _state,
            size: _size,
            theme: _theme,
            paused: _paused,
            ...canvas
        } = props;

        return <canvas data-testid="orb" {...canvas} />;
    },
}));

function emulateReducedMotion(reduce: boolean) {
    vi.stubGlobal(
        'matchMedia',
        vi.fn((query: string) => ({
            matches: reduce && query === '(prefers-reduced-motion: reduce)',
            media: query,
            onchange: null,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            addListener: vi.fn(),
            removeListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })),
    );
}

beforeEach(() => {
    orbProps.last = null;
    emulateReducedMotion(false);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('Pending', () => {
    it('keeps an empty, visually hidden status region at rest', () => {
        render(<Pending label={null} orb="save" />);

        const region = screen.getByRole('status');

        expect(region).toBeEmptyDOMElement();
        expect(region).toHaveClass('sr-only');
        expect(screen.queryByTestId('orb')).toBeNull();
    });

    it('shows the text in the region and a motif hidden from screen readers', () => {
        render(<Pending label="Enregistrement du joueur…" orb="save" />);

        const region = screen.getByRole('status');

        expect(region).toHaveTextContent('Enregistrement du joueur…');
        expect(region).not.toHaveClass('sr-only');
        expect(screen.getByTestId('orb')).toHaveAttribute(
            'aria-hidden',
            'true',
        );
        expect(region.contains(screen.getByTestId('orb'))).toBe(true);
    });

    it('lets the orb follow the device theme', () => {
        render(<Pending label="Enregistrement du joueur…" orb="save" />);

        expect(orbProps.last?.theme).toBe('auto');
        expect(orbProps.last?.state).toBe('working');
        expect(orbProps.last?.size).toBe(20);
    });

    it('freezes the orb when reduced motion is requested', () => {
        emulateReducedMotion(true);

        render(<Pending label="Enregistrement du joueur…" orb="save" />);

        expect(orbProps.last?.paused).toBe(true);
        expect(screen.getByRole('status')).toHaveTextContent(
            'Enregistrement du joueur…',
        );
    });

    it('animates the orb when reduced motion is not requested', () => {
        render(<Pending label="Enregistrement du joueur…" orb="save" />);

        expect(orbProps.last?.paused).toBe(false);
    });
});
