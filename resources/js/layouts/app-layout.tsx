import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { AppNav } from '@/components/app-nav';
import { AppVersion } from '@/components/app-version';
import { Toaster } from '@/components/ui/toaster';
import { showFlashToast } from '@/lib/flash-toast';

/**
 * Cadre commun de toutes les pages, posé par défaut dans `app.tsx` (D3, D4).
 * La colonne de 390 px, centrée à toute largeur (décision Q3), marges de
 * 20 px. Pas d'en-tête collé : chaque page commence par sa `PageTopRow`, et
 * une bande fixe de la hauteur de la zone sûre, au fond de la page, garde la
 * barre d'état nette. Le bas de `main` laisse 108 px plus la zone sûre à la
 * barre flottante : la ligne de version, dernier enfant de `main`, reste
 * visible au-dessus. `chrome="editor"` (éditeur de round) retire la barre
 * d'onglets et le bouton Importer. Le toast, monté une fois, affiche la donnée
 * flash `toast` de chaque réponse.
 */
export default function AppLayout({
    children,
    chrome = 'nav',
}: {
    children: ReactNode;
    chrome?: 'nav' | 'editor';
}) {
    const { app } = usePage().props;

    useEffect(
        () =>
            router.on('flash', (event) => {
                showFlashToast(event.detail.flash);
            }),
        [],
    );

    return (
        <div className="flex min-h-dvh flex-col">
            <div
                aria-hidden="true"
                data-slot="safe-area-band"
                className="pointer-events-none fixed inset-x-0 top-0 z-(--z-sticky) h-(--safe-top) bg-bg"
            />
            <main className="mx-auto flex w-full max-w-(--column) min-w-0 flex-1 flex-col px-(--gutter) pb-[calc(108px+var(--safe-bottom))]">
                {children}
                <AppVersion version={app.version} commit={app.commit} />
            </main>
            {chrome === 'nav' && <AppNav />}
            <Toaster />
        </div>
    );
}
