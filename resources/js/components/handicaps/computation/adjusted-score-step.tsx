import { RULE_LINES } from '@/components/handicaps/computation-help';
import { RuleLine, StepHeading } from './step-heading';
import type { CappedHole, ComputationRound } from './types';

/** « MAX » d'un trou : le plus qu'un trou compte au-dessus de son par (ligne de règle 01, « par + 4 »). */
export const MAX_OVER_PAR = 4;

const CAPTION = 'font-mono text-caption text-ink-2 uppercase';

/**
 * Étape 01, score ajusté (spec « Étape 01, score ajusté », design D6) : le
 * dernier trou plafonné du joueur sur ses 20 derniers rounds (« TROU 1 · P4 »,
 * coups joués barrés, « MAX » par + 4), ou la phrase qui dit qu'il n'y en a
 * pas ; puis « DERNIER ROUND », ajusté sur brut. Sans round, la ligne de règle
 * seule.
 */
export function AdjustedScoreStep({
    colorSlot,
    cappedHole,
    latest,
}: {
    colorSlot: number | null;
    cappedHole: CappedHole | null;
    /** Le dernier round du joueur ; undefined sans round. */
    latest: ComputationRound | undefined;
}) {
    return (
        <section data-testid="step-01">
            <StepHeading
                number="01"
                colorSlot={colorSlot}
                className="mt-[30px]"
            >
                Score ajusté
            </StepHeading>
            <RuleLine>{RULE_LINES[0]}</RuleLine>
            {latest !== undefined ? (
                <div className="mt-4 grid grid-cols-3 border-y border-rule-soft">
                    {cappedHole !== null ? (
                        <>
                            <div className="flex flex-col gap-2 py-3.5">
                                <span
                                    data-testid="capped-hole"
                                    className={CAPTION}
                                >
                                    Trou {cappedHole.hole} · P{cappedHole.par}
                                </span>
                                <s
                                    data-testid="capped-strokes"
                                    className="font-mono text-kpi text-ink-2 decoration-2"
                                >
                                    {cappedHole.strokes}
                                </s>
                            </div>
                            <div className="flex flex-col gap-2 py-3.5">
                                <span className={CAPTION}>Max</span>
                                <span
                                    data-testid="capped-max"
                                    className="font-mono text-kpi"
                                >
                                    {cappedHole.par + MAX_OVER_PAR}
                                </span>
                            </div>
                        </>
                    ) : (
                        <p className="col-span-2 py-3.5 pr-3 text-body-13 text-ink-2">
                            Aucun trou plafonné sur les 20 derniers rounds.
                        </p>
                    )}
                    <div className="flex flex-col items-end gap-2 py-3.5">
                        <span className={CAPTION}>Dernier round</span>
                        <span
                            data-testid="latest-round"
                            className="font-mono text-kpi whitespace-nowrap"
                        >
                            {latest.adjusted}
                            <span className="text-meta tracking-normal text-ink-2">
                                {' '}
                                / {latest.gross}
                            </span>
                        </span>
                    </div>
                </div>
            ) : null}
        </section>
    );
}
