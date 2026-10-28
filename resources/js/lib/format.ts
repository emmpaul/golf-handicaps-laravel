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

/** An empty value in a table: a name or a figure that does not exist yet. */
export const NO_VALUE = '—';
