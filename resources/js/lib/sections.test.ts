import { describe, expect, it } from 'vite-plus/test';
import { inSection } from '@/lib/sections';

// Spec app-shell, « Section courante indiquée ».
// Mutation: `path.startsWith(prefix)` without the trailing `/` in inSection →
// `/roundsx` falls in Rounds and the « Préfixe sans barre oblique » case goes red.
const SECTION_HREFS = [
    '/import',
    '/rounds',
    '/handicaps',
    '/strokes',
    '/players',
];

function sectionsOf(url: string): string[] {
    return SECTION_HREFS.filter((href) => inSection(url, href));
}

describe('inSection', () => {
    it.each([
        ['/rounds/0190a6b2-7c3e-7000-8000-000000000001', ['/rounds']],
        ['/imports/x', ['/import']],
        ['/courses', ['/rounds']],
        ['/', []],
        ['/roundsx', []],
        ['/handicaps?x=1', ['/handicaps']],
    ])('places %s in %j', (url, expected) => {
        expect(sectionsOf(url)).toEqual(expected);
    });
});
