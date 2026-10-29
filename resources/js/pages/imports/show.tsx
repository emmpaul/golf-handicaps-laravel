import { Head } from '@inertiajs/react';
import { PageTitle } from '@/components/page-title';

/**
 * Écran de correction d'un import : page minimale posée avec le backend
 * (tâche 6.1) pour que le contrôleur la serve ; l'éditeur vient en 7.4.
 */
export default function ImportShow() {
    return (
        <>
            <Head title="Vérifier la carte" />
            <PageTitle>Vérifier la carte</PageTitle>
        </>
    );
}
