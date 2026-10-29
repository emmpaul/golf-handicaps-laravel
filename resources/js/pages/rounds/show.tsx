import { Head, Link } from '@inertiajs/react';
import { index as coursesIndex } from '@/actions/App/Http/Controllers/CourseController';
import {
    edit,
    index as roundsIndex,
} from '@/actions/App/Http/Controllers/RoundController';
import RoundPhotoController from '@/actions/App/Http/Controllers/RoundPhotoController';
import { ComputationHelp } from '@/components/handicaps/computation-help';
import { PageTopRow } from '@/components/page-top-row';
import { DeleteRound } from '@/components/rounds/delete-round';
import { NetLeaderboard } from '@/components/rounds/net-leaderboard';
import type { LeaderboardEntry } from '@/components/rounds/net-leaderboard';
import { RoundScorecard } from '@/components/rounds/round-scorecard';
import type { SavedCard } from '@/components/rounds/round-scorecard';
import { formatDottedPlayedOn, formatTenths } from '@/lib/format';

export type RoundDetail = {
    id: string;
    courseName: string;
    tees: string;
    playedOn: string;
    par: number;
    hasPhoto: boolean;
    leaderboard: LeaderboardEntry[];
    /** Rating des différentiels ; sans rating, le par total × 10 et 113 (design D5). */
    rating: { rated: boolean; ratingT: number; slope: number };
    card: SavedCard;
};

/**
 * Page d'un round (spec `rounds`, « Mise en page de la page d'un round ») :
 * rangée du haut (retour « Rounds », « Modifier », « Supprimer »), nom du
 * parcours, méta « RED · 17.10.2026 · PAR 71 · 71,4 / 144 » dont la rating
 * mène à la page Parcours, classement net, carte de score et légende, puis la
 * photo et « Comment l’index est calculé », que le design ne dessine pas.
 */
export default function RoundsShow({ round }: { round: RoundDetail }) {
    const photo = RoundPhotoController.url(round.id);

    return (
        <>
            <Head title="Round" />
            <PageTopRow
                back={{ href: roundsIndex.url(), label: 'Rounds' }}
                actions={
                    <div className="-mr-2 flex">
                        <Link
                            href={edit(round.id)}
                            className="flex min-h-11 items-center px-2 text-link"
                        >
                            Modifier
                        </Link>
                        <DeleteRound
                            roundId={round.id}
                            courseName={round.courseName}
                            playedOn={round.playedOn}
                        />
                    </div>
                }
            />
            <h1 className="text-title">{round.courseName}</h1>
            <p
                data-testid="round-meta"
                className="mt-2 font-mono text-meta-round text-ink-2"
            >
                {round.tees.toLocaleUpperCase('fr')} ·{' '}
                <time dateTime={round.playedOn}>
                    {formatDottedPlayedOn(round.playedOn)}
                </time>{' '}
                · PAR {round.par} ·{' '}
                <Link href={coursesIndex()} className="underline">
                    {round.rating.rated
                        ? `${formatTenths(round.rating.ratingT)} / ${round.rating.slope}`
                        : 'SANS RATING'}
                </Link>
            </p>
            <div className="mt-[30px]">
                <NetLeaderboard entries={round.leaderboard} />
            </div>
            <div className="mt-11">
                <RoundScorecard card={round.card} />
            </div>
            {round.hasPhoto ? (
                <a
                    href={photo}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-6 block"
                >
                    <img
                        src={photo}
                        alt="Carte du round"
                        className="aspect-[16/10] w-full rounded-12 bg-surface-2 object-cover"
                    />
                </a>
            ) : null}
            <div className="mt-6">
                <ComputationHelp />
            </div>
        </>
    );
}
