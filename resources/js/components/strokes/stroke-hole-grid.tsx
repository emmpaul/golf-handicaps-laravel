import { PLAYER_TEXT } from '@/components/player-swatch';
import { cn } from '@/lib/utils';

const plural = (count: number) => (Math.abs(count) > 1 ? 'coups' : 'coup');

/** Name of a hole for screen readers: « Trou 3 : aucun coup », « Trou 15 : 2 coups », « Trou 3 : rend 1 coup ». */
function holeLabel(hole: number, count: number): string {
    if (count === 0) {
        return `Trou ${hole} : aucun coup`;
    }

    return count < 0
        ? `Trou ${hole} : rend ${-count} ${plural(count)}`
        : `Trou ${hole} : ${count} ${plural(count)}`;
}

/** Visible count: « · » without stroke, « −1 » with U+2212 for a stroke given back. */
function holeCount(count: number): string {
    if (count === 0) {
        return '·';
    }

    return count < 0 ? `−${-count}` : String(count);
}

/**
 * The 18 holes of a player, front nine then back nine, nine cells per row
 * even at 320 px (spec `match-strokes`, « Grille des coups par trou », « Bloc
 * de résultat des coups »): cells of 44 px, the hole number in Geist Mono
 * 9 px grey, the strokes in Geist Mono 600 15 px in the colour of the player
 * on `surface-3`; a hole without stroke shows « · » in `ink-4` on a
 * transparent background, the cell named « Trou 3 : aucun coup ».
 */
export function StrokeHoleGrid({
    perHole,
    colorSlot,
}: {
    perHole: number[];
    colorSlot: number;
}) {
    return (
        <div
            data-testid="strokes-holes"
            className="mt-3.5 grid min-w-0 grid-cols-9 gap-1"
        >
            {perHole.map((count, i) => {
                const hole = i + 1;

                return (
                    <div
                        key={hole}
                        role="img"
                        aria-label={holeLabel(hole, count)}
                        className={cn(
                            'flex h-11 min-w-0 flex-col items-center justify-center gap-[3px] rounded-6 transition-[background-color] duration-(--dur-swap)',
                            count === 0 ? 'bg-transparent' : 'bg-surface-3',
                        )}
                    >
                        <span
                            aria-hidden="true"
                            className="font-mono text-caption-9 text-ink-2"
                        >
                            {hole}
                        </span>
                        <span
                            aria-hidden="true"
                            className={cn(
                                'font-mono text-num-m-15 font-semibold',
                                count === 0
                                    ? 'text-ink-4'
                                    : PLAYER_TEXT[colorSlot],
                            )}
                        >
                            {holeCount(count)}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}
