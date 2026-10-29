import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { store } from '@/actions/App/Http/Controllers/LoginController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pending } from '@/components/ui/pending';
import BareLayout from '@/layouts/bare-layout';

/**
 * Saisie du code d'accès partagé, hors du cadre commun (spec app-access,
 * recomposée avec les éléments du design, décision Q9, DO12) : la rangée
 * « Golf », le titre, le champ de 48 px et le bouton plein. Un code faux
 * revient en erreur `passcode` (« Code incorrect » ou le message du
 * limiteur), lue sous le champ ; le champ est vidé à chaque erreur.
 */
export default function Login() {
    return (
        <>
            <Head title="Connexion" />
            <p className="flex min-h-11 items-center pt-[max(54px,var(--safe-top))]">
                <span className="text-label">Golf</span>
            </p>
            <h1 className="mt-[22px] text-title">Handicaps golf</h1>
            <div className="mt-6">
                <Form
                    action={store()}
                    resetOnError
                    className="flex flex-col gap-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <Label htmlFor="passcode">Code d'accès</Label>
                            <Input
                                id="passcode"
                                name="passcode"
                                type="password"
                                autoComplete="current-password"
                                required
                                aria-invalid={
                                    errors.passcode ? true : undefined
                                }
                                aria-describedby={
                                    errors.passcode
                                        ? 'passcode-error'
                                        : undefined
                                }
                            />
                            {errors.passcode ? (
                                <p
                                    id="passcode-error"
                                    role="alert"
                                    className="text-body-12 text-signal"
                                >
                                    {errors.passcode}
                                </p>
                            ) : null}
                            <Button
                                type="submit"
                                disabled={processing}
                                className="mt-2 w-full"
                            >
                                Entrer
                            </Button>
                            <Pending
                                label={processing ? 'Connexion…' : null}
                                orb="send"
                            />
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Login.layout = (page: ReactNode) => <BareLayout>{page}</BareLayout>;
