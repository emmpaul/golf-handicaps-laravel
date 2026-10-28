/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

// jsdom evaluates no media query: the rule is read from base.css itself.
// Mutation: the @media (prefers-reduced-motion: reduce) block removed from
// base.css → every test below goes red.
const base = readFileSync('resources/css/base.css', 'utf8');

function reducedMotionRule() {
    const block = base.match(
        /@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{\s*([^{]+)\{([^}]+)\}/,
    );

    return {
        selectors: (block?.[1] ?? '').split(',').map((s) => s.trim()),
        declarations: new Map(
            [...(block?.[2] ?? '').matchAll(/([\w-]+):\s*([^;]+);/g)].map(
                ([, property, value]) => [property, value.trim()] as const,
            ),
        ),
    };
}

describe('reduced motion in base.css', () => {
    it('targets every element and its pseudo-elements', () => {
        expect(reducedMotionRule().selectors).toEqual([
            '*:not(.keep-motion)',
            '*:not(.keep-motion)::before',
            '*:not(.keep-motion)::after',
        ]);
    });

    it('brings animations down to 0.01ms', () => {
        expect(reducedMotionRule().declarations.get('animation-duration')).toBe(
            '0.01ms !important',
        );
    });

    it('brings transitions down to 0.01ms', () => {
        expect(
            reducedMotionRule().declarations.get('transition-duration'),
        ).toBe('0.01ms !important');
    });
});
