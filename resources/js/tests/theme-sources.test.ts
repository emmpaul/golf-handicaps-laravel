/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';

// Stands in for the Hallmark linter of the Next.js app (design D10): every
// colour comes from a token, and no transition covers all properties.
// Mutations: `transition-all` added to a component → that file is named;
// `text-[#123456]` added to a component → that file is named.
const ROOT = 'resources/js';
const GENERATED = ['actions', 'routes', 'wayfinder'];

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
// variables, so app.tsx gives it the light --color-ink, #0F1D3E, and no other
// value (design D6).
// Mutation: progress colour '#4B5563' in app.tsx → app.tsx is named.
const EXCEPTIONS: Record<string, RegExp> = {
    'resources/js/app.tsx': /progress:\s*\{\s*color:\s*'#0F1D3E',?\s*\}/,
};

function sources(dir: string): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            return dir === ROOT && GENERATED.includes(entry.name)
                ? []
                : sources(path);
        }

        return /\.tsx?$/.test(entry.name) && !/\.test\./.test(entry.name)
            ? [path]
            : [];
    });
}

describe('theme sources', () => {
    it('scans the hand-written sources of resources/js', () => {
        expect(sources(ROOT)).toContain('resources/js/tests/palette.ts');
    });

    it('declares no hard-coded colour and no global transition', () => {
        const offenders = sources(ROOT).flatMap((file) => {
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
