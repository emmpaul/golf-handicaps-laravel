import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';

// jsdom computes no font features: the class on the table is the contract
// (spec visual-theme « Chiffres alignés »).
// Mutation: `tabular-nums` removed from table.tsx → this test goes red.

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
});
