import { Head } from '@inertiajs/react';
import {
    computation as computationPage,
    index as handicapsIndex,
} from '@/actions/App/Http/Controllers/HandicapController';
import { AdjustedScoreStep } from '@/components/handicaps/computation/adjusted-score-step';
import { AdjustmentStep } from '@/components/handicaps/computation/adjustment-step';
import { DifferentialStep } from '@/components/handicaps/computation/differential-step';
import { IndexStep } from '@/components/handicaps/computation/index-step';
import { RetainedStep } from '@/components/handicaps/computation/retained-step';
import { SpecialCases } from '@/components/handicaps/computation/special-cases';
import type { IndexComputation } from '@/components/handicaps/computation/types';
import { PlayerTabs } from '@/components/handicaps/player-tabs';
import { PageTopRow } from '@/components/page-top-row';
import type { PlayerStanding } from '@/pages/home';

/**
 * Page « Calcul de l'index » (spec `handicap-index`, design D1 à D8
 * d'index-computation-page) : le lien de retour vers la page Handicaps sur le
 * même joueur, le titre, les onglets de joueur, puis les cinq étapes et les
 * cas particuliers, chacun sous sa ligne de règle et montré avec les rounds
 * du joueur choisi. Chaque valeur vient du serveur (PlayerIndexComputation,
 * PlayerStandings) : la page ne recalcule aucune règle de l'index.
 */
export default function HandicapsComputation({
    players,
    selectedPlayerId,
    computation,
}: {
    players: PlayerStanding[];
    selectedPlayerId: string | null;
    /** Null seulement quand il n'y a aucun joueur. */
    computation: IndexComputation | null;
}) {
    const selected = players.find((player) => player.id === selectedPlayerId);
    const colorSlot = selected?.colorSlot ?? null;
    const latest = computation?.rounds[0];

    return (
        <>
            <Head title="Comment l’index est calculé" />
            <PageTopRow
                back={{
                    href: handicapsIndex.url(
                        selected === undefined
                            ? undefined
                            : { query: { joueur: selected.id } },
                    ),
                    label: 'Handicaps',
                }}
            />
            <h1 className="text-title text-balance">
                Comment l’index est calculé
            </h1>
            {selected === undefined ? (
                <p className="mt-5 text-body text-ink-2">Aucun joueur.</p>
            ) : (
                <>
                    <PlayerTabs
                        players={players}
                        selectedPlayerId={selected.id}
                        hrefFor={(playerId) =>
                            computationPage({ query: { joueur: playerId } })
                        }
                        only={['selectedPlayerId', 'computation']}
                    />
                    <p
                        data-testid="computation-intro"
                        className="mt-2.5 text-body-13 text-ink-2"
                    >
                        Chaque étape avec les rounds de{' '}
                        <span className="text-ink">{selected.name}</span>.
                    </p>
                </>
            )}
            <AdjustedScoreStep
                colorSlot={colorSlot}
                cappedHole={computation?.cappedHole ?? null}
                latest={latest}
            />
            <DifferentialStep colorSlot={colorSlot} latest={latest} />
            <RetainedStep
                colorSlot={colorSlot}
                rounds={computation?.rounds ?? []}
            />
            <AdjustmentStep
                colorSlot={colorSlot}
                rounds={computation?.rounds.length ?? 0}
                adjustmentT={computation?.adjustmentT ?? 0}
            />
            <IndexStep player={selected} computation={computation} />
            <SpecialCases rounds={computation?.rounds ?? []} />
        </>
    );
}
