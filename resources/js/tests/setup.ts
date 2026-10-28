import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vite-plus/test';

// Vitest globals are off, so Testing Library cannot register its own cleanup:
// unmount every rendered tree after each test.
afterEach(() => {
    cleanup();
});
