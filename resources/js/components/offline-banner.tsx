import { usePage } from '@inertiajs/react';
import { WifiOff } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { formatStoredAt } from '@/lib/format';
import { offlineSnapshot, subscribeOffline } from '@/lib/offline';
import type { StoredCopy } from '@/lib/offline';

/** La copie servie est celle de la page affichée : même chemin, même requête. */
function isCurrentPage(copy: StoredCopy | null, pageUrl: string): boolean {
    if (copy === null) {
        return false;
    }

    const url = new URL(copy.url);

    return `${url.pathname}${url.search}` === pageUrl.split('#')[0];
}

/**
 * Bandeau « Hors ligne » (spec offline-mode « Bandeau hors ligne », design
 * D5), posé par le layout sous la rangée du haut. Il s'affiche quand
 * l'appareil est hors ligne ou que la page affichée vient de sa copie, suivi
 * alors de « · page du 30 oct. à 21:04 », l'heure de la copie. Comme
 * `Pending`, la région `role="status"` est toujours montée et vide au repos
 * (`sr-only`) : le lecteur d'écran annonce le bandeau une fois, quand il se
 * remplit.
 */
export function OfflineBanner() {
    const { url } = usePage();
    const { online, copy } = useSyncExternalStore(
        subscribeOffline,
        offlineSnapshot,
    );
    const fromCopy = isCurrentPage(copy, url);
    const visible = !online || fromCopy;
    const storedAt = fromCopy ? copy?.storedAt : null;

    return (
        <p
            role="status"
            data-slot="offline-banner"
            className={
                visible
                    ? 'mb-4 flex items-center gap-2 rounded-10 border border-rule bg-surface-1 px-3 py-2 font-mono text-meta-round text-ink-2'
                    : 'sr-only'
            }
        >
            {visible ? (
                <>
                    <WifiOff
                        aria-hidden
                        className="size-3.5 flex-none text-accent"
                    />
                    <span className="min-w-0">
                        {storedAt
                            ? `Hors ligne · page du ${formatStoredAt(storedAt)}`
                            : 'Hors ligne'}
                    </span>
                </>
            ) : null}
        </p>
    );
}
