import { Head } from '@inertiajs/react';
import { index as handicapsIndex } from '@/actions/App/Http/Controllers/HandicapController';
import { PageTopRow } from '@/components/page-top-row';
import {
    type PlannerPlayer,
    StrokesPlanner,
} from '@/components/strokes/strokes-planner';
import type { CourseOverview } from '@/pages/courses/index';

/**
 * Strokes page (spec `match-strokes`, « Mise en page de la page Coups »), a
 * sub-page of Handicaps with its back link (design D3): the
 * current index of each player and the known courses with their rating and
 * stroke indexes; the strokes are computed in the browser (design D5).
 */
export default function StrokesIndex({
    players,
    courses,
}: {
    players: PlannerPlayer[];
    courses: CourseOverview[];
}) {
    return (
        <>
            <Head title="Coups" />
            <PageTopRow
                back={{ href: handicapsIndex.url(), label: 'Handicaps' }}
            />
            <h1 className="text-title">Coups à donner</h1>
            <StrokesPlanner players={players} courses={courses} />
        </>
    );
}
