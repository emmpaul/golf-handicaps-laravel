import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { store } from '@/actions/App/Http/Controllers/LoginController';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pending } from '@/components/ui/pending';
import BareLayout from '@/layouts/bare-layout';

/**
 * Saisie du code d'accès partagé, hors du cadre commun. Un code faux revient
 * en erreur `passcode` (« Code incorrect » ou le message du limiteur), lue
 * sous le champ ; le champ est vidé à chaque erreur.
 */
export default function Login() {
    return (
        <>
            <Head title="Connexion" />
            {/* Formulaire centré dans la hauteur restante, version en bas de page. */}
            <div className="flex flex-1 flex-col justify-center gap-6">
                <div className="flex flex-col gap-3 border-t-2 border-foreground pt-4">
                    <p className="font-heading text-xl flex items-center gap-2 leading-none font-extrabold">
                        <BrandMark /> Golf
                    </p>
                    <h1 className="font-heading text-3xl leading-tight font-extrabold tracking-(--tracking-display)">
                        Handicaps golf
                    </h1>
                </div>
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
                                    className="text-sm text-destructive"
                                >
                                    {errors.passcode}
                                </p>
                            ) : null}
                            <Button
                                type="submit"
                                size="lg"
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
