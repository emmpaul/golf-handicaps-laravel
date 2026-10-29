import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

import Login from '@/pages/login';

// Spec app-access « Page de saisie du code », « Ouverture de session » (code
// faux) and loading-feedback « Attente de l'ouverture de session ». The real
// <Form> renders the form element; only its slot state (errors, processing) is
// forced, since jsdom cannot run a visit. usePage feeds the version line; Head
// needs the head manager of createInertiaApp and renders nothing here; the orb
// paints on a canvas jsdom cannot draw.
// Mutations: `disabled={processing}` removed from « Entrer » → the processing
// test goes red; label « Connexion » without « … » → the processing test goes
// red; the page left in AppLayout (no own layout) → the « no section link »
// test goes red; `aria-describedby` dropped → the error test goes red.
// Visual-redesign (decision Q9, DO12): the page is recomposed with the
// elements of the design, labels unchanged. Mutations: the drawn BrandMark
// kept beside « Golf » (E3) → the design test finds an svg and goes red; the
// button left in outline → the design test goes red; the narrow column of
// the former layout (`max-w-sm px-4`) → the column test goes red.
// Reduce-mobile-padding (spec app-shell « Rangée du haut des pages »): the
// row « Golf » starts at the bottom of the safe area, no margin added.
// Mutation: `pt-[max(54px,calc(var(--safe-top)+10px))]` back on the row →
// the top-of-row test goes red.

const formState = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
}));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const inertia = await importOriginal<typeof import('@inertiajs/react')>();
    const RealForm = inertia.Form;

    return {
        ...inertia,
        Head: () => null,
        usePage: () => ({
            url: '/login',
            props: { app: { version: '0.0.0', commit: null } },
        }),
        Form: ({
            children,
            ...props
        }: React.ComponentProps<typeof RealForm>) => (
            <RealForm {...props}>
                {(slot) =>
                    typeof children === 'function'
                        ? children({
                              ...slot,
                              errors: formState.errors,
                              processing: formState.processing,
                          })
                        : children
                }
            </RealForm>
        ),
    };
});

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

/** The page inside the layout it declares, as Inertia mounts it. */
function renderPage() {
    const layout = Login.layout as (page: ReactNode) => ReactNode;

    return render(layout(<Login />));
}

beforeEach(() => {
    formState.errors = {};
    formState.processing = false;
});

describe('Login page', () => {
    it('shows the title, the masked passcode field and the « Entrer » button', () => {
        renderPage();

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Handicaps golf',
        );

        const field = screen.getByLabelText("Code d'accès");

        expect(field.getAttribute('type')).toBe('password');
        expect(field.getAttribute('autocomplete')).toBe('current-password');
        expect(field.getAttribute('name')).toBe('passcode');
        expect(screen.getByRole('button', { name: 'Entrer' })).toBeEnabled();
    });

    it('opens on the row « Golf » in text, the title at 28 px, the field of 48 px and the filled « Entrer »', () => {
        renderPage();

        const golf = screen.getByText('Golf');

        expect(golf.className).toContain('text-label');
        expect(golf.querySelector('svg')).toBeNull();
        expect(golf.parentElement?.querySelector('svg')).toBeNull();
        expect(screen.getByRole('heading', { level: 1 }).className).toContain(
            'text-title',
        );
        expect(screen.getByLabelText("Code d'accès").className).toContain(
            'h-12',
        );
        expect(
            screen.getByRole('button', { name: 'Entrer' }).className,
        ).toContain('bg-ink');
    });

    it('starts the row « Golf » at 54 px or at the bottom of the safe area, no margin added', () => {
        renderPage();

        const row = screen.getByText('Golf').parentElement as HTMLElement;
        const classes = row.className.split(/\s+/);

        expect(classes).toContain('pt-[max(54px,var(--safe-top))]');
        expect(classes.filter((name) => name.includes('+10px'))).toEqual([]);
    });

    it('lays the page in the column of 390 px with the gutters of 16 px', () => {
        renderPage();

        const main = screen.getByRole('main');

        expect(main.className).toContain('max-w-(--column)');
        expect(main.className).toContain('px-(--gutter)');
    });

    it('posts the form to /login', () => {
        renderPage();

        const form = screen
            .getByRole('button', { name: 'Entrer' })
            .closest('form');

        expect(form?.getAttribute('action')).toBe('/login');
        expect(form?.getAttribute('method')).toBe('post');
    });

    it('shows no section link, only the version line', () => {
        renderPage();

        expect(screen.queryAllByRole('link')).toEqual([]);
        expect(screen.queryByRole('navigation')).toBeNull();
        expect(screen.getByText('v0.0.0')).toBeInTheDocument();
    });

    it('shows « Code incorrect » in an alert tied to the invalid field', () => {
        formState.errors = { passcode: 'Code incorrect' };

        renderPage();

        const alert = screen.getByRole('alert');
        const field = screen.getByLabelText("Code d'accès");

        expect(alert.textContent).toBe('Code incorrect');
        expect(alert.className).toContain('text-body-12');
        expect(field.getAttribute('aria-invalid')).toBe('true');
        expect(field.getAttribute('aria-describedby')).toBe(alert.id);
        expect(alert.id).not.toBe('');
    });

    it('shows no alert and a valid field without error', () => {
        renderPage();

        const field = screen.getByLabelText("Code d'accès");

        expect(screen.queryByRole('alert')).toBeNull();
        expect(field.getAttribute('aria-invalid')).toBeNull();
        expect(field.getAttribute('aria-describedby')).toBeNull();
    });

    it('shows « Connexion… » and disables « Entrer » while sending', () => {
        formState.processing = true;

        renderPage();

        expect(screen.getByRole('status').textContent).toBe('Connexion…');
        expect(screen.getByRole('button', { name: 'Entrer' })).toBeDisabled();
    });

    it('keeps the status region empty at rest', () => {
        renderPage();

        expect(screen.getByRole('status')).toBeEmptyDOMElement();
    });
});
