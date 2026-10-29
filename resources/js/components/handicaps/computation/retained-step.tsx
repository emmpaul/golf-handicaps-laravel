import { RULE_LINES } from '@/components/handicaps/computation-help';
import { PLAYER_BG, PLAYER_TEXT } from '@/components/player-swatch';
import { formatTenths, playedOnParts } from '@/lib/format';
import { cn } from '@/lib/utils';
import { shownRating, shownSlope } from './differential-step';
import { RuleLine, StepHeading } from './step-heading';
import type { ComputationRound } from './types';

/**
 * Différentiels retenus pour 1 à 20 rounds (design D2) : la ligne de règle 03
 * (« 1 de 3 à 5 rounds, 2 de 6 à 8, … 8 à 20 ») et, sous 3 rounds, la ligne
 * de l'index provisoire (tous). Écrits en littéraux : ils ne dépendent pas du
 * joueur, et le test de la page les compare à la ligne de règle.
 */
export const RETAINED_BY_ROUNDS = [
    1, 2, 1, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 4, 5, 5, 6, 6, 7, 8,
] as const;

/** Le plus grand nombre retenu (20 rounds), la plus haute marche à 82 % de la hauteur (design). */
const MOST_RETAINED = 8;
const TALLEST_PERCENT = 82;

/** Nombres de rounds écrits sous les marches, en plus de celui du joueur. */
const TICKS = [1, 5, 10, 15, 20];

/** Les marches provisoires (1 et 2 rounds) sont d'un ton plus sombres que les autres. */
const PROVISIONAL_BELOW = 3;

const CAPTION = 'font-mono text-caption tracking-[0.06em] text-ink-2 uppercase';

/**
 * Étape 03, différentiels retenus (spec « Étape 03 », « Liste des 20 derniers
 * rounds de l'étape 03 », design D6 et D8) : 20 marches, une par nombre de
 * rounds, celle du joueur dans la couleur du joueur et libellée avec le
 * nombre retenu, « {n} → {k} RETENU(S) », puis les 20 derniers rounds du
 * joueur du plus récent au plus ancien, les retenus à l'encre avec la
 * pastille « Retenu », les autres en `ink-2`. Sans round, les marches seules,
 * aucune en évidence.
 */
export function RetainedStep({
    colorSlot,
    rounds,
}: {
    colorSlot: number | null;
    /** Les 20 derniers rounds au plus, du plus récent au plus ancien. */
    rounds: ComputationRound[];
}) {
    const count = rounds.length;
    const retained = count > 0 ? RETAINED_BY_ROUNDS[count - 1] : 0;

    return (
        <section data-testid="step-03">
            <StepHeading number="03" colorSlot={colorSlot} className="mt-9">
                Différentiels retenus
            </StepHeading>
            <RuleLine>{RULE_LINES[2]}</RuleLine>
            <Stairs count={count} colorSlot={colorSlot} />
            {count > 0 ? (
                <>
                    <div className={cn('mt-1.5 flex justify-between', CAPTION)}>
                        <span>Rounds joués</span>
                        <span data-testid="retained-summary">
                            {count} → {retained}{' '}
                            {retained > 1 ? 'retenus' : 'retenu'}
                        </span>
                    </div>
                    <RoundList rounds={rounds} colorSlot={colorSlot} />
                </>
            ) : null}
        </section>
    );
}

