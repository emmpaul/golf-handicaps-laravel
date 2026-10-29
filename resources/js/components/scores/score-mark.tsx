import { cn } from '@/lib/utils';

type Mark = 'eagle' | 'birdie' | 'bogey' | 'double';

/** Annonce de chaque marque, après le chiffre (« 7, double bogey ou plus »). */
const SPOKEN: Record<Mark, string> = {
    eagle: 'eagle',
    birdie: 'birdie',
    bogey: 'bogey',
    double: 'double bogey ou plus',
};

function markOf(strokes: number, par: number): Mark | undefined {
    const diff = strokes - par;

    if (diff <= -2) {
        return 'eagle';
    }

    if (diff >= 2) {
        return 'double';
    }

    return diff === -1 ? 'birdie' : diff === 1 ? 'bogey' : undefined;
}

/**
 * Marque d'un score par trou (spec visual-theme « Marques de score ») : eagle
 * ou mieux en double cercle (24 px dans 32 px) et birdie en cercle de 30 px,
 * chiffre en `signal` ; bogey en carré de 28 px et double bogey ou plus en
 * double carré (24 px dans 32 px, graisse 600), traits `over` ; par sans
 * marque. Traits de 1,5 px. Le nom de la marque est lu, pas vu.
 */
export function ScoreMark({ strokes, par }: { strokes: number; par: number }) {
    const mark = markOf(strokes, par);
    const double = mark === 'eagle' || mark === 'double';
    const round = mark === 'eagle' || mark === 'birdie';
    const ring = cn(
        'inline-flex items-center justify-center',
        mark && 'border-[1.5px]',
        round && 'rounded-full border-signal',
        (mark === 'bogey' || mark === 'double') && 'border-over',
    );
    const spoken = mark ? (
        <span className="sr-only">, {SPOKEN[mark]}</span>
    ) : null;

    if (double) {
        return (
            <span data-mark={mark} className={cn(ring, 'size-8')}>
                <span
                    data-slot="score-mark-inner"
                    className={cn(
                        ring,
                        'size-6',
                        mark === 'eagle' ? 'text-signal' : 'font-semibold',
                    )}
                >
                    {strokes}
                    {spoken}
                </span>
            </span>
        );
    }

    return (
        <span
            data-mark={mark}
            className={cn(
                ring,
                mark === 'bogey' ? 'size-7' : 'size-[30px]',
                mark === 'birdie' && 'text-signal',
            )}
        >
            {strokes}
            {spoken}
        </span>
    );
}

/** Dessin réduit de chaque marque dans la légende. */
const LEGEND: { label: string; outer: string; inner?: string }[] = [
    {
        label: 'Eagle',
        outer: 'size-3.5 rounded-full border-signal',
        inner: 'size-2 rounded-full border-signal',
    },
    { label: 'Birdie', outer: 'size-3 rounded-full border-signal' },
    { label: 'Bogey', outer: 'size-3 border-over' },
    {
        label: 'Double +',
        outer: 'size-3.5 border-over',
        inner: 'size-2 border-over',
    },
];

/**
 * Légende des marques, sous la carte d'un round : Geist Mono 500, 11 px,
 * capitales, gris ; les dessins sont cachés aux lecteurs d'écran, qui lisent
 * déjà le nom de chaque marque sur les scores.
 */
export function ScoreLegend() {
    return (
        <ul
            aria-label="Légende"
            className="flex flex-wrap gap-4 font-mono text-section tracking-normal text-ink-2 uppercase"
        >
            {LEGEND.map(({ label, outer, inner }) => (
                <li key={label} className="flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className={cn(
                            'flex items-center justify-center border-[1.5px]',
                            outer,
                        )}
                    >
                        {inner && (
                            <span className={cn('border-[1.5px]', inner)} />
                        )}
                    </span>
                    {label}
                </li>
            ))}
        </ul>
    );
}
