import { Head, Link } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { index as handicapsIndex } from '@/actions/App/Http/Controllers/HandicapController';
import {
    index as importIndex,
    show as showImport,
} from '@/actions/App/Http/Controllers/ImportController';
import { show as showRound } from '@/actions/App/Http/Controllers/RoundController';
import { LogoutButton } from '@/components/logout-button';
import { PageTitle } from '@/components/page-title';
import { buttonVariants } from '@/components/ui/button';
import {
    formatPlayedOn,
    formatSignedTenths,
    formatTenths,
    NO_INDEX,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { RoundListItem } from '@/pages/rounds/index';

/** A player's current index and delta (PlayerStandings), in tenths. */
export type PlayerStanding = {
    id: string;
    name: string;
    /** Index after the latest round played; `null` without round. */
    indexT: number | null;
    provisional: boolean;
    /** Index after minus index before the latest round; `null` without round or at the first one. */
    deltaT: number | null;
};

export type PendingImports = {
    count: number;
    latestId: string | null;
};

/** Columns of the players table: name, index set large, delta with the round before. */
const PLAYER_COLUMNS =
    'grid grid-cols-[minmax(0,1fr)_auto_3.5rem] items-baseline gap-x-3 px-2';

/**
 * Home: the group's board (spec `home-dashboard`). The import action first, then
 * the players in ruled rows, index very large, then the latest round as a ruled
 * block, and the imports to check.
 */
export default function Home({
    players,
    latestRound,
    pendingImports,
}: {
    players: PlayerStanding[];
    latestRound: RoundListItem | null;
    pendingImports: PendingImports;
}) {
    return (
        <>
            <Head title="Accueil" />
            <PageTitle>Handicaps golf</PageTitle>
            <Link
                href={importIndex()}
                className={cn(
                    buttonVariants({ size: 'lg' }),
                    'w-full sm:w-fit',
                )}
            >
                <Camera aria-hidden data-icon="inline-start" /> Importer une
                carte
            </Link>
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-10">
                <section
                    aria-labelledby="indexes-title"
                    className="flex min-w-0 flex-col gap-2"
                >
                    <h2
                        id="indexes-title"
                        className="font-heading text-xl font-extrabold"
                    >
                        Index des joueurs
                    </h2>
                    <div>
                        <div
                            aria-hidden
                            className={cn(
                                PLAYER_COLUMNS,
                                'bg-band text-sm text-band-ink py-1.5 font-medium',
                            )}
                        >
                            <span>Joueur</span>
                            <span className="text-right">Index</span>
                            <span className="text-right">Écart</span>
                        </div>
                        <ul aria-labelledby="indexes-title">
                            {players.map((player) => (
                                <li key={player.id} className="border-b">
                                    <Link
                                        href={handicapsIndex({
                                            query: { joueur: player.id },
                                        })}
                                        className={cn(
                                            PLAYER_COLUMNS,
                                            'py-2 transition-[background-color] duration-(--dur-micro) ease-out hover:bg-muted focus-visible:-outline-offset-2',
                                        )}
                                    >
                                        {/* Name and index on one baseline: the name clips with
                                            `overflow: clip`, which keeps its baseline; « provisoire »
                                            on its own line below. */}
                                        <span className="flex min-w-0 flex-col">
                                            <span className="font-heading text-2xl overflow-x-clip font-semibold text-ellipsis whitespace-nowrap">
                                                {player.name}
                                            </span>
                                            {player.provisional ? (
                                                <span className="text-sm text-muted-foreground">
                                                    provisoire
                                                </span>
                                            ) : null}
                                        </span>
                                        {player.indexT === null ? (
                                            <span className="text-sm text-right text-muted-foreground">
                                                {NO_INDEX}
                                            </span>
                                        ) : (
                                            <span className="font-heading text-right text-[3.5rem] leading-none font-extrabold tracking-(--tracking-display) tabular-nums">
                                                {formatTenths(player.indexT)}
                                            </span>
                                        )}
                                        <span
                                            data-testid="player-delta"
                                            className="text-right text-muted-foreground tabular-nums"
                                        >
                                            {player.deltaT !== null
                                                ? formatSignedTenths(
                                                      player.deltaT,
                                                  )
                                                : null}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>
                <section
                    aria-labelledby="latest-title"
                    className="flex min-w-0 flex-col gap-2"
                >
                    <h2
                        id="latest-title"
                        className="font-heading text-xl font-extrabold"
                    >
                        Dernier round
                    </h2>
                    {latestRound ? (
                        <LatestRound round={latestRound} />
                    ) : (
                        <p className="border-t-2 border-foreground pt-3 text-muted-foreground">
                            Aucun round enregistré
                        </p>
                    )}
                </section>
            </div>
            {pendingImports.count > 0 && pendingImports.latestId !== null ? (
                <Link
                    href={showImport(pendingImports.latestId)}
                    className={cn(
                        buttonVariants({ variant: 'outline', size: 'lg' }),
                        'w-full sm:w-fit',
                    )}
                >
                    {pendingImports.count}{' '}
                    {pendingImports.count === 1 ? 'import' : 'imports'} à
                    vérifier
                </Link>
            ) : null}
            <LogoutButton />
        </>
    );
}

/** The latest round as one ruled block leading to its page. */
function LatestRound({ round }: { round: RoundListItem }) {
    return (
        <Link
            href={showRound(round.id)}
            className="flex flex-col gap-3 border-t-2 border-foreground px-2 pt-3 pb-3 transition-[background-color] duration-(--dur-micro) ease-out hover:bg-muted focus-visible:-outline-offset-2"
        >
            <span className="flex flex-col gap-1">
                <span className="font-heading text-2xl leading-tight font-extrabold [overflow-wrap:anywhere]">
                    {round.courseName}
                </span>
                <span className="text-sm flex flex-wrap gap-x-4 text-muted-foreground">
                    <span>Tees {round.tees}</span>
                    <time dateTime={round.playedOn}>
                        {formatPlayedOn(round.playedOn)}
                    </time>
                </span>
            </span>
            <ul className="flex flex-wrap gap-x-6 gap-y-1">
                {round.players.map((player) => (
                    <li
                        key={player.name}
                        className="flex items-baseline gap-1.5"
                    >
                        <span>{player.name}</span>{' '}
                        <span className="font-heading text-2xl font-extrabold tabular-nums">
                            {player.gross}
                        </span>
                    </li>
                ))}
            </ul>
            {round.netWinners.length > 0 ? (
                <p className="font-medium">
                    Gagnant net :{' '}
                    <span className="text-signal">
                        {round.netWinners.join(', ')}
                    </span>
                </p>
            ) : null}
        </Link>
    );
}
