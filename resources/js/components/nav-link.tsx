import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { inSection } from '@/lib/sections';

/**
 * Lien de section qui porte `aria-current="page"` sur sa section et ses
 * sous-pages. Le style du lien courant passe par la variante
 * `aria-[current=page]:` de `className`.
 */
export function NavLink({
    href,
    className,
    children,
}: {
    href: string;
    className: string;
    children: ReactNode;
}) {
    const { url } = usePage();

    return (
        <Link
            href={href}
            aria-current={inSection(url, href) ? 'page' : undefined}
            className={className}
        >
            {children}
        </Link>
    );
}
