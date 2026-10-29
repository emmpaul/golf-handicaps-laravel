import { PlayerSwatch } from '@/components/player-swatch';
import { NO_INDEX, NO_VALUE } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Une ligne du classement, telle que RoundLeaderboard l'envoie (design D5). */
export type LeaderboardEntry = {
    playerId: string;
    name: string;
    /** Rang de couleur du joueur (D6). */
    colorSlot: number;
    gross: number;
    indexBeforeT: number | null;
    playingHandicap: number | null;
    net: number | null;
    rank: number | null;
};

/**
 * Lignes par rang net croissant ; à rang égal, l'ordre de la carte (tri
 * stable) ; un joueur sans index, sans rang, après les joueurs classés.
 */
function ranked(entries: LeaderboardEntry[]): LeaderboardEntry[] {
    return [...entries].sort(
        (a, b) =>
            (a.rank ?? Number.POSITIVE_INFINITY) -
            (b.rank ?? Number.POSITIVE_INFINITY),
    );
}

/**
 * Classement net d'un round (spec `rounds`, « Classement net d'un round »,
 * « Mise en page de la page d'un round ») : sous « CLASSEMENT NET » et
 * « BRUT − HCP », une ligne par joueur, rang en Geist Mono 18 px gris, nom en
 * 22 px après sa pastille de 10 px, pastille ambre « 1ER » pour chaque
 * premier, « 72 − 1 » dessous, net en 44 px à droite. Un nom trop long est
 * coupé par « … » ; rang, brut et net ne sont jamais coupés (« Classement
 * lisible sur téléphone »).
 */
export function NetLeaderboard({ entries }: { entries: LeaderboardEntry[] }) {
    return (
        <section aria-labelledby="leaderboard-title">
            <div className="flex justify-between border-b border-rule pb-2.5 font-mono text-caption text-ink-2 uppercase">
                <h2 id="leaderboard-title" className="font-mono">
                    Classement net
                </h2>
                <span>Brut − hcp</span>
            </div>
            <ol aria-label="Classement net">
                {ranked(entries).map((entry) => (
                    <li
                        key={entry.playerId}
                        className="flex items-center gap-4 border-b border-rule-soft py-[18px]"
                    >
                        <span
                            data-slot="leaderboard-rank"
                            className="w-[22px] flex-none font-mono text-num-m-18 text-ink-2"
                        >
                            {entry.rank ?? ''}
                        </span>
                        <span className="flex min-w-0 flex-col gap-2.5">
                            <span className="flex min-w-0 items-center gap-2.5 text-name-l">
                                <PlayerSwatch
                                    colorSlot={entry.colorSlot}
                                    size={10}
                                />
                                <span
                                    data-slot="leaderboard-name"
                                    className="truncate"
                                >
                                    {entry.name}
                                </span>
                                {entry.rank === 1 ? (
                                    <span
                                        data-slot="leaderboard-badge"
                                        className="flex-none rounded-pill bg-accent/15 px-[7px] py-1 font-mono text-caption tracking-[0.06em] text-accent"
                                    >
                                        1ER
                                    </span>
                                ) : null}
                            </span>
                            {/* « 95 · pas encore d'index » passe à la ligne à 320 px plutôt que d'élargir la page. */}
                            <span
                                data-slot="leaderboard-gross"
                                className={cn(
                                    'font-mono text-num-m-15 text-ink-2',
                                    entry.playingHandicap !== null &&
                                        'whitespace-nowrap',
                                )}
                            >
                                {entry.playingHandicap === null
                                    ? `${entry.gross} · ${NO_INDEX}`
                                    : `${entry.gross} − ${entry.playingHandicap}`}
                            </span>
                        </span>
                        <span
                            data-slot="leaderboard-net"
                            className="ml-auto flex-none font-mono text-display"
                        >
                            {entry.net ?? NO_VALUE}
                        </span>
                    </li>
                ))}
            </ol>
        </section>
    );
}
