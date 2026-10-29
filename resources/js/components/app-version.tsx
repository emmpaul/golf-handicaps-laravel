import { releaseUrl, shortCommit } from '@/lib/version';

/**
 * Ligne discrète en bas de page : « v0.6.0 · 4794e1b », liée à la release
 * GitHub. Sans commit connu, la version seule ; en 0.0.0, du texte sans lien.
 * Texte petit, mais zone de toucher de 44 px sous 768 px comme tout contrôle
 * de `main`.
 */
export function AppVersion({
    version,
    commit,
}: {
    version: string;
    commit: string | null;
}) {
    const label =
        commit === null
            ? `v${version}`
            : `v${version} · ${shortCommit(commit)}`;
    const href = releaseUrl(version);

    return (
        <footer className="text-xs mt-auto text-center text-muted-foreground">
            {href === null ? (
                <span className="inline-flex items-center px-2 max-md:min-h-11">
                    {label}
                </span>
            ) : (
                <a
                    href={href}
                    className="inline-flex items-center px-2 hover:underline max-md:min-h-11"
                >
                    {label}
                </a>
            )}
        </footer>
    );
}
