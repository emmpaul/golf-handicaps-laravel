import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';

// jsdom computes no layout: the classes that size the controls are the
// contract (spec app-shell « Cibles tactiles et texte des champs sur
// téléphone », visual-theme « Focus visible au clavier »).
// Mutations: `h-11` → `h-9` in the default size of button.tsx → the button
// test goes red; `text-base` → `text-sm` in input.tsx → the input test goes red.

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

describe('touch targets under 768 px', () => {
    it('gives the default button 44 px under md and 32 px from md', () => {
        render(<Button>Enregistrer</Button>);

        const classes = classesOf(
            screen.getByRole('button', { name: 'Enregistrer' }),
        );

        expect(classes).toContain('h-11');
        expect(classes).toContain('md:h-8');
    });

    it('gives the input 44 px and 16 px text under md', () => {
        render(<Input aria-label="Nom" />);

        const classes = classesOf(screen.getByRole('textbox', { name: 'Nom' }));

        expect(classes).toContain('h-11');
        expect(classes).toContain('text-base');
        expect(classes).toContain('md:h-8');
        expect(classes).toContain('md:text-sm');
    });

    it('gives the native select 44 px and 16 px text under md', () => {
        render(
            <NativeSelect aria-label="Joueur">
                <NativeSelectOption value="1">Alice</NativeSelectOption>
            </NativeSelect>,
        );

        const classes = classesOf(
            screen.getByRole('combobox', { name: 'Joueur' }),
        );

        expect(classes).toContain('h-11');
        expect(classes).toContain('text-base');
        expect(classes).toContain('md:h-8');
        expect(classes).toContain('md:text-sm');
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

describe('alert dialog', () => {
    it('keeps the open dialog within 20 rem, the narrowest screen', () => {
        render(
            <AlertDialog open>
                <AlertDialogContent>
                    <AlertDialogTitle>Supprimer le round ?</AlertDialogTitle>
                </AlertDialogContent>
            </AlertDialog>,
        );

        expect(classesOf(screen.getByRole('alertdialog'))).toContain(
            'data-[size=default]:max-w-xs',
        );
        expect(screen.getByRole('alertdialog')).toHaveAttribute(
            'data-size',
            'default',
        );
    });
});
