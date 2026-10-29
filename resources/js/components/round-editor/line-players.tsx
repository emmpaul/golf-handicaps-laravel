import { XIcon } from 'lucide-react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Pending } from '@/components/ui/pending';
import type { EditorLine, PlayerOption } from './form-state';

/** Valeur de l'option « Nouveau joueur… » du choix du joueur. */
const NEW_PLAYER = 'new';

/** Crée un joueur ; renvoie le message de refus, ou `null` si la ligne lui est associée. */
export type CreatePlayer = (
    key: string,
    name: string,
) => Promise<string | null>;

function NewPlayerForm({
    number,
    onCreate,
}: {
    number: number;
    onCreate: (name: string) => Promise<string | null>;
}) {
    const [error, setError] = useState<string | null>(null);
    const [pending, setPending] = useState(false);
    const errorId = useId();

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const name = new FormData(event.currentTarget).get('name');
        setPending(true);

        try {
            setError(await onCreate(typeof name === 'string' ? name : ''));
        } finally {
            setPending(false);
        }
    }

    return (
        <form
            onSubmit={(event) => void submit(event)}
            className="col-span-2 flex min-w-0 flex-col gap-1"
        >
            <div className="flex gap-2">
                <Input
                    name="name"
                    aria-label={`Nom du nouveau joueur de la ligne ${number}`}
                    placeholder="Nom du joueur"
                    autoComplete="off"
                    required
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                />
                <Button
                    type="submit"
                    variant="outline"
                    disabled={pending}
                    aria-label={`Créer le joueur de la ligne ${number}`}
                >
                    Créer
                </Button>
            </div>
            <Pending
                label={pending ? 'Création du joueur…' : null}
                orb="save"
            />
            {error ? (
                <p
                    id={errorId}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {error}
                </p>
            ) : null}
        </form>
    );
}

function LinePlayer({
    line,
    position,
    players,
    invalid,
    canRemove,
    onAssociate,
    onLeave,
    onRemove,
    onCreate,
}: {
    line: EditorLine;
    position: number;
    players: PlayerOption[];
    invalid: boolean;
    canRemove: boolean;
    onAssociate: (key: string, playerId: string | null) => void;
    onLeave?: (cell: string) => void;
    onRemove: (key: string) => void;
    onCreate: CreatePlayer;
}) {
    const [creating, setCreating] = useState(false);
    const number = position + 1;
    const titleId = useId();

    async function create(name: string) {
        const error = await onCreate(line.key, name);

        if (error === null) {
            setCreating(false);
        }

        return error;
    }

    // Un bloc par ligne (spec « Lignes de joueur groupées sur téléphone ») : le
    // numéro sur une rangée, la liste et le bouton de retrait côte à côte sur
    // la suivante, jamais le bouton seul.
    return (
        <div
            role="group"
            aria-labelledby={titleId}
            className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2"
        >
            <div className="col-span-2 flex flex-wrap items-baseline gap-x-2">
                <span id={titleId} className="w-16 text-sm font-medium">
                    Ligne {number}
                </span>
                {/* Nom lu sur la carte d'un import ; rien pour une ligne ajoutée à la main. */}
                {line.readName ? (
                    <span className="text-sm text-muted-foreground">
                        lu « {line.readName} »
                    </span>
                ) : null}
            </div>
            <NativeSelect
                className="w-full min-w-0"
                aria-label={`Joueur de la ligne ${number}`}
                aria-invalid={invalid || undefined}
                onBlur={() => onLeave?.(`player${position}:player`)}
                value={creating ? NEW_PLAYER : (line.playerId ?? '')}
                onChange={(event) => {
                    const { value } = event.currentTarget;
                    setCreating(value === NEW_PLAYER);

                    if (value !== NEW_PLAYER) {
                        onAssociate(line.key, value || null);
                    }
                }}
            >
                <NativeSelectOption value="">
                    Choisir un joueur
                </NativeSelectOption>
                {players.map((player) => (
                    <NativeSelectOption key={player.id} value={player.id}>
                        {player.name}
                    </NativeSelectOption>
                ))}
                <NativeSelectOption value={NEW_PLAYER}>
                    Nouveau joueur…
                </NativeSelectOption>
            </NativeSelect>
            {canRemove ? (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Retirer la ligne ${number}`}
                    onClick={() => onRemove(line.key)}
                >
                    <XIcon />
                </Button>
            ) : null}
            {creating ? (
                <NewPlayerForm number={number} onCreate={create} />
            ) : null}
        </div>
    );
}

/**
 * Joueur de chaque ligne de la carte, création d'un joueur, ajout (jusqu'à 4)
 * et retrait de lignes (tant qu'il en reste plus d'une).
 */
export function LinePlayers({
    lines,
    players,
    invalid,
    canAdd,
    onAssociate,
    onLeave,
    onCreate,
    onRemove,
    onAdd,
}: {
    lines: EditorLine[];
    players: PlayerOption[];
    invalid: ReadonlySet<string>;
    canAdd: boolean;
    onAssociate: (key: string, playerId: string | null) => void;
    onLeave?: (cell: string) => void;
    onCreate: CreatePlayer;
    onRemove: (key: string) => void;
    onAdd: () => void;
}) {
    return (
        <div className="flex flex-col gap-2">
            {lines.map((line, position) => (
                <LinePlayer
                    key={line.key}
                    line={line}
                    position={position}
                    players={players}
                    invalid={invalid.has(`player${position}:player`)}
                    canRemove={lines.length > 1}
                    onAssociate={onAssociate}
                    onLeave={onLeave}
                    onRemove={onRemove}
                    onCreate={onCreate}
                />
            ))}
            {canAdd ? (
                <Button
                    variant="outline"
                    className="self-start"
                    onClick={onAdd}
                >
                    Ajouter une ligne
                </Button>
            ) : null}
        </div>
    );
}
