import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

import { AppNav } from '@/components/app-nav';

// Spec app-shell « Adresses des sections », « Section courante indiquée »,
// « Section courante visible sur la barre d'onglets » and visual-theme
// « Marque d'app dans l'en-tête ». Only usePage is simulated (design D13).
// Mutations: NavLink never sets `aria-current` → the « Rounds » current tests
// go red; label « Coups » replaced by « Strokes » in LINKS → the link list
// tests go red.

let url = '/';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url }),
}));

const SECTIONS = [
    ['Importer', '/import'],
    ['Rounds', '/rounds'],
    ['Handicaps', '/handicaps'],
    ['Coups', '/strokes'],
    ['Joueurs', '/players'],
];

function topNav() {
    return screen.getByRole('navigation', { name: 'Navigation principale' });
}

function tabBar() {
    return screen.getByRole('navigation', { name: 'Sections' });
}

function sectionLinks(nav: HTMLElement) {
    return within(nav)
        .getAllByRole('link')
        .filter((link) => link.getAttribute('href') !== '/');
}

function linkList(nav: HTMLElement) {
    return sectionLinks(nav).map((link) => [
        link.textContent,
        link.getAttribute('href'),
    ]);
}

function currentLinks(nav: HTMLElement) {
    return sectionLinks(nav)
        .filter((link) => link.getAttribute('aria-current') === 'page')
        .map((link) => link.textContent);
}

describe('AppNav on a round sub-page', () => {
    beforeEach(() => {
        url = '/rounds/0190a6b2-7c3e-7000-8000-000000000001';
        render(<AppNav />);
    });

    it('lists the five sections in order in the top navigation', () => {
        expect(linkList(topNav())).toEqual(SECTIONS);
    });

    it('lists the five sections in order in the tab bar', () => {
        expect(linkList(tabBar())).toEqual(SECTIONS);
    });

    it('marks only Rounds as current in the top navigation', () => {
        expect(currentLinks(topNav())).toEqual(['Rounds']);
    });

    it('marks only Rounds as current in the tab bar', () => {
        expect(currentLinks(tabBar())).toEqual(['Rounds']);
    });

    it('draws the pill only behind the current tab', () => {
        const pills = sectionLinks(tabBar()).map((link) => {
            const pill = link.querySelector('[data-slot="tab-pill"]');

            return [
                link.textContent,
                link.classList.contains('group'),
                link.getAttribute('aria-current'),
                pill?.classList.contains(
                    'group-aria-[current=page]:bg-primary',
                ),
            ];
        });

        expect(pills).toEqual([
            ['Importer', true, null, true],
            ['Rounds', true, 'page', true],
            ['Handicaps', true, null, true],
            ['Coups', true, null, true],
            ['Joueurs', true, null, true],
        ]);
    });

    it('links the brand to home under the accessible name Golf', () => {
        const home = screen.getByRole('link', { name: 'Golf' });

        expect(home.getAttribute('href')).toBe('/');
        expect(home.querySelector('svg')?.getAttribute('aria-hidden')).toBe(
            'true',
        );
        expect(screen.getByRole('banner').textContent).not.toContain('⛳');
    });
});

describe('AppNav on the home page', () => {
    it('marks no section as current', () => {
        url = '/';
        render(<AppNav />);

        expect(currentLinks(topNav())).toEqual([]);
        expect(currentLinks(tabBar())).toEqual([]);
    });
});
