import { Head, Link } from '@inertiajs/react';
import { create } from '@/actions/App/Http/Controllers/RoundController';
import { ImportList } from '@/components/import/import-list';
import type { ImportListItem } from '@/components/import/import-list';
import { ImportUpload } from '@/components/import/import-upload';
import { PendingPhotos } from '@/components/import/pending-photos';
import { ManualEntryLink } from '@/components/import/unreadable-photo';
import { PageTopRow } from '@/components/page-top-row';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { TITLE_LINK } from '@/lib/utils';

/**
 * Écran d'import (spec `scorecard-import`, « Mise en page de l'écran
 * Importer ») : titre et « Saisie manuelle », envoi d'une photo quand une clé
 * d'IA est configurée (`readingEnabled`), sinon la lecture désactivée ; les
 * photos gardées sur l'appareil, « EN ATTENTE D'ENVOI » (offline-pwa) ; puis
 * « IMPORTS », leur nombre et les derniers imports avec leur état et leurs
 * actions.
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
            <PageTopRow />
            <div className="flex items-end justify-between gap-3">
                <h1 className="text-title">Importer</h1>
                <Link href={create()} className={TITLE_LINK}>
                    Saisie manuelle
                </Link>
            </div>
            <p className="mt-1.5 text-body-13 text-ink-2">
                Photo de l'écran de fin de round, prise au téléphone.
            </p>
            {readingEnabled ? (
                <ImportUpload />
            ) : (
                <Alert className="mt-6">
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
            <PendingPhotos />
            <ImportList imports={imports} />
        </>
    );
}
