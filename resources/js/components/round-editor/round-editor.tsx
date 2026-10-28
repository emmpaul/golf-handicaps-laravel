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
import { Pending } from '@/components/ui/pending';
import {
    checkRoundCard,
    problemCountLabel,
    withTotals,
} from '@/lib/round-checks';
import type { CellValue, HoleRow } from '@/lib/round-checks';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';
import { DateField } from './date-field';
import {
    courseLabel,
    createdPlayer,
    editorReducer,
    lineLabel,
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
import { ProblemList } from './problem-list';
import { ScoreGrid } from './score-grid';
import type { GridRow } from './score-grid';

/** Envoi de la carte : `RoundController.store` (saisie) ou `RoundController.update` (correction). */
export type SaveAction = RouteDefinition<'post'> | RouteDefinition<'put'>;

const totals = (row: HoleRow) => ({
    out: row.out,
    in: row.in,
    total: row.total,
});

function gridRows(
    state: EditorState,
    players: readonly PlayerOption[],
): GridRow[] {
    const par = withTotals(state.holes.map((hole) => hole.par));
    const distance = withTotals(state.holes.map((hole) => hole.distance));

    return [
        { key: 'par', label: 'Par', holes: par.holes, totals: totals(par) },
        {
            key: 'strokeIndex',
            label: 'Index',
            holes: state.holes.map((hole) => hole.strokeIndex),
        },
        {
            key: 'distance',
            label: 'Distance',
            holes: distance.holes,
            totals: totals(distance),
        },
        ...state.lines.map((line, i) => {
            const strokes = withTotals(line.strokes);

            return {
                key: `player${i}`,
                label: lineLabel(line, i, players),
                cellLabel: `Ligne ${i + 1}`,
                holes: strokes.holes,
                totals: totals(strokes),
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
 * serveur les rejoue. `flagUntouchedEmpty` : round enregistré, les cases vides
 * sont marquées dès l'ouverture ; saisie manuelle, seulement une fois quittées.
 */
export function RoundEditor({
    initial,
    players,
    courses,
    action,
    flagUntouchedEmpty = false,
}: {
    initial: EditorState;
    players: PlayerOption[];
    courses: KnownCourse[];
    action: SaveAction;
    flagUntouchedEmpty?: boolean;
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
        <div className="flex min-w-0 flex-col gap-4">
            <div className="flex min-w-0 flex-col gap-4">
                <FieldGroup className="gap-4">
                    <div
                        className={cn(
                            'grid grid-cols-1 gap-4 sm:grid-cols-3',
                            courses.length > 0 &&
                                'lg:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,1fr))]',
                        )}
                    >
                        {/* Sur ordinateur, le parcours connu se place sur la même ligne que les champs. */}
                        {courses.length > 0 ? (
                            <Field className="sm:col-span-3 lg:col-span-1">
                                <FieldLabel htmlFor="known-course">
                                    Parcours connu
                                </FieldLabel>
                                <NativeSelect
                                    id="known-course"
                                    defaultValue=""
                                    onChange={(event) => {
                                        const course =
                                            courses[
                                                Number(
                                                    event.currentTarget.value,
                                                )
                                            ];

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
                        {TEXT_FIELDS.map(({ field, label }) => (
                            <Field key={field}>
                                <FieldLabel htmlFor={field}>{label}</FieldLabel>
                                <Input
                                    id={field}
                                    value={state[field] ?? ''}
                                    aria-invalid={
                                        invalid.has(field) || undefined
                                    }
                                    onChange={(event) =>
                                        setField(
                                            field,
                                            event.currentTarget.value,
                                        )
                                    }
                                    onBlur={() => leave(field)}
                                />
                            </Field>
                        ))}
                        <Field>
                            <FieldLabel htmlFor="playedOn">Date</FieldLabel>
                            <DateField
                                id="playedOn"
                                value={state.playedOn}
                                invalid={invalid.has('playedOn')}
                                onChange={(iso) => setField('playedOn', iso)}
                                onBlur={() => leave('playedOn')}
                            />
                        </Field>
                    </div>
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
            </div>
            <ProblemList problems={problems} />
            <ScoreGrid
                rows={gridRows(state, players)}
                invalid={invalid}
                onCell={(row, hole, value: CellValue) =>
                    change({ type: 'cell', row, hole, value })
                }
                onLeave={leave}
            />
            {duplicateOf !== null ? (
                <Alert>
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
            {/* Collée juste au-dessus des onglets sous `md`, au-dessus de la barre d'accueil au-delà ;
                déborde de `main` jusqu'au bord de l'écran sous `lg` seulement ; à partir de `lg`, le
                bouton s'aligne sur le bord du conteneur, comme les champs et la grille. */}
            <div className="sticky bottom-(--bottom-chrome) z-20 flex flex-col gap-2 border-t bg-background px-4 py-2 max-lg:-mx-4 sm:flex-row sm:items-center md:pt-3 md:pb-[calc(--spacing(3)+var(--safe-bottom))] lg:px-0">
                <Button
                    size="lg"
                    className="w-full sm:w-auto"
                    disabled={problems.length > 0 || saving}
                    onClick={() => submit(false)}
                >
                    {problems.length > 0
                        ? problemCountLabel(problems.length)
                        : 'Enregistrer'}
                </Button>
                <Pending
                    label={saving ? 'Enregistrement du round…' : null}
                    orb="save"
                />
            </div>
        </div>
    );
}
