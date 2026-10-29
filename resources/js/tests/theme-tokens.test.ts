/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

// Every value below is a literal of tables D1, D2 and D8 of the visual-redesign
// design (and DO13 for the token names), written by hand. tokens.css only
// supplies the actual values.
// Mutations: --color-accent set to oklch(0.78 0.16 70) → "--color-accent" goes
// red; --text-title set to 26px → "--text-title" goes red; a
// --color-paper token added back → the paper test goes red; the dark
// `prefers-color-scheme` block added back → the media test goes red;
// `color-scheme: dark` removed from app.css → the colour-scheme test goes red.
const tokens = readFileSync('resources/css/tokens.css', 'utf8');
const app = readFileSync('resources/css/app.css', 'utf8');

function declarations(css: string): Map<string, string[]> {
    const found = new Map<string, string[]>();

    for (const [, name, value] of css
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .matchAll(/(--[\w-]+):\s*([^;]+);/g)) {
        found.set(name, [...(found.get(name) ?? []), value.trim()]);
    }

    return found;
}

const declared = declarations(tokens);

function valueOf(name: string): string | undefined {
    const values = declared.get(name);

    return values?.length === 1 ? values[0] : values?.join(' | ');
}

const colours: [string, string][] = [
    ['--color-bg', 'oklch(0.13 0.005 260)'],
    ['--color-surface-1', 'oklch(0.17 0.006 260)'],
    ['--color-surface-2', 'oklch(0.2 0.006 260)'],
    ['--color-surface-3', 'oklch(0.22 0.006 260)'],
    ['--color-rule', 'oklch(0.27 0.008 260)'],
    ['--color-rule-soft', 'oklch(0.21 0.006 260)'],
    ['--color-bar', 'oklch(0.3 0.008 260)'],
    ['--color-ink', 'oklch(0.97 0.003 260)'],
    ['--color-ink-2', 'oklch(0.68 0.01 260)'],
    ['--color-ink-3', 'oklch(0.5 0.01 260)'],
    ['--color-ink-4', 'oklch(0.4 0.01 260)'],
    ['--color-over', 'oklch(0.6 0.01 260)'],
    ['--color-accent', 'oklch(0.78 0.16 60)'],
    ['--color-signal', 'oklch(0.7 0.17 25)'],
    ['--color-info', 'oklch(0.8 0.12 200)'],
    ['--color-ok', 'oklch(0.8 0.14 145)'],
    // #FFFFFF in D1; the formatter writes CSS hex in lower case.
    ['--color-on-signal', '#ffffff'],
    ['--color-scrim', 'rgba(0, 0, 0, 0.6)'],
    ['--color-player-1', 'oklch(0.72 0.24 145)'],
    ['--color-player-2', 'oklch(0.62 0.22 260)'],
    ['--color-player-3', 'oklch(0.75 0.19 55)'],
    ['--color-player-4', 'oklch(0.72 0.17 200)'],
    ['--color-player-5', 'oklch(0.58 0.26 300)'],
    ['--color-player-6', 'oklch(0.85 0.19 105)'],
];

const radii: [string, string][] = [
    ['--radius-pill', '999px'],
    ['--radius-22', '22px'],
    ['--radius-14', '14px'],
    ['--radius-12', '12px'],
    ['--radius-10', '10px'],
    ['--radius-9', '9px'],
    ['--radius-8', '8px'],
    ['--radius-6', '6px'],
    ['--radius-4', '4px'],
    ['--radius-3', '3px'],
    ['--radius-2', '2px'],
];

// [name, size, line height, letter spacing (null: none), weight]
const scale: [string, string, string, string | null, string][] = [
    ['display-xl', '88px', '1', '-0.06em', '500'],
    ['display-l', '48px', '0.9', '-0.04em', '500'],
    ['display', '44px', '1', '-0.04em', '500'],
    ['kpi', '30px', '1', '-0.04em', '500'],
    ['title', '28px', '1.05', '-0.03em', '600'],
    ['num-l', '22px', '1', null, '500'],
    ['name-l', '22px', '1', '-0.01em', '600'],
    ['name-l-course', '22px', '1.1', '-0.02em', '600'],
    ['name-m', '20px', '1', '-0.01em', '600'],
    ['name-m-17', '17px', '1', null, '600'],
    ['num-m', '24px', '1', null, '500'],
    ['num-m-18', '18px', '1', null, '500'],
    ['num-m-17', '17px', '1', null, '500'],
    ['num-m-16', '16px', '1', null, '500'],
    ['num-m-15', '15px', '1', null, '500'],
    ['body-l', '16px', '1', null, '500'],
    ['label', '15px', '1', null, '600'],
    ['label-14', '14px', '1', null, '600'],
    ['label-13', '13px', '1', null, '600'],
    ['body', '14px', '1.5', null, '400'],
    ['body-13', '13px', '1.5', null, '400'],
    ['body-12', '12px', '1.5', null, '400'],
    ['link', '14px', '1', null, '500'],
    ['link-13', '13px', '1', null, '500'],
    ['meta', '13px', '1', null, '500'],
    ['meta-12', '12px', '1', null, '500'],
    ['meta-round', '12px', '1.4', null, '500'],
    ['section', '11px', '1', '0.08em', '500'],
    ['caption', '10px', '1', '0.08em', '500'],
    ['caption-9', '9px', '1', null, '500'],
];

const motion: [string, string][] = [
    ['--ease-design', 'cubic-bezier(0.2, 0.8, 0.2, 1)'],
    ['--ease-pop', 'cubic-bezier(0.3, 1.6, 0.5, 1)'],
    ['--dur-press', '150ms'],
    ['--dur-fade', '250ms'],
    ['--dur-swap', '300ms'],
    ['--dur-nav', '350ms'],
    ['--dur-sheet', '400ms'],
    ['--dur-enter', '420ms'],
    ['--dur-bar', '600ms'],
];

describe('design tokens in tokens.css', () => {
    for (const [name, expected] of [...colours, ...radii, ...motion]) {
        it(`declares ${name}: ${expected} once`, () => {
            expect(valueOf(name)).toBe(expected);
        });
    }

    it('declares --shadow-float once', () => {
        expect(valueOf('--shadow-float')).toBe(
            '0 10px 30px rgba(0, 0, 0, 0.5)',
        );
    });

    for (const [name, size, lineHeight, tracking, weight] of scale) {
        it(`declares --text-${name}`, () => {
            expect({
                size: valueOf(`--text-${name}`),
                lineHeight: valueOf(`--text-${name}--line-height`),
                tracking: valueOf(`--text-${name}--letter-spacing`) ?? null,
                weight: valueOf(`--text-${name}--font-weight`),
            }).toEqual({ size, lineHeight, tracking, weight });
        });
    }

    it('keeps no token of the scoreboard paper', () => {
        expect(
            [...declared.keys()].filter((name) =>
                name.startsWith('--color-paper'),
            ),
        ).toEqual([]);
    });

    it('has no second theme under prefers-color-scheme', () => {
        expect(tokens).not.toMatch(/@media[^{]*prefers-color-scheme/);
        expect(app).not.toMatch(/@media[^{]*prefers-color-scheme/);
    });

    it('declares the dark colour scheme on :root in app.css', () => {
        expect(app).toMatch(/:root\s*\{[^}]*color-scheme:\s*dark;/);
    });
});
