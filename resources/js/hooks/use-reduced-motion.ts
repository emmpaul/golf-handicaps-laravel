import { useMediaQuery } from './use-media-query';

/**
 * Vrai quand le système ou le navigateur demande de réduire les animations.
 * Pour ce qui s'anime en JavaScript et échappe à la règle CSS globale de
 * base.css (l'orbe d'attente, design D9).
 */
export function useReducedMotion(): boolean {
    return useMediaQuery('(prefers-reduced-motion: reduce)');
}
