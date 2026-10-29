import { Form } from '@inertiajs/react';
import type { RouteDefinition } from '@/wayfinder';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Pending } from '@/components/ui/pending';

/**
 * Un champ et un bouton, avec le refus du serveur sous le champ (« Ce joueur
 * existe déjà », « Le nom est obligatoire »), relié au champ par
 * `aria-describedby`. Chaque formulaire a son sac d'erreurs : une erreur
 * `name` de l'ajout ne s'affiche pas sous les champs de renommage. Champ et
 * bouton de 48 px côte à côte (design, page Joueurs) : le bouton plein pour
 * l'ajout d'un joueur, à contour et de 130 px dans un joueur ouvert.
 */
export function InlineForm({
    action,
    errorBag,
    field,
    label,
    submit,
    defaultValue = '',
    filled = false,
}: {
    action: RouteDefinition<'post' | 'patch'>;
    errorBag: string;
    field: 'name' | 'alias';
    label: string;
    submit: string;
    defaultValue?: string;
    /** Bouton plein (« Ajouter ») au lieu du bouton à contour de 130 px. */
    filled?: boolean;
}) {
    const errorId = useId();

    return (
        <Form
            action={action}
            errorBag={errorBag}
            resetOnSuccess
            options={{ preserveScroll: true }}
            className="flex flex-col gap-1.5"
        >
            {({ errors, processing }) => (
                <>
                    <div className="flex gap-2">
                        <Input
                            name={field}
                            aria-label={label}
                            placeholder={label}
                            defaultValue={defaultValue}
                            autoComplete="off"
                            required
                            aria-invalid={errors[field] ? true : undefined}
                            aria-describedby={
                                errors[field] ? errorId : undefined
                            }
                        />
                        <Button
                            type="submit"
                            variant={filled ? 'default' : 'outline'}
                            className={
                                filled ? undefined : 'w-[130px] text-label-14'
                            }
                            disabled={processing}
                        >
                            {submit}
                        </Button>
                    </div>
                    <Pending
                        label={processing ? 'Enregistrement du joueur…' : null}
                        orb="save"
                    />
                    {errors[field] ? (
                        <p
                            id={errorId}
                            role="alert"
                            className="text-body-12 text-signal"
                        >
                            {errors[field]}
                        </p>
                    ) : null}
                </>
            )}
        </Form>
    );
}
