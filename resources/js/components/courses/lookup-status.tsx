import { Badge } from '@/components/ui/badge';
import { PendingOrb } from '@/components/ui/pending';
import type { CourseWithLookup } from '@/pages/courses/index';

/**
 * État de la recherche d'un parcours (spec `course-ratings`, « Statuts de la
 * recherche ») : son libellé en `Badge`, sa note dessous quand elle en a une
 * (jamais sur une ligne confirmée, décision Q18). L'orbe tourne sur une ligne
 * en recherche seulement, sans `role="status"` : le badge change déjà à chaque
 * rafraîchissement. Rien pour une paire sans ligne.
 */
export function LookupStatus({
    course,
}: {
    course: Pick<CourseWithLookup, 'status' | 'label' | 'note'>;
}) {
    if (course.status === null || course.label === null) {
        return null;
    }

    return (
        <>
            <span className="inline-flex items-center gap-2">
                <Badge
                    data-testid="course-status"
                    variant={
                        course.status === 'failed' ? 'destructive' : 'secondary'
                    }
                >
                    {course.label}
                </Badge>
                {course.status === 'pending' ? (
                    <PendingOrb state="searching" />
                ) : null}
            </span>
            {course.note !== null ? (
                <p
                    data-testid="course-note"
                    className="basis-full text-sm text-muted-foreground"
                >
                    {course.note}
                </p>
            ) : null}
        </>
    );
}
