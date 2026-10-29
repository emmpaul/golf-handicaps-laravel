import { RULE_LINES } from '@/components/handicaps/computation-help';
import { PLAYER_TEXT } from '@/components/player-swatch';
import { cn } from '@/lib/utils';
import { RuleLine, StepHeading } from './step-heading';

/** Bordure de chaque couleur de joueur (D6), écrite en entier pour que Tailwind trouve chaque classe. */
const PLAYER_BORDER = [
    'border-player-1',
    'border-player-2',
    'border-player-3',
    'border-player-4',
    'border-player-5',
    'border-player-6',
] as const;

/**
 * Les quatre cases de la ligne de règle 04 (« −2,0 à 3 rounds, −1,0 à 4
 * rounds et à 6 rounds, aucun sinon ») : `rounds` donne le nombre de rounds
 * d'une case, null pour « AUTRES ».
 */
const CELLS = [
    { rounds: 3, label: '3 rounds', value: '−2,0' },
    { rounds: 4, label: '4 rounds', value: '−1,0' },
    { rounds: 6, label: '6 rounds', value: '−1,0' },
    { rounds: null, label: 'Autres', value: '0' },
] as const;

/**
 * Étape 04, ajustement (spec « Étape 04, ajustement », design D6 et D8) : les
 * quatre cases, celle qui s'applique au joueur bordée et libellée dans la
 * couleur du joueur, avec `aria-current="true"` et « appliqué » pour les
 * lecteurs d'écran. Le serveur dit si un ajustement s'applique
 * (`adjustmentT`) ; aucun donne « AUTRES », index provisoire compris. Sans
 * round, aucune case n'est appliquée.
 */
export function AdjustmentStep({
    colorSlot,
    rounds,
    adjustmentT,
}: {
    colorSlot: number | null;
    /** Les rounds du calcul (20 au plus) ; 0 sans round. */
    rounds: number;
    adjustmentT: number;
}) {
    const applied =
        rounds === 0 || colorSlot === null
            ? undefined
            : adjustmentT === 0
              ? null
              : rounds;

    return (
        <section data-testid="step-04">
            <StepHeading number="04" colorSlot={colorSlot} className="mt-9">
                Ajustement
            </StepHeading>
            <RuleLine>{RULE_LINES[3]}</RuleLine>
            <ul className="mt-4 grid grid-cols-4 gap-1.5">
                {CELLS.map((cell) => {
                    const slot =
                        applied !== undefined && cell.rounds === applied
                            ? colorSlot
                            : null;

                    return (
                        <li
                            key={cell.label}
                            aria-current={slot !== null ? 'true' : undefined}
                            className={cn(
                                'flex min-w-0 flex-col gap-2 rounded-12 border px-2.5 py-3 transition-colors duration-(--dur-swap)',
                                slot !== null
                                    ? cn('bg-surface-1', PLAYER_BORDER[slot])
                                    : 'border-rule bg-transparent',
                            )}
                        >
                            <span
                                data-testid="adjustment-label"
                                className={cn(
                                    'font-mono text-caption tracking-[0.06em] uppercase',
                                    slot !== null
                                        ? PLAYER_TEXT[slot]
                                        : 'text-ink-2',
                                )}
                            >
                                {cell.label}
                            </span>
                            <span
                                data-testid="adjustment-value"
                                className={cn(
                                    'font-mono text-num-20',
                                    slot !== null ? 'text-ink' : 'text-ink-2',
                                )}
                            >
                                {cell.value}
                            </span>
                            {slot !== null ? (
                                <span className="sr-only">appliqué</span>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
