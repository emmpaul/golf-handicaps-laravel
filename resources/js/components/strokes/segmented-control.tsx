import { cn } from '@/lib/utils';

/**
 * Sélecteur à deux positions du design (page Coups, D5) : un cadre de 4 px de
 * remplissage, fond `surface-1`, contour `rule`, coins de 12 px ; la position
 * choisie porte un curseur d'encre aux coins de 9 px qui glisse, texte `bg`.
 * Deux radios natives couvrent chacune leur moitié : le clavier, le nom et
 * l'état viennent du navigateur.
 */
export function SegmentedControl<T extends string>({
    name,
    label,
    options,
    value,
    onChange,
}: {
    name: string;
    /** Nom du groupe pour les lecteurs d'écran. */
    label: string;
    options: readonly [
        { value: T; label: string },
        { value: T; label: string },
    ];
    value: T;
    onChange: (value: T) => void;
}) {
    const second = value === options[1].value;

    return (
        <div
            role="radiogroup"
            aria-label={label}
            className="relative grid grid-cols-2 rounded-12 border border-rule bg-surface-1 p-1"
        >
            <span
                aria-hidden="true"
                className={cn(
                    'absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-9 bg-ink transition-transform duration-(--dur-nav) ease-(--ease-design)',
                    second && 'translate-x-full',
                )}
            />
            {options.map((option) => (
                <label
                    key={option.value}
                    className="relative flex h-[42px] cursor-pointer items-center justify-center text-label-14 text-ink-2 transition-[color] duration-(--dur-swap) ease-(--ease-design) has-checked:text-bg has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring has-focus-visible:outline-solid"
                >
                    <input
                        type="radio"
                        name={name}
                        className="absolute inset-0 cursor-pointer appearance-none opacity-0"
                        checked={value === option.value}
                        onChange={() => onChange(option.value)}
                    />
                    {option.label}
                </label>
            ))}
        </div>
    );
}
