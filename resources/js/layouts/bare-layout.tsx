import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppVersion } from '@/components/app-version';

/**
 * Page nue, hors du cadre commun : ni navigation ni barre d'onglets. Colonne
 * de 390 px et marges de 20 px du design (D3) ; le contenu prend la hauteur
 * restante et la ligne de version, dernier enfant de `main`, reste en bas de
 * page. Sert à `/login`.
 */
export default function BareLayout({ children }: { children: ReactNode }) {
    const { app } = usePage().props;

    return (
        <div className="flex min-h-dvh flex-col">
            <main className="mx-auto flex w-full max-w-(--column) flex-1 flex-col px-(--gutter) pb-8">
                <div className="flex-1">{children}</div>
                <AppVersion version={app.version} commit={app.commit} />
            </main>
        </div>
    );
}
