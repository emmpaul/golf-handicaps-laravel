import { Form, Head } from '@inertiajs/react';
import { XIcon } from 'lucide-react';
import { useState } from 'react';
import {
    destroy as removeAlias,
    store as storeAlias,
} from '@/actions/App/Http/Controllers/PlayerAliasController';
import {
    store as storePlayer,
    update as updatePlayer,
} from '@/actions/App/Http/Controllers/PlayerController';
import { ComputationLink } from '@/components/handicaps/computation-help';
import { PageTopRow } from '@/components/page-top-row';
import { PlayerSwatch } from '@/components/player-swatch';
import { InlineForm } from '@/components/players/inline-form';
import { formatTenths, NO_VALUE } from '@/lib/format';

export type PlayerOverview = {
    id: string;
    name: string;
    aliases: { id: string; alias: string }[];
    /** Index d'après le dernier round joué, en dixièmes ; `null` sans round. */
    indexT: number | null;
    provisional: boolean;
    /** Rang de couleur du joueur, 0 à 5 (D6). */
    colorSlot: number;
};

/** État écrit sous l'index (spec players « Index sur la page Joueurs »). */
function indexState({ indexT, provisional }: PlayerOverview): string {
    if (indexT === null) {
        return "PAS D'INDEX";
    }

    return provisional ? 'PROVISOIRE' : 'INDEX';
}

/** Pastille d'un alias (32 px) et son bouton de retrait, sans confirmation (spec `players`). */
function AliasPill({
    player,
    alias,
}: {
    player: PlayerOverview;
    alias: PlayerOverview['aliases'][number];
}) {
    return (
        <li className="flex h-8 items-center gap-1 rounded-pill bg-surface-2 pr-1 pl-2.5 font-mono text-meta-12">
            <span data-slot="alias">{alias.alias}</span>
            <Form
                action={removeAlias({ player: player.id, alias: alias.id })}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <button
                        type="submit"
                        disabled={processing}
                        aria-label={`Retirer l’alias ${alias.alias} de ${player.name}`}
                        className="flex size-6 items-center justify-center rounded-full text-ink-2 focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-solid disabled:cursor-not-allowed disabled:text-ink-3"
                    >
                        <XIcon aria-hidden="true" className="size-3" />
                    </button>
                )}
            </Form>
        </li>
    );
}

/**
 * Une ligne de joueur (spec « Mise en page de la page Joueurs ») : pastille,
 * nom, index et son état à droite ; dessous « ALIAS », ses pastilles et
 * « Modifier ». Ouverte, elle montre le renommage et l'ajout d'alias.
 */
function PlayerLine({
    player,
    open,
    onToggle,
}: {
    player: PlayerOverview;
    open: boolean;
    onToggle: () => void;
}) {
    const panelId = `player-edit-${player.id}`;

    return (
        <li className="border-b border-rule-soft py-4">
            <div className="flex min-h-11 items-center gap-2.5">
                <PlayerSwatch colorSlot={player.colorSlot} size={10} />
                <h2 className="min-w-0 text-name-m [overflow-wrap:anywhere]">
                    {player.name}
                </h2>
                <span
                    data-testid="player-index"
                    className="ml-auto flex flex-none flex-col items-end gap-1 font-mono"
                >
                    <span className="text-num-l">
                        {player.indexT === null
                            ? NO_VALUE
                            : formatTenths(player.indexT)}
                    </span>
                    <span className="text-caption text-ink-2">
                        {indexState(player)}
                    </span>
                </span>
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                <span className="mr-1 font-mono text-section text-ink-2 uppercase">
                    Alias
                </span>
                {player.aliases.length > 0 ? (
                    <ul
                        aria-label={`Alias de ${player.name}`}
                        className="flex flex-wrap gap-1.5"
                    >
                        {player.aliases.map((alias) => (
                            <AliasPill
                                key={alias.id}
                                player={player}
                                alias={alias}
                            />
                        ))}
                    </ul>
                ) : (
                    <span className="text-body-13 text-ink-2">Aucun alias</span>
                )}
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={open ? panelId : undefined}
                    onClick={onToggle}
                    className="ml-auto flex min-h-8 items-center text-link-13 text-ink-2"
                >
                    {open ? 'Fermer' : 'Modifier'}
                </button>
            </div>
            {open ? (
                <div id={panelId} className="mt-3.5 flex flex-col gap-2">
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
                    <p className="text-body-12 text-ink-2">
                        Les alias servent à reconnaître le joueur sur les photos
                        de carte.
                    </p>
                </div>
            ) : null}
        </li>
    );
}

/**
 * Joueurs du groupe : ajout, puis une ligne par joueur avec son index actuel,
 * ses alias et, repliés derrière « Modifier », le renommage et l'ajout
 * d'alias (spec `players`) ; un seul joueur ouvert à la fois. Puis
 * l'explication du calcul. La liste arrive triée par nom sans tenir compte de
 * la casse, chaque joueur avec ses alias par ordre alphabétique.
 */
export default function PlayersIndex({
    players,
}: {
    players: PlayerOverview[];
}) {
    const [openId, setOpenId] = useState<string | null>(null);

    return (
        <>
            <Head title="Joueurs" />
            <PageTopRow />
            <h1 className="text-title">Joueurs</h1>
            <section aria-label="Ajouter un joueur" className="mt-5">
                <InlineForm
                    action={storePlayer()}
                    errorBag="addPlayer"
                    field="name"
                    label="Nouveau joueur"
                    submit="Ajouter"
                    filled
                />
            </section>
            <ul aria-label="Joueurs" className="mt-6 border-t border-rule">
                {players.map((player) => (
                    <PlayerLine
                        key={player.id}
                        player={player}
                        open={openId === player.id}
                        onToggle={() =>
                            setOpenId(openId === player.id ? null : player.id)
                        }
                    />
                ))}
            </ul>
            <div className="mt-7">
                <ComputationLink />
            </div>
        </>
    );
}
