/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

import { cn } from '@/lib/utils';

// tailwind-merge only knows the default Tailwind scale: an unknown text style
// such as `text-label` is taken for a colour, and `cn('text-bg', 'text-label')`
// would drop `text-bg`. cn must know every text style of tokens.css (D2).
// Mutation: `label` removed from the text styles of utils.ts → the test names
// `text-label`.
const STYLES = [
    ...readFileSync('resources/css/tokens.css', 'utf8').matchAll(
        /^\s*--text-([a-z0-9-]+?):/gm,
    ),
]
    .map(([, name]) => name)
    .filter((name) => !name.includes('--'));

describe('cn', () => {
    it('reads the text styles of tokens.css', () => {
        expect(STYLES).toContain('label-14');
        expect(STYLES).toHaveLength(30);
    });

    it('keeps a text colour beside each text style of the design', () => {
        const dropped = STYLES.filter(
            (name) => cn('text-bg', `text-${name}`) !== `text-bg text-${name}`,
        ).map((name) => `text-${name}`);

        expect(dropped).toEqual([]);
    });

    it('keeps the last of two text styles', () => {
        expect(cn('text-label', 'text-meta')).toBe('text-meta');
    });

    it('keeps the last of two design radii', () => {
        expect(cn('rounded-10', 'rounded-pill')).toBe('rounded-pill');
    });
});
