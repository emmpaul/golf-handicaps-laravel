import { Link } from '@inertiajs/react';
import { show as showRound } from '@/actions/App/Http/Controllers/RoundController';
import { Badge } from '@/components/ui/badge';
import { formatShortPlayedOn, formatTenths } from '@/lib/format';
import { cn } from '@/lib/utils';

/** One round of a player (PlayerHandicapHistory), values in tenths. */
export type HandicapHistoryRow = {
    roundId: string;
    playedOn: string;
    courseName: string;
    tees: string;
    differentialT: number;
    indexAfterT: number;
    /** The differential is one of those averaged in the current index. */
    countsInCurrent: boolean;
};

/** Columns: round (date and mark, course and tees), differential, index after; narrow under 768 px so the date and its mark hold on one line at 320 px. */
const HISTORY_COLUMNS =
    'grid grid-cols-[minmax(0,1fr)_2.75rem_3.5rem] items-baseline gap-x-2 px-2 max-md:text-sm md:grid-cols-[minmax(0,1fr)_5rem_6rem] md:gap-x-4';

/**
 * The rounds of one player, in the order received (newest first): date with the
 * « Compte » mark on the same line, course and tees apart, differential (bold
 * when it counts) and index after the round. Each row leads to its round.
 */
export function RoundHistory({
    name,
    history,
}: {
    name: string;
    history: HandicapHistoryRow[];
}) {
    return (
        <div className="min-w-0">
            <div
                aria-hidden
                className={cn(
                    HISTORY_COLUMNS,
                    'bg-band py-1.5 text-sm font-medium text-band-ink',
                )}
            >
                <span>Round</span>
                <span className="text-right">Diff.</span>
                <span className="text-right">Index après</span>
            </div>
            <ul aria-label={`Rounds de ${name}`}>
                {history.map((round) => (
                    <li key={round.roundId} className="border-b">
                        <Link
                            href={showRound(round.roundId)}
                            className={cn(
                                HISTORY_COLUMNS,
                                'py-2 transition-[background-color] duration-(--dur-micro) ease-out hover:bg-muted focus-visible:-outline-offset-2',
                            )}
                        >
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span
                                    data-testid="round-date"
                                    className="flex items-center gap-2 whitespace-nowrap"
                                >
                                    <time dateTime={round.playedOn}>
                                        {formatShortPlayedOn(round.playedOn)}
                                    </time>
                                    {round.countsInCurrent ? (
                                        <Badge>Compte</Badge>
                                    ) : null}
                                </span>
                                <span className="flex flex-wrap gap-x-2 text-sm [overflow-wrap:anywhere] text-muted-foreground">
                                    <span>{round.courseName}</span>
                                    <span>{round.tees}</span>
                                </span>
                            </span>
                            <span
                                data-testid="round-differential"
                                className={cn(
                                    'text-right tabular-nums',
                                    round.countsInCurrent && 'font-semibold',
                                )}
                            >
                                {formatTenths(round.differentialT)}
                            </span>
                            <span
                                data-testid="round-index-after"
                                className="text-right tabular-nums"
                            >
                                {formatTenths(round.indexAfterT)}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
