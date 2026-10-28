import { Head } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/RoundController';
import { PageTitle } from '@/components/page-title';
import { cardEditorState } from '@/components/round-editor/form-state';
import type {
    KnownCourse,
    PlayerOption,
} from '@/components/round-editor/form-state';
import { RoundEditor } from '@/components/round-editor/round-editor';
import type { RoundCard } from '@/lib/round-checks';

/**
 * Correction d'un round enregistré (spec `rounds`) : le même éditeur et les
 * mêmes contrôles que la saisie manuelle, pré-rempli avec le round. Une case
 * vide d'un round enregistré est marquée dès l'ouverture.
 */
export default function RoundsEdit({
    round,
    players,
    courses,
}: {
    round: RoundCard & { id: string };
    players: PlayerOption[];
    courses: KnownCourse[];
}) {
    const { id, ...card } = round;

    return (
        <>
            <Head title="Modifier le round" />
            <PageTitle>Modifier le round</PageTitle>
            <RoundEditor
                initial={cardEditorState(card)}
                players={players}
                courses={courses}
                action={update(id)}
                flagUntouchedEmpty
            />
        </>
    );
}
