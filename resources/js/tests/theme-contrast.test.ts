import { describe, expect, it } from 'vite-plus/test';
import { contrast, hex } from './palette';

// Spec visual-theme « Contraste sur le thème sombre » and design D9: every text
// pair keeps 4.5:1, every non-text mark 3:1, except the three pairs of the
// maintainer's decision Q4, each pinned to its ratio.
// Mutations: --color-ink-2 set to oklch(0.55 0.01 260) → "ink-2/bg" and the
// other ink-2 pairs are named under 4.5; the field outline (--input) pointed at
// ink-3 → the "input/bg" exception reads 3.35 instead of 1.34; a fourth entry
// added to EXCEPTIONS without its ratio → the exception list test goes red.

// Text: running text, secondary text, text on filled controls and pills.
const textPairs: [string, string][] = [
    ['color-ink', 'color-bg'],
    ['color-ink', 'color-surface-1'],
    ['color-ink', 'color-surface-2'],
    ['color-ink', 'color-surface-3'],
    ['color-ink-2', 'color-bg'],
    ['color-ink-2', 'color-surface-1'],
    ['color-ink-2', 'color-surface-2'],
    ['color-ink-2', 'color-surface-3'],
    ['color-bg', 'color-ink'],
    ['color-accent', 'color-bg'],
    ['color-signal', 'color-bg'],
    ['color-info', 'color-bg'],
    ['color-ok', 'color-bg'],
    ['color-bg', 'color-signal'],
    ['color-bg', 'color-accent'],
];

// Non-text marks: focus outline, « over par » marks, the Importer icon.
const markPairs: [string, string][] = [
    ['ring', 'color-bg'],
    ['color-ink', 'color-bg'],
    ['color-over', 'color-bg'],
];

// [fg, bg, threshold the pair stays under, ratio rounded to the hundredth]
const EXCEPTIONS: [string, string, number, number][] = [
    // Field outline (design value oklch(0.27 0.008 260)).
    ['input', 'color-bg', 3, 1.34],
    // Border of an unchecked box (design value oklch(0.4 0.01 260)).
    ['color-ink-4', 'color-bg', 3, 2.18],
    // White text of the red « Supprimer » button.
    ['destructive-foreground', 'destructive', 4.5, 2.88],
];

describe('dark palette values', () => {
    for (const [name, expected] of [
        ['color-bg', '#060709'],
        ['color-surface-1', '#0E0F12'],
        ['color-surface-2', '#141619'],
        ['color-surface-3', '#191B1D'],
        ['color-rule', '#24272A'],
        ['color-rule-soft', '#17181B'],
        ['color-bar', '#2B2E32'],
        ['color-ink', '#F4F5F7'],
        ['color-ink-2', '#95989F'],
        ['color-ink-3', '#606369'],
        ['color-ink-4', '#45484D'],
        ['color-over', '#7D8086'],
        ['color-accent', '#FF9C3B'],
        ['color-signal', '#F66D67'],
        ['color-info', '#43D5DC'],
        ['color-ok', '#81D584'],
        ['color-on-signal', '#FFFFFF'],
        ['background', '#060709'],
        ['foreground', '#F4F5F7'],
        ['input', '#24272A'],
        ['destructive', '#F66D67'],
        ['ring', '#F4F5F7'],
    ] as const) {
        it(`--${name} is ${expected}`, () => {
            expect(hex(name)).toBe(expected);
        });
    }
});

describe('dark palette contrast', () => {
    it('keeps every text pair at 4.5:1 and every mark at 3:1', () => {
        const under = [
            ...textPairs.map(([fg, bg]) => [fg, bg, 4.5] as const),
            ...markPairs.map(([fg, bg]) => [fg, bg, 3] as const),
        ]
            .filter(([fg, bg, threshold]) => contrast(fg, bg) < threshold)
            .map(
                ([fg, bg, threshold]) =>
                    `${fg}/${bg} ${contrast(fg, bg).toFixed(2)} < ${threshold}`,
            );

        expect(under).toEqual([]);
    });

    it('measures the secondary text of the spec scenario', () => {
        expect(contrast('color-ink-2', 'color-bg').toFixed(2)).toBe('6.98');
        expect(contrast('color-ink-2', 'color-surface-3').toFixed(2)).toBe(
            '6.01',
        );
        expect(contrast('color-over', 'color-bg').toFixed(2)).toBe('5.10');
    });

    it('names exactly the three exceptions of decision Q4', () => {
        expect(
            EXCEPTIONS.map(
                ([fg, bg, threshold, ratio]) =>
                    `${fg}/${bg} < ${threshold} at ${ratio.toFixed(2)}`,
            ),
        ).toEqual([
            'input/color-bg < 3 at 1.34',
            'color-ink-4/color-bg < 3 at 2.18',
            'destructive-foreground/destructive < 4.5 at 2.88',
        ]);
    });

    for (const [fg, bg, threshold, ratio] of EXCEPTIONS) {
        it(`keeps the exception ${fg}/${bg} at ${ratio}:1, under ${threshold}`, () => {
            const measured = contrast(fg, bg);

            expect(measured.toFixed(2)).toBe(ratio.toFixed(2));
            expect(measured).toBeLessThan(threshold);
        });
    }
});
