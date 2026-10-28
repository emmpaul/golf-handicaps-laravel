import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { LogoutButton } from '@/components/logout-button';

// Spec app-access « Fermeture de session »: the button posts to the logout
// route. The real <Form> renders the form element; nothing is simulated.
// Mutation: the form sent with method `get` instead of the Wayfinder `logout`
// action (POST) → the method assertion goes red.

describe('LogoutButton', () => {
    it('posts a « Se déconnecter » form to /logout', () => {
        render(<LogoutButton />);

        const button = screen.getByRole('button', { name: 'Se déconnecter' });
        const form = button.closest('form');

        expect(button.getAttribute('type')).toBe('submit');
        expect(form?.getAttribute('action')).toBe('/logout');
        expect(form?.getAttribute('method')).toBe('post');
    });
});
