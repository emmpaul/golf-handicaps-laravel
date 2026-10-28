import { Head } from '@inertiajs/react';
import { PageTitle } from '@/components/page-title';

/** Accueil : le tableau du groupe arrive avec `home-and-match-strokes`. */
export default function Home() {
    return (
        <>
            <Head title="Accueil" />
            <PageTitle>Handicaps golf</PageTitle>
            <p className="text-muted-foreground">
                Le tableau du groupe arrive bientôt.
            </p>
        </>
    );
}
