import { Head } from '@inertiajs/react';
import { ComputationHelp } from '@/components/handicaps/computation-help';
import { DifferentialBars } from '@/components/handicaps/differential-bars';
import { PlayerTabs } from '@/components/handicaps/player-tabs';
import {
    type HandicapHistoryRow,
    RoundHistory,
} from '@/components/handicaps/round-history';
import { PageTopRow } from '@/components/page-top-row';
import { PLAYER_TEXT } from '@/components/player-swatch';
import { Badge } from '@/components/ui/badge';
import { formatTenths, NO_VALUE } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PlayerStanding } from '@/pages/home';

/** The index averages the differentials of the 20 latest rounds at most (spec handicap-index). */
const WINDOW = 20;

/** Below 3 rounds, the index is provisional. */
const PROVISIONAL_BELOW = 3;

/** Adjustment of the index by number of rounds retained, in tenths (spec handicap-index). */
const ADJUSTMENT_T: Record<number, number> = { 3: 20, 4: 10, 6: 10 };

/** « ▼ 0,4 », « ▲ 0,4 », « = stable », or nothing before the second round. */
function trend(deltaT: number | null): string | null {
    if (deltaT === null) {
        return null;
    }

    if (deltaT === 0) {
        return '= stable';
    }

    return `${deltaT < 0 ? '▼' : '▲'} ${formatTenths(Math.abs(deltaT))}`;
}

/**
 * The summary under the index (spec « Résumé de l'index d'un joueur »):
 * « 3 rounds · meilleur différentiel sur 3 − 2,0. », the rule read from the
 * rounds that count in the current index; never a playing handicap (E34).
 */
function summary(history: HandicapHistoryRow[]): string {
    const rounds = history.length;
    const retained = Math.min(rounds, WINDOW);
    const counted = history.filter((round) => round.countsInCurrent).length;
    let rule: string;

    if (rounds < PROVISIONAL_BELOW) {
        rule =
            counted > 1
                ? `moyenne des ${counted} différentiels`
                : 'moyenne de 1 différentiel';
    } else {
        rule =
            counted > 1
                ? `${counted} meilleurs différentiels sur ${retained}`
                : `meilleur différentiel sur ${retained}`;

        if (ADJUSTMENT_T[retained] !== undefined) {
            rule += ` − ${formatTenths(ADJUSTMENT_T[retained])}`;
        }
    }

    return `${rounds} ${rounds > 1 ? 'rounds' : 'round'} · ${rule}.`;
}

/**
 * Handicaps page, one player at a time (spec `handicap-index`, « Mise en page
 * de la page Handicaps »): the player tabs, the chosen player's index, trend
 * and summary, the chart of the differentials, the list of the rounds, then
 * the folded explanation of the computation.
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
            <PageTopRow />
            <h1 className="text-title">Handicaps</h1>
            {selected === undefined ? (
                <p className="mt-5 text-body text-ink-2">Aucun joueur.</p>
            ) : (
                <>
                    <PlayerTabs
                        players={players}
                        selectedPlayerId={selected.id}
                    />
                    <PlayerSection player={selected} history={history} />
                </>
            )}
            <div className="mt-7">
                <ComputationHelp />
            </div>
        </>
    );
}

/** The chosen player: index set very large, trend and summary, chart, then rounds. */
function PlayerSection({
    player,
    history,
}: {
    player: PlayerStanding;
    history: HandicapHistoryRow[];
}) {
    const playerTrend = trend(player.deltaT);

    if (player.indexT === null) {
        return (
            <section aria-label={player.name} className="mt-10">
                <p
                    data-testid="player-index"
                    className="font-mono text-display-xl text-ink-2"
                >
                    {NO_VALUE}
                </p>
                <p className="mt-3.5 text-body text-ink-2">
                    Pas encore d'index. Un premier round donne un index
                    provisoire.
                </p>
            </section>
        );
    }

    return (
        <section aria-label={player.name} className="mt-7">
            <div className="flex items-center gap-2 font-mono text-section text-ink-2">
                <span className="uppercase">Index actuel</span>
                {player.provisional ? (
                    <Badge
                        variant="accent"
                        className="h-auto py-1 text-section tracking-[0.08em]"
                    >
                        PROVISOIRE
                    </Badge>
                ) : null}
            </div>
            <div className="mt-2 flex items-baseline gap-3">
                <span
                    data-testid="player-index"
                    className="inline-block font-mono text-display-xl"
                >
                    {formatTenths(player.indexT)}
                </span>
                {playerTrend !== null ? (
                    <span
                        data-testid="index-trend"
                        className={cn(
                            'font-mono text-meta',
                            PLAYER_TEXT[player.colorSlot],
                        )}
                    >
                        {playerTrend}
                    </span>
                ) : null}
            </div>
            <p
                data-testid="index-summary"
                className="mt-1.5 text-body-13 text-ink-2"
            >
                {summary(history)}
            </p>
            {history.length > 0 ? (
                <>
                    <DifferentialBars
                        history={history}
                        colorSlot={player.colorSlot}
                    />
                    <RoundHistory
                        name={player.name}
                        history={history}
                        colorSlot={player.colorSlot}
                    />
                </>
            ) : null}
        </section>
    );
}
