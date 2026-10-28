import { Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/RoundController';
import { PageTitle } from '@/components/page-title';
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
 * fois quittées.
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
            <Head title="Saisie manuelle" />
            <PageTitle>Saisie manuelle</PageTitle>
            <RoundEditor
                initial={emptyEditorState(today)}
                players={players}
                courses={courses}
                action={store()}
            />
        </>
    );
}
