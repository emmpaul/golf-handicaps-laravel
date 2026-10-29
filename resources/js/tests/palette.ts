/// <reference types="node" />
import { readFileSync } from 'node:fs';

// Reads the single dark theme of the visual redesign (design D1, D9): tokens in
// `@theme static` of `tokens.css`, shadcn variables in `:root` of `app.css`
// pointing at them. The OKLCH → sRGB conversion is the CSS Color 4 one.
const tokens = readFileSync('resources/css/tokens.css', 'utf8');
const app = readFileSync('resources/css/app.css', 'utf8');

function declarations(block: string) {
    return new Map(
        [...block.matchAll(/--([\w-]+):\s*([^;]+);/g)].map(
            ([, name, value]) => [name, value.trim()] as const,
        ),
    );
}

const themeTokens = declarations(
    tokens.match(/@theme static\s*\{([\s\S]+?)\n\}/)?.[1] ?? '',
);
const shadcn = declarations(app.match(/:root\s*\{([^}]+)\}/)?.[1] ?? '');

/** Follows `var(--x)` from a shadcn variable or a token down to its colour value. */
function resolve(name: string, depth = 0): string {
    if (depth > 8) {
        throw new Error(`var() loop at --${name}`);
    }

    const value = themeTokens.get(name) ?? shadcn.get(name);

    if (!value) {
        throw new Error(`Undeclared variable: --${name}`);
    }

    const ref = value.match(/^var\(--([\w-]+)\)$/);

    return ref ? resolve(ref[1], depth + 1) : value;
}

/** Colour of a variable in linear sRGB. Contrast is measured before gamma encoding. */
function linear(name: string): [number, number, number] {
    const value = resolve(name);
    const hexValue = value.match(/^#([\da-f]{6})$/i)?.[1];

    if (hexValue) {
        return [0, 2, 4].map((i) => {
            const v = parseInt(hexValue.slice(i, i + 2), 16) / 255;

            return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        }) as [number, number, number];
    }

    const oklch = value.match(/^oklch\(([^)]+)\)$/)?.[1];

    if (!oklch) {
        throw new Error(`Not an OKLCH or hex value: --${name}`);
    }

    const [l, c, hue] = oklch.split(/\s+/).map(Number);
    const a = c * Math.cos((hue * Math.PI) / 180);
    const b = c * Math.sin((hue * Math.PI) / 180);
    const x = (l + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const y = (l - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const z = (l - 0.0894841775 * a - 1.291485548 * b) ** 3;

    return [
        4.0767416621 * x - 3.3077115913 * y + 0.2309699292 * z,
        -1.2684380046 * x + 2.6097574011 * y - 0.3413193965 * z,
        -0.0041960863 * x - 0.7034186147 * y + 1.707614701 * z,
    ];
}

/** WCAG 2 contrast ratio between two variables. */
export function contrast(fg: string, bg: string): number {
    const luminance = (name: string) => {
        const [r, g, b] = linear(name).map((v) => Math.min(1, Math.max(0, v)));

        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const [a, b] = [luminance(fg), luminance(bg)];

    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

/** sRGB hex (`#RRGGBB`, upper case) of a variable. */
export function hex(name: string): string {
    return `#${linear(name)
        .map((v) => Math.min(1, Math.max(0, v)))
        .map((v) =>
            Math.round(
                255 *
                    (v <= 0.0031308
                        ? 12.92 * v
                        : 1.055 * v ** (1 / 2.4) - 0.055),
            ),
        )
        .map((v) => v.toString(16).padStart(2, '0').toUpperCase())
        .join('')}`;
}
