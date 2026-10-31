import { store } from '@/actions/App/Http/Controllers/ImportController';

/**
 * File des photos sur l'appareil (design D7) : toute photo préparée y entre,
 * puis part par `flush()`, une à une, de la plus ancienne à la plus récente.
 * Une photo ne quitte l'appareil qu'après une réponse du serveur qui la crée
 * (`201`, ou `200` pour une clé déjà reçue) ou la refuse pour elle-même
 * (`422`, ou `413` du serveur web pour une photo trop lourde), ou par
 * `remove()`. `401`, erreur réseau, `5xx`, toute autre réponse
 * et 60 s sans réponse arrêtent l'envoi et gardent les photos. Un `419` refait
 * un seul essai après un `GET` de la page courante, qui renouvelle le témoin
 * `XSRF-TOKEN`.
 */

const DB_NAME = 'golf-offline';
const DB_VERSION = 1;
const STORE = 'photos';
const BY_TAKEN_AT = 'takenAt';
const CHANNEL = 'golf-offline-photos';
const LOCK = 'golf-offline-photos';
const TIMEOUT_MS = 60_000;
const FILE_NAME = 'carte.jpg';
/** Refus d'un `422` dont le corps ne donne aucun message. */
const UNKNOWN_REFUSAL = 'Photo refusée par le serveur.';
/** `413` du serveur web, avant Laravel : le message de la règle `max` de la photo. */
const TOO_LARGE = 'La photo dépasse 20 Mo';

export type QueuedPhoto = {
    /** UUID tiré sur l'appareil, envoyé comme `upload_key`. */
    key: string;
    /** JPEG préparé par `decodePhoto`. */
    blob: Blob;
    /** Instant ISO 8601 de la mise en file. */
    takenAt: string;
};

export type Rejection = {
    key: string;
    takenAt: string;
    /** Refus du serveur : `error`, sinon la première erreur de validation. */
    message: string;
};

export type FlushOutcome = {
    /** Photos créées par le serveur pendant ce passage. */
    sent: number;
    sentKeys: string[];
    /** Photos refusées (`422`, `413`) et effacées de l'appareil, dans l'ordre d'envoi. */
    rejected: Rejection[];
    /** `401` : la session manque, l'application doit afficher `/login`. */
    login: boolean;
    /** Envoi arrêté avant la fin de la file (`401`, réseau, `5xx`, délai). */
    stopped: boolean;
};

type Result =
    | { kind: 'sent' }
    | { kind: 'rejected'; message: string }
    | { kind: 'login' }
    | { kind: 'stop' };

type Message = { type: 'changed'; sending: string | null };

const listeners = new Set<() => void>();
let channel: BroadcastChannel | null = null;
let database: Promise<IDBDatabase> | null = null;
let persistenceAsked = false;
/** Photo en cours d'envoi dans cet onglet. */
let sending: string | null = null;
/** Photo en cours d'envoi dans un autre onglet, d'après son dernier message. */
let sendingElsewhere: string | null = null;
/** Sans `navigator.locks`, les passages de cet onglet se suivent. */
let tail: Promise<unknown> = Promise.resolve();

function open(): Promise<IDBDatabase> {
    database ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            request.result
                .createObjectStore(STORE, { keyPath: 'key' })
                .createIndex(BY_TAKEN_AT, 'takenAt');
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return database;
}

