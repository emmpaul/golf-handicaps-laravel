import type { ReactNode } from 'react';

/** Titre de page : police d'affichage condensée en 800, un pas au-dessus des titres de section. */
export function PageTitle({ children }: { children: ReactNode }) {
    return (
        <h1 className="font-heading text-2xl md:text-3xl leading-tight font-extrabold tracking-(--tracking-display)">
            {children}
        </h1>
    );
}
