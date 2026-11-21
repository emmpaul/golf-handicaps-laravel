import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { VirtualMatchSuggestion } from '@/components/rounds/virtual-match-suggestion';
import { GAB, MANU, ROUND, SEAN } from '@/tests/reference-round';

// Spec virtual-match « Suggestion sur la page d'un round », « Suggestion sans
// écriture ni rejet » and « Lien de comparaison sans suggestion » (design D6):
// the server sends the suggested players and the picks of the link; the
// component chooses the text.
// Mutations:
// - the singular « a joué » kept for two players → the two players test
//   reads « Gab (18 nov.) et Sean (20 nov.) a joué ce parcours. »;
// - the date of the round written with the year → the one player test reads
//   « Gab (18 nov. 2026) »;
// - the picks dropped from the link → the link tests read an address without
//   `picks`;
// - the box kept without suggestion → the no suggestion test finds
//   « Voir la partie virtuelle ».

const GAB_ROUND = '0190a6b2-7c3e-7000-8000-0000000000b1';
const SEAN_ROUND = '0190a6b2-7c3e-7000-8000-0000000000b3';

const PICKS = `${ROUND}.${MANU},${GAB_ROUND}.${GAB}`;

const gab = { name: 'Gab', colorSlot: 0, playedOn: '2026-11-18' };
const sean = { name: 'Sean', colorSlot: 2, playedOn: '2026-11-20' };

describe('VirtualMatchSuggestion', () => {
    it('says « Gab (18 nov.) a joué ce parcours. » and links to the virtual match', () => {
        render(
            <VirtualMatchSuggestion
                courseName="BETHPAGE BLACK"
                tees="Red"
                suggested={[gab]}
                picks={PICKS}
            />,
        );

        expect(
            screen.getByText('Gab (18 nov.) a joué ce parcours.'),
        ).toBeInTheDocument();
        expect(
            screen
                .getByRole('link', { name: 'Voir la partie virtuelle' })
                .getAttribute('href'),
        ).toBe(
            `/rounds/compare?course=BETHPAGE+BLACK&tees=Red&picks=${ROUND}.${MANU}%2C${GAB_ROUND}.${GAB}`,
        );
        expect(
            screen.queryByRole('button', { name: /Ignorer/ }),
        ).not.toBeInTheDocument();
        expect(screen.queryByText(/Ignorer/)).not.toBeInTheDocument();
    });

    it('says « Gab (18 nov.) et Sean (20 nov.) ont joué ce parcours. » in one box', () => {
        render(
            <VirtualMatchSuggestion
                courseName="BETHPAGE BLACK"
                tees="Red"
                suggested={[gab, sean]}
                picks={`${PICKS},${SEAN_ROUND}.${SEAN}`}
            />,
        );

        expect(
            screen.getByText(
                'Gab (18 nov.) et Sean (20 nov.) ont joué ce parcours.',
            ),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('link')).toHaveLength(1);
    });

    it('links « Comparer avec d’autres rounds » with the picks of the card without suggestion', () => {
        render(
            <VirtualMatchSuggestion
                courseName="BETHPAGE BLACK"
                tees="Red"
                suggested={[]}
                picks={`${ROUND}.${GAB},${ROUND}.${MANU}`}
            />,
        );

        expect(
            screen
                .getByRole('link', { name: "Comparer avec d'autres rounds" })
                .getAttribute('href'),
        ).toBe(
            `/rounds/compare?course=BETHPAGE+BLACK&tees=Red&picks=${ROUND}.${GAB}%2C${ROUND}.${MANU}`,
        );
        expect(
            screen.queryByRole('link', { name: 'Voir la partie virtuelle' }),
        ).not.toBeInTheDocument();
        expect(screen.queryByText(/ce parcours/)).not.toBeInTheDocument();
    });
});
