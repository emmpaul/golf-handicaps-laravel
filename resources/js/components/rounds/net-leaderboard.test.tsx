import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { NetLeaderboard } from '@/components/rounds/net-leaderboard';
import { GAB, MANU, SEAN } from '@/tests/reference-round';

// Spec rounds « Classement net d'un round » and « Classement lisible sur
// téléphone »: Gab 1,0 and Manu 17,4 before the reference card, without
// rating, tie at 71; Sean on his first round, without index.
// Mutation: the winner marked at rank 2 instead of 1 → the signal test goes
// red. Mutation: « pas encore d'index » dropped → the no-index test goes red.

const entries = [
    {
        playerId: GAB,
        name: 'Gab',
        gross: 72,
        indexBeforeT: 10,
        playingHandicap: 1,
        net: 71,
        rank: 1,
    },
    {
        playerId: MANU,
        name: 'Manu',
        gross: 88,
        indexBeforeT: 174,
        playingHandicap: 17,
        net: 71,
        rank: 1,
    },
    {
        playerId: SEAN,
        name: 'Sean',
        gross: 81,
        indexBeforeT: null,
        playingHandicap: null,
        net: null,
        rank: null,
    },
];

/** The cells of each body row, in the order shown. */
function bodyRows(): string[][] {
    return within(screen.getByRole('table', { name: 'Classement net' }))
        .getAllByRole('row')
        .slice(1)
        .map((row) =>
            within(row)
                .getAllByRole('cell')
                .map((cell) => cell.textContent ?? ''),
        );
}

describe('NetLeaderboard', () => {
    it('heads the columns « Joueur », « Brut », « Hcp de jeu », « Net », « Rang »', () => {
        render(<NetLeaderboard entries={entries} />);

        expect(
            screen
                .getAllByRole('columnheader')
                .map((header) => header.textContent),
        ).toEqual(['Joueur', 'Brut', 'Hcp de jeu', 'Net', 'Rang']);
    });

    it('shows Gab 72, 1, 71, rank 1, in the order of the card', () => {
        render(<NetLeaderboard entries={entries} />);

        const [gab, manu] = bodyRows();

        expect(gab).toEqual(['Gab', '72', '1', '71', '1']);
        expect(manu).toEqual(['Manu', '88', '17', '71', '1']);
    });

    it('shows « — » three times and « pas encore d’index » for a player without index', () => {
        render(<NetLeaderboard entries={entries} />);

        const [name, gross, ...rest] = bodyRows()[2];

        expect(name).toBe("Sean pas encore d'index");
        expect(gross).toBe('81');
        expect(rest).toEqual(['—', '—', '—']);
    });

    it('marks the net winner in `text-signal`, not the second', () => {
        render(
            <NetLeaderboard
                entries={[
                    { ...entries[0], playingHandicap: 2, net: 70, rank: 2 },
                    { ...entries[1], playingHandicap: 23, net: 65, rank: 1 },
                ]}
            />,
        );

        const [gab, manu] = screen
            .getAllByRole('row')
            .slice(1)
            .map((row) => within(row).getAllByRole('cell')[0]);

        expect(manu).toHaveClass('text-signal');
        expect(gab).not.toHaveClass('text-signal');
    });
});
