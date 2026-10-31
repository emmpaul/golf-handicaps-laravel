/**
 * Préfixes d'adresse rattachés à une section en plus du sien : un import ouvert
 * relève d'« Importer », la page Coups, hors de la navigation, de « Handicaps ».
 */
export const SECTION_PREFIXES: Record<string, readonly string[]> = {
    '/import': ['/imports'],
    '/handicaps': ['/strokes'],
};

/**
 * Vrai si l'adresse `url` (query string et fragment ignorés) est la page de la
 * section `href` ou l'une de ses sous-pages.
 */
export function inSection(url: string, href: string): boolean {
    const path = url.split(/[?#]/, 1)[0];

    return [href, ...(SECTION_PREFIXES[href] ?? [])].some(
        (prefix) => path === prefix || path.startsWith(`${prefix}/`),
    );
}
