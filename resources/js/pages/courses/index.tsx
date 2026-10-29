import { Head, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { relaunch } from '@/actions/App/Http/Controllers/CourseRatingLookupController';
import {
    CourseAction,
    CourseActionScope,
} from '@/components/courses/course-action';
import type { CourseActionKind } from '@/components/courses/course-action';
import { LookupStatus } from '@/components/courses/lookup-status';
import { ManualRatingForm } from '@/components/courses/manual-rating-form';
import { hasSgtOutcome, SgtOutcome } from '@/components/courses/sgt-outcome';
import { ComputationHelp } from '@/components/handicaps/computation-help';
import { PageTitle } from '@/components/page-title';
import { formatTenths } from '@/lib/format';
import { cn } from '@/lib/utils';

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
 * Colonnes de la table à partir de 1 024 px : parcours et tees, rating et
 * état, colonne Simulator Golf Tour, saisie à la main (variantes `sgt` et
 * `plain` de Next). Sans rien à montrer côté SGT sur aucune rangée, la colonne
 * disparaît. En dessous, chaque rangée empile ces parties.
 */
const COURSE_COLUMNS = {
    sgt: 'lg:grid lg:grid-cols-[minmax(0,1.1fr)_10rem_minmax(0,1.3fr)_minmax(0,1.2fr)] lg:items-start lg:gap-x-6',
    plain: 'lg:grid lg:grid-cols-[minmax(0,1.1fr)_10rem_minmax(0,2.5fr)] lg:items-start lg:gap-x-6',
};

/**
 * Un parcours : rating ou « sans rating » et l'état de sa recherche, sa
 * colonne SGT, sa saisie à la main et « Relancer la recherche » quand il a une
 * ligne, recherche en cours comprise (Q6). Une action envoyée attend sur sa
 * seule ligne.
 */
function CourseRow({
    course,
    sgt,
}: {
    course: CourseWithLookup;
    sgt: boolean;
}) {
    const label = `${course.courseName} / ${course.tees}`;
    const { id, ratingT, slope } = course;
    const [sending, setSending] = useState<CourseActionKind | null>(null);

    return (
        <CourseActionScope value={{ sending, setSending }}>
            <li
                aria-label={label}
                className={cn(
                    'flex min-w-0 flex-col gap-3 border-b px-2 py-3',
                    sgt ? COURSE_COLUMNS.sgt : COURSE_COLUMNS.plain,
                )}
            >
                {/* Parcours et tees en deux éléments, sans point médian ; le par à côté. */}
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h2 className="font-heading text-xl leading-tight font-bold [overflow-wrap:anywhere]">
                        {course.courseName}
                    </h2>
                    <span className="text-sm flex flex-wrap gap-x-4 text-muted-foreground">
                        <span>Tees {course.tees}</span>
                        <span>Par {course.par}</span>
                    </span>
                </div>
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 lg:flex-col lg:items-start">
                    <span
                        data-testid="course-rating"
                        className="font-heading text-xl font-bold tabular-nums"
                    >
                        {ratingT !== null && slope !== null
                            ? `${formatTenths(ratingT)} / ${slope}`
                            : 'sans rating'}
                    </span>
                    <LookupStatus course={course} />
                </div>
                {sgt ? (
                    <div className="flex min-w-0 flex-col gap-2 max-lg:empty:hidden">
                        <SgtOutcome course={course} />
                    </div>
                ) : null}
                <div className="flex min-w-0 flex-col items-start gap-2">
                    {/* Clé sur la rating reçue : après une saisie ou un retrait, les champs repartent des valeurs du serveur ; une relecture qui la garde ne vide pas une saisie en cours. */}
                    <ManualRatingForm
                        key={`${ratingT}-${slope}`}
                        courseName={course.courseName}
                        tees={course.tees}
                        ratingT={ratingT}
                        slope={slope}
                    />
                    {id === null ? null : (
                        <CourseAction
                            action={relaunch(id)}
                            kind="relaunch"
                            label="Relancer la recherche"
                            accessibleName={`Relancer la recherche pour ${label}`}
                        />
                    )}
                </div>
            </li>
        </CourseActionScope>
    );
}

/**
 * Parcours connus avec leur course rating et leur slope (spec
 * `course-ratings`) : valeur confirmée ou « sans rating », état de la
 * recherche sur Simulator Golf Tour et ses actions, saisie, correction et
 * retrait à la main, puis l'explication du calcul. Tant qu'une recherche est
 * en cours, seules `courses` et `lookupPending` se relisent toutes les 2 s. Hors de la navigation
 * principale : « Rounds » reste la section courante (`lib/sections.ts`).
 */
export default function CoursesIndex({
    courses,
    lookupPending,
}: {
    courses: CourseWithLookup[];
    lookupPending: boolean;
}) {
    const sgt = courses.some(hasSgtOutcome);
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
            <div className="flex flex-col gap-1">
                <PageTitle>Parcours</PageTitle>
                <p className="text-sm text-muted-foreground">
                    Course rating et slope par parcours et tees. Une rating
                    saisie compte aussitôt dans les index, pour tous les rounds
                    du parcours.
                </p>
            </div>
            {courses.length === 0 ? (
                <p className="border-t-2 border-foreground pt-3 text-muted-foreground">
                    Aucun parcours : enregistrez d'abord un round.
                </p>
            ) : (
                <div className="min-w-0">
                    <div
                        aria-hidden
                        className={cn(
                            'bg-band text-sm text-band-ink hidden px-2 py-1.5 font-medium',
                            sgt ? COURSE_COLUMNS.sgt : COURSE_COLUMNS.plain,
                        )}
                    >
                        <span>Parcours</span>
                        <span>Rating / slope</span>
                        {sgt ? <span>Simulator Golf Tour</span> : null}
                        <span>Saisie à la main</span>
                    </div>
                    <ul
                        aria-label="Parcours"
                        className="max-lg:border-t-2 max-lg:border-foreground"
                    >
                        {courses.map((course) => (
                            <CourseRow
                                key={`${course.courseName} / ${course.tees}`}
                                course={course}
                                sgt={sgt}
                            />
                        ))}
                    </ul>
                </div>
            )}
            <ComputationHelp />
        </>
    );
}
