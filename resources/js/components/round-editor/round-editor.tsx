import { Link, router } from '@inertiajs/react';
import type { VisitHelperOptions } from '@inertiajs/core';
import { useMemo, useReducer, useState } from 'react';
import { store as storePlayer } from '@/actions/App/Http/Controllers/PlayerController';
import { show } from '@/actions/App/Http/Controllers/RoundController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { PLAYER_TEXT } from '@/components/player-swatch';
import { ORB_FOR, PendingOrb } from '@/components/ui/pending';
import { checkRoundCard, withTotals } from '@/lib/round-checks';
import type { CellValue, HoleRow, ReadTotals } from '@/lib/round-checks';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';
import { SAVING, useCorrectionAutosave } from './autosave';
import { DateField } from './date-field';
import { DistancesBlock } from './distances-block';
import { EditorSaveBar } from './editor-save-bar';
import {
    courseLabel,
    createdPlayer,
    editorReducer,
    MAX_LINES,
    shownInvalidCells,
    toRoundCard,
} from './form-state';
import type {
    EditorAction,
    EditorState,
    KnownCourse,
    PlayerOption,
    TextField,
} from './form-state';
import { LinePlayers } from './line-players';
import { ScoreGrid } from './score-grid';
import type { GridRow } from './score-grid';

/**
 * Envoi de la carte : `RoundController.store` (saisie), `RoundController.update`
 * (correction) ou `ImportRoundController` (import).
 */
export type SaveAction = RouteDefinition<'post'> | RouteDefinition<'put'>;

/**
 * L'écran de correction d'un import (design D7) : sa photo, la raison de son
 * échec, le repère de lecture par l'IA (`readByAi`) et la sauvegarde
 * automatique de la correction. La saisie manuelle et la correction d'un
 * round n'en passent pas.
 */
export type ImportContext = {
    importId: string;
    photoUrl: string;
    readByAi: boolean;
    failure: string | null;
};

const totals = (row: HoleRow) => ({
    out: row.out,
    in: row.in,
    total: row.total,
});

/** Totaux d'une ligne : ceux lus sur la carte, modifiables, sinon calculés. */
const rowTotals = (row: HoleRow, read: ReadTotals | null | undefined) =>
    read ? { totals: { ...read }, readTotals: true } : { totals: totals(row) };

/**
 * Lignes de la grille : par, index, puis une par ligne de joueur, nommée en
 * tête par son joueur dans sa couleur, sinon par le nom lu ou « L3 » en ambre.
 */
function gridRows(
    state: EditorState,
    players: readonly PlayerOption[],
): GridRow[] {
    const par = withTotals(state.holes.map((hole) => hole.par));

    return [
        {
            key: 'par',
            label: 'Par',
            holes: par.holes,
            ...rowTotals(par, state.parTotals),
        },
        {
            key: 'strokeIndex',
            label: 'Index',
            holes: state.holes.map((hole) => hole.strokeIndex),
        },
        ...state.lines.map((line, i) => {
            const strokes = withTotals(line.strokes);
            const player = players.find(({ id }) => id === line.playerId);

            return {
                key: `player${i}`,
                label: player?.name ?? line.readName ?? `L${i + 1}`,
                headClass: player
                    ? PLAYER_TEXT[player.colorSlot]
                    : 'text-accent',
                cellLabel: `Ligne ${i + 1}`,
                holes: strokes.holes,
                ...rowTotals(strokes, line.totals),
            };
        }),
    ];
}

const TEXT_FIELDS = [
    { field: 'courseName', label: 'Parcours' },
    { field: 'tees', label: 'Tees' },
] as const;

/**
 * Éditeur de round, partagé par la saisie manuelle et la correction d'un round
 * enregistré (design D9). L'état reste local ; les contrôles de cohérence se
 * recalculent à chaque frappe et le bouton compte les problèmes restants ; le
 * serveur les rejoue. `flagUntouchedEmpty` : round enregistré ou carte lue
 * d'un import, les cases vides sont marquées dès l'ouverture ; saisie
 * manuelle, seulement une fois quittées. `importContext` : écran de
 * correction d'un import.
 */
