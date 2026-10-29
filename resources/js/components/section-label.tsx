import type { ReactNode } from 'react';

/**
 * Libellé de section (spec app-shell, D5) : Geist Mono 500, 11 px, capitales,
 * gris, 10 px au-dessus d'un filet `rule` ; une valeur à droite (compte,
 * unité) prend le même style. Le libellé est un titre de niveau 2 : il nomme
 * la liste qu'il introduit.
 */
export function SectionLabel({
    children,
    value,
}: {
    children: ReactNode;
    value?: ReactNode;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3 border-b border-rule pb-2.5 font-mono text-section text-ink-2 uppercase">
            <h2 className="font-mono">{children}</h2>
            {value !== undefined && <span>{value}</span>}
        </div>
    );
}
