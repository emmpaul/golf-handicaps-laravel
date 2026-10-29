import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { PlayerSwatch } from '@/components/player-swatch';

/**
 * Onglets de joueur (spec « Mise en page de la page Handicaps », D5) : une
 * pilule de 44 px par joueur, avec sa pastille de 8 px. L'onglet courant,
 * plein d'encre, porte `aria-current="page"` ; les autres ont un contour
 * `rule` et un texte gris. La page qui les monte donne l'adresse de chaque
 * onglet (`hrefFor`, par exemple `/handicaps?joueur=<id>`) et les props à
 * relire (`only`), design D5 d'`index-computation-page` : la visite garde la
 * page (l’index change sur place, D8) et ne relit que ces props.
 */
export function PlayerTabs({
    players,
    selectedPlayerId,
    hrefFor,
    only,
}: {
    players: { id: string; name: string; colorSlot: number }[];
    selectedPlayerId: string | null;
    hrefFor: (playerId: string) => InertiaLinkProps['href'];
    only: string[];
}) {
    return (
        <nav
            aria-label="Choisir un joueur"
            className="mt-4 flex flex-wrap gap-2"
        >
            {players.map((player) => (
                <Link
                    key={player.id}
                    href={hrefFor(player.id)}
                    only={only}
                    preserveState
                    preserveScroll
                    aria-current={
                        player.id === selectedPlayerId ? 'page' : undefined
                    }
                    className="flex h-11 items-center gap-2 rounded-pill border border-rule bg-transparent px-4 text-label-14 text-ink-2 transition-[background-color,color,border-color,scale] duration-(--dur-swap) ease-(--ease-design) active:scale-[0.96] aria-[current=page]:border-ink aria-[current=page]:bg-ink aria-[current=page]:text-bg"
                >
                    <PlayerSwatch colorSlot={player.colorSlot} />
                    {player.name}
                </Link>
            ))}
        </nav>
    );
}
