import { Head } from '@inertiajs/react';
import { PageTitle } from '@/components/page-title';
import {
    type PlannerPlayer,
    StrokesPlanner,
} from '@/components/strokes/strokes-planner';
import type { CourseOverview } from '@/pages/courses/index';

/**
 * Strokes page (spec `match-strokes`): the current index of each player and the
 * known courses with their rating and stroke indexes; the strokes are computed
 * in the browser (design D5).
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
            <PageTitle>Coups à donner</PageTitle>
            <StrokesPlanner players={players} courses={courses} />
        </>
    );
}
