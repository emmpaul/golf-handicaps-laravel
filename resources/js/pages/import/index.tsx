import { Head } from '@inertiajs/react';
import { ImportList } from '@/components/import/import-list';
import type { ImportListItem } from '@/components/import/import-list';
import { ImportUpload } from '@/components/import/import-upload';
import { ManualEntryLink } from '@/components/import/unreadable-photo';
import { PageTitle } from '@/components/page-title';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * Écran d'import (spec `scorecard-import`) : envoi d'une photo quand une clé
 * d'IA est configurée (`readingEnabled`), sinon la lecture désactivée et la
 * saisie manuelle ; puis les derniers imports avec leur état et leurs actions.
 */
export default function ImportIndex({
    imports,
    readingEnabled,
}: {
    imports: ImportListItem[];
    readingEnabled: boolean;
}) {
    return (
        <>
            <Head title="Importer" />
            <PageTitle>Importer</PageTitle>
            {readingEnabled ? (
                <ImportUpload />
            ) : (
                <Alert>
                    <AlertTitle>
                        Lecture des photos désactivée : aucune clé d’IA n’est
                        configurée.
                    </AlertTitle>
                    <AlertDescription>
                        <p>
                            Saisis le round à la main : <ManualEntryLink />
                        </p>
                    </AlertDescription>
                </Alert>
            )}
            <ImportList imports={imports} />
        </>
    );
}
