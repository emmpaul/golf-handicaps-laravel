import { Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/ImportController';
import ImportReadingController from '@/actions/App/Http/Controllers/ImportReadingController';
import { show as showRound } from '@/actions/App/Http/Controllers/RoundController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ORB_FOR, Pending, PendingOrb } from '@/components/ui/pending';
import { formatPlayedOn } from '@/lib/format';
import { ACTION_LINK, cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';
import { DeleteImport } from './delete-import';

export type ImportStatus = 'queued' | 'reading' | 'ready' | 'failed';

export type ImportAction =
    | 'review'
    | 'correctEmpty'
    | 'retry'
    | 'viewRound'
    | 'delete';

/** Un import de la liste (Query `ImportList`) : son état et les actions qu'il permet. */
export type ImportListItem = {
    id: string;
    status: ImportStatus;
    label: string;
    failure: string | null;
    roundId: string | null;
    /** `yyyy-mm-dd hh:mm`, heure de Montréal. */
    createdAt: string;
    actions: ImportAction[];
};

/** La liste se recharge toutes les 2 s tant qu'un import attend sa lecture (design D6). */
export const POLL_INTERVAL_MS = 2000;

const isWaiting = ({ status }: ImportListItem) =>
    status === 'queued' || status === 'reading';

/** « 17 octobre 2026 à 21:05 » pour `2026-10-17 21:05`. */
const createdLabel = (createdAt: string) => {
    const [date, time] = createdAt.split(' ');

    return `${formatPlayedOn(date)} à ${time}`;
};

/** Lien d'action ; désactivé pendant la relance de la lecture de sa ligne. */
function ActionLink({
    href,
    disabled,
    children,
}: {
    href: RouteDefinition<'get'>;
    disabled: boolean;
    children: string;
}) {
    return (
        <Link
            href={href}
            aria-disabled={disabled || undefined}
            tabIndex={disabled ? -1 : undefined}
            onClick={(event) => {
                if (disabled) {
                    event.preventDefault();
                }
            }}
            className={cn(
                ACTION_LINK,
                'aria-disabled:cursor-not-allowed aria-disabled:opacity-55',
            )}
        >
            {children}
        </Link>
    );
}

/**
 * Une ligne de la liste : date d'envoi, état (avec l'orbe tant que la lecture
 * attend), raison de l'échec et actions de son état, données par le serveur.
 */
function ImportLine({ item }: { item: ImportListItem }) {
    const [retrying, setRetrying] = useState(false);
    const has = (action: ImportAction) => item.actions.includes(action);
    const heading = createdLabel(item.createdAt);

    function retry() {
        router.post(
            ImportReadingController(item.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setRetrying(true),
                onFinish: () => setRetrying(false),
            },
        );
    }

    return (
        <li className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b py-2 text-sm">
            <h3 className="font-heading text-base font-semibold">
                <time dateTime={item.createdAt.replace(' ', 'T')}>
                    {heading}
                </time>
            </h3>
            {/* Orbe seule, sans `role="status"` : le badge change déjà à chaque rafraîchissement. */}
            {item.status === 'queued' || item.status === 'reading' ? (
                <PendingOrb state={ORB_FOR[item.status]} />
            ) : null}
            <span className="flex basis-full flex-wrap items-baseline justify-between gap-x-3">
                <Badge
                    data-testid="import-status"
                    variant={
                        item.status === 'failed' ? 'destructive' : 'secondary'
                    }
                >
                    {item.label}
                </Badge>
                <span className="ml-auto flex min-w-0 flex-wrap items-center justify-end gap-x-4">
                    {has('viewRound') && item.roundId !== null ? (
                        <ActionLink
                            href={showRound(item.roundId)}
                            disabled={false}
                        >
                            Voir le round
                        </ActionLink>
                    ) : null}
                    {has('review') ? (
                        <ActionLink href={show(item.id)} disabled={retrying}>
                            Vérifier
                        </ActionLink>
                    ) : null}
                    {has('correctEmpty') ? (
                        <ActionLink href={show(item.id)} disabled={retrying}>
                            Ouvrir l'écran de correction vide
                        </ActionLink>
                    ) : null}
                    {has('retry') ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="max-md:min-h-11"
                            disabled={retrying}
                            onClick={retry}
                        >
                            Relancer la lecture
                        </Button>
                    ) : null}
                    {has('delete') ? (
                        <DeleteImport importId={item.id} disabled={retrying} />
                    ) : null}
                </span>
            </span>
            {item.failure !== null ? (
                <p className="basis-full text-muted-foreground">
                    {item.failure}
                </p>
            ) : null}
            {has('retry') ? (
                <Pending
                    label={retrying ? 'Relance de la lecture…' : null}
                    orb="send"
                />
            ) : null}
        </li>
    );
}

/**
 * Les 20 derniers imports, du plus récent au plus ancien (spec « Liste des
 * imports »). Tant qu'un import est en attente ou en lecture, seule la prop
 * `imports` se recharge toutes les 2 s ; le poll s'arrête ensuite.
 */
export function ImportList({ imports }: { imports: ImportListItem[] }) {
    const waiting = imports.some(isWaiting);
    const { start, stop } = usePoll(
        POLL_INTERVAL_MS,
        { only: ['imports'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (waiting) {
            start();
        } else {
            stop();
        }
    }, [waiting, start, stop]);

    if (imports.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="imports-title"
            className="flex flex-col gap-3"
        >
            <h2
                id="imports-title"
                className="font-heading text-xl font-extrabold"
            >
                Imports
            </h2>
            {/* Registre réglé : filet d'encre en tête, une rangée par import sur un filet fin. */}
            <ul
                aria-labelledby="imports-title"
                className="flex flex-col border-t-2 border-foreground"
            >
                {imports.map((item) => (
                    <ImportLine key={item.id} item={item} />
                ))}
            </ul>
        </section>
    );
}
