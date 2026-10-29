import { Link } from '@inertiajs/react';
import { index as coursesIndex } from '@/actions/App/Http/Controllers/CourseController';
import { formatTenths } from '@/lib/format';
import { ACTION_LINK, cn } from '@/lib/utils';

/**
 * Rating line of a course, a link to the Courses page (specs `rounds` and
 * `match-strokes`): « Rating 71,4 » and « Slope 144 » as two elements, without
 * a middle dot (DESIGN.md, screen rules), or « Sans rating (par 71, slope 113) ».
 */
export function RatingLink({
    rating,
    par,
    testId,
    className,
}: {
    /** The entered rating, `null` without one. */
    rating: { ratingT: number; slope: number } | null;
    par: number;
    testId: string;
    className?: string;
}) {
    return (
        <Link
            href={coursesIndex()}
            data-testid={testId}
            className={cn(
                ACTION_LINK,
                'w-fit gap-x-4 text-sm tabular-nums',
                className,
            )}
        >
            {rating !== null ? (
                <>
                    <span>Rating {formatTenths(rating.ratingT)}</span>
                    <span>Slope {rating.slope}</span>
                </>
            ) : (
                `Sans rating (par ${par}, slope 113)`
            )}
        </Link>
    );
}
