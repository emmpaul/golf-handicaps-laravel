import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { AppNav } from '@/components/app-nav';
import { AppVersion } from '@/components/app-version';
import { Toaster } from '@/components/ui/toaster';
import { showFlashToast } from '@/lib/flash-toast';

/**
 * Cadre commun de toutes les pages, posé par défaut dans `app.tsx`. Même
 * largeur de contenu partout, navigation comprise (`max-w-7xl`). Le bas de
 * `main` laisse la place de la barre d'onglets et de la barre d'accueil
 * (`--bottom-chrome`) : la ligne de version, dernier enfant de `main`, reste
 * visible au-dessus de la barre d'onglets. Le toast, monté une fois, affiche
 * la donnée flash `toast` de chaque réponse.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
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
            <AppNav />
            <main className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-4 px-4 pt-4 pb-[calc(--spacing(4)+var(--bottom-chrome))] lg:pt-5 lg:pb-[calc(--spacing(5)+var(--bottom-chrome))]">
                {children}
                <AppVersion version={app.version} commit={app.commit} />
            </main>
            <Toaster />
        </div>
    );
}
