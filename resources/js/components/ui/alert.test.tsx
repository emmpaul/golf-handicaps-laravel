import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

// Spec visual-theme « Échelle typographique » and DO24 of visual-redesign: the
// closed scale of tokens.css drops the Tailwind sizes, so `text-sm` produces
// no style; the alert takes the running text of the design (Geist 13 px) and a
// title in Geist 600, 14 px.
// Mutations: `text-sm` kept on the alert or its description → the scale test
// goes red; the destructive variant left in `text-destructive` → the variant
// test goes red.

describe('Alert', () => {
    it('writes the title and the description in styles of the scale', () => {
        render(
            <Alert>
                <AlertTitle>Ce round semble déjà enregistré</AlertTitle>
                <AlertDescription>Voir le round existant</AlertDescription>
            </Alert>,
        );

        expect(screen.getByRole('alert')).toHaveClass('text-body-13');
        expect(screen.getByRole('alert')).not.toHaveClass('text-sm');
        expect(
            screen.getByText('Ce round semble déjà enregistré'),
        ).toHaveClass('text-label-14');
        expect(screen.getByText('Voir le round existant')).toHaveClass(
            'text-body-13',
            'text-ink-2',
        );
    });

    it('writes a destructive alert in `signal`', () => {
        render(
            <Alert variant="destructive">
                <AlertTitle>Lecture IA indisponible</AlertTitle>
            </Alert>,
        );

        expect(screen.getByRole('alert')).toHaveClass('text-signal');
    });
});
