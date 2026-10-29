import { cn } from '@/lib/utils';

/**
 * Fond de chaque rang de couleur (`colorSlot`, 0 à 5, D6), écrit en entier
 * pour que Tailwind trouve chaque classe.
 */
export const PLAYER_BG = [
    'bg-player-1',
    'bg-player-2',
    'bg-player-3',
    'bg-player-4',
    'bg-player-5',
    'bg-player-6',
] as const;

/** Texte de chaque rang de couleur (noms des lignes de l'éditeur), écrit en entier. */
export const PLAYER_TEXT = [
    'text-player-1',
    'text-player-2',
    'text-player-3',
    'text-player-4',
    'text-player-5',
    'text-player-6',
] as const;

const SIZES = {
    6: 'size-1.5 rounded-2',
    8: 'size-2 rounded-2',
    10: 'size-2.5 rounded-3',
} as const;

/**
 * Pastille de couleur d'un joueur (D5) : 6, 8 ou 10 px, rayon 2 ou 3 px.
 * Décor caché aux lecteurs d'écran : le nom du joueur l'accompagne toujours
 * (spec visual-theme « Couleur de joueur »).
 */
export function PlayerSwatch({
    colorSlot,
    size = 8,
    className,
}: {
    colorSlot: number;
    size?: keyof typeof SIZES;
    className?: string;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-block flex-none',
                SIZES[size],
                PLAYER_BG[colorSlot],
                className,
            )}
        />
    );
}