/** Les 20 marches, une image de la règle : le texte « {n} → {k} » dit la même chose. */
function Stairs({
    count,
    colorSlot,
}: {
    count: number;
    colorSlot: number | null;
}) {
    return (
        <div
            role="img"
            aria-label="Différentiels retenus selon le nombre de rounds, de 1 à 20"
            className="mt-5"
        >
            <div className="flex h-28 items-end gap-0.75 border-b border-rule">
                {RETAINED_BY_ROUNDS.map((retained, position) => {
                    const rounds = position + 1;
                    // La couleur du joueur sur la marche du joueur, aucune sans joueur.
                    const slot = rounds === count ? colorSlot : null;
                    const current = slot !== null;

                    return (
                        <div
                            key={rounds}
                            data-testid="stair"
                            data-rounds={rounds}
                            data-current={current ? 'true' : undefined}
                            className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-[5px]"
                        >
                            <span
                                data-part="retained"
                                className={cn(
                                    'font-mono text-section font-semibold tracking-normal transition-opacity duration-(--dur-swap)',
                                    slot !== null
                                        ? PLAYER_TEXT[slot]
                                        : 'opacity-0',
                                )}
                            >
                                {retained}
                            </span>
                            <span
                                data-part="bar"
                                style={{
                                    height: `${(retained / MOST_RETAINED) * TALLEST_PERCENT}%`,
                                }}
                                className={cn(
                                    'w-full rounded-t-3 transition-colors duration-(--dur-nav) ease-(--ease-design)',
                                    slot !== null
                                        ? PLAYER_BG[slot]
                                        : rounds < PROVISIONAL_BELOW
                                          ? 'bg-rule-soft'
                                          : 'bg-rule',
                                )}
                            />
                        </div>
                    );
                })}
            </div>
            <div className="mt-2 flex gap-0.75 text-center font-mono text-caption-9 text-ink-2">
                {RETAINED_BY_ROUNDS.map((_, position) => {
                    const rounds = position + 1;
                    const current = rounds === count;

                    return (
                        <span
                            key={rounds}
                            className={cn(
                                'min-w-0 flex-1',
                                current && 'text-ink',
                            )}
                        >
                            {TICKS.includes(rounds) || current ? rounds : ''}
                        </span>
                    );
                })}
            </div>
        </div>
    );
}

/** Une ligne de la liste : jour et mois, parcours, « {ajusté} ajusté · {rating} / {slope} », différentiel. */
function RoundList({
    rounds,
    colorSlot,
}: {
    rounds: ComputationRound[];
    colorSlot: number | null;
}) {
    return (
        <div className="mt-[22px]">
            <div
                className={cn(
                    'flex justify-between border-b border-rule pb-2.5 tracking-[0.08em]',
                    CAPTION,
                )}
            >
                <h3 id="computation-rounds" data-testid="rounds-label">
                    20 derniers rounds · {rounds.length}
                </h3>
                <span aria-hidden>Diff.</span>
            </div>
            <ul aria-labelledby="computation-rounds">
                {rounds.map((round) => {
                    const { day, month } = playedOnParts(round.playedOn);
                    // La pastille du joueur sur un round retenu, aucune sinon.
                    const slot = round.countsInCurrent ? colorSlot : null;

                    return (
                        <li
                            key={round.roundId}
                            className={cn(
                                'flex min-h-14 items-center gap-3.5 border-b border-rule-soft transition-colors duration-(--dur-swap)',
                                slot !== null ? 'text-ink' : 'text-ink-2',
                            )}
                        >
                            <span className="flex w-10 flex-none flex-col gap-1">
                                <span
                                    data-testid="row-day"
                                    className="font-mono text-num-m-17"
                                >
                                    {day}
                                </span>
                                <span
                                    data-testid="row-month"
                                    className="font-mono text-caption-9 tracking-[0.08em] text-ink-2"
                                >
                                    {month}
                                </span>
                            </span>
                            <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                                <span
                                    data-testid="row-course"
                                    className="text-label-14 leading-[1.15] break-words uppercase"
                                >
                                    {round.courseName}
                                </span>
                                <span
                                    data-testid="row-detail"
                                    className="font-mono text-section tracking-normal text-ink-2"
                                >
                                    {round.adjusted} ajusté ·{' '}
                                    {round.ratingT === null ? 'par ' : ''}
                                    {shownRating(round)} / {shownSlope(round)}
                                </span>
                            </span>
                            <span className="flex flex-none items-center gap-2">
                                {slot !== null ? (
                                    <span
                                        role="img"
                                        aria-label="Retenu"
                                        className={cn(
                                            'size-2 flex-none rounded-2',
                                            PLAYER_BG[slot],
                                        )}
                                    />
                                ) : (
                                    <span
                                        aria-hidden
                                        className="size-2 flex-none"
                                    />
                                )}
                                <span
                                    data-testid="row-differential"
                                    className="font-mono text-num-20"
                                >
                                    {formatTenths(round.differentialT)}
                                </span>
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
