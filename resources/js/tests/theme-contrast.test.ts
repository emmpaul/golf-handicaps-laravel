/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';
import { contrast, hex } from './palette';
import { sources } from './sources';

// Spec visual-theme « Contraste sur le thème sombre » and design D9: every text
// pair keeps 4.5:1, every non-text mark 3:1, except the three pairs of the
// maintainer's decision Q4, each pinned to its ratio.
// Mutations: --color-ink-2 set to oklch(0.55 0.01 260) → "ink-2/bg" and the
// other ink-2 pairs are named under 4.5; the field outline (--input) pointed at
// ink-3 → the "input/bg" exception reads 3.35 instead of 1.34; a fourth entry
// added to EXCEPTIONS without its ratio → the exception list test goes red.
// DO15: the four tokens kept out of the pairs are never readable text.
// Mutations: input placeholder at `placeholder:text-ink-3` → input.tsx is
// named; a fifth token added to OUT_OF_PAIRS → the out-of-pairs list test goes
// red; --color-ink-3 dropped from OUT_OF_PAIRS → the classification test names
// it.
// index-computation-page D7: ink-soft is the text of the rule lines, on bg and
// surface-1. Mutation: --color-ink-soft set to oklch(0.4 0.005 260) → the hex
// test and the pair test name it.

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
    ['color-ink-soft', 'color-bg'],
    ['color-ink-soft', 'color-surface-1'],
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

// Colour tokens kept out of the pairs (DO15), with the only use each is
// allowed: none of them is readable text. A graphic mark whose information is
// also written as text is a permitted use of ink-4 and bar (DO22).
const OUT_OF_PAIRS: [string, string][] = [
    ['color-ink-3', 'contrôle désactivé (exempté par WCAG 1.4.3) ou décor'],
    [
        'color-ink-4',
        'décor masqué aux lecteurs d’écran ou trait non porteur d’information ; bordure de case décochée (exception Q4) ; segment « Double bogey + », dont le compte est écrit (DO22)',
    ],
    [
        'color-bar',
        'décor ou trait non porteur d’information ; barre de différentiel qui ne compte pas, l’historique portant « Compte » (DO22)',
    ],
    ['color-rule-soft', 'décor ou trait non porteur d’information'],
];

// The shadcn variables of the pairs, as the tokens they point at (app.css).
const ALIASES: Record<string, string> = {
    input: 'color-rule',
    ring: 'color-ink',
    destructive: 'color-signal',
    'destructive-foreground': 'color-on-signal',
};

// Colour tokens that are no text nor mark of the pairs: utility keywords, the
// scrim, and the player colours, out of the sRGB gamut, always beside a name.
const NOT_MEASURED = /^color-(transparent|current|scrim|player-\d)$/;

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
        ['color-ink-soft', '#D5D7DB'],
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

describe('tokens kept out of the pairs (DO15)', () => {
    it('names exactly the four tokens kept out of the pairs', () => {
        expect(OUT_OF_PAIRS.map(([token]) => token)).toEqual([
            'color-ink-3',
            'color-ink-4',
            'color-bar',
            'color-rule-soft',
        ]);
    });

    it('classifies every colour token of tokens.css: in a pair, an exception or out of the pairs', () => {
        const classified = new Set(
            [
                ...textPairs.flat(),
                ...markPairs.flat(),
                ...EXCEPTIONS.flatMap(([fg, bg]) => [fg, bg]),
                ...OUT_OF_PAIRS.map(([token]) => token),
            ].map((name) => ALIASES[name] ?? name),
        );
        const tokens = [
            ...readFileSync('resources/css/tokens.css', 'utf8').matchAll(
                /^\s*--(color-[\w-]+):/gm,
            ),
        ]
            .map(([, name]) => name)
            .filter((name) => !NOT_MEASURED.test(name));

        expect(tokens).toContain('color-surface-2');
        expect(tokens.filter((name) => !classified.has(name))).toEqual([]);
    });

    it('writes no placeholder and no enabled text in ink-3 or ink-4', () => {
        // A class such as `placeholder:text-ink-3`, `text-ink-4` or
        // `hover:text-ink-3`; allowed only under a disabled variant
        // (`disabled:`, `aria-disabled:`, `*:disabled:`…).
        const offenders = sources().flatMap((file) =>
            [
                ...readFileSync(file, 'utf8').matchAll(
                    /(?<![\w-])((?:[^\s"'`:]+:)*)text-ink-[34](?![\w-])/g,
                ),
            ]
                .filter(([, variants]) => {
                    const chain = variants.split(':').filter(Boolean);

                    return (
                        chain.includes('placeholder') ||
                        !chain.some((variant) => variant.includes('disabled'))
                    );
                })
                .map(([match]) => `${file}: ${match}`),
        );

        expect(offenders).toEqual([]);
    });
});
