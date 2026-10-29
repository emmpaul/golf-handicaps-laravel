import { router } from '@inertiajs/react';
import { act } from '@testing-library/react';
import type { MockInstance } from 'vite-plus/test';
import { expect, vi } from 'vite-plus/test';

// The poll of Inertia (usePoll) reloads through the router's doReload, stubbed
// here to record the request options; the tests run it on fake timers.

export type PollReload = MockInstance<(options: { only?: string[] }) => void>;

/** Stubs the reload of the poll and records its options. */
export function spyOnPollReload(): PollReload {
    return vi
        .spyOn(
            router as unknown as {
                doReload: (options: { only?: string[] }) => void;
            },
            'doReload',
        )
        .mockImplementation(() => {});
}

/** Moves the fake timers on by `ms`, inside act. */
export async function advancePoll(ms: number): Promise<void> {
    await act(async () => {
        await vi.advanceTimersByTimeAsync(ms);
    });
}

/**
 * No reload before 2 s, a first one at 2 s that reloads only `only`, a second
 * one at 4 s.
 */
export async function expectReloadsEvery2s(
    reload: PollReload,
    only: string[],
): Promise<void> {
    await advancePoll(1999);
    expect(reload).not.toHaveBeenCalled();

    await advancePoll(1);
    expect(reload).toHaveBeenCalledTimes(1);
    expect(reload.mock.calls[0][0].only).toEqual(only);

    await advancePoll(2000);
    expect(reload).toHaveBeenCalledTimes(2);
}
