import type { ReactNode } from 'react';
import { RULE_LINES } from '@/components/handicaps/computation-help';
import { PLAYER_TEXT } from '@/components/player-swatch';
import { formatTenths, playedOnParts } from '@/lib/format';
import { cn } from '@/lib/utils';
import { RuleLine, StepHeading } from './step-heading';
import type { ComputationRound } from './types';

/**
 * Le slope de la formule (ligne de règle 02, « 113 / slope »), aussi celui
 * que prend un parcours sans rating (ligne de règle 07, « le slope 113 »).
 */
export const NEUTRAL_SLOPE = 113;

/** La rating qu'a utilisée le calcul : la rating confirmée (« 71,4 »), sinon le par (« 71 »). */
export function shownRating(round: ComputationRound): string {
    return round.ratingT === null
        ? String(round.parTotal)
        : formatTenths(round.ratingT);
}

/** Le slope qu'a utilisé le calcul : le slope confirmé, sinon 113. */
export function shownSlope(round: ComputationRound): number {
    return round.slope ?? NEUTRAL_SLOPE;
}

/** Un nombre de la formule, sa légende dessous (« SLOPE »), ou une ligne vide de même hauteur. */
function Term({ value, label }: { value: ReactNode; label?: string }) {
    return (
        <span className="flex flex-col gap-1.5">
            <span data-testid="formula-value">{value}</span>
            {label !== undefined ? (
                <span
                    data-testid="formula-label"
                    className="text-caption-9 tracking-[0.06em] text-ink-2 uppercase"
                >
                    {label}
                </span>
            ) : (
                <span aria-hidden className="h-2.25" />
            )}
        </span>
    );
}

/** Un opérateur de la formule, en gris. */
function Operator({ children }: { children: ReactNode }) {
    return <span className="text-ink-2">{children}</span>;
}

/**
 * Étape 02, différentiel (spec « Étape 02, différentiel », design D6) : la
 * formule écrite avec le dernier round du joueur, la rating et le slope
 * qu'a utilisés le calcul (par du round et 113 sans rating confirmée), puis
 * « {PARCOURS} · {jour} {MOIS} » et son différentiel en Geist Mono 36 px à la
 * couleur du joueur. Sans round, la ligne de règle seule.
 */
export function DifferentialStep({
    colorSlot,
    latest,
}: {
    colorSlot: number | null;
    latest: ComputationRound | undefined;
}) {
    return (
        <section data-testid="step-02">
            <StepHeading number="02" colorSlot={colorSlot} className="mt-9">
                Différentiel
            </StepHeading>
            <RuleLine>{RULE_LINES[1]}</RuleLine>
            {latest !== undefined ? (
                <div className="mt-4 flex flex-col gap-3.5 rounded-14 bg-surface-1 px-4 py-[18px]">
                    <div
                        data-testid="formula"
                        className="flex flex-wrap items-baseline gap-x-2 gap-y-1.5 font-mono text-num-m-17 tracking-[-0.02em]"
                    >
                        <Term value={NEUTRAL_SLOPE} />
                        <Operator>/</Operator>
                        <Term value={shownSlope(latest)} label="Slope" />
                        <Operator>× (</Operator>
                        <Term value={latest.adjusted} label="Ajusté" />
                        <Operator>−</Operator>
                        <Term value={shownRating(latest)} label="Rating" />
                        <Operator>)</Operator>
                    </div>
                    <div className="flex items-baseline justify-between gap-3 border-t border-rule pt-3.5">
                        <span
                            data-testid="latest-course"
                            className="font-mono text-section leading-[1.3] tracking-[0.04em] text-ink-2 uppercase"
                        >
                            {latest.courseName} ·{' '}
                            {playedOnParts(latest.playedOn).day}{' '}
                            {playedOnParts(latest.playedOn).month}
                        </span>
                        <span
                            data-testid="latest-differential"
                            className={cn(
                                'font-mono text-num-36',
                                colorSlot === null
                                    ? undefined
                                    : PLAYER_TEXT[colorSlot],
                            )}
                        >
                            {formatTenths(latest.differentialT)}
                        </span>
                    </div>
                </div>
            ) : null}
        </section>
    );
}
