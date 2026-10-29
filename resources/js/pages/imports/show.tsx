import { Head } from '@inertiajs/react';
import ImportPhotoController from '@/actions/App/Http/Controllers/ImportPhotoController';
import ImportRoundController from '@/actions/App/Http/Controllers/ImportRoundController';
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import type { ImportStatus } from '@/components/import/import-list';
import { PageTopRow } from '@/components/page-top-row';
import { cardEditorState } from '@/components/round-editor/form-state';
import type {
    KnownCourse,
    PlayerOption,
} from '@/components/round-editor/form-state';
import { RoundEditor } from '@/components/round-editor/round-editor';
import type { RoundCard } from '@/lib/round-checks';

export type ImportSummary = {
    id: string;
    status: ImportStatus;
    label: string;
    failure: string | null;
};

/**
 * État d'ouverture de l'écran (`ImportEditorState`) : la correction
 * enregistrée, sinon la lecture, sinon l'écran vide du jour. `fromReading` :
 * la carte vient de la lecture, ses cases vides sont marquées dès
 * l'ouverture. `readByAi` : l'IA a donné un brouillon.
 */
export type ImportEditor = {
    card: RoundCard;
    fromReading: boolean;
    readByAi: boolean;
};

/**
 * Écran de vérification d'un import (spec `scorecard-import`, « Mise en page
 * de l'écran de vérification ») : lien de retour « Importer », titre
 * « Vérifier la carte », puis l'éditeur de round pré-rempli, la photo sous le
 * titre, la sauvegarde automatique de chaque modification et l'enregistrement
 * du round par `ImportRoundController`. Sans barre d'onglets (`layout`).
 */
export default function ImportShow({
    import: summary,
    editor,
    players,
    courses,
}: {
    import: ImportSummary;
    editor: ImportEditor;
    players: PlayerOption[];
    courses: KnownCourse[];
}) {
    return (
        <>
            <Head title="Vérifier la carte" />
            <PageTopRow
                back={{ href: ImportController.index.url(), label: 'Importer' }}
            />
            <h1 className="text-title">Vérifier la carte</h1>
            <RoundEditor
                initial={cardEditorState(editor.card)}
                players={players}
                courses={courses}
                action={ImportRoundController(summary.id)}
                flagUntouchedEmpty={editor.fromReading}
                importContext={{
                    importId: summary.id,
                    photoUrl: ImportPhotoController.url(summary.id),
                    readByAi: editor.readByAi,
                    failure: summary.failure,
                }}
            />
        </>
    );
}

/** Barre d'enregistrement à la place des onglets (spec app-shell, « Navigation masquée pendant l'édition »). */
ImportShow.layout = { chrome: 'editor' };
