import { Badge } from '@/components/ui/badge';
import { PendingOrb } from '@/components/ui/pending';
import type {
    CourseRatingStatus,
    CourseWithLookup,
} from '@/pages/courses/index';

/** Pastille de chaque statut (spec « Recherche SGT dans le bloc d'un parcours »). */
const STATUS_VARIANT: Record<
    Exclude<CourseRatingStatus, 'confirmed'>,
    'info' | 'accent' | 'destructive' | 'secondary'
> = {
    pending: 'info',
    proposed: 'accent',
    choose_course: 'accent',
    choose_tee: 'accent',
    failed: 'destructive',
    manual: 'secondary',
    not_found: 'secondary',
};

/** Pastille du bloc d'un parcours : Geist Mono 500, 12 px, rayon 999 px. */
export const COURSE_PILL = 'h-auto py-[5px] text-meta-12 tracking-normal';

/**
 * État de la recherche d'un parcours (spec `course-ratings`, « Statuts de la
 * recherche ») : son libellé en pastille d'état, cyan en recherche, ambre pour
 * un choix à faire, rouge en échec, neutre sinon. Une ligne confirmée n'en a
 * pas : sa pastille de rating porte « · CONFIRMÉ » (DO10). L'orbe tourne sur
 * une ligne en recherche seulement, sans `role="status"` : la pastille change
 * déjà à chaque rafraîchissement. Rien pour une paire sans ligne.
 */
export function LookupStatus({
    course,
}: {
    course: Pick<CourseWithLookup, 'status' | 'label'>;
}) {
    if (
        course.status === null ||
        course.status === 'confirmed' ||
        course.label === null
    ) {
        return null;
    }

    return (
        <span className="inline-flex items-center gap-2">
            <Badge
                data-testid="course-status"
                variant={STATUS_VARIANT[course.status]}
                className={COURSE_PILL}
            >
                {course.label}
            </Badge>
            {course.status === 'pending' ? (
                <PendingOrb state="searching" />
            ) : null}
        </span>
    );
}
