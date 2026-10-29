import { Head } from '@inertiajs/react';
import { ComputationHelp } from '@/components/handicaps/computation-help';
import { PlayerPicker } from '@/components/handicaps/player-picker';
import {
    type HandicapHistoryRow,
    RoundHistory,
} from '@/components/handicaps/round-history';
import { PageTitle } from '@/components/page-title';
import { formatSignedTenths, formatTenths, NO_INDEX } from '@/lib/format';
import type { PlayerStanding } from '@/pages/home';

/**
 * Handicaps page, one player at a time at every width (spec `handicap-index`,
 * design D4): the picker, the chosen player's summary read from `players`, the
 * list of their rounds, then the folded explanation of the computation.
 */
export default function HandicapsIndex({
    players,
    selectedPlayerId,
    history,
}: {
    players: PlayerStanding[];
    selectedPlayerId: string | null;
    history: HandicapHistoryRow[];
}) {
    const selected = players.find((player) => player.id === selectedPlayerId);

    return (
        <>
            <Head title="Handicaps" />
            <PageTitle>Handicaps</PageTitle>
            {selected === undefined ? (
                <p className="border-t-2 border-foreground pt-3 text-muted-foreground">
                    Aucun joueur.
                </p>
            ) : (
                <>
                    <PlayerPicker
                        players={players}
                        selectedPlayerId={selected.id}
                    />
                    <PlayerSection player={selected} history={history} />
                </>
            )}
            <ComputationHelp />
        </>
    );
}

/** The chosen player: name, delta and mention, index set large, then their rounds. */
function PlayerSection({
    player,
    history,
}: {
    player: PlayerStanding;
    history: HandicapHistoryRow[];
}) {
    const titleId = `player-${player.id}`;

    return (
        <section
            aria-labelledby={titleId}
            className="flex min-w-0 flex-col gap-4 border-t-2 border-foreground pt-3"
        >
            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-x-4">
                <div className="flex min-w-0 flex-col gap-1">
                    <h2
                        id={titleId}
                        className="font-heading text-3xl leading-tight font-bold [overflow-wrap:anywhere]"
                    >
                        {player.name}
                    </h2>
                    <span className="flex flex-wrap gap-x-3 text-sm text-muted-foreground">
                        {player.deltaT !== null ? (
                            <span data-testid="index-delta">
                                Écart{' '}
                                <span className="tabular-nums">
                                    {formatSignedTenths(player.deltaT)}
                                </span>
                            </span>
                        ) : null}
                        {player.provisional ? <span>provisoire</span> : null}
                    </span>
                </div>
                <span
                    data-testid="player-index"
                    className="text-right font-heading text-[4.5rem] leading-[0.85] font-extrabold tracking-(--tracking-display) tabular-nums"
                >
                    {player.indexT === null ? (
                        <span className="font-sans text-sm font-normal tracking-normal text-muted-foreground">
                            {NO_INDEX}
                        </span>
                    ) : (
                        formatTenths(player.indexT)
                    )}
                </span>
            </div>
            {history.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Aucun round pour l’instant.
                </p>
            ) : (
                <RoundHistory name={player.name} history={history} />
            )}
        </section>
    );
}
