import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Pending } from '@/components/ui/pending';
import { problemCountLabel } from '@/lib/round-checks';
import type { Problem } from '@/lib/round-checks';

/** Messages des contrôles, les identiques regroupés (« Case vide (×18) »). */
function groupedMessages(problems: readonly Problem[]): string[] {
    const counts = new Map<string, number>();

    for (const { message } of problems) {
        counts.set(message, (counts.get(message) ?? 0) + 1);
    }

    return [...counts].map(([message, count]) =>
        count > 1 ? `${message} (×${count})` : message,
    );
}

/**
 * Barre d'enregistrement de l'éditeur (spec `rounds`, « Barre
 * d'enregistrement de l'éditeur ») : collée en bas de l'écran, au-dessus de la
 * zone sûre, sur un dégradé du fond vers le transparent. Les problèmes
 * restants, après un point ambre, en Geist 500, 13 px, ambre ; puis le bouton
 * de 56 px, rayon 14 px, bloqué tant que la carte est incohérente (fond
 * `surface-3`, texte `ink-3`) ou pendant l'enregistrement. `children` : ce qui
 * accompagne l'attente (indicateur de sauvegarde d'un import). Le bouton
 * change de fond et de texte en 300 ms et s'enfonce à 0,98 à l'appui (D8).
 */
export function EditorSaveBar({
    problems,
    saving,
    onSave,
    children,
}: {
    problems: readonly Problem[];
    saving: boolean;
    onSave: () => void;
    children?: ReactNode;
}) {
    return (
        <div className="sticky bottom-0 z-20 -mx-(--gutter) mt-6 flex flex-col gap-2.5 bg-linear-to-t from-bg from-70% to-bg/0 px-(--gutter-nav) pt-3.5 pb-[max(30px,var(--safe-bottom))]">
            {problems.length > 0 ? (
                <div className="flex items-center gap-2 text-link-13 leading-[1.3] text-accent">
                    <span
                        aria-hidden="true"
                        className="size-1.5 flex-none rounded-full bg-accent"
                    />
                    {/* Une liste nommée pour les lecteurs d'écran ; à l'œil, les messages joints par « · » (DO43). */}
                    <ul
                        aria-label="Problèmes"
                        data-testid="problem-messages"
                        className="inline"
                    >
                        {groupedMessages(problems).map((message, index) => (
                            <li key={message} className="inline">
                                {index > 0 ? (
                                    <span aria-hidden="true">{' · '}</span>
                                ) : null}
                                {message}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
            <Button
                size="xl"
                className="w-full duration-(--dur-swap) ease-[ease] active:not-disabled:scale-[0.98]"
                disabled={problems.length > 0 || saving}
                onClick={onSave}
            >
                {problems.length > 0
                    ? problemCountLabel(problems.length)
                    : 'Enregistrer'}
            </Button>
            <Pending
                label={saving ? 'Enregistrement du round…' : null}
                orb="save"
            />
            {children}
        </div>
    );
}
