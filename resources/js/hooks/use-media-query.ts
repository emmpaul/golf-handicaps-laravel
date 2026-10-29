import { useCallback, useSyncExternalStore } from 'react';

function mediaQuery(query: string): MediaQueryList | null {
    return typeof window.matchMedia === 'function'
        ? window.matchMedia(query)
        : null;
}

/** Vrai tant que la requête média `query` correspond ; faux sans `matchMedia`. */
export function useMediaQuery(query: string): boolean {
    const subscribe = useCallback(
        (onChange: () => void) => {
            const list = mediaQuery(query);
            list?.addEventListener('change', onChange);

            return () => list?.removeEventListener('change', onChange);
        },
        [query],
    );

    return useSyncExternalStore(
        subscribe,
        () => mediaQuery(query)?.matches ?? false,
        () => false,
    );
}
