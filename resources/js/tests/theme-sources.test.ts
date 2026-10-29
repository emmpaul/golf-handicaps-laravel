/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';
import { sources } from './sources';

// Stands in for the Hallmark linter of the Next.js app (design D10): every
// colour comes from a token, and no transition covers all properties.
// Mutations: `transition-all` added to a component → that file is named;
// `text-[#123456]` added to a component → that file is named.
const FORBIDDEN: [string, RegExp][] = [
    ['quoted hex colour', /(['"`])#(?:[\da-f]{3,4}|[\da-f]{6}|[\da-f]{8})\1/i],
    ['arbitrary hex class', /\[#/],
    ['oklch()', /oklch\(/],
    ['rgb()', /rgba?\(/],
    ['hsl()', /hsla?\(/],
    ['transition-all', /\btransition-all\b/],
    ['transition: all', /transition:\s*all\b/],
];

// The single named exception: the Inertia progress bar cannot read CSS
// variables, so app.tsx gives it --color-ink of the dark theme, #F4F5F7, and no
// other value (DO16 of visual-redesign).
// Mutations: progress colour '#4B5563' in app.tsx → app.tsx is named; the
// former light ink '#0F1D3E' kept → app.tsx is named.
const EXCEPTIONS: Record<string, RegExp> = {
    'resources/js/app.tsx': /progress:\s*\{\s*color:\s*'#F4F5F7',?\s*\}/,
};

describe('theme sources', () => {
    it('scans the hand-written sources of resources/js', () => {
        expect(sources()).toContain('resources/js/tests/palette.ts');
    });

    it('declares no hard-coded colour and no global transition', () => {
        const offenders = sources().flatMap((file) => {
            const exception = EXCEPTIONS[file];
            const text = exception
                ? readFileSync(file, 'utf8').replace(exception, '')
                : readFileSync(file, 'utf8');
            const found = FORBIDDEN.filter(([, pattern]) =>
                pattern.test(text),
            ).map(([label]) => label);

            return found.length > 0 ? [`${file}: ${found.join(', ')}`] : [];
        });

        expect(offenders).toEqual([]);
    });
});
