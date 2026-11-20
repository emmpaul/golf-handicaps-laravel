/** A line of the virtual match leaderboard, as far as the winner box reads it. */
export type RankedNet = {
    name: string;
    net: number | null;
    /** Competition rank of the server, `null` without index. */
    rank: number | null;
};

/** The texts of the winner box (spec virtual-match, « Encadré du gagnant »). */
export type WinnerSummary = {
    /** « AURAIT GAGNÉ », or « ÉGALITÉ » for first ones tied. */
    heading: string;
    /** The first one, or the first ones tied joined by « et ». */
    names: string;
    /** « 65 net · 3 coups devant Sean », or « 70 net » for a tie. */
    detail: string;
};

/** « Gab », « Gab et Manu », « Gab, Manu et Sean ». */
export function joinNames(names: string[]): string {
    if (names.length <= 1) {
        return names.join('');
    }

    return `${names.slice(0, -1).join(', ')} et ${names[names.length - 1]}`;
}

/**
 * The texts of the winner box, from the lines in the order of the choice: the
 * first one by net with the gap to the second ones (« 1 coup » in the
 * singular), or « ÉGALITÉ » for first ones tied. `null` with fewer than two
 * ranked players: the page then shows no box.
 */
export function winnerSummary(lines: RankedNet[]): WinnerSummary | null {
    const ranked = lines
        .filter(
            (line): line is RankedNet & { net: number; rank: number } =>
                line.rank !== null && line.net !== null,
        )
        .sort((a, b) => a.rank - b.rank);

    if (ranked.length < 2) {
        return null;
    }

    const firsts = ranked.filter((line) => line.rank === ranked[0].rank);
    const net = firsts[0].net;

    if (firsts.length > 1) {
        return {
            heading: 'ÉGALITÉ',
            names: joinNames(firsts.map((line) => line.name)),
            detail: `${net} net`,
        };
    }

    const seconds = ranked.filter((line) => line.rank === ranked[1].rank);
    const gap = seconds[0].net - net;

    return {
        heading: 'AURAIT GAGNÉ',
        names: firsts[0].name,
        detail: `${net} net · ${gap} ${gap === 1 ? 'coup' : 'coups'} devant ${joinNames(seconds.map((line) => line.name))}`,
    };
}
