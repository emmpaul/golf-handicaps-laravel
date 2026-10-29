import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { createCorrectionAutosave } from '@/components/round-editor/autosave';
import { cardEditorState } from '@/components/round-editor/form-state';
import type { EditorState } from '@/components/round-editor/form-state';
import { GAB_STROKES, HOLES, MANU_STROKES } from '@/tests/reference-round';

// Spec scorecard-import « Sauvegarde automatique de la correction d'un
// import », « Indicateur d'enregistrement », « Correction refusée hors
// vérification » and design D7: a PUT 1 s after the last change, one send at
// a time, an immediate send with `keepalive` when the page hides or closes, a
// new try 5 s after a network error or a 5xx, a stop on 409 (message of the
// server), 401, 404 and 422. Timers and `fetch` are simulated: each call waits
// for the response the test gives it.
// Mutations:
// - the delay reset by the send in flight (no `inFlight` guard: a change sent
//   while the previous PUT waits) → two parallel sends, the « during the send »
//   test goes red;
// - `keepalive` dropped from the immediate send → the pagehide and
//   visibilitychange tests go red;
// - the starting point empty instead of the opening state → the « nothing
//   changed » test sees a PUT and goes red;
// - no new try after a network error, or after 4 s → the retry test goes red;
// - the 409 retried as a network error, or its message replaced → the 409
//   test goes red.

const IMPORT = '0190a6b2-7c3e-7000-8000-0000000000c1';

/** The reference card as read: GAB then MANU, lines without player, totals read. */
const opening = (): EditorState =>
    cardEditorState({
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        playedOn: '2026-10-17',
        holes: HOLES,
        lines: [
            {
                playerId: null,
                strokes: GAB_STROKES,
                readName: 'GAB',
                totals: { out: 38, in: 34, total: 72 },
            },
            {
                playerId: null,
                strokes: MANU_STROKES,
                readName: 'MANU',
                totals: { out: 46, in: 42, total: 88 },
            },
        ],
        parTotals: { out: 36, in: 35, total: 71 },
    });

/** The opening state where Manu (line 2) has `strokes` at hole 1. */
function withManuHole1(strokes: number): EditorState {
    const state = opening();
    state.lines[1].strokes[0] = strokes;

    return state;
}

type Call = { url: string; init: RequestInit };

function fakeFetch() {
    const calls: Call[] = [];
    const replies: ((response: Response) => void)[] = [];
    const failures: ((error: Error) => void)[] = [];

    return {
        calls,
        fetch: (url: string, init: RequestInit) => {
            calls.push({ url, init });

            return new Promise<Response>((resolve, reject) => {
                replies.push(resolve);
                failures.push(reject);
            });
        },
        reply: async (index: number, response: Response) => {
            replies[index](response);
            await vi.advanceTimersByTimeAsync(0);
        },
        fail: async (index: number) => {
            failures[index](new TypeError('Failed to fetch'));
            await vi.advanceTimersByTimeAsync(0);
        },
    };
}

const body = (call: Call) => JSON.parse(call.init.body as string);
const manuHole1 = (call: Call) => body(call).card.lines[1].strokes[0];

