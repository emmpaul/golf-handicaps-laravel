import { Head } from '@inertiajs/react';
import { ManualRatingForm } from '@/components/courses/manual-rating-form';
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
};

/**
 * Colonnes de la table à partir de 1 024 px : parcours et tees, rating, saisie
 * à la main (variante `plain` de Next, sans la colonne Simulator Golf Tour). En
 * dessous, chaque rangée empile ces parties.
 */
const COURSE_COLUMNS =
    'lg:grid lg:grid-cols-[minmax(0,1.1fr)_10rem_minmax(0,2.5fr)] lg:items-start lg:gap-x-6';

/** Un parcours : rating saisie ou « sans rating », et sa saisie à la main. */
function CourseRow({ course }: { course: CourseOverview }) {
    const label = `${course.courseName} / ${course.tees}`;
    const { ratingT, slope } = course;

    return (
        <li
            aria-label={label}
            className={cn(
                'flex min-w-0 flex-col gap-3 border-b px-2 py-3',
                COURSE_COLUMNS,
            )}
        >
            {/* Parcours et tees en deux éléments, sans point médian ; le par à côté. */}
            <div className="flex min-w-0 flex-col gap-0.5">
                <h2 className="font-heading text-xl leading-tight font-bold [overflow-wrap:anywhere]">
                    {course.courseName}
                </h2>
                <span className="flex flex-wrap gap-x-4 text-sm text-muted-foreground">
                    <span>Tees {course.tees}</span>
                    <span>Par {course.par}</span>
                </span>
            </div>
            <span
                data-testid="course-rating"
                className="font-heading text-xl font-bold tabular-nums"
            >
                {ratingT !== null && slope !== null
                    ? `${formatTenths(ratingT)} / ${slope}`
                    : 'sans rating'}
            </span>
            {/* Clé sur la rating reçue : après une saisie ou un retrait, les champs repartent des valeurs du serveur. */}
            <div className="min-w-0">
                <ManualRatingForm
                    key={`${ratingT}-${slope}`}
                    courseName={course.courseName}
                    tees={course.tees}
                    ratingT={ratingT}
                    slope={slope}
                />
            </div>
        </li>
    );
}

/**
 * Parcours connus avec leur course rating et leur slope (spec
 * `course-ratings`) : valeur saisie ou « sans rating », saisie, correction et
 * retrait à la main, puis l'explication du calcul. Hors de la navigation
 * principale : « Rounds » reste la section courante (`lib/sections.ts`).
 */
export default function CoursesIndex({
    courses,
}: {
    courses: CourseOverview[];
}) {
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
                            'hidden bg-band px-2 py-1.5 text-sm font-medium text-band-ink',
                            COURSE_COLUMNS,
                        )}
                    >
                        <span>Parcours</span>
                        <span>Rating / slope</span>
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
                            />
                        ))}
                    </ul>
                </div>
            )}
            <ComputationHelp />
        </>
    );
}
