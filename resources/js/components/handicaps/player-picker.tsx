import { Link } from '@inertiajs/react';
import { index as handicapsIndex } from '@/actions/App/Http/Controllers/HandicapController';

/**
 * Player picker of the Handicaps page (design D4): one link per player to
 * `/handicaps?joueur=<id>`, square segments, the chosen one in solid ink with
 * `aria-current="page"`. The visit reloads the chosen player and their history
 * only: `players` is not read again.
 */
export function PlayerPicker({
    players,
    selectedPlayerId,
}: {
    players: { id: string; name: string }[];
    selectedPlayerId: string | null;
}) {
    return (
        <nav
            aria-label="Choisir un joueur"
            className="grid auto-cols-fr grid-flow-col border border-foreground"
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
                    className="font-heading text-lg flex min-h-11 min-w-0 items-center justify-center px-2 font-semibold [overflow-wrap:anywhere] transition-[color,background-color] duration-(--dur-micro) ease-out not-last:border-r not-last:border-foreground not-aria-[current=page]:hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-ring aria-[current=page]:bg-primary aria-[current=page]:text-primary-foreground md:min-h-8"
                >
                    {player.name}
                </Link>
            ))}
        </nav>
    );
}
