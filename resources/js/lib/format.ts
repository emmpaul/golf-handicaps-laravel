/**
 * Dates of play are civil days (`yyyy-mm-dd`), never instants: they are read and written
 * through the local calendar, without passing through UTC (design D9). `new Date('2026-10-17')`
 * reads midnight UTC, the evening of the 16th west of Greenwich.
 */

const playedOnFormat = new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const shortPlayedOnFormat = new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/** ISO date `2026-10-17` to the 17th of October at midnight, local time. */
export function isoToLocalDate(iso: string): Date {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** Local date to `2026-10-17`, without passing through UTC. */
export function localDateToIso(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/** Date of play `2026-10-17` to « 17 octobre 2026 ». */
export function formatPlayedOn(playedOn: string): string {
    return playedOnFormat.format(isoToLocalDate(playedOn));
}

/**
 * The month abbreviations of the design (« JANV » … « DÉC »), without the point
 * of `Intl` (« oct. »), January first.
 */
const MONTHS = [
    'JANV',
    'FÉVR',
    'MARS',
    'AVR',
    'MAI',
    'JUIN',
    'JUIL',
    'AOÛT',
    'SEPT',
    'OCT',
    'NOV',
    'DÉC',
] as const;

/** Date of play `2026-10-17` to its day « 17 » and month « OCT » (page Rounds). */
export function playedOnParts(playedOn: string): {
    day: string;
    month: string;
} {
    const [, month, day] = playedOn.split('-').map(Number);

    return { day: String(day), month: MONTHS[month - 1] };
}

/** Date of play `2026-10-17` to « 17.10.2026 » (meta of a round). */
export function formatDottedPlayedOn(playedOn: string): string {
    const [year, month, day] = playedOn.split('-');

    return `${day}.${month}.${year}`;
}

/** Date of play `2026-10-17` to « 17 oct. 2026 », a civil day as `formatPlayedOn`. */
export function formatShortPlayedOn(playedOn: string): string {
    return shortPlayedOnFormat.format(isoToLocalDate(playedOn));
}

/** An empty value in a table: a name or a figure that does not exist yet. */
export const NO_VALUE = '—';

/** The mention of a player without index before a round (spec `rounds`). */
export const NO_INDEX = "pas encore d'index";

/** Integer tenths to « 17,4 » (design D2): the server sends tenths, the page formats them. */
export function formatTenths(tenths: number): string {
    return (tenths / 10).toFixed(1).replace('.', ',');
}

/** Index shown: « 10,0 », « 14,5 (provisoire) », or « — » without round. */
export function formatIndex(
    indexT: number | null,
    provisional: boolean,
): string {
    if (indexT === null) {
        return NO_VALUE;
    }

    return provisional
        ? `${formatTenths(indexT)} (provisoire)`
        : formatTenths(indexT);
}

/** Delta in tenths: « +0,3 », « −0,3 » with the typographic minus U+2212, or « = ». */
export function formatSignedTenths(tenths: number): string {
    if (tenths === 0) {
        return '=';
    }

    return `${tenths < 0 ? '\u2212' : '+'}${formatTenths(Math.abs(tenths))}`;
}
