import { useEffect, useState, useSyncExternalStore } from 'react';
import type { CSSProperties } from 'react';

const REDUCE = '(prefers-reduced-motion: reduce)';

/** The query of reduced motion, or null where the browser has no matchMedia (jsdom). */
function reduceQuery(): MediaQueryList | null {
    return typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function'
        ? window.matchMedia(REDUCE)
        : null;
}

function subscribe(onChange: () => void): () => void {
    const query = reduceQuery();
    query?.addEventListener('change', onChange);

    return () => query?.removeEventListener('change', onChange);
}

/** Whether the device asks for reduced motion now. */
export function prefersReducedMotion(): boolean {
    return reduceQuery()?.matches ?? false;
}

/** Whether the device asks for reduced motion, kept up to date. */
export function useReducedMotion(): boolean {
    return useSyncExternalStore(subscribe, prefersReducedMotion, () => false);
}

const NOT_ENTERED = Symbol('not entered');

/**
 * False on the first image, true two frames later, as the prototype does with
 * two requestAnimationFrame (D8): an element drawn in its initial state then
 * moves to its final state through its transition. Starts again when `key`
 * changes. Under reduced motion, true from the first image: the final state
 * without movement.
 */
export function useEntered(key: unknown = null): boolean {
    const reduced = useReducedMotion();
    const [enteredKey, setEnteredKey] = useState<unknown>(NOT_ENTERED);

    useEffect(() => {
        if (reduced) {
            return;
        }

        let second = 0;
        const first = requestAnimationFrame(() => {
            second = requestAnimationFrame(() => setEnteredKey(key));
        });

        return () => {
            cancelAnimationFrame(first);
            cancelAnimationFrame(second);
        };
    }, [key, reduced]);

    return reduced || Object.is(enteredKey, key);
}

/** Entrance of a page (spec « Entrée d'une page »): 10 px lower and transparent, then in place. */
export function pageEnterStyle(
    entered: boolean,
    reduced: boolean,
): CSSProperties {
    return {
        opacity: entered ? 1 : 0,
        transform: entered ? 'translateY(0)' : 'translateY(10px)',
        transition: reduced
            ? undefined
            : 'opacity 320ms ease, transform 420ms cubic-bezier(0.2, 0.8, 0.2, 1)',
    };
}

/** Delay before the new value replaces the former one (« Changement de joueur »). */
const SWAP_DELAY_MS = 140;

/**
 * A value that changes with `key` in two steps (spec « Changement de joueur
 * sur la page Handicaps »): the former value fades out 10 px up, and the new
 * one replaces it 140 ms later, coming down to its place. Under reduced
 * motion, the new value at once.
 */
export function useSwap<T>(
    key: unknown,
    value: T,
): { shown: T; style: CSSProperties } {
    const reduced = useReducedMotion();
    const [current, setCurrent] = useState({ key, value });

    useEffect(() => {
        if (Object.is(current.key, key)) {
            return;
        }

        const timer = setTimeout(
            () => setCurrent({ key, value }),
            reduced ? 0 : SWAP_DELAY_MS,
        );

        return () => clearTimeout(timer);
    }, [current.key, key, value, reduced]);

    const out = !reduced && !Object.is(current.key, key);

    return {
        shown: out ? current.value : value,
        style: {
            opacity: out ? 0 : 1,
            transform: out ? 'translateY(-10px)' : 'translateY(0)',
            transition: reduced
                ? undefined
                : 'opacity 250ms ease, transform 350ms cubic-bezier(0.2, 0.8, 0.2, 1)',
        },
    };
}
