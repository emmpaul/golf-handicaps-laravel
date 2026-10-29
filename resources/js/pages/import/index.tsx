import { Head } from '@inertiajs/react';
import { PageTitle } from '@/components/page-title';

/**
 * Écran d'import : page minimale posée avec le backend (tâche 6.1) pour que
 * le contrôleur la serve ; l'envoi de photo et la liste viennent en 7.2.
 */
export default function ImportIndex() {
    return (
        <>
            <Head title="Importer" />
            <PageTitle>Importer</PageTitle>
        </>
    );
}
