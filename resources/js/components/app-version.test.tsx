import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { AppVersion } from '@/components/app-version';

// Spec app-version « Version affichée dans l'interface ».
// Mutations: the full sha shown instead of its first 7 characters → the
// « v0.6.0 · 4794e1b » test goes red; the link kept at 0.0.0 → the
// « v0.0.0 » test goes red.

describe('AppVersion', () => {
    it('shows the version and the short commit, linked to the release', () => {
        render(
            <AppVersion
                version="0.6.0"
                commit="4794e1b0c0ffee00000000000000000000000000"
            />,
        );

        expect(
            screen
                .getByRole('link', { name: 'v0.6.0 · 4794e1b' })
                .getAttribute('href'),
        ).toBe(
            'https://github.com/emmpaul/golf-handicaps-laravel/releases/tag/v0.6.0',
        );
    });

    it('shows the version alone without a known commit, linked to the release', () => {
        render(<AppVersion version="0.6.0" commit={null} />);

        expect(
            screen.getByRole('link', { name: 'v0.6.0' }).getAttribute('href'),
        ).toBe(
            'https://github.com/emmpaul/golf-handicaps-laravel/releases/tag/v0.6.0',
        );
    });

    it('shows v0.0.0 as plain text, without a link', () => {
        render(<AppVersion version="0.0.0" commit={null} />);

        expect(screen.getByText('v0.0.0').tagName).toBe('SPAN');
        expect(screen.queryByRole('link')).toBeNull();
    });
});
