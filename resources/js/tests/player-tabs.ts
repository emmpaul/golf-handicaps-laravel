import { screen, within } from '@testing-library/react';
import { expect } from 'vite-plus/test';

// The pages Handicaps and « Calcul de l'index » share the same player tabs
// (PlayerTabs, design D5): Gab, Manu, Sean, Manu chosen. Only the address of
// each tab differs, so the caller hands the three expected addresses. The
// tests that use it go red when a tab is missing, reordered, points to the
// wrong page, or when `aria-current` lands on another player.

/**
 * Checks the three player tabs, Manu chosen, against the expected addresses,
 * and returns them in order for the caller's own assertions.
 */
export function expectPlayerTabs(hrefs: string[]): HTMLElement[] {
    const tabs = within(
        screen.getByRole('navigation', { name: 'Choisir un joueur' }),
    ).getAllByRole('link');

    expect(tabs.map((tab) => tab.textContent)).toEqual(['Gab', 'Manu', 'Sean']);
    expect(tabs.map((tab) => tab.getAttribute('href'))).toEqual(hrefs);
    expect(tabs.map((tab) => tab.getAttribute('aria-current'))).toEqual([
        null,
        'page',
        null,
    ]);

    return tabs;
}
