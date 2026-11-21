import { PlayerSwatch } from '@/components/player-swatch';
import { winnerSummary } from '@/lib/virtual-match';
import type { RankedNet } from '@/lib/virtual-match';

/**
 * Encadré du gagnant d'une partie virtuelle (spec `virtual-match`, « Encadré
 * du gagnant », « Mise en page de la page Partie virtuelle ») : fond
 * `surface-1`, coins de 14 px, « AURAIT GAGNÉ » en Geist Mono 12 px gris, la
 * pastille de 10 px et le nom du premier en Geist Mono 48 px, puis l'écart en
 * Geist Mono 15 px gris. Des premiers ex æquo donnent « ÉGALITÉ » et leurs
 * noms, sans pastille. Rien avec moins de deux joueurs classés.
 */
export function VirtualMatchWinner({
    leaderboard,
}: {
    leaderboard: (RankedNet & { colorSlot: number })[];
}) {
    const summary = winnerSummary(leaderboard);

    if (summary === null) {
        return null;
    }

    const winner =
        summary.heading === 'ÉGALITÉ'
            ? undefined
            : leaderboard.find((line) => line.rank === 1);

    return (
        <section
            aria-labelledby="virtual-match-winner"
            className="flex flex-col gap-3 rounded-14 bg-surface-1 p-4"
        >
            <h2
                id="virtual-match-winner"
                className="font-mono text-meta-12 text-ink-2"
            >
                {summary.heading}
            </h2>
            <p className="flex min-w-0 items-center gap-3">
                {winner === undefined ? null : (
                    <PlayerSwatch colorSlot={winner.colorSlot} size={10} />
                )}
                <span className="min-w-0 font-mono text-display-l break-words">
                    {summary.names}
                </span>
            </p>
            <p className="font-mono text-num-m-15 text-ink-2">
                {summary.detail}
            </p>
        </section>
    );
}
