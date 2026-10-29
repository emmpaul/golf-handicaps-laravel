// Seul fichier qui importe thinking-orbs : changer d'indicateur ne touche que lui.
import { ThinkingOrb } from 'thinking-orbs';
import type { OrbState } from 'thinking-orbs';

import { useReducedMotion } from '@/hooks/use-reduced-motion';

/**
 * Animation de l'orbe selon le type d'attente : `prepare` pour la photo
 * décodée dans le navigateur, `queued` et `reading` pour un import qui attend
 * sa lecture.
 */
export const ORB_FOR = {
    prepare: 'working',
    send: 'working',
    save: 'working',
    queued: 'breathing',
    reading: 'searching',
} as const satisfies Record<string, OrbState>;

export type PendingKind = keyof typeof ORB_FOR;

/**
 * Orbe seule, décorative : `aria-hidden` écarte son libellé anglais par défaut.
 * `theme="dark"` : sa variante sombre quel que soit le réglage de l'appareil,
 * comme l'application, sombre seule (spec loading-feedback, DO24). Elle
 * s'anime en JavaScript, hors de la règle CSS globale de mouvement réduit :
 * `paused` la fige quand le système le demande.
 */
export function PendingOrb({ state }: { state: OrbState }) {
    const reducedMotion = useReducedMotion();

    return (
        <ThinkingOrb
            state={state}
            size={20}
            theme="dark"
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
 * du flux, elle n'ajoute ni espace ni décalage. Le texte prend le style des
 * métas du design : Geist Mono 500, 12 px, `ink-2`.
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
                    : 'inline-flex items-center gap-2 font-mono text-meta-12 text-ink-2'
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
