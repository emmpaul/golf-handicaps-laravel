import { RULE_LINES } from '@/components/handicaps/computation-help';
import { Badge } from '@/components/ui/badge';
import { NEUTRAL_SLOPE } from './differential-step';
import { StepHeading } from './step-heading';
import type { ComputationRound } from './types';

const LINE = 'text-body text-pretty text-ink-soft';

/**
 * « CAS PARTICULIERS » (spec « Cas particuliers de la page « Calcul de
 * l'index » », design D7 et D8) : la pilule ambre « PROVISOIRE » et la règle
 * de l'index provisoire, puis la pilule neutre « SANS RATING » et la règle de
 * repli, avec « Ex. {parcours} → rating {par} · slope 113 » tiré du plus
 * récent des 20 rounds sans rating confirmée, s'il y en a un.
 */
export function SpecialCases({ rounds }: { rounds: ComputationRound[] }) {
    const unrated = rounds.find((round) => round.ratingT === null);

    return (
        <section data-testid="special-cases">
            <StepHeading colorSlot={null} className="mt-10">
                Cas particuliers
            </StepHeading>
            <div className="flex flex-col gap-2.5 border-b border-rule-soft py-4">
                <Badge
                    variant="accent"
                    className="h-auto py-1 tracking-[0.08em]"
                >
                    PROVISOIRE
                </Badge>
                <p data-testid="rule-line" className={LINE}>
                    {RULE_LINES[5]}
                </p>
            </div>
            <div className="flex flex-col gap-2.5 py-4">
                <Badge className="h-auto py-1 tracking-[0.08em]">
                    SANS RATING
                </Badge>
                <p data-testid="rule-line" className={LINE}>
                    {RULE_LINES[6]}
                </p>
                {unrated !== undefined ? (
                    <p
                        data-testid="special-example"
                        className="font-mono text-section leading-[1.4] tracking-normal text-ink-2"
                    >
                        Ex. {unrated.courseName} → rating {unrated.parTotal} ·
                        slope {NEUTRAL_SLOPE}
                    </p>
                ) : null}
            </div>
        </section>
    );
}