export function RoundEditor({
    initial,
    players,
    courses,
    action,
    flagUntouchedEmpty = false,
    importContext,
}: {
    initial: EditorState;
    players: PlayerOption[];
    courses: KnownCourse[];
    action: SaveAction;
    flagUntouchedEmpty?: boolean;
    importContext?: ImportContext;
}) {
    const [state, dispatch] = useReducer(editorReducer, initial);
    const [duplicateOf, setDuplicateOf] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const card = useMemo(() => toRoundCard(state), [state]);
    const problems = useMemo(() => checkRoundCard(card), [card]);
    // Cases quittées au moins une fois : une case vide n'est marquée qu'après (saisie manuelle).
    const [touched, setTouched] = useState<ReadonlySet<string>>(
        () => new Set(),
    );
    const invalid = useMemo(
        () => shownInvalidCells(problems, { touched, flagUntouchedEmpty }),
        [problems, touched, flagUntouchedEmpty],
    );
    // Sauvegarde automatique : correction d'un import seulement.
    const autosave = useCorrectionAutosave(
        importContext?.importId ?? null,
        state,
    );

    function leave(cell: string) {
        setTouched((current) =>
            current.has(cell) ? current : new Set(current).add(cell),
        );
    }

    function change(next: EditorAction) {
        dispatch(next);
        setDuplicateOf(null);
    }

    function setField(field: TextField, value: string) {
        change({ type: 'field', field, value: value || null });
    }

    /**
     * Crée le joueur, puis associe à la ligne celui des props rechargées dont
     * le nom égale, sans casse, le nom saisi (design D8). Le refus du serveur
     * (« Ce joueur existe déjà ») revient sous la ligne ; l'état de l'éditeur
     * est gardé.
     */
    function createPlayer(key: string, name: string): Promise<string | null> {
        return new Promise((resolve) => {
            let error: string | null = null;

            router.post(
                storePlayer(),
                { name },
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['players'],
                    errorBag: 'linePlayer',
                    onSuccess: (page) => {
                        const player = createdPlayer(
                            page.props.players as PlayerOption[],
                            name,
                        );

                        if (player) {
                            change({
                                type: 'associate',
                                key,
                                playerId: player.id,
                            });
                        }
                    },
                    onError: (errors) => {
                        error = errors.name ?? null;
                    },
                    onFinish: () => resolve(error),
                },
            );
        });
    }

    /**
     * Envoie la carte entière. Le serveur revient sur l'éditeur (doublon,
     * carte refusée) ou ouvre la page suivante ; l'attente reste affichée
     * jusqu'à cette page (spec loading-feedback).
     */
    function submit(force: boolean) {
        const from = window.location.pathname;
        let left = false;
        // Rien ne part pendant l'enregistrement du round ; la sauvegarde reprend s'il échoue.
        autosave.pause();
        const options: VisitHelperOptions = {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFlash: (flash) => {
                if (flash.duplicate) {
                    setDuplicateOf(flash.duplicate.roundId);
                }
            },
            onSuccess: (page) => {
                left =
                    new URL(page.url, window.location.href).pathname !== from;
            },
            onFinish: () => {
                if (!left) {
                    setSaving(false);
                    autosave.resume();
                }
            },
        };
        const data = { ...card, force };

        if (action.method === 'put') {
            router.put(action, data, options);
        } else {
            router.post(action, data, options);
        }
    }

    return (
        <div className="flex min-w-0 flex-col">
            {importContext ? (
                <>
                    <a
                        href={importContext.photoUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-[18px] block"
                    >
                        <img
                            src={importContext.photoUrl}
                            alt="Photo de la carte importée"
                            className="aspect-[16/10] w-full rounded-12 bg-surface-2 object-cover"
                        />
                    </a>
                    <div className="mt-3 flex min-h-5 items-center justify-between gap-3">
                        {importContext.readByAi ? (
                            <p className="text-body-13 text-info">
                                Carte lue par l’IA, vérifie chaque case
                            </p>
                        ) : (
                            <span />
                        )}
                        <p
                            role="status"
                            data-testid="autosave-status"
                            className={cn(
                                'inline-flex flex-none items-center gap-2 font-mono text-section tracking-normal',
                                autosave.status.error
                                    ? 'text-signal'
                                    : 'text-ink-2',
                            )}
                        >
                            {autosave.status.message === SAVING ? (
                                <PendingOrb state={ORB_FOR.save} />
                            ) : null}
                            {autosave.status.message}
                        </p>
                    </div>
                    {importContext.failure ? (
                        <Alert variant="destructive" className="mt-3">
                            <AlertTitle>{importContext.failure}</AlertTitle>
                        </Alert>
                    ) : null}
                </>
            ) : null}
            <FieldGroup className="mt-4 gap-3">
                {/* Parcours connu : pré-remplit parcours, tees, pars, index et distances. */}
                {courses.length > 0 ? (
                    <Field className="gap-1.5">
                        <FieldLabel htmlFor="known-course">
                            Parcours connu
                        </FieldLabel>
                        <NativeSelect
                            id="known-course"
                            className="w-full"
                            defaultValue=""
                            onChange={(event) => {
                                const course =
                                    courses[Number(event.currentTarget.value)];

                                if (
                                    event.currentTarget.value !== '' &&
                                    course
                                ) {
                                    change({ type: 'course', course });
                                }
                            }}
                        >
                            <NativeSelectOption value="">
                                Choisir un parcours
                            </NativeSelectOption>
                            {courses.map((course, i) => (
                                <NativeSelectOption
                                    key={courseLabel(course)}
                                    value={i}
                                >
                                    {courseLabel(course)}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                    </Field>
                ) : null}
                <div className="grid grid-cols-[1fr_110px] gap-2">
                    {TEXT_FIELDS.map(({ field, label }) => (
                        <Field key={field} className="min-w-0 gap-1.5">
                            <FieldLabel htmlFor={field}>{label}</FieldLabel>
                            <Input
                                id={field}
                                value={state[field] ?? ''}
                                aria-invalid={invalid.has(field) || undefined}
                                onChange={(event) =>
                                    setField(field, event.currentTarget.value)
                                }
                                onBlur={() => leave(field)}
                            />
                        </Field>
                    ))}
                </div>
                <Field className="gap-1.5">
                    <FieldLabel htmlFor="playedOn">Date</FieldLabel>
                    <DateField
                        id="playedOn"
                        value={state.playedOn}
                        invalid={invalid.has('playedOn')}
                        onChange={(iso) => setField('playedOn', iso)}
                        onBlur={() => leave('playedOn')}
                    />
                </Field>
            </FieldGroup>
            <LinePlayers
                lines={state.lines}
                players={players}
                invalid={invalid}
                canAdd={state.lines.length < MAX_LINES}
                onAssociate={(key, playerId) =>
                    change({ type: 'associate', key, playerId })
                }
                onLeave={leave}
                onCreate={createPlayer}
                onRemove={(key) => change({ type: 'removeLine', key })}
                onAdd={() => change({ type: 'addLine' })}
            />
            <ScoreGrid
                rows={gridRows(state, players)}
                invalid={invalid}
                onCell={(row, hole, value: CellValue) =>
                    change({ type: 'cell', row, hole, value })
                }
                onTotal={(row, column, value) =>
                    change({ type: 'total', row, column, value })
                }
                onLeave={leave}
            />
            <DistancesBlock
                distances={state.holes.map((hole) => hole.distance)}
                invalid={invalid}
                onCell={(row, hole, value) =>
                    change({ type: 'cell', row, hole, value })
                }
                onLeave={leave}
            />
            {duplicateOf !== null ? (
                <Alert className="mt-6">
                    <AlertTitle>Ce round semble déjà enregistré</AlertTitle>
                    <AlertDescription>
                        <p>
                            <Link href={show(duplicateOf)}>
                                Voir le round existant
                            </Link>
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                disabled={saving}
                                onClick={() => submit(true)}
                            >
                                Enregistrer quand même
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setDuplicateOf(null)}
                            >
                                Annuler
                            </Button>
                        </div>
                    </AlertDescription>
                </Alert>
            ) : null}
            <EditorSaveBar
                problems={problems}
                saving={saving}
                onSave={() => submit(false)}
            />
        </div>
    );
}
