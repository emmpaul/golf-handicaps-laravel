import { router } from '@inertiajs/react';
import { createElement } from 'react';
import { toast } from 'sonner';
import { ToastPill } from '@/components/ui/toaster';
import { enqueue, flush, list } from '@/lib/photo-queue';
import type { FlushOutcome, Rejection } from '@/lib/photo-queue';
import { index as importIndex } from '@/routes/import';
import { login } from '@/routes';

/**
 * Envoi des photos gardées sur l'appareil, par la page (design D7, spec
 * `scorecard-import`). `startPhotoSync` lance l'envoi à l'ouverture de
 * l'application, au retour du réseau (`online`), au retour au premier plan
 * (`visibilitychange`) et après la saisie du code (première page affichée
 * après `/login`). Chaque envoi passe par `sendPendingPhotos`, qui tire les
 * conséquences de la réponse : `/login` sur un `401`, message « N photos
 * envoyées. » et rechargement de la liste des imports sur l'écran Importer,
 * refus du serveur gardés pour l'écran Importer.
 */

export const QUEUED_MESSAGE =
    "Lecture en file d'attente. Tu peux envoyer la photo suivante.";
export const KEPT_MESSAGE =
    'Photo gardée sur le téléphone. Elle partira au retour du réseau.';
export const UNSAVED_MESSAGE = 'Photo non gardée sur le téléphone. Réessaie.';

/** « 1 photo envoyée. », « 2 photos envoyées. » */
export function sentMessage(count: number): string {
    return count === 1 ? '1 photo envoyée.' : `${count} photos envoyées.`;
}

/**
 * Sort d'une photo choisie sur l'écran Importer : `login` quand un `401` a
 * arrêté l'envoi (la photo reste gardée, `/login` s'ouvre), `unsaved` quand
 * l'appareil n'a pas pu la garder (rien n'est parti).
 */
export type UploadFate = 'sent' | 'kept' | 'rejected' | 'login' | 'unsaved';

export type SyncState = {
    /** Photo que l'écran Importer est en train d'envoyer lui-même. */
    uploading: string | null;
    /** Photos refusées par le serveur, à montrer avec « Saisie manuelle ». */
    refusals: Rejection[];
};

const listeners = new Set<() => void>();
let state: SyncState = { uploading: null, refusals: [] };
/**
 * Sort des photos de l'écran Importer réglé par un envoi de cet onglet, lu
 * une fois par `uploadPhoto` puis oublié.
 */
const settled = new Map<string, 'sent' | 'rejected'>();

function update(next: Partial<SyncState>): void {
    state = { ...state, ...next };
    listeners.forEach((listener) => listener());
}

export function subscribeSync(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/** État courant, stable entre deux changements (pour `useSyncExternalStore`). */
export function syncSnapshot(): SyncState {
    return state;
}

/** Oublie les refus montrés : l'écran Importer les efface en se fermant. */
export function clearRefusals(): void {
    if (state.refusals.length > 0) {
        update({ refusals: [] });
    }
}

function showSuccess(message: string): void {
    toast.custom((id) =>
        createElement(ToastPill, { id, type: 'success', message }),
    );
}

function showError(message: string): void {
    toast.custom(
        (id) => createElement(ToastPill, { id, type: 'error', message }),
        { id: message },
    );
}

const onImportScreen = () => window.location.pathname === importIndex.url();

/**
 * Envoie les photos en attente, sauf hors ligne (rien ne partirait). `ownKey`
 * est la photo que l'écran Importer vient de choisir : envoyée seule, elle
 * garde le message « Lecture en file d'attente… » d'avant ce changement.
 */
export async function sendPendingPhotos(
    ownKey?: string,
): Promise<FlushOutcome | null> {
    if (!navigator.onLine) {
        return null;
    }

    const outcome = await flush();

    if (ownKey !== undefined || state.uploading !== null) {
        outcome.sentKeys.forEach((key) => settled.set(key, 'sent'));
        outcome.rejected.forEach(({ key }) => settled.set(key, 'rejected'));
    }

    if (outcome.rejected.length > 0) {
        update({ refusals: [...state.refusals, ...outcome.rejected] });
    }

    if (outcome.sent > 0) {
        showSuccess(
            outcome.sent === 1 && outcome.sentKeys[0] === ownKey
                ? QUEUED_MESSAGE
                : sentMessage(outcome.sent),
        );
    }

    if (outcome.sent > 0 && onImportScreen()) {
        router.reload({ only: ['imports', 'pendingImports'] });
    }

    if (outcome.login) {
        router.visit(login.url());
    }

    return outcome;
}

/**
 * Photo préparée sur l'écran Importer : gardée sur l'appareil, puis envoyée
 * aussitôt avec du réseau (`onSending` juste avant). Le sort se lit dans
 * l'issue des envois de cet onglet, sinon dans la file : une photo encore
 * gardée est `kept`, une photo partie par un autre onglet est `sent`. Si
 * l'appareil ne peut pas la garder (IndexedDB absente, quota atteint), le
 * toast d'erreur « Photo non gardée sur le téléphone. Réessaie. » et rien
 * n'est envoyé.
 */
export async function uploadPhoto(
    blob: Blob,
    onSending: () => void,
): Promise<UploadFate> {
    update({ refusals: [] });
    let key: string;

    try {
        ({ key } = await enqueue(blob));
    } catch {
        showError(UNSAVED_MESSAGE);

        return 'unsaved';
    }

    if (!navigator.onLine) {
        return 'kept';
    }

    update({ uploading: key });
    onSending();

    let outcome: FlushOutcome | null;

    try {
        outcome = await sendPendingPhotos(key);
    } finally {
        update({ uploading: null });
    }

    if (outcome?.login) {
        return 'login';
    }

    const fate = settled.get(key);
    settled.delete(key);

    if (fate !== undefined) {
        return fate;
    }

    return (await list()).some((photo) => photo.key === key) ? 'kept' : 'sent';
}

/** Envoi lancé par un déclencheur : une erreur d'IndexedDB n'arrête pas l'application. */
function sendInBackground(): void {
    if (window.location.pathname === login.url()) {
        return;
    }

    sendPendingPhotos().catch(() => undefined);
}

/** Branche les déclencheurs d'envoi et envoie tout de suite ; rend la fonction qui les retire. */
export function startPhotoSync(): () => void {
    let previousPath = window.location.pathname;
    const onVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            sendInBackground();
        }
    };

    window.addEventListener('online', sendInBackground);
    document.addEventListener('visibilitychange', onVisibilityChange);
    const stopNavigate = router.on('navigate', (event) => {
        const path = new URL(event.detail.page.url, window.location.href)
            .pathname;

        if (previousPath === login.url() && path !== login.url()) {
            sendInBackground();
        }

        previousPath = path;
    });

    sendInBackground();

    return () => {
        window.removeEventListener('online', sendInBackground);
        document.removeEventListener('visibilitychange', onVisibilityChange);
        stopNavigate();
    };
}
