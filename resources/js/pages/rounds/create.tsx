import { Head } from '@inertiajs/react';
import { index, store } from '@/actions/App/Http/Controllers/RoundController';
import { PageTopRow } from '@/components/page-top-row';
import { emptyEditorState } from '@/components/round-editor/form-state';
import type {
    KnownCourse,
    PlayerOption,
} from '@/components/round-editor/form-state';
import { RoundEditor } from '@/components/round-editor/round-editor';

/**
 * Round saisi entièrement à la main (spec `rounds`, « Saisie entièrement
 * manuelle ») : une ligne de joueur, la date du jour à Montréal donnée par le
 * serveur (`today`), le reste vide. Les cases vides ne sont marquées qu'une
 * fois quittées. Lien de retour « Rounds », titre « Nouveau round », sans
 * barre d'onglets (`layout`, spec `rounds` « Mise en page de l'éditeur »).
 */
export default function RoundsCreate({
    today,
    players,
    courses,
}: {
    today: string;
    players: PlayerOption[];
    courses: KnownCourse[];
}) {
    return (
        <>
            <Head title="Nouveau round" />
            <PageTopRow back={{ href: index.url(), label: 'Rounds' }} />
            <h1 className="text-title">Nouveau round</h1>
            <RoundEditor
                initial={emptyEditorState(today)}
                players={players}
                courses={courses}
                action={store()}
            />
        </>
    );
}

/** Barre d'enregistrement à la place des onglets (spec app-shell, « Navigation masquée pendant l'édition »). */
RoundsCreate.layout = { chrome: 'editor' };
