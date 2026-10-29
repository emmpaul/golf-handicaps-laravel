import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';

// jsdom computes no layout: the classes that size and colour the controls
// are the contract (spec app-shell « Cibles tactiles et texte des champs sur
// téléphone », visual-theme « Contraste sur le thème sombre », « Focus visible
// au clavier »; design D5, D9).
// Mutations: `md:h-8` kept on the default button, the input or the select →
// the « no md:h-8 » tests go red; field contour at `border-ink-3` instead of
// `border-rule` → the input and select tests go red; contour of a field in
// error at `signal` (the former `aria-invalid:border-signal`) instead of the
// amber of the grid cells and the date (DO25) → the error tests go red.

function classesOf(element: Element): string[] {
    return element.className.split(/\s+/);
}

// A transition that would fade the outline in: `transition`, `transition-all`
// or a named list that includes `outline`.
function outlineTransitions(element: Element): string[] {
    return classesOf(element).filter(
        (name) =>
            name === 'transition' ||
            name === 'transition-all' ||
            (name.startsWith('transition-[') && name.includes('outline')),
    );
}

describe('buttons', () => {
    it('gives the default button 48 px at every width, filled with ink', () => {
        render(<Button>Ajouter</Button>);

        const classes = classesOf(screen.getByRole('button', { name: 'Ajouter' }));

        expect(classes).toEqual(
            expect.arrayContaining(['h-12', 'bg-ink', 'text-bg', 'rounded-10']),
        );
        expect(classes.filter((name) => name.startsWith('md:'))).toEqual([]);
    });

    it('outlines the outline button with the rule colour', () => {
        render(<Button variant="outline">Renommer</Button>);

        expect(screen.getByRole('button', { name: 'Renommer' })).toHaveClass(
            'border-rule',
            'bg-transparent',
        );
    });

    it('fills the destructive button with signal, text in on-signal', () => {
        render(<Button variant="destructive">Supprimer</Button>);

        expect(screen.getByRole('button', { name: 'Supprimer' })).toHaveClass(
            'bg-signal',
            'text-on-signal',
        );
    });
});

describe('fields', () => {
    // DO25: a field in error takes the amber contour of the grid cells and of
    // the date; `signal` stays for what destroys and for « under par ».
    it('outlines an input in error in amber, never in signal', () => {
        render(<Input aria-label="Nouveau joueur" aria-invalid />);

        const classes = classesOf(
            screen.getByRole('textbox', { name: 'Nouveau joueur' }),
        );

        expect(classes).toEqual(
            expect.arrayContaining([
                'aria-invalid:border-accent/60',
                'aria-invalid:bg-accent/8',
            ]),
        );
        expect(classes.filter((name) => name.includes('signal'))).toEqual([]);
    });

    it('outlines a native select in error in amber, never in signal', () => {
        render(
            <NativeSelect aria-label="Parcours" aria-invalid>
                <NativeSelectOption value="1">BETHPAGE BLACK</NativeSelectOption>
            </NativeSelect>,
        );

        const classes = classesOf(
            screen.getByRole('combobox', { name: 'Parcours' }),
        );

        expect(classes).toEqual(
            expect.arrayContaining([
                'aria-invalid:border-accent/60',
                'aria-invalid:bg-accent/8',
            ]),
        );
        expect(classes.filter((name) => name.includes('signal'))).toEqual([]);
    });

    it('colours the label of a field in error in amber, never in signal', () => {
        render(
            <Field data-invalid>
                <FieldLabel>Rating</FieldLabel>
            </Field>,
        );

        const classes = classesOf(
            screen.getByRole('group'),
        );

        expect(classes).toContain('data-[invalid=true]:text-accent');
        expect(
            classes.filter(
                (name) => name.includes('signal') || name.includes('destructive'),
            ),
        ).toEqual([]);
    });

    it('gives the input 48 px, 16 px text, the rule contour and a 10 px radius', () => {
        render(<Input aria-label="Nouveau joueur" />);

        const classes = classesOf(
            screen.getByRole('textbox', { name: 'Nouveau joueur' }),
        );

        expect(classes).toEqual(
            expect.arrayContaining([
                'h-12',
                'text-body-l',
                'border-rule',
                'rounded-10',
                'bg-surface-1',
            ]),
        );
        expect(classes.filter((name) => name.startsWith('md:'))).toEqual([]);
    });

    it('gives the native select 48 px, 16 px text, the rule contour and a 10 px radius', () => {
        render(
            <NativeSelect aria-label="Parcours">
                <NativeSelectOption value="1">BETHPAGE BLACK</NativeSelectOption>
            </NativeSelect>,
        );

        const classes = classesOf(
            screen.getByRole('combobox', { name: 'Parcours' }),
        );

        expect(classes).toEqual(
            expect.arrayContaining([
                'h-12',
                'text-body-l',
                'border-rule',
                'rounded-10',
                'bg-surface-1',
            ]),
        );
        expect(classes.filter((name) => name.startsWith('md:'))).toEqual([]);
    });

    it('borders the unchecked checkbox with ink-4', () => {
        render(<Checkbox aria-label="Sean" />);

        expect(screen.getByRole('checkbox', { name: 'Sean' })).toHaveClass(
            'border-ink-4',
            'checked:border-ink',
            'checked:bg-ink',
        );
    });
});

describe('keyboard focus', () => {
    it('outlines the focused button without fading the outline', () => {
        render(<Button>Enregistrer</Button>);

        const button = screen.getByRole('button', { name: 'Enregistrer' });

        expect(classesOf(button)).toContain('focus-visible:outline-2');
        expect(classesOf(button)).toContain('focus-visible:outline-ring');
        expect(outlineTransitions(button)).toEqual([]);
    });

    it('outlines the focused input without fading the outline', () => {
        render(<Input aria-label="Nom" />);

        const input = screen.getByRole('textbox', { name: 'Nom' });

        expect(classesOf(input)).toContain('focus-visible:outline-2');
        expect(classesOf(input)).toContain('focus-visible:outline-ring');
        expect(outlineTransitions(input)).toEqual([]);
    });
});
