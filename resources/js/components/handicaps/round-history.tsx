import { Link } from '@inertiajs/react';
import { show as showRound } from '@/actions/App/Http/Controllers/RoundController';
import { PLAYER_BG } from '@/components/player-swatch';
import { formatShortPlayedOn, formatTenths } from '@/lib/format';
import { listLinkPrefetch } from '@/lib/prefetch';
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

/** Columns of the design: date and course, differential, index after (60 px each). */
const HISTORY_COLUMNS = 'grid grid-cols-[minmax(0,1fr)_60px_60px] items-center';

/**
 * The rounds of one player (spec handicap-index, « Liste des rounds d'un
 * joueur », « Marque « Compte » sur la ligne du round »), in the order received
 * (newest first): the date with the « Compte » dot of 6 px in the colour of
 * the player on the same line, course « · » tees under it, cut by « … »,
 * differential and index after in Geist Mono. Each row leads to its round.
 */
export function RoundHistory({
    name,
    history,
    colorSlot,
}: {
    name: string;
    history: HandicapHistoryRow[];
    colorSlot: number;
}) {
    return (
        <div className="mt-[34px] min-w-0">
            <div
                aria-hidden
                className={cn(
                    HISTORY_COLUMNS,
                    'border-b border-rule pb-2.5 font-mono text-caption text-ink-2 uppercase',
                )}
            >
                <span>Date · parcours</span>
                <span className="text-right">Diff.</span>
                <span className="text-right">Après</span>
            </div>
            <ul aria-label={`Rounds de ${name}`}>
                {history.map((round) => (
                    <li
                        key={round.roundId}
                        className="border-b border-rule-soft"
                    >
                        <Link
                            href={showRound(round.roundId)}
                            {...listLinkPrefetch(showRound(round.roundId))}
                            className={cn(
                                HISTORY_COLUMNS,
                                'min-h-13 py-2 font-mono text-link transition-opacity duration-(--dur-press) active:opacity-60',
                            )}
                        >
                            <span className="flex min-w-0 flex-col gap-[5px] font-sans">
                                <span
                                    data-testid="round-date"
                                    className="flex items-center gap-2 text-link whitespace-nowrap"
                                >
                                    <time dateTime={round.playedOn}>
                                        {formatShortPlayedOn(round.playedOn)}
                                    </time>
                                    {round.countsInCurrent ? (
                                        <span
                                            role="img"
                                            aria-label="Compte"
                                            className={cn(
                                                'size-1.5 flex-none rounded-full',
                                                PLAYER_BG[colorSlot],
                                            )}
                                        />
                                    ) : null}
                                </span>
                                <span className="truncate text-body-12 leading-none text-ink-2">
                                    {round.courseName} · {round.tees}
                                </span>
                            </span>
                            <span
                                data-testid="round-differential"
                                className="text-right"
                            >
                                {formatTenths(round.differentialT)}
                            </span>
                            <span
                                data-testid="round-index-after"
                                className="text-right text-ink-2"
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
