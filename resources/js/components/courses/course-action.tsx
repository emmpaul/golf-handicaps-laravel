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
 * Lien d'action du bloc d'un parcours (« Relancer la recherche », « Retirer la
 * rating », « Carte SGT ») : souligné, Geist 500, 13 px, gris, 40 px de haut
 * (spec « Mise en page de la page Parcours »).
 */
export const COURSE_LINK =
    'flex min-h-10 w-fit items-center text-link-13 text-ink-2 underline underline-offset-3 disabled:cursor-not-allowed disabled:text-ink-3';

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
    appearance = 'button',
}: {
    action: RouteDefinition<'post'>;
    data?: Record<string, string | number>;
    kind: CourseActionKind;
    label: string;
    accessibleName: string;
    /** Bouton à contour de 44 px, ou lien souligné du bloc (`COURSE_LINK`). */
    appearance?: 'button' | 'link';
}) {
    const { sending, setSending } = useContext(CourseActionContext);
    const props = {
        type: 'button' as const,
        disabled: sending !== null,
        'aria-label': accessibleName,
        onClick: () =>
            router.post(action, data, {
                preserveScroll: true,
                onStart: () => setSending(kind),
                onFinish: () => setSending(null),
            }),
    };

    return appearance === 'link' ? (
        <button {...props} className={COURSE_LINK}>
            {label}
        </button>
    ) : (
        <Button {...props} variant="outline" size="sm">
            {label}
        </Button>
    );
}

/** L'action en cours sur la ligne, pour son libellé d'attente et ses autres boutons. */
export function useCourseActionSending(): CourseActionKind | null {
    return useContext(CourseActionContext).sending;
}
