import { Head, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { relaunch } from '@/actions/App/Http/Controllers/CourseRatingLookupController';
import {
    CourseAction,
    CourseActionScope,
} from '@/components/courses/course-action';
import type { CourseActionKind } from '@/components/courses/course-action';
import { COURSE_PILL, LookupStatus } from '@/components/courses/lookup-status';
import { ManualRatingForm } from '@/components/courses/manual-rating-form';
import { hasSgtOutcome, SgtOutcome } from '@/components/courses/sgt-outcome';
import { ComputationLink } from '@/components/handicaps/computation-help';
import { PageTopRow } from '@/components/page-top-row';
import { Badge } from '@/components/ui/badge';
import { formatTenths } from '@/lib/format';

/** Un parcours connu, tel que CourseOverview l'envoie (design D6). */
export type CourseOverview = {
    courseName: string;
    tees: string;
    par: number;
    ratingT: number | null;
    slope: number | null;
    /** Stroke index of each hole, holes 1 to 18, from the latest round on this course and tees. */
    strokeIndexes: number[];
};

/** Statut de la recherche d'une ligne de `course_ratings` (enum `CourseRatingStatus`). */
export type CourseRatingStatus =
    | 'pending'
    | 'proposed'
    | 'choose_course'
    | 'choose_tee'
    | 'manual'
    | 'not_found'
    | 'failed'
    | 'confirmed';

/** Un tee lu sur la carte SGT, rating en dixièmes. */
export type SgtTee = { name: string; ratingT: number; slope: number };

/** Un parcours SGT candidat, au même par, quand plusieurs noms correspondent. */
export type SgtCandidate = {
    id: number;
    name: string;
    location: string;
    par: number;
    scorecardUrl: string;
};

/**
 * Un parcours de la page Parcours avec l'état de sa recherche sur Simulator
 * Golf Tour (design D8 de course-rating-sgt). Une paire sans ligne a `id` et
 * `status` à `null` ; `note` est `null` sur une ligne confirmée (Q18).
 */
export type CourseWithLookup = CourseOverview & {
    /** Rounds joués sur ce parcours et ces tees (« 3 rounds », D14). */
    roundCount: number;
    id: string | null;
    status: CourseRatingStatus | null;
    label: string | null;
    note: string | null;
    source: 'manual' | 'sgt' | null;
    sgtCourseName: string | null;
    sgtCourseLocation: string | null;
    proposal: SgtTee | null;
    teesRead: SgtTee[];
    candidates: SgtCandidate[];
    scorecardUrl: string | null;
    courseViewUrl: string | null;
};

/** La page se relit toutes les 2 s tant qu'une recherche est en cours (design D9). */
const POLL_INTERVAL_MS = 2000;

/**
 * Pastille de rating (spec « Page Parcours », DO10) : « 71,4 / 144 · CONFIRMÉ »
 * en vert quand la recherche est « Confirmé », « 71,4 / 144 » neutre pendant
 * une nouvelle proposition, sinon « SANS RATING » neutre.
 */
function RatingPill({ course }: { course: CourseWithLookup }) {
    const { ratingT, slope, status } = course;
    const rated = ratingT !== null && slope !== null;
    const confirmed = rated && status === 'confirmed';
    let text = 'SANS RATING';

    if (rated) {
        text = `${formatTenths(ratingT)} / ${slope}${confirmed ? ' · CONFIRMÉ' : ''}`;
    }

    return (
        <Badge
            data-testid="course-rating"
            variant={confirmed ? 'ok' : 'secondary'}
            className={COURSE_PILL}
        >
            {text}
        </Badge>
    );
}

/**
 * Le bloc d'un parcours (spec « Mise en page de la page Parcours ») : nom,
 * « · » et tees, « PAR 71 » ; pastille de rating, statut de la recherche et
 * nombre de rounds ; note ; parcours SGT, proposition, tees lus ou candidats ;
 * saisie à la main, puis « Relancer la recherche » quand il a une ligne,
 * recherche en cours comprise (Q6), et « Retirer la rating ». Une action
 * envoyée attend sur son seul bloc.
 */
function CourseRow({ course }: { course: CourseWithLookup }) {
    const label = `${course.courseName} / ${course.tees}`;
    const { id, ratingT, slope } = course;
    const [sending, setSending] = useState<CourseActionKind | null>(null);

    return (
        <CourseActionScope value={{ sending, setSending }}>
            <li
                aria-label={label}
                className="flex min-w-0 flex-col border-b border-rule-soft py-[18px]"
            >
                <div className="flex items-baseline justify-between gap-3">
                    <h2 className="min-w-0 text-name-m-17 leading-[1.2] [overflow-wrap:anywhere]">
                        {course.courseName}{' '}
                        <span className="font-normal text-ink-2">
                            · {course.tees}
                        </span>
                    </h2>
                    <span className="flex-none font-mono text-meta-12 text-ink-2">
                        PAR {course.par}
                    </span>
                </div>
                <div className="mt-2.5 flex flex-wrap items-center gap-2 font-mono text-meta-12">
                    <RatingPill course={course} />
                    <LookupStatus course={course} />
                    <span className="text-ink-2">
                        {course.roundCount}{' '}
                        {course.roundCount > 1 ? 'rounds' : 'round'}
                    </span>
                </div>
                {course.note !== null ? (
                    <p
                        data-testid="course-note"
                        className="mt-2 text-body-13 text-ink-2"
                    >
                        {course.note}
                    </p>
                ) : null}
                {hasSgtOutcome(course) ? (
                    <div className="mt-3 flex min-w-0 flex-col gap-1">
                        <SgtOutcome course={course} />
                    </div>
                ) : null}
                {/* Clé sur la rating reçue : après une saisie ou un retrait, les champs repartent des valeurs du serveur ; une relecture qui la garde ne vide pas une saisie en cours. */}
                <ManualRatingForm
                    key={`${ratingT}-${slope}`}
                    courseName={course.courseName}
                    tees={course.tees}
                    ratingT={ratingT}
                    slope={slope}
                    links={
                        id === null ? null : (
                            <CourseAction
                                action={relaunch(id)}
                                kind="relaunch"
                                label="Relancer la recherche"
                                accessibleName={`Relancer la recherche pour ${label}`}
                                appearance="link"
                            />
                        )
                    }
                />
            </li>
        </CourseActionScope>
    );
}

/**
 * Parcours connus avec leur course rating et leur slope (spec
 * `course-ratings`) : valeur confirmée ou « sans rating », état de la
 * recherche sur Simulator Golf Tour et ses actions, saisie, correction et
 * retrait à la main, un bloc par parcours, puis l'explication du calcul (spec
 * « Mise en page de la page Parcours »). Tant qu'une recherche est
 * en cours, seules `courses` et `lookupPending` se relisent toutes les 2 s.
 * Page de section, l'onglet « Parcours » : rangée du haut « Golf », sans lien
 * de retour (design D3).
 */
export default function CoursesIndex({
    courses,
    lookupPending,
}: {
    courses: CourseWithLookup[];
    lookupPending: boolean;
}) {
    const { start, stop } = usePoll(
        POLL_INTERVAL_MS,
        { only: ['courses', 'lookupPending'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (lookupPending) {
            start();
        } else {
            stop();
        }
    }, [lookupPending, start, stop]);

    return (
        <>
            <Head title="Parcours" />
            <PageTopRow />
            <h1 className="text-title">Parcours</h1>
            <p className="mt-2.5 text-body-13 text-ink-2">
                Course rating et slope par parcours et tees. Une valeur ne
                compte dans les index qu'une fois confirmée.
            </p>
            {courses.length === 0 ? (
                <p className="mt-5 border-t border-rule pt-5 text-body text-ink-2">
                    Aucun parcours : enregistrez d'abord un round.
                </p>
            ) : (
                <ul aria-label="Parcours" className="mt-5 border-t border-rule">
                    {courses.map((course) => (
                        <CourseRow
                            key={`${course.courseName} / ${course.tees}`}
                            course={course}
                        />
                    ))}
                </ul>
            )}
            <div className="mt-7">
                <ComputationLink />
            </div>
        </>
    );
}
