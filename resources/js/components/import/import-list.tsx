import { Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/ImportController';
import ImportPhotoController from '@/actions/App/Http/Controllers/ImportPhotoController';
import ImportReadingController from '@/actions/App/Http/Controllers/ImportReadingController';
import { show as showRound } from '@/actions/App/Http/Controllers/RoundController';
import { Badge } from '@/components/ui/badge';
import { SectionLabel } from '@/components/section-label';
import { Button, buttonVariants } from '@/components/ui/button';
import { ORB_FOR, Pending, PendingOrb } from '@/components/ui/pending';
import { formatShortPlayedOn } from '@/lib/format';
import { cn } from '@/lib/utils';
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

/** « 23 oct. 2026 · 16:58 » pour `2026-10-23 16:58` (décision Q5). */
const createdLabel = (createdAt: string) => {
    const [date, time] = createdAt.split(' ');

    return `${formatShortPlayedOn(date)} · ${time}`;
};

/**
 * Pastille d'état (spec « Ligne d'un import ») : les mots du design pour un
 * import prêt (« À VÉRIFIER », ambre) et en lecture (« LECTURE… », cyan), le
 * libellé du serveur pour les autres, « Échec » en rouge, le reste neutre.
 */
function statusPill(item: ImportListItem): {
    text: string;
    variant: 'accent' | 'info' | 'destructive' | 'secondary';
} {
    if (item.roundId !== null) {
        return { text: item.label, variant: 'secondary' };
    }

    switch (item.status) {
        case 'ready':
            return { text: 'À VÉRIFIER', variant: 'accent' };
        case 'reading':
            return { text: 'LECTURE…', variant: 'info' };
        case 'failed':
            return { text: item.label, variant: 'destructive' };
        default:
            return { text: item.label, variant: 'secondary' };
    }
}

/**
 * Lien d'action en bouton de 44 px : le premier est plein et prend la largeur
 * restante ; désactivé pendant la relance de la lecture de sa ligne.
 */
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
                buttonVariants({ size: 'sm' }),
                'flex-1',
                'aria-disabled:cursor-not-allowed aria-disabled:bg-surface-3 aria-disabled:text-ink-3',
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

    const pill = statusPill(item);

    return (
        <li className="border-b border-rule-soft py-4">
            <div className="flex items-center gap-3">
                <div className="h-[42px] w-14 flex-none overflow-hidden rounded-6 bg-surface-2">
                    <img
                        src={ImportPhotoController.url(item.id)}
                        alt=""
                        className="size-full object-cover object-[50%_55%]"
                    />
                </div>
                <h3 className="min-w-0 text-label">
                    <time dateTime={item.createdAt.replace(' ', 'T')}>
                        {heading}
                    </time>
                </h3>
                <span className="ml-auto flex flex-none items-center gap-2">
                    {/* Orbe seule, sans `role="status"` : la pastille change déjà à chaque rafraîchissement. */}
                    {item.status === 'queued' || item.status === 'reading' ? (
                        <PendingOrb state={ORB_FOR[item.status]} />
                    ) : null}
                    <Badge
                        data-testid="import-status"
                        variant={pill.variant}
                        className="h-auto px-2 py-[5px] tracking-[0.06em]"
                    >
                        {pill.text}
                    </Badge>
                </span>
            </div>
            {item.failure !== null ? (
                <p className="mt-2 text-body-13 text-ink-2">{item.failure}</p>
            ) : null}
            {item.actions.length > 0 ? (
                <div className="mt-3 flex flex-wrap gap-2">
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
                            variant="outline"
                            size="sm"
                            className="font-medium"
                            disabled={retrying}
                            onClick={retry}
                        >
                            Relancer la lecture
                        </Button>
                    ) : null}
                    {has('delete') ? (
                        <DeleteImport importId={item.id} disabled={retrying} />
                    ) : null}
                </div>
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

    return (
        <section aria-labelledby="imports-title" className="mt-[30px]">
            <SectionLabel id="imports-title" value={imports.length}>
                Imports
            </SectionLabel>
            {imports.length === 0 ? (
                <p className="mt-5 text-body text-ink-2">
                    Aucun import en attente.
                </p>
            ) : (
                <ul aria-labelledby="imports-title">
                    {imports.map((item) => (
                        <ImportLine key={item.id} item={item} />
                    ))}
                </ul>
            )}
        </section>
    );
}
