import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { index as roundsIndex } from '@/actions/App/Http/Controllers/RoundController';
import { CoursePicker } from '@/components/course-picker';
import { PageTopRow } from '@/components/page-top-row';
import { NetLeaderboard } from '@/components/rounds/net-leaderboard';
import type { LeaderboardEntry } from '@/components/rounds/net-leaderboard';
import { RoundScorecard } from '@/components/rounds/round-scorecard';
import { VirtualMatchPicks } from '@/components/rounds/virtual-match-picks';
import type {
    VirtualMatchCandidate,
    VirtualMatchPick,
} from '@/components/rounds/virtual-match-picks';
import { VirtualMatchWinner } from '@/components/rounds/virtual-match-winner';
import { Button } from '@/components/ui/button';
import { formatTenths } from '@/lib/format';
import { visitCompare } from '@/lib/virtual-match';

export type KnownCourse = { courseName: string; tees: string };

/** The virtual match of the chosen course and tees, as VirtualMatch sends it (design D3). */
export type VirtualMatchData = KnownCourse & {
    rating: { rated: boolean; ratingT: number | null; slope: number };
    candidates: VirtualMatchCandidate[];
    picks: (VirtualMatchPick & { strokes: number[] })[];
    leaderboard: (LeaderboardEntry & { roundId: string; playedOn: string })[];
    /** Pars and indexes of the latest round picked. */
    holes: { par: number; strokeIndex: number }[];
    holesDiffer: boolean;
};

export type VirtualMatchPage = {
    courses: KnownCourse[];
    /** `null` without known course. */
    match: VirtualMatchData | null;
};

const MIN_PICKS = 2;

/** « rating 71,4 · slope 144 » or « sans rating (slope 113) », as on the Strokes page. */
function ratingText(rating: VirtualMatchData['rating']): string {
    return rating.ratingT === null
        ? `sans rating (slope ${rating.slope})`
        : `rating ${formatTenths(rating.ratingT)} · slope ${rating.slope}`;
}

/**
 * Page « Partie virtuelle » (spec `virtual-match`, design D4, D5 et D7,
 * variante B) : rangée du haut avec le retour « Rounds », titre, « PARCOURS »
 * et sa liste, la ligne de rating vers `/courses`, l'encadré du gagnant, les
 * pastilles, le classement net avec la date de chaque round, puis le bouton
 * qui déplie sur place la carte combinée. Changer de parcours visite son
 * adresse sans choix ; la carte est un état local, sans requête.
 */
export default function RoundsCompare({ courses, match }: VirtualMatchPage) {
    const [cardOpen, setCardOpen] = useState(false);
    const courseIndex =
        match === null
            ? -1
            : courses.findIndex(
                  (course) =>
                      course.courseName === match.courseName &&
                      course.tees === match.tees,
              );

    return (
        <>
            <Head title="Partie virtuelle" />
            <PageTopRow back={{ href: roundsIndex.url(), label: 'Rounds' }} />
            <h1 className="text-title">Partie virtuelle</h1>
            <CoursePicker
                courses={courses}
                value={courseIndex}
                onChange={(index) =>
                    visitCompare(
                        courses[index].courseName,
                        courses[index].tees,
                        '',
                    )
                }
                rating={match === null ? '' : ratingText(match.rating)}
            />
            {match === null ? null : (
                <>
                    {match.picks.length >= MIN_PICKS ? (
                        <div className="mt-6">
                            <VirtualMatchWinner
                                leaderboard={match.leaderboard}
                            />
                        </div>
                    ) : null}
                    <div className="mt-4">
                        <VirtualMatchPicks
                            courseName={match.courseName}
                            tees={match.tees}
                            picks={match.picks}
                            candidates={match.candidates}
                        />
                    </div>
                    {match.picks.length >= MIN_PICKS ? (
                        <>
                            <div className="mt-[30px]">
                                <NetLeaderboard entries={match.leaderboard} />
                            </div>
                            <Button
                                variant="outline"
                                className="mt-6 w-full"
                                aria-expanded={cardOpen}
                                onClick={() => setCardOpen((open) => !open)}
                            >
                                {cardOpen
                                    ? 'Masquer la carte combinée'
                                    : 'Voir la carte combinée'}
                            </Button>
                            {cardOpen ? (
                                <div className="mt-6">
                                    <RoundScorecard
                                        card={{
                                            holes: match.holes.map((hole) => ({
                                                ...hole,
                                                distance: null,
                                            })),
                                            lines: match.picks,
                                        }}
                                    />
                                    {match.holesDiffer ? (
                                        <p className="mt-4 text-body text-ink-2">
                                            Les pars ou les index de trou
                                            diffèrent entre ces rounds.
                                        </p>
                                    ) : null}
                                </div>
                            ) : null}
                        </>
                    ) : (
                        <p className="mt-7 rounded-12 border border-dashed border-rule p-4 text-body text-ink-2">
                            Choisissez au moins 2 joueurs.
                        </p>
                    )}
                </>
            )}
        </>
    );
}
