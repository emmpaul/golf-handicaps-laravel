import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { index as coursesIndex } from '@/actions/App/Http/Controllers/CourseController';
import { PlayerSwatch } from '@/components/player-swatch';
import { courseLabel } from '@/components/round-editor/form-state';
import { SegmentedControl } from '@/components/strokes/segmented-control';
import { StrokeHoleGrid } from '@/components/strokes/stroke-hole-grid';
import { Checkbox } from '@/components/ui/checkbox';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { formatTenths, NO_INDEX } from '@/lib/format';
import { allocateStrokes, matchStrokes, type StrokeMode } from '@/lib/strokes';
import { cn } from '@/lib/utils';
import type { CourseOverview } from '@/pages/courses/index';

export type PlannerPlayer = {
    id: string;
    name: string;
    indexT: number | null;
    /** Colour slot of the player, 0 to 5 (D6). */
    colorSlot: number;
};

const MIN_PLAYERS = 2;
const MAX_PLAYERS = 4;

const MODES = [
    { value: 'ecart', label: 'Écart' },
    { value: 'complet', label: 'Complet' },
] as const satisfies readonly { value: StrokeMode; label: string }[];

/** The explanation of each mode, under the control (design). */
const MODE_HINT: Record<StrokeMode, string> = {
    ecart: 'Le meilleur joueur joue scratch, les autres reçoivent la différence.',
    complet: 'Chaque joueur reçoit son handicap de jeu complet.',
};

/** Label of a section of the page: Geist Mono 500, 11 px, capitals, grey. */
const SECTION = 'mb-2 font-mono text-section text-ink-2 uppercase';

const plural = (count: number) => (Math.abs(count) > 1 ? 'coups' : 'coup');

/** « reçoit 16 coups », « reçoit 1 coup », « ne reçoit aucun coup », « rend 2 coups ». */
function totalLabel(strokes: number): string {
    if (strokes === 0) {
        return 'ne reçoit aucun coup';
    }

    return strokes < 0
        ? `rend ${-strokes} ${plural(strokes)}`
        : `reçoit ${strokes} ${plural(strokes)}`;
}

/** The course's rating for the course handicap, or none: the rounded index (design D5). */
function ratedCourse(course: CourseOverview) {
    return course.ratingT !== null && course.slope !== null
        ? { ratingT: course.ratingT, slope: course.slope, par: course.par }
        : undefined;
}

/** « rating 71,4 · slope 144 » or « sans rating (slope 113) » (spec « Rating du parcours choisi »). */
function ratingText(course: CourseOverview): string {
    return course.ratingT !== null && course.slope !== null
        ? `rating ${formatTenths(course.ratingT)} · slope ${course.slope}`
        : 'sans rating (slope 113)';
}

/**
 * Strokes to give before a match (spec `match-strokes`, « Mise en page de la
 * page Coups », design D5): 2 to 4 players with an index, a known course (the
 * first one at the opening, DO7), mode « Écart » by default or « Complet »,
 * then a block per chosen player. The choice stays in the page's state: no
 * request is sent.
 */
