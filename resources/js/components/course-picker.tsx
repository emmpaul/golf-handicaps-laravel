import { Link } from '@inertiajs/react';
import { index as coursesIndex } from '@/actions/App/Http/Controllers/CourseController';
import { courseLabel } from '@/components/round-editor/form-state';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { cn } from '@/lib/utils';

/** Libellé de section de la page : Geist Mono 500, 11 px, capitales, gris. */
const SECTION = 'mb-2 font-mono text-section text-ink-2 uppercase';

/**
 * Choix du parcours des pages Coups et Partie virtuelle (specs `match-strokes`
 * et `virtual-match`) : « PARCOURS », la liste des parcours connus par nom
 * puis tees, et la ligne de rating `rating` vers `/courses`. Sans parcours
 * connu, « Aucun parcours : enregistrez d'abord un round. » à la place de la
 * liste.
 */
export function CoursePicker({
    courses,
    value,
    onChange,
    rating,
}: {
    courses: { courseName: string; tees: string }[];
    /** Position du parcours choisi dans `courses`. */
    value: number;
    onChange: (index: number) => void;
    /** « rating 71,4 · slope 144 » ou « sans rating (slope 113) ». */
    rating: string;
}) {
    if (courses.length === 0) {
        return (
            <>
                <p className={cn(SECTION, 'mt-6')}>Parcours</p>
                <p className="text-body text-ink-2">
                    Aucun parcours : enregistrez d'abord un round.
                </p>
            </>
        );
    }

    return (
        <>
            <label htmlFor="course" className={cn(SECTION, 'mt-6 block')}>
                Parcours
            </label>
            <NativeSelect
                id="course"
                className="w-full"
                value={value}
                onChange={(event) =>
                    onChange(Number(event.currentTarget.value))
                }
            >
                {courses.map((known, i) => (
                    <NativeSelectOption key={courseLabel(known)} value={i}>
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
                {rating}
            </Link>
        </>
    );
}