/** Lance `work` dans une transaction et rend son résultat une fois la transaction terminée. */
async function transact<T>(
    mode: IDBTransactionMode,
    work: (photos: IDBObjectStore) => IDBRequest<T>,
): Promise<T> {
    const transaction = (await open()).transaction(STORE, mode);
    const request = work(transaction.objectStore(STORE));

    return new Promise((resolve, reject) => {
        transaction.oncomplete = () => resolve(request.result);
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
}

function connect(): BroadcastChannel | null {
    if (channel === null && typeof BroadcastChannel !== 'undefined') {
        channel = new BroadcastChannel(CHANNEL);
        channel.onmessage = (event: MessageEvent<Message>) => {
            sendingElsewhere = event.data.sending;
            tell();
        };
    }

    return channel;
}

function tell(): void {
    listeners.forEach((listener) => listener());
}

/** Prévient les abonnés de cet onglet et des autres. */
function changed(): void {
    connect()?.postMessage({ type: 'changed', sending } satisfies Message);
    tell();
}

function askPersistence(): void {
    if (persistenceAsked) {
        return;
    }

    persistenceAsked = true;
    void navigator.storage?.persist?.().catch(() => false);
}

/** Garde la photo sur l'appareil et rend sa clé et l'instant de la mise en file. */
export async function enqueue(
    blob: Blob,
): Promise<{ key: string; takenAt: string }> {
    const photo: QueuedPhoto = {
        key: crypto.randomUUID(),
        blob,
        takenAt: new Date().toISOString(),
    };

    await transact('readwrite', (photos) => photos.add(photo));
    askPersistence();
    changed();

    return { key: photo.key, takenAt: photo.takenAt };
}

/** Photos gardées, de la plus ancienne à la plus récente. */
export function list(): Promise<QueuedPhoto[]> {
    return transact('readonly', (photos) => photos.index(BY_TAKEN_AT).getAll());
}

/** Efface la photo de l'appareil sans rien envoyer. */
export async function remove(key: string): Promise<void> {
    await transact('readwrite', (photos) => photos.delete(key));
    changed();
}

/**
 * Appelle `listener` à chaque changement de la file ou de la photo en cours
 * d'envoi, dans cet onglet ou un autre. Rend la fonction qui désabonne.
 */
export function subscribe(listener: () => void): () => void {
    connect();
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/** Clé de la photo en cours d'envoi, dans cet onglet ou un autre ; `null` sinon. */
export function sendingKey(): string | null {
    return sending ?? sendingElsewhere;
}

/**
 * Envoie les photos gardées, une à une, de la plus ancienne à la plus récente.
 * Un seul passage à la fois entre onglets (`navigator.locks`, sinon un passage
 * après l'autre dans l'onglet) ; un passage qui attend son tour n'envoie que ce
 * qui reste. Le résultat ne compte que ce passage.
 */
export function flush(): Promise<FlushOutcome> {
    if (navigator.locks) {
        return navigator.locks.request(LOCK, drain);
    }

    const run = tail.then(drain);
    tail = run.catch(() => undefined);

    return run;
}

async function drain(): Promise<FlushOutcome> {
    const outcome: FlushOutcome = {
        sent: 0,
        sentKeys: [],
        rejected: [],
        login: false,
        stopped: false,
    };

    for (;;) {
        const [photo] = await list();

        if (photo === undefined) {
            return outcome;
        }

        sending = photo.key;
        changed();
        let result: Result;

        // Une erreur d'IndexedDB ne doit pas laisser la photo « en cours
        // d'envoi » : « Retirer » resterait désactivé dans chaque onglet.
        try {
            result = await send(photo);

            if (result.kind === 'sent' || result.kind === 'rejected') {
                await transact('readwrite', (photos) =>
                    photos.delete(photo.key),
                );
            }
        } finally {
            sending = null;
            changed();
        }

        if (result.kind === 'sent') {
            outcome.sent += 1;
            outcome.sentKeys.push(photo.key);
        } else if (result.kind === 'rejected') {
            outcome.rejected.push({
                key: photo.key,
                takenAt: photo.takenAt,
                message: result.message,
            });
        } else {
            outcome.login = result.kind === 'login';
            outcome.stopped = true;

            return outcome;
        }
    }
}

async function send(photo: QueuedPhoto, retried = false): Promise<Result> {
    const body = new FormData();
    body.append('photo', photo.blob, FILE_NAME);
    body.append('upload_key', photo.key);

    let response: { status: number; text: string };

    try {
        response = await within(TIMEOUT_MS, async (signal) => {
            const reply = await fetch(store.url(), {
                method: 'POST',
                body,
                headers: headers(),
                signal,
            });

            return { status: reply.status, text: await reply.text() };
        });
    } catch {
        return { kind: 'stop' };
    }

    switch (response.status) {
        case 200:
        case 201:
            return { kind: 'sent' };
        case 422:
            return { kind: 'rejected', message: refusal(response.text) };
        case 413:
            return { kind: 'rejected', message: TOO_LARGE };
        case 401:
            return { kind: 'login' };
        case 419:
            return retried ? { kind: 'stop' } : renewTokenThenSend(photo);
        default:
            return { kind: 'stop' };
    }
}

/** `GET` de la page courante : le serveur y repose le témoin `XSRF-TOKEN`. */
async function renewTokenThenSend(photo: QueuedPhoto): Promise<Result> {
    try {
        await within(TIMEOUT_MS, (signal) =>
            fetch(window.location.href, { method: 'GET', signal }),
        );
    } catch {
        return { kind: 'stop' };
    }

    return send(photo, true);
}

/** Lance `work` avec un signal qui l'interrompt après `ms` millisecondes. */
async function within<T>(
    ms: number,
    work: (signal: AbortSignal) => Promise<T>,
): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), ms);

    try {
        return await work(controller.signal);
    } finally {
        clearTimeout(timer);
    }
}

function headers(): Record<string, string> {
    const token = xsrfToken();

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token === null ? {} : { 'X-XSRF-TOKEN': token }),
    };
}

function xsrfToken(): string | null {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie === undefined
        ? null
        : decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
}

/** `{ "error": … }` (lecture désactivée), sinon la première erreur de validation. */
function refusal(text: string): string {
    try {
        const body = JSON.parse(text) as {
            error?: unknown;
            errors?: Record<string, unknown[]>;
            message?: unknown;
        };
        const first = Object.values(body.errors ?? {}).flat()[0];

        for (const candidate of [body.error, first, body.message]) {
            if (typeof candidate === 'string') {
                return candidate;
            }
        }
    } catch {
        // Corps illisible : message générique.
    }

    return UNKNOWN_REFUSAL;
}
