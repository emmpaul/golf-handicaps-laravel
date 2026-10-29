import { render, screen } from '@testing-library/react';
import type { ThinkingOrbProps } from 'thinking-orbs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';

import { Pending } from '@/components/ui/pending';

// Spec loading-feedback « Indicateur d'attente », « Indicateur dans les deux
// thèmes » (dark variant only, text as the metas of the design: Geist Mono 500,
// 12 px, ink-2; DO24 of visual-redesign), « Mouvement réduit ». The orb is
// simulated to read its props: it paints on a canvas jsdom cannot draw.
// Mutations: `theme="auto"` kept in PendingOrb → the theme test goes red; the
// text left in `text-sm text-muted-foreground` (no style of the scale) → the
// meta test goes red; `paused` not passed to ThinkingOrb → the reduced-motion
// test goes red.

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

    it('writes the text as a meta of the design: Geist Mono 12 px, ink-2', () => {
        render(<Pending label="Enregistrement du joueur…" orb="save" />);

        expect(screen.getByRole('status')).toHaveClass(
            'font-mono',
            'text-meta-12',
            'text-ink-2',
        );
    });

    it('draws the orb in its dark variant whatever the device theme', () => {
        render(<Pending label="Enregistrement du joueur…" orb="save" />);

        expect(orbProps.last?.theme).toBe('dark');
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
