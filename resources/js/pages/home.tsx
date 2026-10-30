import { Head, Link, usePage } from '@inertiajs/react';
import { index as handicapsIndex } from '@/actions/App/Http/Controllers/HandicapController';
import { index as importIndex } from '@/actions/App/Http/Controllers/ImportController';
import { show as showRound } from '@/actions/App/Http/Controllers/RoundController';
import { LogoutButton } from '@/components/logout-button';
import { PageTopRow } from '@/components/page-top-row';
import { PlayerSwatch } from '@/components/player-swatch';
import type { LeaderboardEntry } from '@/components/rounds/net-leaderboard';
import { ranked } from '@/components/rounds/net-leaderboard';
import { SectionLabel } from '@/components/section-label';
import {
    formatDayMonthShort,
    formatTenths,
    NO_INDEX,
    NO_VALUE,
} from '@/lib/format';
import { listLinkPrefetch } from '@/lib/prefetch';

/** A player's current index and trend (PlayerStandings::ranked), in tenths. */
export type PlayerStanding = {
    id: string;
    name: string;
    /** Index after the latest round played; `null` without round. */
    indexT: number | null;
    provisional: boolean;
    /** Index after minus index before the latest round; `null` without round or at the first one. */
    deltaT: number | null;
    /** Rounds played (« 8 ROUNDS »). */
    rounds: number;
    /** Colour slot of the player, 0 to 5 (D6). */
    colorSlot: number;
};

/** The latest round played and its net leaderboard (LatestRound). */
export type LatestRound = {
    id: string;
    playedOn: string;
    courseName: string;
    tees: string;
    /** In the order of the card, as RoundLeaderboard sends it. */
    leaderboard: LeaderboardEntry[];
};

/** « ▼ 1,5 », « ▲ 0,3 », « = STABLE », or nothing before the second round. */
function trend(deltaT: number | null): string | null {
    if (deltaT === null) {
        return null;
    }

    if (deltaT === 0) {
        return '= STABLE';
    }

    return `${deltaT < 0 ? '▼' : '▲'} ${formatTenths(Math.abs(deltaT))}`;
}

/** The state line of a player: trend and « PROVISOIRE » or « 8 ROUNDS », joined by « · ». */
function stateLine(player: PlayerStanding): string {
    if (player.indexT === null) {
        return "PAS ENCORE D'INDEX";
    }

    return [
        trend(player.deltaT),
        player.provisional ? 'PROVISOIRE' : `${player.rounds} ROUNDS`,
    ]
        .filter((part) => part !== null)
        .join(' · ');
}

/**
 * The result of the latest round (spec « Dernier round »): the winner and his
 * gap to the second, the winners tied, the only player ranked, or nothing
 * when nobody is ranked.
 */
function resultOf(lines: LeaderboardEntry[]): string | null {
    const ranks = ranked(lines).filter((line) => line.rank !== null);
    const winners = ranks.filter((line) => line.rank === 1);

    if (winners.length === 0) {
        return null;
    }

    if (winners.length > 1) {
        return `Égalité : ${winners.map((line) => line.name).join(', ')}`;
    }

    if (ranks.length < 2) {
        return `${winners[0].name} seul en jeu`;
    }

    const gap = (ranks[1].net ?? 0) - (winners[0].net ?? 0);

    return `${winners[0].name} gagne de ${gap} coup${gap > 1 ? 's' : ''}`;
}

/** The row of the imports to check (spec « Imports à vérifier »), from the shared count (D7). */
function PendingImportsRow() {
    const { pendingImports } = usePage().props;

    if (typeof pendingImports !== 'number' || pendingImports === 0) {
        return null;
    }

    return (
        <Link
            href={importIndex()}
            className="-mt-2.5 mb-[22px] flex min-h-11 items-center gap-2.5 text-link text-accent"
        >
            <span className="rounded-pill bg-accent/15 px-2 py-[5px] font-mono text-section font-semibold tracking-normal">
                {pendingImports}
            </span>
            {pendingImports > 1 ? 'imports à vérifier' : 'import à vérifier'}
            <span aria-hidden="true" className="ml-auto">
                →
            </span>
        </Link>
    );
}

/**
 * The « INDEX » board (spec « Index des joueurs »): the players in the order
 * of the server, numbered while they have an index, « – » without.
 */
