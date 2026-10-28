/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

// jsdom applies no stylesheet: the rule is read from base.css itself
// (spec visual-theme « Chiffres alignés »).
// Mutation: the `.num, .figure, td.num` rule removed from base.css → this
// test goes red.
const base = readFileSync('resources/css/base.css', 'utf8');

function declarationsOf(selector: string): Map<string, string> {
    const declarations = new Map<string, string>();

    for (const [, selectors, body] of base.matchAll(/([^{}]+)\{([^}]*)\}/g)) {
        const list = selectors.split(',').map((s) => s.trim());

        if (!list.includes(selector)) {
            continue;
        }

        for (const [, property, value] of body.matchAll(
            /([\w-]+):\s*([^;]+);/g,
        )) {
            declarations.set(property, value.trim());
        }
    }

    return declarations;
}

describe('aligned figures in base.css', () => {
    it('gives .num tabular figures', () => {
        expect(declarationsOf('.num').get('font-variant-numeric')).toBe(
            'tabular-nums',
        );
    });
});
