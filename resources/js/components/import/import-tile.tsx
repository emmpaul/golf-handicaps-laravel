import type { ChangeEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Tuile d'envoi d'une photo (spec scorecard-import « Une action principale à
 * l'import », D5) : libellé stylé autour d'un champ fichier masqué (`sr-only`),
 * fond `surface-1`, icône de 22 px au-dessus du titre (Geist 600, 15 px) et de
 * sa source (Geist Mono 500, 11 px, gris, capitales). Le champ garde le focus
 * clavier, dont le contour s'affiche sur la tuile ; son nom accessible est
 * `aria-label`, pas le texte de la tuile.
 */
export function ImportTile({
    icon,
    title,
    source,
    ...input
}: {
    icon: ReactNode;
    title: string;
    source: string;
    disabled: boolean;
    capture?: 'environment';
    'aria-label': string;
    onChange: (event: ChangeEvent<HTMLInputElement>) => void;
}) {
    return (
        <label
            className={cn(
                'flex flex-1 cursor-pointer flex-col gap-[22px] bg-surface-1 px-3.5 py-4',
                'transition-[background-color,scale] duration-(--dur-press) hover:bg-surface-2 active:scale-[0.97]',
                'focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-ring focus-within:outline-solid',
                'has-disabled:cursor-not-allowed has-disabled:opacity-55',
            )}
        >
            {icon}
            <span className="flex flex-col gap-1.5">
                <span className="text-label">{title}</span>
                <span className="font-mono text-section tracking-normal text-ink-2 uppercase">
                    {source}
                </span>
            </span>
            <input
                type="file"
                accept="image/*"
                className="sr-only"
                {...input}
            />
        </label>
    );
}
