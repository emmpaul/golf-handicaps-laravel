// Seul fichier qui importe thinking-orbs : changer d'indicateur ne touche que lui.
import { ThinkingOrb } from 'thinking-orbs';
import type { OrbState } from 'thinking-orbs';

import { useReducedMotion } from '@/hooks/use-reduced-motion';

/**
 * Animation de l'orbe selon le type d'attente. Les attentes de l'import
 * (`queued`, `reading`) arrivent avec scorecard-ai-import (design D9).
 */
export const ORB_FOR = {
    send: 'working',
    save: 'working',
} as const satisfies Record<string, OrbState>;

export type PendingKind = keyof typeof ORB_FOR;

/**
 * Orbe seule, décorative : `aria-hidden` écarte son libellé anglais par défaut.
 * `theme="auto"` : elle suit le mode sombre du système, comme le reste de
 * l'application. Elle s'anime en JavaScript, hors de la règle CSS globale de
 * mouvement réduit : `paused` la fige quand le système le demande.
 */
export function PendingOrb({ state }: { state: OrbState }) {
    const reducedMotion = useReducedMotion();

    return (
        <ThinkingOrb
            state={state}
            size={20}
            theme="auto"
            paused={reducedMotion}
            aria-hidden
            role="presentation"
        />
    );
}

/**
 * Région `role="status"` toujours montée : orbe et texte court pendant
 * l'attente (`label`), vide au repos (`null`). Un lecteur d'écran n'annonce
 * pas toujours une région insérée déjà remplie (VoiceOver) : on la monte avant
 * l'attente et on n'en change que le contenu. Vide, elle est `sr-only` : hors
 * du flux, elle n'ajoute ni espace ni décalage.
 */
export function Pending({
    label,
    orb,
}: {
    label: string | null;
    orb: PendingKind;
}) {
    return (
        <span
            role="status"
            className={
                label === null
                    ? 'sr-only'
                    : 'inline-flex items-center gap-2 text-sm text-muted-foreground'
            }
        >
            {label === null ? null : (
                <>
                    <PendingOrb state={ORB_FOR[orb]} />
                    <span>{label}</span>
                </>
            )}
        </span>
    );
}
