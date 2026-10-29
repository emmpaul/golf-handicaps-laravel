import { Link } from '@inertiajs/react';
import { index as handicapsIndex } from '@/actions/App/Http/Controllers/HandicapController';
import { PlayerSwatch } from '@/components/player-swatch';

/**
 * Onglets de joueur de la page Handicaps (spec « Mise en page de la page
 * Handicaps », D5) : une pilule de 44 px par joueur, lien vers
 * `/handicaps?joueur=<id>`, avec sa pastille de 8 px. L'onglet courant, plein
 * d'encre, porte `aria-current="page"` ; les autres ont un contour `rule` et
 * un texte gris. La visite ne relit que le joueur choisi et son historique.
 */
export function PlayerTabs({
    players,
    selectedPlayerId,
}: {
    players: { id: string; name: string; colorSlot: number }[];
    selectedPlayerId: string | null;
}) {
    return (
        <nav
            aria-label="Choisir un joueur"
            className="mt-4 flex flex-wrap gap-2"
        >
            {players.map((player) => (
                <Link
                    key={player.id}
                    href={handicapsIndex({ query: { joueur: player.id } })}
                    only={['selectedPlayerId', 'history']}
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
