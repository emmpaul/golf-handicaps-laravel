/** Page des releases GitHub du dépôt ; le tag est `v<version>`. */
export const RELEASES_URL =
    'https://github.com/emmpaul/golf-handicaps-laravel/releases/tag/';

/** Version d'avant la première release : aucune page à lier. */
const UNRELEASED_VERSION = '0.0.0';

/** Page de la release GitHub du tag `v<version>`, `null` avant la première release. */
export function releaseUrl(version: string): string | null {
    return version === UNRELEASED_VERSION ? null : `${RELEASES_URL}v${version}`;
}

/** Les 7 premiers caractères du sha, comme GitHub les affiche. */
export function shortCommit(commit: string): string {
    return commit.slice(0, 7);
}
