import { Head, Link } from '@inertiajs/react';
import { edit } from '@/actions/App/Http/Controllers/RoundController';
import RoundPhotoController from '@/actions/App/Http/Controllers/RoundPhotoController';
import { PageTitle } from '@/components/page-title';
import { DeleteRound } from '@/components/rounds/delete-round';
import { RoundGross } from '@/components/rounds/round-gross';
import { RoundScorecard } from '@/components/rounds/round-scorecard';
import type { SavedCard } from '@/components/rounds/round-scorecard';
import { buttonVariants } from '@/components/ui/button';
import { formatPlayedOn } from '@/lib/format';
import { cn } from '@/lib/utils';

export type RoundDetail = {
    id: string;
    courseName: string;
    tees: string;
    playedOn: string;
    par: number;
    hasPhoto: boolean;
    players: { name: string; gross: number }[];
    card: SavedCard;
};

/**
 * Page d'un round (spec `rounds`) : en-tête, bruts, photo s'il en a une, carte
 * de score, puis « Modifier » et « Supprimer ».
 */
export default function RoundsShow({ round }: { round: RoundDetail }) {
    const photo = RoundPhotoController.url(round.id);

    return (
        <>
            <Head title="Round" />
            <div className="flex flex-col gap-2">
                <PageTitle>{round.courseName}</PageTitle>
                {/* Tees, date et par en trois éléments, sans points médians (spec `rounds`, « En-tête d'un round »). */}
                <p
                    data-testid="round-summary"
                    className="flex flex-wrap gap-x-4 gap-y-1 text-muted-foreground"
                >
                    <span>Tees {round.tees}</span>
                    <time dateTime={round.playedOn}>
                        {formatPlayedOn(round.playedOn)}
                    </time>
                    <span>Par {round.par}</span>
                </p>
            </div>
            {/* Une colonne sous 1 024 px : bruts, carte de score, photo, actions. À partir de
                1 024 px, la photo se pose à côté des bruts et la carte prend toute la largeur. */}
            <div
                className={cn(
                    'grid grid-cols-1 gap-6 lg:items-start',
                    round.hasPhoto &&
                        'lg:grid-cols-[minmax(0,42rem)_minmax(0,1fr)]',
                )}
            >
                <section
                    aria-labelledby="gross-title"
                    className="flex max-w-2xl min-w-0 flex-col gap-2"
                >
                    <h2
                        id="gross-title"
                        className="font-heading text-xl font-extrabold"
                    >
                        Bruts
                    </h2>
                    <RoundGross players={round.players} />
                </section>
                {round.hasPhoto ? (
                    <a
                        href={photo}
                        target="_blank"
                        rel="noreferrer"
                        className="block max-lg:order-1"
                    >
                        <img
                            src={photo}
                            alt="Carte du round"
                            className="w-full border object-contain lg:max-h-96"
                        />
                    </a>
                ) : null}
                <div className="min-w-0 lg:col-span-full">
                    <RoundScorecard card={round.card} />
                </div>
                <div className="flex flex-wrap gap-2 border-t pt-4 max-lg:order-2 lg:col-span-full">
                    <Link
                        href={edit(round.id)}
                        className={buttonVariants({ size: 'lg' })}
                    >
                        Modifier
                    </Link>
                    <DeleteRound roundId={round.id} />
                </div>
            </div>
        </>
    );
}
