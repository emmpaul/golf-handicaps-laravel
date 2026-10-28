/// <reference types="node" />
import { readdirSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

// The @inertiajs/vite plugin bundles every ./pages/**/*.tsx as a page and has
// no exclude option: a test file there ships in the production build (with
// @testing-library) and resolves as an Inertia page. Page tests live under
// resources/js/tests/pages/.
// Mutation: login.test.tsx put back under resources/js/pages/ → this test
// names it.
describe('the Inertia page directory', () => {
    it('holds no test file', () => {
        const files = readdirSync('resources/js/pages', { recursive: true })
            .map(String)
            .filter((file) => /\.test\.[jt]sx?$/.test(file));

        expect(files).toEqual([]);
    });
});
