import { useSyncExternalStore } from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

function mediaQuery(): MediaQueryList | null {
    return typeof window.matchMedia === 'function'
        ? window.matchMedia(QUERY)
        : null;
}

function subscribe(onChange: () => void): () => void {
    const query = mediaQuery();
    query?.addEventListener('change', onChange);

    return () => query?.removeEventListener('change', onChange);
}

function snapshot(): boolean {
    return mediaQuery()?.matches ?? false;
}

/**
 * Vrai quand le système ou le navigateur demande de réduire les animations.
 * Pour ce qui s'anime en JavaScript et échappe à la règle CSS globale de
 * base.css (l'orbe d'attente, design D9).
 */
export function useReducedMotion(): boolean {
    return useSyncExternalStore(subscribe, snapshot, () => false);
}
