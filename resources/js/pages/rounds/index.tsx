import { Head, Link } from '@inertiajs/react';
import { index as coursesIndex } from '@/actions/App/Http/Controllers/CourseController';
import { create, show } from '@/actions/App/Http/Controllers/RoundController';
import { PageTopRow } from '@/components/page-top-row';
import { PlayerSwatch } from '@/components/player-swatch';
import { formatPlayedOn, NO_VALUE, playedOnParts } from '@/lib/format';
import { listLinkPrefetch } from '@/lib/prefetch';
import { TITLE_LINK } from '@/lib/utils';

export type RoundListItem = {
    id: string;
    playedOn: string;
    courseName: string;
    tees: string;
    /** Bruts dans l'ordre de la carte, avec le rang de couleur du joueur (D6). */
    players: { name: string; gross: number; colorSlot: number }[];
    /** Joueurs classés premiers en net, ex æquo compris ; vide si personne n'avait d'index. */
    netWinners: string[];
};

/**
 * Rounds du plus récent au plus ancien (l'ordre vient du serveur, RoundList),
 * avec les bruts dans l'ordre de la carte et le ou les gagnants nets (spec
 * `rounds`, « Liste des rounds », « Mise en page de la page Rounds ») : une
 * ligne par round en trois colonnes, date, parcours et bruts, « 1ER NET ».
 */
export default function RoundsIndex({ rounds }: { rounds: RoundListItem[] }) {
    return (
        <>
            <Head title="Rounds" />
            <PageTopRow
                meta={`${rounds.length} ${rounds.length > 1 ? 'ROUNDS' : 'ROUND'}`}
            />
            <div className="flex items-end justify-between gap-3">
                <h1 className="text-title">Rounds</h1>
                <div className="flex gap-4">
                    <Link href={coursesIndex()} className={TITLE_LINK}>
                        Parcours
                    </Link>
                    <Link href={create()} className={TITLE_LINK}>
                        Saisie manuelle
                    </Link>
                </div>
            </div>
            <div className="mt-3 border-t border-rule" />
            {rounds.length === 0 ? (
                <p className="my-10 text-body text-ink-2">
                    Aucun round. Importez une carte pour commencer.
                </p>
            ) : (
                <ul aria-label="Rounds">
                    {rounds.map((round) => {
                        const { day, month } = playedOnParts(round.playedOn);

                        return (
                            <li
                                key={round.id}
                                className="border-b border-rule-soft"
                            >
                                <Link
                                    href={show(round.id)}
                                    {...listLinkPrefetch(show(round.id))}
                                    className="grid grid-cols-[44px_minmax(0,1fr)_auto] items-center gap-3.5 py-4 transition-opacity duration-(--dur-press) active:opacity-60"
                                >
                                    {/* « 17 » sur « OCT » à l'œil ; la date complète pour les lecteurs d'écran. */}
                                    <time
                                        dateTime={round.playedOn}
                                        className="flex flex-col gap-1 font-mono"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="text-num-l"
                                        >
                                            {day}
                                        </span>
                                        <span
                                            aria-hidden="true"
                                            data-slot="round-month"
                                            className="text-caption text-ink-2"
                                        >
                                            {month}
                                        </span>
                                        <span className="sr-only">
                                            {formatPlayedOn(round.playedOn)}
                                        </span>
                                    </time>
                                    <span className="flex min-w-0 flex-col gap-[7px]">
                                        <span className="text-label leading-[1.2]">
                                            {round.courseName}{' '}
                                            <span className="font-normal text-ink-2">
                                                · {round.tees}
                                            </span>
                                        </span>
                                        <span className="flex flex-wrap gap-x-3 gap-y-1 font-mono text-meta-12 text-ink-2">
                                            {round.players.map(
                                                ({
                                                    name,
                                                    gross,
                                                    colorSlot,
                                                }) => (
                                                    <span
                                                        key={name}
                                                        data-testid="round-gross"
                                                        className="flex items-center gap-[5px]"
                                                    >
                                                        <PlayerSwatch
                                                            colorSlot={
                                                                colorSlot
                                                            }
                                                            size={6}
                                                        />
                                                        {name} {gross}
                                                    </span>
                                                ),
                                            )}
                                        </span>
                                    </span>
                                    <span className="flex flex-col items-end gap-[5px]">
                                        <span className="font-mono text-caption text-ink-2">
                                            1ER NET
                                        </span>
                                        {round.netWinners.length > 0 ? (
                                            <span
                                                data-testid="round-winner"
                                                className="text-label-14 whitespace-nowrap text-accent"
                                            >
                                                {round.netWinners.join(', ')}
                                            </span>
                                        ) : (
                                            <span
                                                data-testid="round-winner"
                                                className="text-label-14 text-ink-2"
                                            >
                                                {NO_VALUE}
                                            </span>
                                        )}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </>
    );
}
