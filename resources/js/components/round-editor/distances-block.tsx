import { ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';
import type { CellValue } from '@/lib/round-checks';
import { cn } from '@/lib/utils';
import { GridInput } from './score-grid';

/**
 * Bloc « Distances », replié sous la grille (décision Q6, spec `rounds`,
 * « Grille de saisie sur téléphone ») : une case de 44 px par trou, nommée
 * « Distance trou 1 » à « Distance trou 18 », six par rangée pour tenir à
 * 320 px. `details` natif : replié, ses cases restent montées, et un problème
 * de distance reste compté et listé dans la barre d'enregistrement. Il s'ouvre
 * de lui-même quand une distance devient un problème (DO42), pour que la case
 * ambre soit en vue ; refermé ensuite par l'utilisateur, il le reste.
 */
export function DistancesBlock({
    distances,
    invalid,
    onCell,
    onLeave,
}: {
    distances: CellValue[];
    invalid: ReadonlySet<string>;
    onCell: (row: string, hole: number, value: CellValue) => void;
    onLeave?: (cell: string) => void;
}) {
    const distanceInvalid = [...invalid].some((key) =>
        key.startsWith('distance:'),
    );
    const [open, setOpen] = useState(distanceInvalid);
    const [wasInvalid, setWasInvalid] = useState(distanceInvalid);

    // A distance that turns invalid opens the block (state adjusted during render).
    if (distanceInvalid !== wasInvalid) {
        setWasInvalid(distanceInvalid);

        if (distanceInvalid) {
            setOpen(true);
        }
    }

    return (
        <details
            open={open}
            onToggle={(event) => setOpen(event.currentTarget.open)}
            className="group mt-6 border-t border-rule"
        >
            <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between font-mono text-section text-ink-2 uppercase [&::-webkit-details-marker]:hidden">
                Distances
                <ChevronDownIcon
                    aria-hidden="true"
                    className="size-4 transition-[rotate] duration-(--dur-press) group-open:rotate-180"
                />
            </summary>
            <div className="grid grid-cols-6 gap-1.5 pb-2">
                {distances.map((distance, i) => {
                    const key = `distance:${i + 1}`;
                    const error = invalid.has(key);

                    return (
                        <label
                            key={key}
                            className="flex min-w-0 flex-col gap-1"
                        >
                            <span
                                aria-hidden="true"
                                className="text-center font-mono text-caption-9 text-ink-2"
                            >
                                {i + 1}
                            </span>
                            <GridInput
                                label={`Distance trou ${i + 1}`}
                                value={distance}
                                invalid={error}
                                onChange={(value) =>
                                    onCell('distance', i + 1, value)
                                }
                                onBlur={() => onLeave?.(key)}
                                className={cn(
                                    'h-11 w-full min-w-0 rounded-8 border px-0.5 text-center font-mono text-num-m-16 text-ink-2 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-solid',
                                    error
                                        ? 'border-accent/60 bg-accent/8'
                                        : 'border-rule bg-transparent',
                                )}
                            />
                        </label>
                    );
                })}
            </div>
        </details>
    );
}
