import { render } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import { takeKeyWarnings } from './key-warnings';

// L18: setup.ts fails a test whose render logs a React key warning, so a
// duplicate key (the cause of B1, two « Différentiels » charts) cannot pass
// silently. Each test takes the warning itself, which keeps it green; the
// afterEach of setup.ts would fail it otherwise. Mutation: the guard not
// installed in setup.ts, or a pattern reworded → nothing is caught, these
// tests go red.
describe('React key warning guard of the test setup', () => {
    it('catches two children of the same key', () => {
        render(
            <ul>
                {['a', 'a'].map((key) => (
                    <li key={key}>{key}</li>
                ))}
            </ul>,
        );

        const warnings = takeKeyWarnings();

        expect(warnings).toHaveLength(1);
        expect(warnings[0]).toContain(
            'Encountered two children with the same key',
        );
    });

    it('catches a list without keys', () => {
        render(
            <ul>
                {['a', 'b'].map((text) => (
                    // eslint-disable-next-line react/jsx-key
                    <li>{text}</li>
                ))}
            </ul>,
        );

        const warnings = takeKeyWarnings();

        expect(warnings).toHaveLength(1);
        expect(warnings[0]).toContain(
            'Each child in a list should have a unique "key" prop',
        );
    });
});
