import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { inSection } from '@/lib/sections';

/**
 * Lien de section qui porte `aria-current="page"` sur sa section et ses
 * sous-pages. Le nom accessible est `label`, fixé par `aria-label` : il ne
 * dépend pas du libellé visible, que la barre d'onglets referme hors de
 * l'onglet courant. `className` et `children` reçoivent l'état courant.
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

    return (
        <Link
            href={href}
            aria-label={label}
            aria-current={current ? 'page' : undefined}
            className={className(current)}
            {...rest}
        >
            {children(current)}
        </Link>
    );
}
