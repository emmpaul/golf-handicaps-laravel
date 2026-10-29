import { RULE_LINES } from '@/components/handicaps/computation-help';
import { PlayerSwatch } from '@/components/player-swatch';
import { formatTenths } from '@/lib/format';
import type { PlayerStanding } from '@/pages/home';
import { RuleLine, StepHeading } from './step-heading';
import type { IndexComputation } from './types';

const ROW =
    'flex min-h-12 items-center justify-between gap-3 border-b border-rule-soft';

/**
 * « Meilleur différentiel » pour un seul retenu, « Moyenne des 2 meilleurs
 * (12,0 · 14,0) », ou « Moyenne des 2 différentiels (12,0 · 17,0) » pour un
 * index provisoire : les différentiels retenus, le plus petit d'abord.
 */
function meanLabel(computation: IndexComputation, provisional: boolean) {
    const retained = computation.rounds
        .filter((round) => round.countsInCurrent)
        .map((round) => round.differentialT)
        .sort((a, b) => a - b);

    if (retained.length === 1) {
        return 'Meilleur différentiel';
    }

    const listed = retained.map(formatTenths).join(' · ');

    return provisional
        ? `Moyenne des ${retained.length} différentiels (${listed})`
        : `Moyenne des ${retained.length} meilleurs (${listed})`;
}

/**
 * Étape 05, index (spec « Étape 05, index », design D6) : la moyenne des
 * différentiels retenus et l'ajustement, tous deux envoyés par le serveur
 * (`meanT`, `adjustmentT`), puis « INDEX {NOM} » et l'index courant de
 * PlayerStandings à 88 px ; la page ne recalcule ni l'un ni l'autre. Sans
 * round, « Pas encore d'index. Un premier round donne un index provisoire. ».
 */
export function IndexStep({
    player,
    computation,
}: {
    player: PlayerStanding | undefined;
    computation: IndexComputation | null;
}) {
    const shown =
        player !== undefined &&
        player.indexT !== null &&
        computation !== null &&
        computation.meanT !== null;

    return (
        <section data-testid="step-05">
            <StepHeading
                number="05"
                colorSlot={player?.colorSlot ?? null}
                className="mt-9"
            >
                Index
            </StepHeading>
            <RuleLine>{RULE_LINES[4]}</RuleLine>
            {shown ? (
                <>
                    <div className="mt-4 border-t border-rule-soft">
                        <div className={ROW}>
                            <span
                                data-testid="mean-label"
                                className="text-body text-ink-2"
                            >
                                {meanLabel(computation, player.provisional)}
                            </span>
                            <span
                                data-testid="mean-value"
                                className="font-mono text-num-m-17"
                            >
                                {formatTenths(computation.meanT ?? 0)}
                            </span>
                        </div>
                        <div className={ROW}>
                            <span
                                data-testid="adjustment-label"
                                className="text-body text-ink-2"
                            >
                                Ajustement
                            </span>
                            <span
                                data-testid="adjustment-applied"
                                className="font-mono text-num-m-17"
                            >
                                {computation.adjustmentT === 0
                                    ? 'aucun'
                                    : formatTenths(computation.adjustmentT)}
                            </span>
                        </div>
                    </div>
                    <div className="mt-[18px] flex items-end justify-between gap-3">
                        <span
                            data-testid="index-label"
                            className="flex items-center gap-2 pb-3 font-mono text-section text-ink-2 uppercase"
                        >
                            <PlayerSwatch colorSlot={player.colorSlot} />
                            Index {player.name}
                        </span>
                        <span
                            data-testid="index-value"
                            className="font-mono text-display-xl"
                        >
                            {formatTenths(player.indexT ?? 0)}
                        </span>
                    </div>
                </>
            ) : player !== undefined ? (
                <p className="mt-4 text-body text-ink-2">
                    Pas encore d'index. Un premier round donne un index
                    provisoire.
                </p>
            ) : null}
        </section>
    );
}
