import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vite-plus/test';

import { installKeyWarningGuard, takeKeyWarnings } from './key-warnings';

installKeyWarningGuard();

// Vitest globals are off, so Testing Library cannot register its own cleanup:
// unmount every rendered tree after each test, then fail it on a React key
// warning it caused (L18).
afterEach(() => {
    cleanup();

    const warnings = takeKeyWarnings();

    if (warnings.length > 0) {
        throw new Error(`React key warning:\n${warnings.join('\n')}`);
    }
});
