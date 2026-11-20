import { Head } from '@inertiajs/react';
import { index as roundsIndex } from '@/actions/App/Http/Controllers/RoundController';
import { PageTopRow } from '@/components/page-top-row';

export type KnownCourse = { courseName: string; tees: string };

/**
 * Page « Partie virtuelle » (spec `virtual-match`, design D4) : le serveur
 * envoie les parcours connus et la partie du parcours choisi. Cette première
 * version ne montre que la rangée du haut et le titre ; la page complète suit.
 */
export default function RoundsCompare({ courses }: { courses: KnownCourse[] }) {
    return (
        <>
            <Head title="Partie virtuelle" />
            <PageTopRow back={{ href: roundsIndex.url(), label: 'Rounds' }} />
            <h1 className="text-title">Partie virtuelle</h1>
            {courses.length === 0 && (
                <p className="my-10 text-body text-ink-2">
                    Aucun parcours : enregistrez d'abord un round.
                </p>
            )}
        </>
    );
}
