import { Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { MouseEvent, ReactNode } from 'react';
import { prefersReducedMotion } from '@/lib/motion';
import { prefetchPage } from '@/lib/prefetch';
import { inSection } from '@/lib/sections';

/**
 * Lien de section qui porte `aria-current="page"` sur sa section et ses
 * sous-pages. Le nom accessible est `label`, fixé par `aria-label` : il ne
 * dépend pas du libellé visible, que la barre d'onglets referme hors de
 * l'onglet courant. `className` et `children` reçoivent l'état courant.
 * Touché sur la page même de sa section, il remonte en haut par un défilement
 * doux, sans visite ni entrée de page (spec « Même onglet touché »).
 * Hors de sa propre page, il précharge sa page dès l'affichage (spec « Sections
 * préchargées », design D5) : fraîche 30 s, puis affichée et rechargée en
 * arrière-plan jusqu'à 5 min. Il repart à chaque page affichée : la barre
 * reste montée, et une écriture vide le cache avant sa redirection. Le
 * préchargement passe par un effet du lien et
 * non par `prefetch="mount"` : la barre reste montée d'une page à l'autre, et
 * basculer `prefetch` entre `false` et `'mount'` change la taille des
 * dépendances d'un effet du `Link` d'Inertia (avertissement React, et l'onglet
 * qui cesse d'être la page courante ne se préchargeait plus).
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
    const page = usePage();
    const { url } = page;
    const current = inSection(url, href);
    const onOwnPage = url.split('?')[0] === href;

    useEffect(() => {
        if (onOwnPage) {
            return;
        }

        // Comme `prefetch="mount"` d'Inertia : après le rendu de la page.
        const timer = setTimeout(() => prefetchPage(href));

        return () => clearTimeout(timer);
        // `page` : chaque page affichée est un nouvel objet. Le préchargement
        // repart après chaque visite, redirection d'une écriture vers la même
        // adresse comprise. Une copie encore fraîche ne part pas en requête.
    }, [href, onOwnPage, page]);

    const scrollUp = (event: MouseEvent) => {
        if (!onOwnPage) {
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
