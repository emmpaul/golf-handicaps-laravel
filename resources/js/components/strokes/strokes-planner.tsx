import { useState } from 'react';
import { RatingLink } from '@/components/courses/rating-link';
import { courseLabel } from '@/components/round-editor/form-state';
import { StrokesGrid } from '@/components/strokes/strokes-grid';
import {
    Field,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { formatTenths, NO_INDEX } from '@/lib/format';
import { allocateStrokes, matchStrokes, type StrokeMode } from '@/lib/strokes';
import { cn } from '@/lib/utils';
import type { CourseOverview } from '@/pages/courses/index';

export type PlannerPlayer = { id: string; name: string; indexT: number | null };

const MIN_PLAYERS = 2;
const MAX_PLAYERS = 4;

const MODES: { mode: StrokeMode; label: string }[] = [
    { mode: 'ecart', label: 'Écart' },
    { mode: 'complet', label: 'Complet' },
];

const plural = (count: number) => (Math.abs(count) > 1 ? 'coups' : 'coup');

/** « Manu reçoit 16 coups », « Gab reçoit 1 coup », « Gab ne reçoit aucun coup », « Rory rend 2 coups ». */
function totalLabel(name: string, strokes: number): string {
    if (strokes === 0) {
        return `${name} ne reçoit aucun coup`;
    }

    return strokes < 0
        ? `${name} rend ${-strokes} ${plural(strokes)}`
        : `${name} reçoit ${strokes} ${plural(strokes)}`;
}

/** The course's rating for the course handicap, or none: the rounded index (design D5). */
function ratedCourse(course: CourseOverview) {
    return course.ratingT !== null && course.slope !== null
        ? { ratingT: course.ratingT, slope: course.slope, par: course.par }
        : undefined;
}

/**
 * Strokes to give before a match (spec `match-strokes`, design D5): 2 to 4
 * players with an index, a known course, mode « Écart » by default or
 * « Complet ». The choice stays in the page's state: no request is sent.
 */
export function StrokesPlanner({
    players,
    courses,
}: {
    players: PlannerPlayer[];
    courses: CourseOverview[];
}) {
    const [selected, setSelected] = useState<string[]>([]);
    const [courseIndex, setCourseIndex] = useState<number | null>(null);
    const [mode, setMode] = useState<StrokeMode>('ecart');
    const course = courseIndex === null ? undefined : courses[courseIndex];
    const chosen = players.filter((player) => selected.includes(player.id));
    const ready = course !== undefined && chosen.length >= MIN_PLAYERS;
    const strokes = ready
        ? matchStrokes(
              chosen.map((player) => player.indexT ?? 0),
              mode,
              ratedCourse(course),
          )
        : [];

    function toggle(id: string, on: boolean) {
        setSelected((current) =>
            on ? [...current, id] : current.filter((other) => other !== id),
        );
    }

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
            <div className="flex flex-col gap-4">
                <FieldSet>
                    <FieldLegend variant="label">Joueurs (2 à 4)</FieldLegend>
                    {/* One ruled row per player, under an ink rule: no cards. */}
                    <div className="flex flex-col border-t-2 border-foreground">
                        {players.map((player) => {
                            const disabled =
                                player.indexT === null ||
                                (!selected.includes(player.id) &&
                                    selected.length >= MAX_PLAYERS);

                            return (
                                <label
                                    key={player.id}
                                    data-testid={`player-choice-${player.name}`}
                                    data-disabled={
                                        player.indexT === null || undefined
                                    }
                                    className={cn(
                                        'flex min-h-11 items-center gap-3 border-b px-2 py-1.5 transition-[background-color] duration-(--dur-micro) ease-out hover:bg-muted has-checked:bg-accent',
                                        player.indexT === null && 'opacity-55',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        className="size-5 accent-primary"
                                        disabled={disabled}
                                        checked={selected.includes(player.id)}
                                        onChange={(event) =>
                                            toggle(
                                                player.id,
                                                event.currentTarget.checked,
                                            )
                                        }
                                    />
                                    <span className="min-w-0 font-heading text-xl font-semibold [overflow-wrap:anywhere]">
                                        {player.name}
                                    </span>
                                    <span className="ml-auto text-sm text-muted-foreground tabular-nums">
                                        {player.indexT === null
                                            ? NO_INDEX
                                            : `Index ${formatTenths(player.indexT)}`}
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                </FieldSet>
                {courses.length === 0 ? (
                    <p className="text-muted-foreground">
                        Aucun parcours : enregistrez d'abord un round.
                    </p>
                ) : (
                    <Field>
                        <FieldLabel htmlFor="course">Parcours</FieldLabel>
                        <NativeSelect
                            id="course"
                            value={courseIndex ?? ''}
                            onChange={(event) =>
                                setCourseIndex(
                                    event.currentTarget.value === ''
                                        ? null
                                        : Number(event.currentTarget.value),
                                )
                            }
                        >
                            <NativeSelectOption value="">
                                Choisir un parcours
                            </NativeSelectOption>
                            {courses.map((known, i) => (
                                <NativeSelectOption
                                    key={courseLabel(known)}
                                    value={i}
                                >
                                    {courseLabel(known)}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                    </Field>
                )}
                {/* Rating of the chosen course, a link to the Courses page (spec `match-strokes`). */}
                {course ? (
                    <RatingLink
                        rating={ratedCourse(course) ?? null}
                        par={course.par}
                        testId="course-rating"
                        className="-mt-2"
                    />
                ) : null}
                <FieldSet>
                    <FieldLegend variant="label">Mode</FieldLegend>
                    {/* Square segments: the radio covers the whole segment, the chosen mode in solid ink. */}
                    <div className="grid w-full auto-cols-fr grid-flow-col border border-foreground sm:w-fit">
                        {MODES.map((option) => (
                            <label
                                key={option.mode}
                                className="relative flex min-h-11 items-center justify-center px-5 font-medium transition-[color,background-color] duration-(--dur-micro) ease-out not-last:border-r not-last:border-foreground not-has-checked:hover:bg-muted has-checked:bg-primary has-checked:text-primary-foreground has-focus-visible:outline-2 has-focus-visible:-outline-offset-4 has-focus-visible:outline-ring md:min-h-8"
                            >
                                <input
                                    type="radio"
                                    name="mode"
                                    className="absolute inset-0 cursor-pointer appearance-none opacity-0 focus-visible:outline-none"
                                    checked={mode === option.mode}
                                    onChange={() => setMode(option.mode)}
                                />
                                {option.label}
                            </label>
                        ))}
                    </div>
                </FieldSet>
            </div>
            {ready ? (
                <ul aria-label="Coups reçus" className="flex flex-col gap-6">
                    {chosen.map((player, i) => (
                        <li
                            key={player.id}
                            className="flex flex-col gap-2 border-t-2 border-foreground pt-2"
                        >
                            <span
                                data-testid="strokes-total"
                                className="font-heading text-2xl font-bold"
                            >
                                {totalLabel(player.name, strokes[i])}
                            </span>
                            {strokes[i] !== 0 ? (
                                <StrokesGrid
                                    perHole={allocateStrokes(
                                        strokes[i],
                                        course.strokeIndexes,
                                    )}
                                />
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Choisis 2 à 4 joueurs et un parcours pour voir les coups
                    reçus.
                </p>
            )}
        </div>
    );
}
