import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { NetLeaderboard } from '@/components/rounds/net-leaderboard';
import type { LeaderboardEntry } from '@/components/rounds/net-leaderboard';
import { GAB, MANU, SEAN } from '@/tests/reference-round';

// Spec rounds « Classement net d'un round », « Classement lisible sur
// téléphone » and « Mise en page de la page d'un round ». The server sends the
// entries in the order of the card with their competition rank
// (RoundLeaderboard); the leaderboard ranks them.
// Mutations:
// - the entries kept in the order of the card (no sort) → the rating test
//   reads Gab first and the ranks test reads the first line of the card first;
// - ties broken by name instead of the order of the card → the ranks test
//   reads « Bea » before « Dan » at rank 2;
// - a player without index ranked first (null rank sorted as 0) → the no-index
//   test reads Sean first;
// - the « 1ER » pill given to rank 2 → the rating test goes red;
// - the gross line written with an ASCII « - » → the rating test goes red;
// - the swatch of a player at the slot + 1 → the swatch test reads
//   `bg-player-2` for Gab.

const entry = (
    playerId: string,
    name: string,
    colorSlot: number,
    values: Pick<
        LeaderboardEntry,
        'gross' | 'playingHandicap' | 'net' | 'rank'
    >,
): LeaderboardEntry => ({
    playerId,
    name,
    colorSlot,
    indexBeforeT: values.playingHandicap === null ? null : 10,
    ...values,
});

/** The parts of each line, in the order shown. */
function lines(): string[][] {
    return within(screen.getByRole('list', { name: 'Classement net' }))
        .getAllByRole('listitem')
        .map((line) =>
            ['rank', 'name', 'badge', 'gross', 'net'].map(
                (part) =>
                    line.querySelector(`[data-slot="leaderboard-${part}"]`)
                        ?.textContent ?? '',
            ),
        );
}

describe('NetLeaderboard', () => {
    it('ranks Manu « 1 », « 1ER », « 88 − 23 », « 65 » before Gab « 2 », « 72 − 2 », « 70 »', () => {
        render(
            <NetLeaderboard
                entries={[
                    entry(GAB, 'Gab', 0, {
                        gross: 72,
                        playingHandicap: 2,
                        net: 70,
                        rank: 2,
                    }),
                    entry(MANU, 'Manu', 1, {
                        gross: 88,
                        playingHandicap: 23,
                        net: 65,
                        rank: 1,
                    }),
                ]}
            />,
        );

        expect(lines()).toEqual([
            ['1', 'Manu', '1ER', '88 − 23', '65'],
            ['2', 'Gab', '', '72 − 2', '70'],
        ]);
    });

    it('skips the shared places after a tie, ties in the order of the card', () => {
        const players = [
            ['Ana', 80, 4],
            ['Dan', 70, 2],
            ['Cyd', 59, 1],
            ['Bea', 70, 2],
        ] as const;

        render(
            <NetLeaderboard
                entries={players.map(([name, net, rank], i) =>
                    entry(`${GAB.slice(0, -1)}${i + 5}`, name, i, {
                        gross: net + 10,
                        playingHandicap: 10,
                        net,
                        rank,
                    }),
                )}
            />,
        );

        expect(
            lines().map(([rank, name, badge]) => [rank, name, badge]),
        ).toEqual([
            ['1', 'Cyd', '1ER'],
            ['2', 'Dan', ''],
            ['2', 'Bea', ''],
            ['4', 'Ana', ''],
        ]);
    });

    it('gives the « 1ER » pill to Gab then Manu, tied at rank 1', () => {
        render(
            <NetLeaderboard
                entries={[
                    entry(GAB, 'Gab', 0, {
                        gross: 72,
                        playingHandicap: 1,
                        net: 71,
                        rank: 1,
                    }),
                    entry(MANU, 'Manu', 1, {
                        gross: 88,
                        playingHandicap: 17,
                        net: 71,
                        rank: 1,
                    }),
                ]}
            />,
        );

        expect(
            lines().map(([rank, name, badge]) => [rank, name, badge]),
        ).toEqual([
            ['1', 'Gab', '1ER'],
            ['1', 'Manu', '1ER'],
        ]);
    });

    it('puts a player without index last, without rank, « 95 · pas encore d’index » and « — »', () => {
        render(
            <NetLeaderboard
                entries={[
                    entry(SEAN, 'Sean', 2, {
                        gross: 95,
                        playingHandicap: null,
                        net: null,
                        rank: null,
                    }),
                    entry(GAB, 'Gab', 0, {
                        gross: 72,
                        playingHandicap: 1,
                        net: 71,
                        rank: 1,
                    }),
                ]}
            />,
        );

        expect(lines()).toEqual([
            ['1', 'Gab', '1ER', '72 − 1', '71'],
            ['', 'Sean', '', "95 · pas encore d'index", '—'],
        ]);
    });

    it('paints the 10 px swatch of each player in its colour', () => {
        render(
            <NetLeaderboard
                entries={[
                    entry(GAB, 'Gab', 0, {
                        gross: 72,
                        playingHandicap: 1,
                        net: 71,
                        rank: 1,
                    }),
                ]}
            />,
        );

        const swatch = screen
            .getByRole('listitem')
            .querySelector('[aria-hidden="true"]');

        expect(swatch).toHaveClass('bg-player-1', 'size-2.5');
    });
});
