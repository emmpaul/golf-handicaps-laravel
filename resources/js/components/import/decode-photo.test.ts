import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { decodePhoto } from '@/components/import/decode-photo';

// Spec scorecard-import « Envoi d'une photo » and design D8: the photo is decoded
// by the browser (`createImageBitmap`) and encoded again as a JPEG of 3000 px
// at most on its longer side. jsdom decodes no image and draws on no canvas:
// `createImageBitmap` is simulated by the size of the photo, and the canvas
// records the size it is given and the type it is asked to encode to.
// Mutations: the bound applied to the width only
// (`Math.min(1, 3000 / bitmap.width)`) → the portrait photo stays 3000×4000
// and goes red; `canvas.toBlob(…, 'image/png')` → the type test goes red;
// the rejection of createImageBitmap swallowed (resolving with the file) →
// the unreadable test goes red.

const encoded: { width: number; height: number; type: string }[] = [];

function decodesAs(width: number, height: number) {
    vi.stubGlobal(
        'createImageBitmap',
        vi.fn(async () => ({ width, height, close: () => {} })),
    );
}

beforeEach(() => {
    encoded.length = 0;
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockImplementation(
        () => ({ drawImage: () => {} }) as unknown as CanvasRenderingContext2D,
    );
    vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation(
        function (this: HTMLCanvasElement, callback, type) {
            encoded.push({
                width: this.width,
                height: this.height,
                type: type ?? '',
            });
            callback(new Blob(['jpeg'], { type }));
        },
    );
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const photo = () => new Blob(['photo'], { type: 'image/heic' });

describe('decodePhoto', () => {
    it('turns a 4000×3000 photo into a JPEG of 3000×2250', async () => {
        decodesAs(4000, 3000);

        const blob = await decodePhoto(photo());

        expect(encoded).toEqual([
            { width: 3000, height: 2250, type: 'image/jpeg' },
        ]);
        expect(blob.type).toBe('image/jpeg');
    });

    it('bounds a 3000×4000 portrait photo by its height: 2250×3000', async () => {
        decodesAs(3000, 4000);

        await decodePhoto(photo());

        expect(encoded).toEqual([
            { width: 2250, height: 3000, type: 'image/jpeg' },
        ]);
    });

    it('keeps a 1200×900 photo at its size', async () => {
        decodesAs(1200, 900);

        await decodePhoto(photo());

        expect(encoded).toEqual([
            { width: 1200, height: 900, type: 'image/jpeg' },
        ]);
    });

    it('rejects a file the browser cannot decode', async () => {
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn(async () => {
                throw new DOMException(
                    'The source image could not be decoded.',
                    'InvalidStateError',
                );
            }),
        );

        await expect(decodePhoto(photo())).rejects.toThrow(
            'The source image could not be decoded.',
        );
        expect(encoded).toEqual([]);
    });
});
