/// <reference types="node" />
import { readdirSync } from 'node:fs';
import { join } from 'node:path';

// The hand-written sources of resources/js, read by the guard tests of the
// theme (theme-sources, theme-contrast): every .ts and .tsx file outside the
// generated folders and the tests themselves.
export const ROOT = 'resources/js';
const GENERATED = ['actions', 'routes', 'wayfinder'];

export function sources(dir: string = ROOT): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            return dir === ROOT && GENERATED.includes(entry.name)
                ? []
                : sources(path);
        }

        return /\.tsx?$/.test(entry.name) && !/\.test\./.test(entry.name)
            ? [path]
            : [];
    });
}
