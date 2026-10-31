import { router } from '@inertiajs/react';
import { createElement } from 'react';
import { toast } from 'sonner';
import { ToastPill } from '@/components/ui/toaster';

/**
 * L'application sans réseau, côté page (spec `offline-mode`, design D4, D5) :
 *
 * - une visite vers une page sans copie reçoit du service worker un `503`
 *   `X-Offline: miss` ; la page courante reste et le message « Page
 *   indisponible hors ligne. » s'affiche, à la place de la fenêtre d'erreur
 *   d'Inertia ;
 * - une écriture (visite qui n'est pas un `GET`) qui finit en erreur réseau
 *   affiche « Hors ligne : rien n'a été envoyé. ». Inertia ne touche pas au
 *   formulaire : la saisie reste à l'écran ;
 * - l'état lu par le bandeau « Hors ligne » : `navigator.onLine` et la copie
 *   que le service worker vient de servir (message `served-from-cache`).
 *   Le client Inertia ne donne pas les en-têtes d'une réponse réussie : le
 *   message du service worker est la seule trace d'une copie. Une nouvelle
 *   visite oublie la copie (la réponse suivante dira si elle en vient), le
 *   retour du réseau aussi.
 */

export const PAGE_UNAVAILABLE = 'Page indisponible hors ligne.';
export const NOTHING_SENT = "Hors ligne : rien n'a été envoyé.";

/** Copie d'une page servie par le service worker : adresse absolue et instant de la copie. */
export type StoredCopy = { url: string; storedAt: string | null };

export type OfflineState = { online: boolean; copy: StoredCopy | null };

type ServiceWorkerMessage = {
    type?: unknown;
    url?: unknown;
    storedAt?: unknown;
};

const listeners = new Set<() => void>();
let state: OfflineState = { online: true, copy: null };
/** Écritures parties et pas encore finies : une erreur réseau leur revient. */
let pendingWrites = 0;

function update(next: OfflineState): void {
    if (next.online === state.online && next.copy === state.copy) {
        return;
    }

    state = next;
    listeners.forEach((listener) => listener());
}

/** Message éphémère d'erreur, une seule fois s'il revient avant de disparaître. */
function showError(message: string): void {
    toast.custom(
        (id) => createElement(ToastPill, { id, type: 'error', message }),
        { id: message },
    );
}

const isWrite = (method: string) => method.toLowerCase() !== 'get';

/**
 * Branche les écouteurs (à l'ouverture, avant le premier rendu, pour ne pas
 * manquer le message d'une ouverture à froid servie depuis la copie). Rend la
 * fonction qui les retire.
 */
export function installOfflineHandling(): () => void {
    state = { online: navigator.onLine, copy: null };
    pendingWrites = 0;

    const goOnline = () => update({ online: true, copy: null });
    const goOffline = () => update({ online: false, copy: state.copy });
    const onWorkerMessage = (event: Event) => {
        const data = (event as MessageEvent<ServiceWorkerMessage>).data;

        if (
            data?.type !== 'served-from-cache' ||
            typeof data.url !== 'string'
        ) {
            return;
        }

        update({
            online: state.online,
            copy: {
                url: data.url,
                storedAt:
                    typeof data.storedAt === 'string' ? data.storedAt : null,
            },
        });
    };

    window.addEventListener('online', goOnline);
    window.addEventListener('offline', goOffline);
    navigator.serviceWorker?.addEventListener('message', onWorkerMessage);

    const stops = [
        router.on('start', (event) => {
            if (isWrite(event.detail.visit.method)) {
                pendingWrites += 1;
            }

            update({ online: state.online, copy: null });
        }),
        router.on('finish', (event) => {
            if (isWrite(event.detail.visit.method)) {
                pendingWrites = Math.max(0, pendingWrites - 1);
            }
        }),
        router.on('httpException', (event) => {
            const { status, headers } = event.detail.response;

            if (status !== 503 || headers['x-offline'] !== 'miss') {
                return;
            }

            showError(PAGE_UNAVAILABLE);

            return false;
        }),
        router.on('networkError', () => {
            if (pendingWrites === 0) {
                return;
            }

            showError(NOTHING_SENT);

            return false;
        }),
    ];

    return () => {
        window.removeEventListener('online', goOnline);
        window.removeEventListener('offline', goOffline);
        navigator.serviceWorker?.removeEventListener(
            'message',
            onWorkerMessage,
        );
        stops.forEach((stop) => stop());
    };
}

/** Appelle `listener` à chaque changement de l'état hors ligne ; rend la fonction qui désabonne. */
export function subscribeOffline(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/** État courant, stable entre deux changements (pour `useSyncExternalStore`). */
export function offlineSnapshot(): OfflineState {
    return state;
}
