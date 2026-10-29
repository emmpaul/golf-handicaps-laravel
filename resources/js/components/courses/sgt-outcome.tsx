import {
    chooseCandidate,
    chooseTee,
    confirm,
} from '@/actions/App/Http/Controllers/CourseRatingLookupController';
import { CourseAction } from '@/components/courses/course-action';
import { formatTenths } from '@/lib/format';
import { ACTION_LINK, cn } from '@/lib/utils';
import type { CourseWithLookup } from '@/pages/courses/index';

const ratingText = (ratingT: number, slope: number) =>
    `${formatTenths(ratingT)} / ${slope}`;

/** Lien d'aide vers SGT ou The Course View, ouvert dans un nouvel onglet. */
function ExternalLink({ href, children }: { href: string; children: string }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className={cn(ACTION_LINK, 'text-sm')}
        >
            {children}
        </a>
    );
}

/** Le parcours SGT retenu (nom et lieu, contre les homonymes) et ses liens d'aide. */
function SgtCourse({ course }: { course: CourseWithLookup }) {
    const { sgtCourseName, sgtCourseLocation, scorecardUrl, courseViewUrl } =
        course;

    if (scorecardUrl === null) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1 text-sm">
            {sgtCourseName === null ? null : (
                // Nom et lieu du parcours SGT en deux éléments, sans point médian.
                <span className="flex min-w-0 flex-wrap gap-x-3 [overflow-wrap:anywhere] text-muted-foreground">
                    <span>SGT : {sgtCourseName}</span>
                    {sgtCourseLocation !== null ? (
                        <span>{sgtCourseLocation}</span>
                    ) : null}
                </span>
            )}
            <span className="flex flex-wrap gap-x-4">
                <ExternalLink href={scorecardUrl}>Carte SGT</ExternalLink>
                {courseViewUrl !== null ? (
                    <ExternalLink href={courseViewUrl}>
                        The Course View
                    </ExternalLink>
                ) : null}
            </span>
        </div>
    );
}

/** La proposition, les tees lus ou les candidats SGT, selon l'état de la recherche. */
function Outcome({ course, id }: { course: CourseWithLookup; id: string }) {
    const label = `${course.courseName} / ${course.tees}`;

    if (course.status === 'proposed' && course.proposal !== null) {
        const { name, ratingT, slope } = course.proposal;

        return (
            <div className="flex flex-wrap items-center gap-2">
                <p data-testid="course-proposal" className="text-sm">
                    Proposition : tee {name}, {ratingText(ratingT, slope)}
                </p>
                <CourseAction
                    action={confirm(id)}
                    kind="confirm"
                    label="Confirmer"
                    accessibleName={`Confirmer ${ratingText(ratingT, slope)} pour ${label}`}
                    variant="default"
                />
            </div>
        );
    }

    if (course.status === 'choose_tee' && course.teesRead.length > 0) {
        return (
            <ul aria-label={`Tees lus pour ${label}`} className="flex flex-col">
                {course.teesRead.map((tee) => (
                    <li
                        key={tee.name}
                        className="flex flex-wrap items-center justify-between gap-2 border-t py-1.5"
                    >
                        <span className="flex gap-x-3 text-sm">
                            <span className="font-medium">{tee.name}</span>
                            <span className="tabular-nums">
                                {ratingText(tee.ratingT, tee.slope)}
                            </span>
                        </span>
                        <CourseAction
                            action={chooseTee(id)}
                            data={{ tee: tee.name }}
                            kind="confirm"
                            label="Retenir"
                            accessibleName={`Retenir le tee ${tee.name} pour ${label}`}
                        />
                    </li>
                ))}
            </ul>
        );
    }

    if (course.status === 'choose_course' && course.candidates.length > 0) {
        return (
            <ul
                aria-label={`Parcours SGT possibles pour ${label}`}
                className="flex flex-col"
            >
                {course.candidates.map((candidate) => (
                    <li
                        key={candidate.id}
                        className="flex flex-wrap items-center justify-between gap-2 border-t py-1.5"
                    >
                        <span className="flex min-w-0 flex-col text-sm">
                            <span className="font-medium [overflow-wrap:anywhere]">
                                {candidate.name}
                            </span>
                            <span className="flex flex-wrap gap-x-3 text-muted-foreground">
                                <span>{candidate.location}</span>
                                <span>Par {candidate.par}</span>
                            </span>
                        </span>
                        <span className="flex flex-wrap items-center gap-3">
                            <ExternalLink href={candidate.scorecardUrl}>
                                Carte SGT
                            </ExternalLink>
                            <CourseAction
                                action={chooseCandidate(id)}
                                data={{ sgtCourseId: candidate.id }}
                                kind="relaunch"
                                label="Choisir"
                                accessibleName={`Choisir ${candidate.name} (${candidate.location})`}
                            />
                        </span>
                    </li>
                ))}
            </ul>
        );
    }

    return null;
}

/**
 * Colonne Simulator Golf Tour d'un parcours (spec `course-ratings`, « Parcours
 * SGT et proposition sur la page Parcours ») : parcours SGT retenu et ses
 * liens, puis proposition et « Confirmer », tees lus et « Retenir », ou
 * candidats et « Choisir ».
 */
export function SgtOutcome({ course }: { course: CourseWithLookup }) {
    return (
        <>
            <SgtCourse course={course} />
            {course.id === null ? null : (
                <Outcome course={course} id={course.id} />
            )}
        </>
    );
}

/** Le parcours a-t-il quelque chose à montrer dans la colonne SGT ? */
export function hasSgtOutcome(course: CourseWithLookup): boolean {
    return (
        course.scorecardUrl !== null ||
        (course.status === 'proposed' && course.proposal !== null) ||
        (course.status === 'choose_tee' && course.teesRead.length > 0) ||
        (course.status === 'choose_course' && course.candidates.length > 0)
    );
}