function setup() {
    const server = fakeFetch();
    const statuses: string[] = [];
    const document = Object.assign(new EventTarget(), {
        visibilityState: 'visible' as DocumentVisibilityState,
    });
    const window = new EventTarget();
    const autosave = createCorrectionAutosave({
        importId: IMPORT,
        initial: opening(),
        fetch: server.fetch,
        window,
        document,
        onStatus: (status) => statuses.push(status.message),
    });

    return { server, statuses, document, window, autosave };
}

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('autosave of the correction of an import', () => {
    it('sends nothing and shows nothing while the opening state is unchanged', async () => {
        const { server, statuses, autosave } = setup();

        autosave.change(opening());
        await vi.advanceTimersByTimeAsync(10_000);

        expect(server.calls).toHaveLength(0);
        expect(statuses).toEqual([]);
    });

    it('sends one PUT of the whole card 1 s after a change', async () => {
        const { server, autosave } = setup();

        autosave.change(withManuHole1(6));
        await vi.advanceTimersByTimeAsync(999);
        expect(server.calls).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(1);
        expect(server.calls).toHaveLength(1);
        expect(server.calls[0].url).toBe(`/imports/${IMPORT}/correction`);
        expect(server.calls[0].init.method).toBe('PUT');
        expect(server.calls[0].init.keepalive).toBe(false);
        expect(body(server.calls[0])).toEqual({
            version: 1,
            card: {
                courseName: 'BETHPAGE BLACK',
                tees: 'Red',
                playedOn: '2026-10-17',
                holes: HOLES,
                lines: [
                    {
                        playerId: null,
                        strokes: GAB_STROKES,
                        readName: 'GAB',
                        totals: { out: 38, in: 34, total: 72 },
                    },
                    {
                        playerId: null,
                        strokes: [6, ...MANU_STROKES.slice(1)],
                        readName: 'MANU',
                        totals: { out: 46, in: 42, total: 88 },
                    },
                ],
                parTotals: { out: 36, in: 35, total: 71 },
            },
        });
    });

    it('goes from « Enregistrement… » to « Enregistré » when the server accepts', async () => {
        const { server, statuses, autosave } = setup();

        autosave.change(withManuHole1(6));
        await vi.advanceTimersByTimeAsync(1000);
        expect(statuses).toEqual(['Enregistrement…']);

        await server.reply(0, new Response(null, { status: 204 }));
        expect(statuses).toEqual(['Enregistrement…', 'Enregistré']);
    });

    it('sends three changes 300 ms apart once, 1 s after the last', async () => {
        const { server, autosave } = setup();

        autosave.change(withManuHole1(6));
        await vi.advanceTimersByTimeAsync(300);
        autosave.change(withManuHole1(5));
        await vi.advanceTimersByTimeAsync(300);
        autosave.change(withManuHole1(4));
        await vi.advanceTimersByTimeAsync(999);
        expect(server.calls).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(1);
        expect(server.calls).toHaveLength(1);
        expect(manuHole1(server.calls[0])).toBe(4);
    });

    it('sends a change made during a send at its end, with the last state only', async () => {
        const { server, autosave } = setup();

        autosave.change(withManuHole1(6));
        await vi.advanceTimersByTimeAsync(1000);
        autosave.change(withManuHole1(5));
        await vi.advanceTimersByTimeAsync(1000);
        autosave.change(withManuHole1(4));
        await vi.advanceTimersByTimeAsync(1000);
        expect(server.calls).toHaveLength(1);

        await server.reply(0, new Response(null, { status: 204 }));
        expect(server.calls).toHaveLength(2);
        expect(manuHole1(server.calls[1])).toBe(4);
    });

    it('sends at once with keepalive on pagehide', () => {
        const { server, window, autosave } = setup();

        autosave.change(withManuHole1(6));
        window.dispatchEvent(new Event('pagehide'));

        expect(server.calls).toHaveLength(1);
        expect(server.calls[0].init.keepalive).toBe(true);
        expect(manuHole1(server.calls[0])).toBe(6);
    });

    it('sends at once with keepalive when the page becomes hidden, not when it becomes visible', async () => {
        const { server, document, autosave } = setup();

        autosave.change(withManuHole1(6));
        await vi.advanceTimersByTimeAsync(300);
        document.dispatchEvent(new Event('visibilitychange'));
        expect(server.calls).toHaveLength(0);

        document.visibilityState = 'hidden';
        document.dispatchEvent(new Event('visibilitychange'));
        expect(server.calls).toHaveLength(1);
        expect(server.calls[0].init.keepalive).toBe(true);
    });

    it('sends nothing on pagehide without a pending change', () => {
        const { server, window } = setup();

        window.dispatchEvent(new Event('pagehide'));

        expect(server.calls).toHaveLength(0);
    });

    it('shows « Modifications non enregistrées » on a network error and tries again 5 s later', async () => {
        const { server, statuses, autosave } = setup();

        autosave.change(withManuHole1(6));
        await vi.advanceTimersByTimeAsync(1000);
        await server.fail(0);
        expect(statuses).toEqual([
            'Enregistrement…',
            'Modifications non enregistrées',
        ]);

        await vi.advanceTimersByTimeAsync(4999);
        expect(server.calls).toHaveLength(1);
        await vi.advanceTimersByTimeAsync(1);
        expect(server.calls).toHaveLength(2);
        expect(manuHole1(server.calls[1])).toBe(6);
    });

    it('tries again 5 s after a 503', async () => {
        const { server, statuses, autosave } = setup();

        autosave.change(withManuHole1(6));
        await vi.advanceTimersByTimeAsync(1000);
        await server.reply(0, new Response(null, { status: 503 }));
        expect(statuses).toEqual([
            'Enregistrement…',
            'Modifications non enregistrées',
        ]);

        await vi.advanceTimersByTimeAsync(5000);
        expect(server.calls).toHaveLength(2);
    });

    it('shows the message of a 409 and sends nothing more', async () => {
        const { server, statuses, autosave } = setup();

        autosave.change(withManuHole1(6));
        await vi.advanceTimersByTimeAsync(1000);
        await server.reply(
            0,
            Response.json(
                { error: 'Cette carte est déjà enregistrée' },
                { status: 409 },
            ),
        );
        expect(statuses).toEqual([
            'Enregistrement…',
            'Cette carte est déjà enregistrée',
        ]);

        autosave.change(withManuHole1(5));
        await vi.advanceTimersByTimeAsync(10_000);
        expect(server.calls).toHaveLength(1);
    });

    it.each([401, 404, 422])(
        'shows « Modifications non enregistrées » on a %i and sends nothing more',
        async (status) => {
            const { server, statuses, autosave } = setup();

            autosave.change(withManuHole1(6));
            await vi.advanceTimersByTimeAsync(1000);
            await server.reply(
                0,
                Response.json({ error: 'Import introuvable' }, { status }),
            );
            expect(statuses).toEqual([
                'Enregistrement…',
                'Modifications non enregistrées',
            ]);

            autosave.change(withManuHole1(5));
            await vi.advanceTimersByTimeAsync(10_000);
            expect(server.calls).toHaveLength(1);
        },
    );

    it('pause holds the pending send; resume sends the last state at once', async () => {
        const { server, autosave } = setup();

        autosave.change(withManuHole1(6));
        autosave.pause();
        autosave.change(withManuHole1(5));
        await vi.advanceTimersByTimeAsync(10_000);
        expect(server.calls).toHaveLength(0);

        autosave.resume();
        await vi.advanceTimersByTimeAsync(0);
        expect(server.calls).toHaveLength(1);
        expect(manuHole1(server.calls[0])).toBe(5);
    });
});
