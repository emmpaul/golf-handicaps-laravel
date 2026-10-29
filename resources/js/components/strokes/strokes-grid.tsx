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

/** Visible count: empty without stroke, « −1 » with U+2212 for a stroke given back. */
function holeCount(count: number): string {
    if (count === 0) {
        return '';
    }

    return count < 0 ? `\u2212${-count}` : String(count);
}

/**
 * Front nine then back nine, nine holes per row even at 320 px (spec
 * `match-strokes`, design D7). Hole number in condensed type; a hole where the
 * player receives or gives a stroke is in solid ink.
 */
export function StrokesGrid({ perHole }: { perHole: number[] }) {
    return (
        <div
            data-testid="strokes-holes"
            className="grid min-w-0 grid-cols-9 gap-1 tabular-nums"
        >
            {perHole.map((count, i) => {
                const hole = i + 1;

                return (
                    <div
                        key={hole}
                        role="img"
                        aria-label={holeLabel(hole, count)}
                        className={cn(
                            'flex min-w-0 flex-col items-center py-1',
                            count === 0
                                ? 'bg-muted text-muted-foreground'
                                : 'bg-primary text-primary-foreground',
                        )}
                    >
                        <span
                            aria-hidden
                            className="font-heading text-xs font-semibold"
                        >
                            {hole}
                        </span>
                        <span
                            aria-hidden
                            className="font-heading text-lg h-6 leading-6 font-extrabold"
                        >
                            {holeCount(count)}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}
