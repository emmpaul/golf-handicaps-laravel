import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { SectionLabel } from '@/components/section-label';

// Spec app-shell « Libellé de section » (scenario « Imports »); design D5.
// Mutations: the value on the right dropped → the « IMPORTS » and « 1 » test
// goes red; the label rendered as a plain div → the heading test goes red.

describe('SectionLabel', () => {
    it('introduces the list with its label on the left and its value on the right', () => {
        const { container } = render(
            <SectionLabel value="1">Imports</SectionLabel>,
        );
        const row = container.firstElementChild!;

        expect([...row.children].map((child) => child.textContent)).toEqual([
            'Imports',
            '1',
        ]);
        expect(row).toHaveClass('border-b', 'border-rule', 'uppercase');
    });

    it('is a level 2 heading named by its label', () => {
        render(<SectionLabel value="1">Imports</SectionLabel>);

        expect(
            screen.getByRole('heading', { level: 2, name: 'Imports' }),
        ).toBeInTheDocument();
    });
});
