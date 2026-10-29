import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

/**
 * Rangée du haut de chaque page (spec app-shell, D3, D5), 44 px de haut, qui
 * défile avec la page. Page de section : « Golf » à gauche, lien vers
 * l'accueil sauf sur l'accueil. Sous-page : le lien de retour `back` (chevron
 * de 18 px, libellé gris). Les actions sont à droite ; `meta`, une valeur qui
 * n'est pas une action (« 8 ROUNDS »), s'y place en Geist Mono 11 px gris. Le
 * haut suit le design : 44 px pour une rangée à lien de retour ou à actions,
 * 54 px pour « Golf » seul ou suivi de `meta`. Quand la zone sûre du haut
 * dépasse cette mesure, la rangée commence au bas de la zone sûre, sans marge
 * ajoutée.
 */
export function PageTopRow({
    back,
    actions,
    meta,
}: {
    back?: { href: string; label: string };
    actions?: ReactNode;
    meta?: ReactNode;
}) {
    const { url } = usePage();
    const onHome = url.split(/[?#]/, 1)[0] === home.url();

    return (
        <div
            data-slot="page-top-row"
            className={cn(
                'flex min-h-11 items-center justify-between gap-3',
                back || actions
                    ? 'pt-[max(44px,var(--safe-top))]'
                    : 'pt-[max(54px,var(--safe-top))]',
                back ? 'mb-3.5' : 'mb-[22px]',
            )}
        >
            {back ? (
                <Link
                    href={back.href}
                    className="-ml-1 flex min-h-11 items-center gap-0.5 text-link text-ink-2"
                >
                    <ChevronLeft aria-hidden className="size-[18px]" />
                    {back.label}
                </Link>
            ) : onHome ? (
                <span className="text-label">Golf</span>
            ) : (
                <Link
                    href={home.url()}
                    className="flex min-h-11 items-center text-label"
                >
                    Golf
                </Link>
            )}
            {actions && (
                <div className="flex items-center gap-4">{actions}</div>
            )}
            {meta !== undefined && !actions && (
                <span className="font-mono text-section tracking-normal text-ink-2">
                    {meta}
                </span>
            )}
        </div>
    );
}
