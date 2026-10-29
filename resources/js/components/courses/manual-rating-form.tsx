import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import {
    destroy,
    update,
} from '@/actions/App/Http/Controllers/CourseRatingController';
import {
    COURSE_ACTION_PENDING,
    useCourseActionSending,
} from '@/components/courses/course-action';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Pending } from '@/components/ui/pending';
import { formatTenths } from '@/lib/format';

/** Libellés d'attente de la ligne (spec `loading-feedback`). */
const PENDING_LABEL = {
    save: 'Enregistrement de la rating…',
    remove: 'Retrait de la rating…',
} as const;

/**
 * Saisie, correction ou retrait de la rating d'un parcours et de ses tees
 * (spec `course-ratings`). Un `useForm` par ligne : son attente et ses erreurs
 * ne touchent qu'elle (design D7), et un nouvel envoi efface les erreurs du
 * précédent. Le serveur contrôle les bornes et renvoie
 * « Rating entre 40,0 et 90,0 » ou « Slope entre 55 et 155 » sous le champ en
 * cause. « Retirer la rating » n'apparaît que sur une ligne qui a une rating,
 * sans confirmation : une nouvelle saisie défait le retrait. Sa région
 * d'attente est celle de la ligne : elle dit aussi l'attente d'une action de
 * la recherche (`CourseAction`), qui désactive ses boutons.
 */
export function ManualRatingForm({
    courseName,
    tees,
    ratingT,
    slope,
}: {
    courseName: string;
    tees: string;
    ratingT: number | null;
    slope: number | null;
}) {
    // « BETHPAGE BLACK / Red » : nomme les champs d'un parcours parmi les autres.
    const course = `${courseName} / ${tees}`;
    const ratingId = useId();
    const slopeId = useId();
    const ratingErrorId = useId();
    const slopeErrorId = useId();
    const form = useForm({
        courseName,
        tees,
        rating: ratingT === null ? '' : formatTenths(ratingT),
        slope: slope === null ? '' : String(slope),
    });
    const [sending, setSending] = useState<keyof typeof PENDING_LABEL>('save');
    const rated = ratingT !== null && slope !== null;
    const actionSending = useCourseActionSending();
    const busy = form.processing || actionSending !== null;
    let pendingLabel: string | null = null;

    if (form.processing) {
        pendingLabel = PENDING_LABEL[sending];
    } else if (actionSending !== null) {
        pendingLabel = COURSE_ACTION_PENDING[actionSending];
    }

    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSending('save');
        // Une ancienne erreur ne reste pas à côté de l'attente du nouvel envoi.
        form.clearErrors();
        form.transform((data) => data);
        form.submit(update(), { preserveScroll: true });
    }

    function remove() {
        setSending('remove');
        form.clearErrors();
        form.transform((data) => ({
            courseName: data.courseName,
            tees: data.tees,
        }));
        form.submit(destroy(), { preserveScroll: true });
    }

    return (
        <form onSubmit={save} className="flex flex-col gap-1">
            <div className="flex flex-wrap items-end gap-2">
                <Field
                    className="w-24"
                    data-invalid={form.errors.rating ? true : undefined}
                >
                    <FieldLabel htmlFor={ratingId}>Rating</FieldLabel>
                    <Input
                        id={ratingId}
                        name="rating"
                        inputMode="decimal"
                        autoComplete="off"
                        aria-label={`Rating de ${course}`}
                        aria-invalid={form.errors.rating ? true : undefined}
                        aria-describedby={
                            form.errors.rating ? ratingErrorId : undefined
                        }
                        value={form.data.rating}
                        onChange={(event) =>
                            form.setData('rating', event.target.value)
                        }
                        placeholder="ex. 71,4"
                    />
                </Field>
                <Field
                    className="w-24"
                    data-invalid={form.errors.slope ? true : undefined}
                >
                    <FieldLabel htmlFor={slopeId}>Slope</FieldLabel>
                    <Input
                        id={slopeId}
                        name="slope"
                        inputMode="numeric"
                        autoComplete="off"
                        aria-label={`Slope de ${course}`}
                        aria-invalid={form.errors.slope ? true : undefined}
                        aria-describedby={
                            form.errors.slope ? slopeErrorId : undefined
                        }
                        value={form.data.slope}
                        onChange={(event) =>
                            form.setData('slope', event.target.value)
                        }
                        placeholder="ex. 144"
                    />
                </Field>
                <Button type="submit" variant="outline" disabled={busy}>
                    Enregistrer
                </Button>
                {rated ? (
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={busy}
                        aria-label={`Retirer la rating de ${course}`}
                        onClick={remove}
                    >
                        Retirer la rating
                    </Button>
                ) : null}
            </div>
            <Pending label={pendingLabel} orb="save" />
            {form.errors.rating ? (
                <p
                    id={ratingErrorId}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {form.errors.rating}
                </p>
            ) : null}
            {form.errors.slope ? (
                <p
                    id={slopeErrorId}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {form.errors.slope}
                </p>
            ) : null}
        </form>
    );
}
