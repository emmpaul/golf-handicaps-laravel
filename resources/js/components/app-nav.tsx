import { usePage } from '@inertiajs/react';
import { Camera, Flag, ListOrdered, TrendingUp, Users } from 'lucide-react';
import { useId } from 'react';
import { NavLink } from '@/components/nav-link';
import { cn } from '@/lib/utils';
import { index as coursesIndex } from '@/routes/courses';
import { index as handicapsIndex } from '@/routes/handicaps';
import { index as importIndex } from '@/routes/import';
import { index as playersIndex } from '@/routes/players';
import { index as roundsIndex } from '@/routes/rounds';

/** Les quatre onglets de la barre, dans l'ordre du design (D4). */
export const TABS = [
    { href: roundsIndex.url(), label: 'Rounds', icon: ListOrdered },
    { href: handicapsIndex.url(), label: 'Handicaps', icon: TrendingUp },
    { href: coursesIndex.url(), label: 'Parcours', icon: Flag },
    { href: playersIndex.url(), label: 'Joueurs', icon: Users },
] as const;

/**
 * Barre flottante du bas (D4) : la pilule des quatre onglets puis le bouton
 * rond Importer, dans la colonne de 390 px (décision Q3), 28 px au-dessus de
 * la zone sûre du bas. Seul l'onglet courant montre son libellé ; les autres
 * le gardent dans le DOM, refermé à une largeur maximale nulle. La pastille du
 * bouton Importer compte les imports prêts à vérifier (`pendingImports`,
 * prop partagée, D7). Sous 375 px de large, gouttières, marges intérieures
 * et largeur minimale des onglets se resserrent pour que la barre tienne dans
 * 320 − 2 × 16 px avec le libellé le plus long ouvert (DO34) ; la hauteur de
 * 44 px ne change pas.
 */
export function AppNav() {
    const { pendingImports } = usePage().props;
    const badgeId = useId();
    const pending = typeof pendingImports === 'number' ? pendingImports : 0;

    return (
        <nav
            aria-label="Sections"
            className="fixed inset-x-0 bottom-[calc(28px+var(--safe-bottom))] z-(--z-sticky-nav) mx-auto flex max-w-(--column) items-center gap-2.5 px-(--gutter-nav) max-[375px]:gap-2"
        >
            <ul className="flex h-15 flex-1 items-center justify-between rounded-pill border border-rule bg-surface-2 px-[7px] text-ink-2 shadow-float max-[375px]:px-1">
                {TABS.map(({ href, label, icon: Icon }) => (
                    <li key={href} className="flex">
                        <NavLink
                            href={href}
                            label={label}
                            className={(current) =>
                                cn(
                                    'flex h-11 min-w-11 items-center justify-center rounded-pill [transition:background-color_var(--dur-nav)_var(--ease-design),color_var(--dur-nav)_var(--ease-design),padding_var(--dur-nav)_var(--ease-design),scale_var(--dur-press)] active:scale-94 max-[375px]:min-w-8',
                                    current
                                        ? 'bg-ink px-3.5 text-bg max-[375px]:px-2.5'
                                        : 'bg-ink/0 px-3 text-ink-2 max-[375px]:px-1.5',
                                )
                            }
                        >
                            {(current) => (
                                <>
                                    <Icon
                                        aria-hidden
                                        className="size-5 flex-none"
                                        strokeWidth={2.1}
                                    />
                                    <span
                                        data-slot="tab-label"
                                        className={cn(
                                            'block overflow-hidden text-label-13 whitespace-nowrap [transition:max-width_var(--dur-nav)_var(--ease-design),opacity_var(--dur-fade)_ease,margin-left_var(--dur-nav)_var(--ease-design)]',
                                            current
                                                ? 'ml-2 max-w-[90px] opacity-100 max-[375px]:ml-1'
                                                : 'ml-0 max-w-0 opacity-0',
                                        )}
                                    >
                                        {label}
                                    </span>
                                </>
                            )}
                        </NavLink>
                    </li>
                ))}
            </ul>
            <NavLink
                href={importIndex.url()}
                label="Importer"
                aria-describedby={pending > 0 ? badgeId : undefined}
                className={() =>
                    'relative flex size-15 flex-none items-center justify-center rounded-full bg-accent text-bg shadow-float transition-[scale] duration-(--dur-press) ease-out active:scale-92'
                }
            >
                {() => (
                    <>
                        <Camera
                            aria-hidden
                            className="size-6"
                            strokeWidth={2.2}
                        />
                        {pending > 0 && (
                            <>
                                <span
                                    data-slot="pending-badge"
                                    aria-hidden
                                    className="absolute -top-0.5 -right-0.5 h-5 min-w-5 rounded-[10px] bg-ink px-[5px] text-center font-mono text-section leading-5 font-semibold tracking-normal text-bg ring-3 ring-bg"
                                >
                                    {pending}
                                </span>
                                <span id={badgeId} className="sr-only">
                                    {pending === 1
                                        ? '1 import à vérifier'
                                        : `${pending} imports à vérifier`}
                                </span>
                            </>
                        )}
                    </>
                )}
            </NavLink>
        </nav>
    );
}
