import type { ReactNode } from 'react';
import { PLAYER_TEXT } from '@/components/player-swatch';
import { cn } from '@/lib/utils';

/**
 * Libellé d'une étape du calcul de l'index (spec « Libellé d'étape », design
 * D6) : Geist Mono 500, 11 px, capitales, gris, 10 px au-dessus d'un filet
 * `rule` ; le numéro (« 02 ») prend la couleur du joueur, `ink-2` sans joueur.
 * Sans numéro, le libellé des cas particuliers.
 */
export function StepHeading({
    number,
    colorSlot,
    className,
    children,
}: {
    number?: string;
    colorSlot: number | null;
    className?: string;
    children: ReactNode;
}) {
    return (
        <h2
            className={cn(
                'flex gap-3 border-b border-rule pb-2.5 font-mono text-section text-ink-2 uppercase',
                className,
            )}
        >
            {number !== undefined ? (
                <span
                    className={cn(
                        'transition-colors duration-(--dur-swap) ease-(--ease-design)',
                        colorSlot === null
                            ? 'text-ink-2'
                            : PLAYER_TEXT[colorSlot],
                    )}
                >
                    {number}
                </span>
            ) : null}
            {children}
        </h2>
    );
}

/** Une ligne de règle sous le libellé d'une étape : Geist 400, 15 px / 1,45, `ink-soft` (D7). */
export function RuleLine({ children }: { children: ReactNode }) {
    return (
        <p
            data-testid="rule-line"
            className="mt-3.5 text-prose text-pretty text-ink-soft"
        >
            {children}
        </p>
    );
}
