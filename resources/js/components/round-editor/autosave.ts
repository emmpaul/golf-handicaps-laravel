import { useCallback, useEffect, useRef, useState } from 'react';
import ImportCorrectionController from '@/actions/App/Http/Controllers/ImportCorrectionController';
import { toRoundCard } from './form-state';
import type { EditorState } from './form-state';

/** Délai après la dernière modification, puis entre deux essais après un échec. */
const DEBOUNCE_MS = 1000;
const RETRY_MS = 5000;

const NOT_SAVED = 'Modifications non enregistrées';
/** Envoi en cours : seul état accompagné de l'orbe. */
export const SAVING = 'Enregistrement…';

/** Texte de l'indicateur d'enregistrement ; `error` : à afficher en couleur d'erreur. */
export type AutosaveStatus = { message: string; error: boolean };

const IDLE: AutosaveStatus = { message: '', error: false };

type AutosaveOptions = {
    importId: string;
    /** État d'ouverture : tant que l'écran n'en diffère pas, rien ne part. */
    initial: EditorState;
    fetch: (url: string, init: RequestInit) => Promise<Response>;
    window: Pick<EventTarget, 'addEventListener' | 'removeEventListener'>;
    document: Pick<EventTarget, 'addEventListener' | 'removeEventListener'> & {
        visibilityState: DocumentVisibilityState;
    };
    onStatus: (status: AutosaveStatus) => void;
};

/** La correction enregistrée (design D7) : `{ version: 1, card }`, la carte sans les `key`. */
const serialize = (state: EditorState) =>
    JSON.stringify({ version: 1, card: toRoundCard(state) });

/**
 * Sauvegarde différée de la correction d'un import (design D7) : un `PUT` 1 s
 * après la dernière modification, un seul envoi à la fois, et un envoi
 * immédiat avec `keepalive` quand la page se cache ou se ferme. Une erreur
 * réseau ou une 5xx réessaie 5 s plus tard ; une 409 (message du serveur), une
 * 401, une 404 ou une 422 arrêtent la sauvegarde.
 */
export function createCorrectionAutosave(options: AutosaveOptions) {
    const url = ImportCorrectionController.url(options.importId);
    let latest = serialize(options.initial);
    let saved = latest;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let inFlight = false;
    // Une modification attend la fin de l'envoi en cours.
    let queued = false;
    // Arrêt définitif (refus du serveur, démontage) ; la pause, elle, se lève avec `resume`.
    let stopped = false;
    let paused = false;

    function clearTimer() {
        if (timer !== null) {
            clearTimeout(timer);
        }

        timer = null;
    }

    function schedule(delay: number) {
        clearTimer();
        timer = setTimeout(() => {
            timer = null;
            send(false);
        }, delay);
    }

    function stop(message: string) {
        stopped = true;
        options.onStatus({ message, error: true });
    }

    async function refusal(response: Response): Promise<string> {
        if (response.status !== 409) {
            return NOT_SAVED;
        }

        const reply: unknown = await response.json().catch(() => null);
        const error =
            typeof reply === 'object' && reply !== null && 'error' in reply
                ? reply.error
                : null;

        return typeof error === 'string' ? error : NOT_SAVED;
    }

    async function settle(body: string, response: Response | null) {
        inFlight = false;

        if (stopped) {
            return;
        }

        if (response === null || response.status >= 500) {
            options.onStatus({ message: NOT_SAVED, error: true });
            schedule(RETRY_MS);

            return;
        }

        if (!response.ok) {
            stop(await refusal(response));

            return;
        }

        saved = body;
        options.onStatus({ message: 'Enregistré', error: false });

        if (queued) {
            queued = false;
            send(false);
        }
    }

    function send(keepalive: boolean) {
        if (stopped || paused || latest === saved) {
            return;
        }

        if (inFlight && !keepalive) {
            queued = true;

            return;
        }

        const body = latest;
        inFlight = true;
        options.onStatus({ message: SAVING, error: false });
        options
            .fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body,
                keepalive,
            })
            .then(
                (response) => settle(body, response),
                () => settle(body, null),
            );
    }

    /** Sortie de page : l'envoi en attente part tout de suite, sans attendre le délai. */
    function flush() {
        if (timer === null && !queued) {
            return;
        }

        clearTimer();
        queued = false;
        send(true);
    }

    const onVisibility = () => {
        if (options.document.visibilityState === 'hidden') {
            flush();
        }
    };
    options.window.addEventListener('pagehide', flush);
    options.document.addEventListener('visibilitychange', onVisibility);

    return {
        change(state: EditorState) {
            const next = serialize(state);

            // Comparée par valeur : un rendu sans modification ne relance pas le délai.
            if (stopped || next === latest) {
                return;
            }

            latest = next;

            if (paused) {
                return;
            }

            if (latest === saved && !inFlight) {
                clearTimer();

                return;
            }

            schedule(DEBOUNCE_MS);
        },
        /** Enregistrement du round en cours : rien ne part, les modifications sont retenues. */
        pause() {
            paused = true;
            queued = false;
            clearTimer();
        },
        /** Round non enregistré : la sauvegarde reprend, l'état non envoyé part tout de suite. */
        resume() {
            if (!paused) {
                return;
            }

            paused = false;
            send(false);
        },
        /** Démontage (navigation dans l'app) : envoie l'attente, puis cesse d'écouter la page. */
        dispose() {
            flush();
            stopped = true;
            queued = false;
            clearTimer();
            options.window.removeEventListener('pagehide', flush);
            options.document.removeEventListener(
                'visibilitychange',
                onVisibility,
            );
        },
    };
}

/** Jeton CSRF du cookie `XSRF-TOKEN` posé par Laravel, attendu dans `X-XSRF-TOKEN`. */
function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

/**
 * Branche la sauvegarde différée sur l'état de l'éditeur. `importId` à `null` :
 * désactivée (saisie manuelle, correction d'un round). `pause` : avant
 * d'enregistrer le round ; `resume` : si le round n'a pas été enregistré.
 */
export function useCorrectionAutosave(
    importId: string | null,
    state: EditorState,
) {
    const [status, setStatus] = useState(IDLE);
    const opening = useRef(state);
    const controller = useRef<ReturnType<
        typeof createCorrectionAutosave
    > | null>(null);

    useEffect(() => {
        if (importId === null) {
            return;
        }

        const autosave = createCorrectionAutosave({
            importId,
            initial: opening.current,
            fetch: (url, init) => {
                const headers = new Headers(init.headers);
                headers.set('X-XSRF-TOKEN', xsrfToken());

                return fetch(url, {
                    ...init,
                    credentials: 'same-origin',
                    headers,
                });
            },
            window,
            document,
            onStatus: setStatus,
        });
        controller.current = autosave;

        return () => {
            autosave.dispose();
            controller.current = null;
        };
    }, [importId]);

    useEffect(() => {
        controller.current?.change(state);
    }, [state]);

    const pause = useCallback(() => controller.current?.pause(), []);
    const resume = useCallback(() => controller.current?.resume(), []);

    return { status, pause, resume };
}
