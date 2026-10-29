import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableRow,
} from '@/components/ui/table';

// jsdom computes no font features: the class on the table is the contract
// (spec visual-theme « Chiffres alignés », « Échelle typographique », DO24 of
// visual-redesign: `text-sm` produces no style on the closed scale).
// Mutations: `tabular-nums` removed from table.tsx → the figures test goes
// red; `text-sm` kept on the table or its caption → the scale test goes red.

describe('aligned figures in tables', () => {
    it('puts tabular-nums on the table', () => {
        render(
            <Table>
                <TableBody>
                    <TableRow>
                        <TableCell>15,8</TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell>17,0</TableCell>
                    </TableRow>
                </TableBody>
            </Table>,
        );

        expect(screen.getByRole('table').className.split(/\s+/)).toContain(
            'tabular-nums',
        );
    });

    it('writes the table and its caption in styles of the scale', () => {
        render(
            <Table>
                <TableCaption>Rounds</TableCaption>
                <TableBody>
                    <TableRow>
                        <TableCell>15,8</TableCell>
                    </TableRow>
                </TableBody>
            </Table>,
        );

        expect(screen.getByRole('table')).toHaveClass('text-body-13');
        expect(screen.getByRole('table')).not.toHaveClass('text-sm');
        expect(screen.getByText('Rounds')).toHaveClass(
            'text-body-12',
            'text-ink-2',
        );
    });
});
