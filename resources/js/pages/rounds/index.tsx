import { Head, Link } from '@inertiajs/react';
import { create, show } from '@/actions/App/Http/Controllers/RoundController';
import { PageTitle } from '@/components/page-title';
import { formatPlayedOn } from '@/lib/format';
import { ACTION_LINK, cn } from '@/lib/utils';

export type RoundListItem = {
    id: string;
    playedOn: string;
    courseName: string;
    tees: string;
    players: { name: string; gross: number }[];
};

/**
 * Colonnes du registre à partir de 768 px : date, parcours, tees, bruts. Sous
 * 768 px, deux lignes par round (parcours, tees et date ; bruts), sans colonne
 * masquée. Le gagnant net arrive avec handicap-index.
 */
const REGISTER_COLUMNS =
    'md:grid-cols-[9rem_minmax(0,1.3fr)_6rem_minmax(0,1.7fr)] md:items-baseline md:gap-x-4';

/**
 * Rounds du plus récent au plus ancien, avec les bruts dans l'ordre de la
 * carte (spec `rounds`, « Liste des rounds »), en registre. L'ordre vient du
 * serveur (RoundList).
 */
export default function RoundsIndex({ rounds }: { rounds: RoundListItem[] }) {
    return (
        <>
            <Head title="Rounds" />
            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <PageTitle>Rounds</PageTitle>
                <Link href={create()} className={cn(ACTION_LINK, 'text-sm')}>
                    Saisie manuelle
                </Link>
            </div>
            {rounds.length === 0 ? (
                <p className="border-t-2 border-foreground pt-3 text-muted-foreground">
                    Aucun round enregistré.
                </p>
            ) : (
                <div>
                    <div
                        aria-hidden
                        className={cn(
                            'hidden bg-band px-2 py-1.5 text-sm font-medium text-band-ink md:grid',
                            REGISTER_COLUMNS,
                        )}
                    >
                        <span>Date</span>
                        <span>Parcours</span>
                        <span>Tees</span>
                        <span>Bruts</span>
                    </div>
                    <ul
                        aria-label="Rounds"
                        className="max-md:border-t-2 max-md:border-foreground"
                    >
                        {rounds.map((round) => (
                            <li key={round.id} className="border-b">
                                <Link
                                    href={show(round.id)}
                                    className={cn(
                                        'grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-1 px-2 py-2.5 transition-[background-color] duration-(--dur-micro) ease-out hover:bg-muted focus-visible:-outline-offset-2',
                                        REGISTER_COLUMNS,
                                    )}
                                >
                                    <time
                                        dateTime={round.playedOn}
                                        className="col-start-2 row-start-1 text-right text-sm text-muted-foreground tabular-nums md:col-start-1 md:text-left md:font-heading md:text-base md:font-semibold md:text-foreground"
                                    >
                                        {formatPlayedOn(round.playedOn)}
                                    </time>
                                    <span className="col-start-1 row-start-1 flex min-w-0 flex-wrap items-baseline gap-x-2 md:col-span-2 md:col-start-2 md:grid md:grid-cols-subgrid">
                                        <span className="min-w-0 font-heading text-lg leading-tight font-bold [overflow-wrap:anywhere]">
                                            {round.courseName}
                                        </span>
                                        <span className="text-sm text-muted-foreground md:text-base md:text-foreground">
                                            <span className="md:hidden">
                                                Tees{' '}
                                            </span>
                                            <span>{round.tees}</span>
                                        </span>
                                    </span>
                                    <span className="col-span-2 col-start-1 row-start-2 flex flex-wrap gap-x-3 text-sm md:col-span-1 md:col-start-4 md:row-start-1 md:text-base">
                                        {round.players.map(
                                            ({ name, gross }) => (
                                                <span
                                                    key={name}
                                                    data-testid="round-gross"
                                                >
                                                    {name}{' '}
                                                    <span className="font-heading font-bold tabular-nums">
                                                        {gross}
                                                    </span>
                                                </span>
                                            ),
                                        )}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}
