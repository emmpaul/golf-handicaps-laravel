import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppVersion } from '@/components/app-version';

/**
 * Page nue, hors du cadre commun : ni navigation ni barre d'onglets. Colonne
 * étroite (`max-w-sm`) ; le contenu prend la hauteur restante et la ligne de
 * version, dernier enfant de `main`, reste en bas de page. Sert à `/login`.
 */
export default function BareLayout({ children }: { children: ReactNode }) {
    const { app } = usePage().props;

    return (
        <div className="flex min-h-dvh flex-col">
            <main className="mx-auto flex w-full max-w-sm flex-1 flex-col gap-6 px-4 py-8">
                {children}
                <AppVersion version={app.version} commit={app.commit} />
            </main>
        </div>
    );
}
