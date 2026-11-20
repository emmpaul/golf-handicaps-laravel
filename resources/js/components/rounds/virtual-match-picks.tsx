import { useState } from 'react';
import { PlayerSwatch } from '@/components/player-swatch';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatDayMonth, formatShortPlayedOn } from '@/lib/format';
import { joinNames, picksParam, visitCompare } from '@/lib/virtual-match';
import type { PickKey } from '@/lib/virtual-match';

/** A picked player round, as VirtualMatch sends it (design D3). */
export type VirtualMatchPick = PickKey & {
    name: string;
    colorSlot: number;
    playedOn: string;
};

/** A player round of the course and tees, to add (design D3). */
export type VirtualMatchCandidate = VirtualMatchPick & {
    partners: string[];
    gross: number;
};

/** « seul », « avec Sean », « avec Manu et Sean ». */
const partnersLabel = (partners: string[]) =>
    partners.length === 0 ? 'seul' : `avec ${joinNames(partners)}`;

/**
 * Pastilles des joueurs-rounds choisis et « + Ajouter » (spec `virtual-match`,
 * « Pastilles des joueurs-rounds choisis », « Liste d'ajout », « Un seul round
 * par joueur ») : une pastille de 44 px par choix, dans l'ordre, que toucher
 * retire ; « + Ajouter » ouvre, dans le popover, la liste défilante des
 * joueurs-rounds du parcours, lignes de 52 px. Un joueur déjà choisi y est
 * grisé avec « déjà choisi ». Chaque changement visite l'adresse du nouveau
 * choix (design D5).
 */
export function VirtualMatchPicks({
    courseName,
    tees,
    picks,
    candidates,
}: {
    courseName: string;
    tees: string;
    picks: VirtualMatchPick[];
    candidates: VirtualMatchCandidate[];
}) {
    const [open, setOpen] = useState(false);
    const pickedPlayers = new Set(picks.map((pick) => pick.playerId));
    const visit = (next: PickKey[]) =>
        visitCompare(courseName, tees, picksParam(next));

    return (
        <ul
            aria-label="Joueurs-rounds choisis"
            className="flex flex-wrap gap-2"
        >
            {picks.map((pick) => (
                <li key={pick.playerId}>
                    <button
                        type="button"
                        aria-label={`Retirer ${pick.name}, ${formatShortPlayedOn(pick.playedOn)}`}
                        className="flex h-11 items-center gap-2 rounded-pill bg-surface-2 px-4 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-solid"
                        onClick={() =>
                            visit(
                                picks.filter(
                                    (other) => other.playerId !== pick.playerId,
                                ),
                            )
                        }
                    >
                        <PlayerSwatch colorSlot={pick.colorSlot} size={8} />
                        <span className="text-link">{pick.name}</span>
                        <span className="font-mono text-meta-12 text-ink-3">
                            {formatDayMonth(pick.playedOn)}
                        </span>
                    </button>
                </li>
            ))}
            <li>
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger className="flex h-11 items-center rounded-pill border border-dashed border-rule px-4 text-link text-ink-2 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-solid">
                        + Ajouter
                    </PopoverTrigger>
                    <PopoverContent
                        align="start"
                        collisionPadding={16}
                        className="max-h-80 w-[min(--spacing(80),calc(100vw-2rem))] overflow-y-auto p-1"
                    >
                        <ul aria-label="Joueurs-rounds">
                            {candidates.map((candidate) => {
                                const taken = pickedPlayers.has(
                                    candidate.playerId,
                                );

                                return (
                                    <li
                                        key={`${candidate.roundId}.${candidate.playerId}`}
                                    >
                                        <button
                                            type="button"
                                            disabled={taken}
                                            className="flex h-13 w-full items-center gap-2.5 rounded-10 px-2.5 text-left outline-none hover:bg-surface-2 focus-visible:bg-surface-2 disabled:text-ink-4 disabled:hover:bg-transparent"
                                            onClick={() => {
                                                setOpen(false);
                                                visit([...picks, candidate]);
                                            }}
                                        >
                                            <PlayerSwatch
                                                colorSlot={candidate.colorSlot}
                                                size={8}
                                            />
                                            <span className="flex min-w-0 flex-col gap-1">
                                                <span className="truncate text-body">
                                                    {`${candidate.name} · ${formatShortPlayedOn(candidate.playedOn)} · ${partnersLabel(candidate.partners)}`}
                                                </span>
                                                {taken ? (
                                                    <span className="font-mono text-meta-12">
                                                        déjà choisi
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span className="ml-auto flex-none font-mono text-num-m-15">
                                                {candidate.gross}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </PopoverContent>
                </Popover>
            </li>
        </ul>
    );
}
