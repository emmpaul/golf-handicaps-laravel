/**
 * React's key warnings fail the test instead of scrolling by (L18): a shared
 * key between siblings once left a stale chart on the page (B1). React logs
 * some of them outside the render call (concurrent rendering), so they are
 * collected here and setup.ts fails the test in `afterEach`.
 */
const KEY_WARNINGS = [
    'Encountered two children with the same key',
    'Each child in a list should have a unique "key" prop',
];

let caught: string[] = [];

/** Wraps console.error: key warnings are kept, everything else passes through. */
export function installKeyWarningGuard(): void {
    const consoleError = console.error.bind(console);

    console.error = (...args: unknown[]) => {
        // React formats its warnings with `%s`: read the whole call.
        const message = args.map(String).join(' ');

        if (KEY_WARNINGS.some((pattern) => message.includes(pattern))) {
            caught.push(message);

            return;
        }

        consoleError(...args);
    };
}

/** The key warnings logged since the last call, then forgotten. */
export function takeKeyWarnings(): string[] {
    const warnings = caught;
    caught = [];

    return warnings;
}
