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
 * `name` de l'ajout ne s'affiche pas sous les champs de renommage.
 */
export function InlineForm({
    action,
    errorBag,
    field,
    label,
    submit,
    defaultValue = '',
}: {
    action: RouteDefinition<'post' | 'patch'>;
    errorBag: string;
    field: 'name' | 'alias';
    label: string;
    submit: string;
    defaultValue?: string;
}) {
    const errorId = useId();

    return (
        <Form
            action={action}
            errorBag={errorBag}
            resetOnSuccess
            options={{ preserveScroll: true }}
            className="flex flex-col gap-1"
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
                            variant="outline"
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
                            className="text-sm text-destructive"
                        >
                            {errors[field]}
                        </p>
                    ) : null}
                </>
            )}
        </Form>
    );
}