export function StrokesPlanner({
    players,
    courses,
}: {
    players: PlannerPlayer[];
    courses: CourseOverview[];
}) {
    const [selected, setSelected] = useState<string[]>([]);
    const [courseIndex, setCourseIndex] = useState(0);
    const [mode, setMode] = useState<StrokeMode>('ecart');
    const course = courses.at(courseIndex);
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
        <>
            <fieldset className="mt-6">
                <legend className={SECTION}>Joueurs · 2 à 4</legend>
                {players.map((player) => {
                    const disabled =
                        player.indexT === null ||
                        (!selected.includes(player.id) &&
                            selected.length >= MAX_PLAYERS);

                    return (
                        <label
                            key={player.id}
                            data-testid={`player-choice-${player.name}`}
                            data-disabled={player.indexT === null || undefined}
                            className={cn(
                                'flex min-h-13 cursor-pointer items-center gap-3 border-b border-rule-soft has-disabled:cursor-not-allowed',
                                player.indexT === null && 'opacity-45',
                            )}
                        >
                            <Checkbox
                                disabled={disabled}
                                checked={selected.includes(player.id)}
                                onChange={(event) =>
                                    toggle(
                                        player.id,
                                        event.currentTarget.checked,
                                    )
                                }
                            />
                            <span className="flex min-w-0 items-center gap-2">
                                <PlayerSwatch colorSlot={player.colorSlot} />
                                <span className="truncate text-label">
                                    {player.name}
                                </span>
                            </span>{' '}
                            <span className="ml-auto flex-none font-mono text-meta text-ink-2">
                                {player.indexT === null
                                    ? NO_INDEX
                                    : `Index ${formatTenths(player.indexT)}`}
                            </span>
                        </label>
                    );
                })}
            </fieldset>
            {course === undefined ? (
                <>
                    <p className={cn(SECTION, 'mt-6')}>Parcours</p>
                    <p className="text-body text-ink-2">
                        Aucun parcours : enregistrez d'abord un round.
                    </p>
                </>
            ) : (
                <>
                    <label
                        htmlFor="course"
                        className={cn(SECTION, 'mt-6 block')}
                    >
                        Parcours
                    </label>
                    <NativeSelect
                        id="course"
                        className="w-full"
                        value={courseIndex}
                        onChange={(event) =>
                            setCourseIndex(Number(event.currentTarget.value))
                        }
                    >
                        {courses.map((known, i) => (
                            <NativeSelectOption
                                key={courseLabel(known)}
                                value={i}
                            >
                                {courseLabel(known)}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                    {/* Rating of the chosen course, a link to the Courses page. */}
                    <Link
                        href={coursesIndex()}
                        data-testid="course-rating"
                        className="mt-2 flex min-h-8 w-fit items-center font-mono text-meta-12 text-ink-2 underline underline-offset-3"
                    >
                        {ratingText(course)}
                    </Link>
                </>
            )}
            <p className={cn(SECTION, 'mt-4')}>Mode</p>
            <SegmentedControl
                name="mode"
                label="Mode"
                options={MODES}
                value={mode}
                onChange={setMode}
            />
            <p className="mt-2 text-body-12 text-ink-2">{MODE_HINT[mode]}</p>
            {ready ? (
                <ul aria-label="Coups reçus">
                    {chosen.map((player, i) => (
                        <li
                            key={player.id}
                            className="mt-6 border-t border-rule pt-[18px]"
                        >
                            <div className="flex items-end justify-between gap-3">
                                <div className="flex min-w-0 flex-col gap-2">
                                    <span className="flex min-w-0 items-center gap-2">
                                        <PlayerSwatch
                                            colorSlot={player.colorSlot}
                                        />
                                        <span
                                            data-testid="strokes-name"
                                            className="truncate text-label"
                                        >
                                            {player.name}
                                        </span>
                                    </span>
                                    <span
                                        data-testid="strokes-total"
                                        className="text-body-13 leading-none text-ink-2"
                                    >
                                        {totalLabel(strokes[i])}
                                    </span>
                                </div>
                                <span
                                    data-testid="strokes-count"
                                    className="flex-none font-mono text-display-l"
                                >
                                    {strokes[i] < 0
                                        ? `\u2212${-strokes[i]}`
                                        : strokes[i]}
                                </span>
                            </div>
                            {strokes[i] !== 0 ? (
                                <StrokeHoleGrid
                                    perHole={allocateStrokes(
                                        strokes[i],
                                        course.strokeIndexes,
                                    )}
                                    colorSlot={player.colorSlot}
                                />
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-7 rounded-12 border border-dashed border-rule p-4 text-body text-ink-2">
                    Choisissez au moins 2 joueurs avec un index.
                </p>
            )}
        </>
    );
}
