import { CalendarIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ComponentProps, ComponentType, Ref } from 'react';
import { fr } from 'react-day-picker/locale';
import { Button } from '@/components/ui/button';
import { Calendar, CalendarDayButton } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatPlayedOn, isoToLocalDate, localDateToIso } from '@/lib/format';

/** Premier mois proposé dans la liste des années du calendrier. */
const START_MONTH = new Date(2020, 0);

type DayButtonProps = ComponentProps<typeof CalendarDayButton>;

/** `CalendarDayButton` passe ses autres props, `ref` compris (React 19), au bouton. */
const RegistryDayButton = CalendarDayButton as ComponentType<
    DayButtonProps & { ref: Ref<HTMLButtonElement> }
>;

/**
 * Bouton du jour qui prend le focus quand le calendrier le désigne. Celui du
 * registre ne relie pas sa ref au bouton : sans ce relais, les flèches ne
 * déplacent pas le focus.
 */
function DayButton({ modifiers, ...props }: DayButtonProps) {
    const ref = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (modifiers.focused) {
            ref.current?.focus();
        }
    }, [modifiers.focused]);

    return (
        <RegistryDayButton
            ref={ref}
            modifiers={modifiers}
            locale={fr}
            {...props}
        />
    );
}

/**
 * Date de jeu de l'éditeur : un bouton qui affiche « 17 octobre 2026 » et
 * ouvre un calendrier en français, semaine au lundi, mois et année au choix.
 * `value` et `onChange` restent en ISO `YYYY-MM-DD`, lus et écrits comme jour
 * civil (spec « Choix de la date au calendrier »). Échap ferme sans rien
 * changer ; le focus revient sur le champ.
 */
export function DateField({
    id,
    value,
    invalid,
    onChange,
    onBlur,
}: {
    id: string;
    value: string | null;
    invalid: boolean;
    onChange: (iso: string) => void;
    onBlur?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const popup = useRef<HTMLDivElement>(null);
    const selected = value ? isoToLocalDate(value) : undefined;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                render={
                    <Button
                        id={id}
                        variant="outline"
                        aria-invalid={invalid || undefined}
                        className="w-full justify-start font-normal"
                        onBlur={onBlur}
                    />
                }
            >
                <CalendarIcon data-icon="inline-start" />
                {value ? formatPlayedOn(value) : 'Choisir une date'}
            </PopoverTrigger>
            {/* À l'ouverture, le focus va au jour choisi : les flèches partent de lui. Cases de 44 px,
                réduites sous 348 px de large pour que les 7 jours tiennent dans l'écran. */}
            <PopoverContent
                ref={popup}
                className="w-auto p-0"
                align="start"
                collisionPadding={8}
                initialFocus={() =>
                    popup.current?.querySelector<HTMLElement>(
                        '[aria-selected="true"] button',
                    ) ?? true
                }
            >
                <Calendar
                    mode="single"
                    required
                    selected={selected}
                    defaultMonth={selected}
                    locale={fr}
                    captionLayout="dropdown"
                    startMonth={START_MONTH}
                    endMonth={new Date(new Date().getFullYear(), 11)}
                    className="[--cell-size:min(--spacing(11),calc((100vw-2.5rem)/7))]"
                    components={{ DayButton }}
                    onSelect={(date) => {
                        onChange(localDateToIso(date));
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
