import type { CacheForOption, UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';

/**
 * Règle de fraîcheur des pages préchargées (spec « Sections préchargées »,
 * design D5) : fraîche 30 s, puis affichée et rechargée en arrière-plan
 * jusqu'à 5 min, redemandée au-delà.
 */
export const PREFETCH_CACHE_FOR: CacheForOption[] = ['30s', '5m'];

/**
 * Précharge une page en GET avec la règle de fraîcheur commune. Les options
 * de visite sont celles d'un clic sur un `Link` simple : la visite qui suit
 * trouve donc la copie préchargée.
 */
export function prefetchPage(href: string): void {
    router.prefetch(href, { method: 'get' }, { cacheFor: PREFETCH_CACHE_FOR });
}

/**
 * Props d'un lien de liste (spec « Liens de liste préchargés », design D5) :
 * préchargé quand le pointeur s'arrête dessus (`hover`) ou dès que le doigt
 * le touche (`touchstart`). Inertia 3.9.1 ne lie le mode `click` qu'à
 * `mousedown`, qu'un toucher n'émet qu'après le relâchement : le toucher est
 * donc écouté ici.
 */
export function listLinkPrefetch(href: string | UrlMethodPair) {
    const url = typeof href === 'string' ? href : href.url;

    return {
        prefetch: 'hover' as const,
        cacheFor: PREFETCH_CACHE_FOR,
        onTouchStart: () => prefetchPage(url),
    };
}

/**
 * Oublie toutes les pages préchargées après une écriture réussie (spec
 * « Données à jour après une écriture », design D5). `start` retient les
 * visites en cours qui ne sont pas des GET, `finish` les oublie. Le cache est
 * vidé à `beforeUpdate`, quand la réponse d'une écriture arrive sans erreur de
 * validation : avant l'affichage de la nouvelle page, dont les onglets
 * préchargent aussitôt les sections. `success` vient après cet affichage
 * (`Response.process` d'Inertia 3.9.1) et effacerait ces préchargements. Une
 * écriture refusée par la validation revient avec `errors` et garde le cache.
 * Rend la fonction qui retire les écouteurs.
 */
export function flushPrefetchAfterWrites(): () => void {
    const writes = new Set<string>();
    const listeners = [
        router.on('start', (event) => {
            if (event.detail.visit.method !== 'get') {
                writes.add(event.detail.visit.id);
            }
        }),
        router.on('beforeUpdate', (event) => {
            const errors = event.detail.page.props.errors ?? {};

            if (writes.size > 0 && Object.keys(errors).length === 0) {
                router.flushAll();
            }
        }),
        router.on('finish', (event) => {
            writes.delete(event.detail.visit.id);
        }),
    ];

    return () => listeners.forEach((remove) => remove());
}
