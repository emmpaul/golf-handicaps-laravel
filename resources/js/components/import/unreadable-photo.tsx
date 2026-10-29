import { Link } from '@inertiajs/react';
import { create } from '@/actions/App/Http/Controllers/RoundController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/** Lien vers la saisie entièrement manuelle, quand la photo ne peut pas être lue. */
export function ManualEntryLink() {
    return <Link href={create()}>Saisie manuelle</Link>;
}

/** Fichier que le navigateur ne sait pas décoder (spec « Envoi d'une photo »). */
export function UnreadablePhoto() {
    return (
        <Alert variant="destructive">
            <AlertTitle>
                Format de photo non lisible, exporte-la en JPEG
            </AlertTitle>
            <AlertDescription>
                <p>
                    Ou saisis le round à la main : <ManualEntryLink />
                </p>
            </AlertDescription>
        </Alert>
    );
}
