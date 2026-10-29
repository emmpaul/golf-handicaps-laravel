import { Link } from '@inertiajs/react';
import { Camera, Flag, ListOrdered, TrendingUp, Users } from 'lucide-react';
import { BrandMark } from '@/components/brand-mark';
import { NavLink } from '@/components/nav-link';
import { home } from '@/routes';
import { index as handicapsIndex } from '@/routes/handicaps';
import { index as importIndex } from '@/routes/import';
import { index as playersIndex } from '@/routes/players';
import { index as roundsIndex } from '@/routes/rounds';
import { index as strokesIndex } from '@/routes/strokes';

/** Sections de l'app, partagées par les navigations du haut et du bas. */
export const LINKS = [
    { href: importIndex.url(), label: 'Importer', icon: Camera },
    { href: roundsIndex.url(), label: 'Rounds', icon: ListOrdered },
    { href: handicapsIndex.url(), label: 'Handicaps', icon: TrendingUp },
    { href: strokesIndex.url(), label: 'Coups', icon: Flag },
    { href: playersIndex.url(), label: 'Joueurs', icon: Users },
] as const;

/**
 * Navigation entre sections : liens en haut à partir de `md` (le courant
 * souligné d'un filet d'encre de 2 px), barre d'onglets fixée en bas en dessous
 * (onglet courant : bloc d'encre plein sous l'icône). Les deux sont rendues,
 * l'une ou l'autre masquée en CSS. Les marges de zone sûre viennent des
 * variables de `app.css`.
 */
export function AppNav() {
    return (
        <>
            {/* Collé en haut, fond plein du papier (= `html` et `body`) : Safari iOS y
                prend sa teinte et rien ne passe flou dessous. La rangée de la marque
                commence 8 px sous la zone sûre. */}
            <header className="sticky top-0 z-40 border-b bg-background pt-[calc(var(--safe-top)+--spacing(2))]">
                <nav
                    aria-label="Navigation principale"
                    className="mx-auto flex w-full max-w-7xl items-center gap-4 px-4 pb-2"
                >
                    <Link
                        href={home.url()}
                        className="font-heading text-xl mr-auto flex shrink-0 items-center gap-2 leading-none font-extrabold max-md:min-h-11"
                    >
                        <BrandMark /> Golf
                    </Link>
                    {LINKS.map(({ href, label }) => (
                        <NavLink
                            key={href}
                            href={href}
                            className="text-sm hidden h-11 shrink-0 items-center border-y-2 border-transparent text-muted-foreground transition-[color,border-color] duration-(--dur-micro) ease-out hover:text-foreground aria-[current=page]:border-b-foreground aria-[current=page]:font-medium aria-[current=page]:text-foreground md:flex"
                        >
                            {label}
                        </NavLink>
                    ))}
                </nav>
            </header>
            <nav
                aria-label="Sections"
                className="fixed inset-x-0 bottom-0 z-40 flex h-(--bottom-chrome) flex-col border-t-2 border-foreground bg-background pb-(--safe-bottom) md:hidden"
            >
                <ul className="grid flex-1 grid-cols-5">
                    {LINKS.map(({ href, label, icon: Icon }) => (
                        <li key={href} className="flex">
                            <NavLink
                                href={href}
                                className="group text-xs flex flex-1 flex-col items-center justify-center gap-0.5 text-muted-foreground aria-[current=page]:font-medium aria-[current=page]:text-foreground"
                            >
                                <span
                                    data-slot="tab-pill"
                                    className="flex h-7 w-12 items-center justify-center group-aria-[current=page]:bg-primary group-aria-[current=page]:text-primary-foreground"
                                >
                                    <Icon aria-hidden className="size-5" />
                                </span>
                                {label}
                            </NavLink>
                        </li>
                    ))}
                </ul>
            </nav>
        </>
    );
}