function IndexBoard({ players }: { players: PlayerStanding[] }) {
    return (
        <section aria-labelledby="index-title">
            <SectionLabel id="index-title">Index</SectionLabel>
            <ul aria-labelledby="index-title">
                {players.map((player, position) => (
                    <li key={player.id} className="border-b border-rule">
                        <Link
                            href={handicapsIndex({
                                query: { joueur: player.id },
                            })}
                            className="flex min-h-19 items-center gap-3 transition-opacity duration-(--dur-press) active:opacity-60"
                        >
                            <span
                                data-part="rank"
                                className="w-4 flex-none font-mono text-meta text-ink-2"
                            >
                                {player.indexT === null ? '–' : position + 1}
                            </span>
                            <span className="flex min-w-0 flex-col gap-[7px]">
                                <span className="flex min-w-0 items-center gap-2">
                                    <PlayerSwatch
                                        colorSlot={player.colorSlot}
                                    />
                                    <span
                                        data-part="name"
                                        className="truncate text-name-m-17"
                                    >
                                        {player.name}
                                    </span>
                                </span>
                                <span
                                    data-part="state"
                                    className="font-mono text-section tracking-normal whitespace-nowrap text-ink-2"
                                >
                                    {stateLine(player)}
                                </span>
                            </span>
                            <span
                                data-part="index"
                                className="ml-auto flex-none font-mono text-display"
                            >
                                {player.indexT === null
                                    ? NO_VALUE
                                    : formatTenths(player.indexT)}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * The latest round (spec « Dernier round »): date, course « · » tees, the
 * players by net as on the page of the round, then « Net · » and the result;
 * the block leads to the round.
 */
function LatestRoundBlock({ round }: { round: LatestRound }) {
    const result = resultOf(round.leaderboard);

    return (
        <section aria-labelledby="latest-title" className="mt-7">
            <Link
                href={showRound(round.id)}
                {...listLinkPrefetch(showRound(round.id))}
                className="block transition-opacity duration-(--dur-press) active:opacity-60"
            >
                <span className="mb-2.5 flex justify-between font-mono text-section text-ink-2">
                    <span id="latest-title" className="uppercase">
                        Dernier round
                    </span>
                    <time dateTime={round.playedOn}>
                        {formatDayMonthShort(round.playedOn)}
                    </time>
                </span>
                <span className="block text-name-l-course [overflow-wrap:anywhere]">
                    <span className="text-name-l-course">
                        {round.courseName}
                    </span>{' '}
                    <span className="font-normal text-ink-2">
                        · {round.tees}
                    </span>
                </span>
                <ul className="mt-3.5 border-t border-rule">
                    {ranked(round.leaderboard).map((line) => (
                        <li
                            key={line.playerId}
                            className="flex min-h-13 items-center gap-3.5 border-b border-rule-soft"
                        >
                            <span
                                data-part="rank"
                                className="w-4 flex-none font-mono text-meta text-ink-2"
                            >
                                {line.rank ?? ''}
                            </span>
                            <span className="flex min-w-0 items-center gap-2">
                                <PlayerSwatch colorSlot={line.colorSlot} />
                                <span
                                    data-part="name"
                                    className="truncate text-label"
                                >
                                    {line.name}
                                </span>
                            </span>
                            <span
                                data-part="gross"
                                className="ml-auto text-right font-mono text-meta-12 text-ink-2"
                            >
                                {line.playingHandicap === null
                                    ? `${line.gross} · ${NO_INDEX}`
                                    : `${line.gross} − ${line.playingHandicap}`}
                            </span>
                            <span
                                data-part="net"
                                className="w-12 flex-none text-right font-mono text-num-m tracking-[-0.03em]"
                            >
                                {line.net ?? NO_VALUE}
                            </span>
                        </li>
                    ))}
                </ul>
                {result !== null ? (
                    <p
                        data-testid="latest-result"
                        className="mt-3 text-body-13 leading-[1.4] text-ink-2"
                    >
                        Net · <span className="text-ink">{result}</span>
                    </p>
                ) : null}
            </Link>
        </section>
    );
}

/**
 * Home: the group at a glance (spec `home-dashboard`, « Mise en page de
 * l'accueil »). « Golf » and « Se déconnecter », the imports to check, the
 * « INDEX » board, then the latest round. The import goes through the round
 * button of the bar (spec app-shell, « Bouton Importer »).
 */
export default function Home({
    players,
    latestRound,
}: {
    players: PlayerStanding[];
    latestRound: LatestRound | null;
}) {
    return (
        <>
            <Head title="Accueil" />
            <PageTopRow actions={<LogoutButton />} />
            <PendingImportsRow />
            <IndexBoard players={players} />
            {latestRound ? <LatestRoundBlock round={latestRound} /> : null}
        </>
    );
}
