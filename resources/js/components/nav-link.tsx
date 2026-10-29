import { Link, usePage } from '@inertiajs/react';
import type { MouseEvent, ReactNode } from 'react';
import { prefersReducedMotion } from '@/lib/motion';
import { inSection } from '@/lib/sections';

/**
 * Lien de section qui porte `aria-current="page"` sur sa section et ses
 * sous-pages. Le nom accessible est `label`, fixé par `aria-label` : il ne
 * dépend pas du libellé visible, que la barre d'onglets referme hors de
 * l'onglet courant. `className` et `children` reçoivent l'état courant.
 * Touché sur la page même de sa section, il remonte en haut par un défilement
 * doux, sans visite ni entrée de page (spec « Même onglet touché »).
 */
export function NavLink({
    href,
    label,
    className,
    children,
    ...rest
}: {
    href: string;
    label: string;
    className: (current: boolean) => string;
    children: (current: boolean) => ReactNode;
    'aria-describedby'?: string;
}) {
    const { url } = usePage();
    const current = inSection(url, href);
    const scrollUp = (event: MouseEvent) => {
        if (url.split('?')[0] !== href) {
            return;
        }

        event.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        });
    };

    return (
        <Link
            href={href}
            aria-label={label}
            aria-current={current ? 'page' : undefined}
            onClick={scrollUp}
            className={className(current)}
            {...rest}
        >
            {children(current)}
        </Link>
    );
}
