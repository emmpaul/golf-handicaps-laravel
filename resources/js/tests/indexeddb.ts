import 'fake-indexeddb/auto';
import { IDBFactory } from 'fake-indexeddb';

/*
 * IndexedDB for Vitest: jsdom has none, so `fake-indexeddb` stands in. It
 * copies every stored value with the global `structuredClone`, which knows
 * Node's own Blob but not jsdom's: a jsdom Blob comes back as an empty object,
 * and `FormData.append` then refuses it. A browser keeps a Blob through
 * IndexedDB, so the copy below keeps Blob instances as they are and copies
 * plain objects field by field; every other value goes to the real
 * `structuredClone`.
 */
const nativeClone = globalThis.structuredClone;

function cloneKeepingBlobs<T>(value: T): T {
    if (value instanceof Blob) {
        return value;
    }

    if (
        value !== null &&
        typeof value === 'object' &&
        Object.getPrototypeOf(value) === Object.prototype
    ) {
        return Object.fromEntries(
            Object.entries(value).map(([field, item]) => [
                field,
                cloneKeepingBlobs(item),
            ]),
        ) as T;
    }

    return nativeClone(value);
}

globalThis.structuredClone = cloneKeepingBlobs as typeof structuredClone;

/** Replaces the IndexedDB of the page by an empty one. */
export function resetIndexedDb(): void {
    globalThis.indexedDB = new IDBFactory();
}
