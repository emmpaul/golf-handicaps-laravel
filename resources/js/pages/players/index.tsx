import { Form, Head } from '@inertiajs/react';
import {
    destroy as removeAlias,
    store as storeAlias,
} from '@/actions/App/Http/Controllers/PlayerAliasController';
import {
    store as storePlayer,
    update as updatePlayer,
} from '@/actions/App/Http/Controllers/PlayerController';
import { ComputationHelp } from '@/components/handicaps/computation-help';
import { PageTitle } from '@/components/page-title';
import { InlineForm } from '@/components/players/inline-form';
import { Button } from '@/components/ui/button';
import { formatIndex } from '@/lib/format';
import { cn } from '@/lib/utils';

export type PlayerOverview = {
    id: string;
    name: string;
    aliases: { id: string; alias: string }[];
    /** Index d'après le dernier round joué, en dixièmes ; `null` sans round. */
    indexT: number | null;
    provisional: boolean;
};

/**
 * Colonnes de la table à partir de 1 024 px : joueur et index, alias,
 * renommer, nouvel alias. En dessous, chaque rangée empile ces quatre parties.
 */
const PLAYER_COLUMNS =
    'lg:grid lg:grid-cols-[12rem_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] lg:items-start lg:gap-x-6';

/**
 * Joueurs du groupe : index actuel, ajout, renommage, alias ajoutés et retirés
 * (spec `players`), puis l'explication du calcul. La liste arrive triée par nom
 * sans tenir compte de la casse, chaque joueur avec ses alias par ordre
 * alphabétique.
 */
export default function PlayersIndex({
    players,
}: {
    players: PlayerOverview[];
}) {
    return (
        <>
            <Head title="Joueurs" />
            <PageTitle>Joueurs</PageTitle>
            {/* La table des joueurs : l'ajout en première rangée, sur téléphone comme sur ordinateur. */}
            <div className="flex min-w-0 flex-col">
                <section
                    aria-labelledby="add-player-title"
                    className="flex flex-col gap-2 border-t-2 border-foreground px-2 py-3 lg:flex-row lg:items-start lg:gap-6"
                >
                    <h2
                        id="add-player-title"
                        className="font-heading text-xl font-bold lg:w-48 lg:shrink-0 lg:pt-0.5"
                    >
                        Ajouter un joueur
                    </h2>
                    <div className="min-w-0 lg:w-96">
                        <InlineForm
                            action={storePlayer()}
                            errorBag="addPlayer"
                            field="name"
                            label="Nouveau joueur"
                            submit="Ajouter"
                        />
                    </div>
                </section>
                <div
                    aria-hidden
                    className={cn(
                        'hidden bg-band px-2 py-1.5 text-sm font-medium text-band-ink',
                        PLAYER_COLUMNS,
                    )}
                >
                    <span>Joueur</span>
                    <span>Alias</span>
                    <span>Renommer</span>
                    <span>Nouvel alias</span>
                </div>
                <ul
                    aria-label="Joueurs"
                    className="max-lg:border-t-2 max-lg:border-foreground"
                >
                    {players.map((player) => (
                        <li
                            key={player.id}
                            className={cn(
                                'flex flex-col gap-3 border-b px-2 py-3',
                                PLAYER_COLUMNS,
                            )}
                        >
                            <div className="flex flex-wrap items-baseline justify-between gap-x-3 lg:flex-col lg:gap-0.5">
                                <h2 className="font-heading text-2xl leading-tight font-bold [overflow-wrap:anywhere]">
                                    {player.name}
                                </h2>
                                <span
                                    data-testid="player-index"
                                    className="text-sm text-muted-foreground tabular-nums"
                                >
                                    Index{' '}
                                    {formatIndex(
                                        player.indexT,
                                        player.provisional,
                                    )}
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm lg:min-h-8">
                                {/* Sous l'en-tête « Alias » à partir de 1 024 px : le libellé reste pour les lecteurs d'écran. */}
                                <span className="text-muted-foreground lg:sr-only">
                                    Alias :
                                </span>{' '}
                                {player.aliases.length > 0 ? (
                                    <ul
                                        aria-label={`Alias de ${player.name}`}
                                        className="flex flex-wrap gap-x-3"
                                    >
                                        {player.aliases.map((alias) => (
                                            <li
                                                key={alias.id}
                                                className="flex items-center gap-1"
                                            >
                                                <span
                                                    data-slot="alias"
                                                    className="font-medium"
                                                >
                                                    {alias.alias}
                                                </span>
                                                {/* Retrait sans confirmation (spec `players`). */}
                                                <Form
                                                    action={removeAlias({
                                                        player: player.id,
                                                        alias: alias.id,
                                                    })}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            variant="ghost"
                                                            size="xs"
                                                            disabled={
                                                                processing
                                                            }
                                                            className="text-muted-foreground max-md:min-h-11"
                                                            aria-label={`Retirer l’alias ${alias.alias} de ${player.name}`}
                                                        >
                                                            Retirer
                                                        </Button>
                                                    )}
                                                </Form>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <span>Aucun alias</span>
                                )}
                            </div>
                            <InlineForm
                                key={`name-${player.name}`}
                                action={updatePlayer(player.id)}
                                errorBag={`rename-${player.id}`}
                                field="name"
                                label={`Nom de ${player.name}`}
                                submit="Renommer"
                                defaultValue={player.name}
                            />
                            <InlineForm
                                action={storeAlias(player.id)}
                                errorBag={`alias-${player.id}`}
                                field="alias"
                                label={`Nouvel alias de ${player.name}`}
                                submit="Ajouter l’alias"
                            />
                        </li>
                    ))}
                </ul>
            </div>
            <ComputationHelp />
        </>
    );
}
