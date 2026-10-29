import { Head } from '@inertiajs/react';
import { show, update } from '@/actions/App/Http/Controllers/RoundController';
import { PageTopRow } from '@/components/page-top-row';
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
 * vide d'un round enregistré est marquée dès l'ouverture. Lien de retour
 * « Round », sans barre d'onglets (`layout`).
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
            <PageTopRow back={{ href: show.url(id), label: 'Round' }} />
            <h1 className="text-title">Modifier le round</h1>
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

/** Barre d'enregistrement à la place des onglets (spec app-shell, « Navigation masquée pendant l'édition »). */
RoundsEdit.layout = { chrome: 'editor' };
