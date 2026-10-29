import { router } from '@inertiajs/react';
import { createContext, useContext } from 'react';
import { Button } from '@/components/ui/button';
import type { RouteDefinition } from '@/wayfinder';

/** Libellés d'attente d'une ligne de `/courses` (spec `loading-feedback`). */
export const COURSE_ACTION_PENDING = {
    confirm: 'Confirmation de la rating…',
    relaunch: 'Relance de la recherche…',
} as const;

export type CourseActionKind = keyof typeof COURSE_ACTION_PENDING;

/**
 * État d'envoi d'une ligne de `/courses` : l'action en cours, ou `null`. Une
 * ligne en fournit un par `CourseActionScope` : l'attente d'une ligne ne
 * touche pas les autres (spec `loading-feedback`).
 */
type CourseActionState = {
    sending: CourseActionKind | null;
    setSending: (kind: CourseActionKind | null) => void;
};

const CourseActionContext = createContext<CourseActionState>({
    sending: null,
    setSending: () => {},
});

export const CourseActionScope = CourseActionContext.Provider;

/**
 * Bouton d'une action de la recherche (confirmer, retenir un tee, choisir un
 * candidat, relancer) : `router.post` sur l'action Wayfinder, sans perdre le
 * défilement. Pendant l'envoi, sa ligne affiche le libellé d'attente de `kind`
 * et désactive tous ses boutons. Le serveur répond par un toast (« Rating
 * confirmée. », « Recherche relancée. » ou le refus).
 */
export function CourseAction({
    action,
    data = {},
    kind,
    label,
    accessibleName,
    variant = 'outline',
}: {
    action: RouteDefinition<'post'>;
    data?: Record<string, string | number>;
    kind: CourseActionKind;
    label: string;
    accessibleName: string;
    variant?: 'default' | 'outline';
}) {
    const { sending, setSending } = useContext(CourseActionContext);

    return (
        <Button
            type="button"
            variant={variant}
            disabled={sending !== null}
            aria-label={accessibleName}
            onClick={() =>
                router.post(action, data, {
                    preserveScroll: true,
                    onStart: () => setSending(kind),
                    onFinish: () => setSending(null),
                })
            }
        >
            {label}
        </Button>
    );
}

/** L'action en cours sur la ligne, pour son libellé d'attente et ses autres boutons. */
export function useCourseActionSending(): CourseActionKind | null {
    return useContext(CourseActionContext).sending;
}
