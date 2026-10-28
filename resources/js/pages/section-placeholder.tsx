import { Head } from '@inertiajs/react';
import { PageTitle } from '@/components/page-title';

/**
 * Page d'attente d'une section sans page : son adresse et ses sous-adresses la
 * servent jusqu'à ce que le changement de la section la remplace.
 */
export default function SectionPlaceholder({ section }: { section: string }) {
    return (
        <>
            <Head title={section} />
            <PageTitle>{section}</PageTitle>
            <p className="text-muted-foreground">Section à venir</p>
        </>
    );
}
