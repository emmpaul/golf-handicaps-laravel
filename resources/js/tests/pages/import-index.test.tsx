import { router } from '@inertiajs/react';
import type { VisitOptions } from '@inertiajs/core';
import type { MockInstance } from 'vite-plus/test';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import ImportIndex from '@/pages/import/index';
import type { ImportListItem } from '@/components/import/import-list';
import { ROUND } from '@/tests/reference-round';

// Spec scorecard-import « Envoi d'une photo », « Prise de photo depuis
// l'app », « Une action principale à l'import », « Lecture en tâche de fond »,
// « Liste des imports », « Relance refusée », « Suppression d'un import »;
// scorecard-ai-completion « Lecture désactivée sans clé d'API »;
// loading-feedback « Attentes de l'envoi d'une photo », « Imports en attente
// de lecture », « Attentes de la suppression d'un import », « Attente de la
// relance d'une lecture ». The photo decoding is simulated (jsdom decodes no
// image) and resolved or rejected by each test. The router's verbs are
// stubbed (jsdom cannot run a visit); the visit callbacks they receive are
// played by hand. The poll of Inertia runs on fake timers; its reload goes
// through the router's `doReload`, stubbed to record the request options.
// Head renders nothing outside createInertiaApp; the orb paints on a canvas
// jsdom cannot draw.
// Mutations:
// - the poll never stopped (no `stop()` once nothing waits) → the poll test
//   records reloads after the import is ready and goes red;
// - the orb shown for `ready` too → the orb test counts it and goes red;
// - « Relancer la lecture » shown for every failed import, without looking at
//   the actions (no key) → the « without key » test goes red;
// - « Prendre une photo » rendered without a coarse pointer → the computer
//   test goes red;
// - the buttons left enabled while preparing → the pending test goes red;
// - « Relancer la lecture » left enabled while its reading is retried → the
//   retry test goes red;
// - « Annuler » wired to the delete visit → the cancel test goes red.

vi.mock('thinking-orbs', () => ({
    ThinkingOrb: () => <canvas data-testid="orb" />,
}));

const decoding = vi.hoisted(() => ({
    resolve: (_blob: Blob) => {},
    reject: (_error: Error) => {},
}));

vi.mock('@/components/import/decode-photo', () => ({
    decodePhoto: () =>
        new Promise<Blob>((resolve, reject) => {
            decoding.resolve = resolve;
            decoding.reject = reject;
        }),
}));

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

const READY = '0190a6b2-7c3e-7000-8000-0000000000c1';
const FAILED = '0190a6b2-7c3e-7000-8000-0000000000c2';
const QUEUED = '0190a6b2-7c3e-7000-8000-0000000000c3';
const READING = '0190a6b2-7c3e-7000-8000-0000000000c4';
const SAVED = '0190a6b2-7c3e-7000-8000-0000000000c5';

const ready: ImportListItem = {
    id: READY,
    status: 'ready',
    label: 'Prêt à vérifier',
    failure: null,
    roundId: null,
    createdAt: '2026-10-17 21:05',
    actions: ['review', 'delete'],
};
const failed: ImportListItem = {
    id: FAILED,
    status: 'failed',
    label: 'Échec',
    failure: 'Budget IA du mois atteint',
    roundId: null,
    createdAt: '2026-10-17 21:04',
    actions: ['correctEmpty', 'retry', 'delete'],
};
const queued: ImportListItem = {
    id: QUEUED,
    status: 'queued',
    label: 'En attente',
    failure: null,
    roundId: null,
    createdAt: '2026-10-17 21:03',
    actions: ['delete'],
};
const reading: ImportListItem = {
    id: READING,
    status: 'reading',
    label: 'Lecture en cours',
    failure: null,
    roundId: null,
    createdAt: '2026-10-17 21:02',
    actions: [],
};
const saved: ImportListItem = {
    id: SAVED,
    status: 'ready',
    label: 'Prêt à vérifier',
    failure: null,
    roundId: ROUND,
    createdAt: '2026-10-17 21:01',
    actions: ['viewRound'],
};

let coarse = false;
let post: MockInstance<typeof router.post>;
let remove: MockInstance<typeof router.delete>;
let reload: MockInstance<(options: VisitOptions) => void>;

beforeEach(() => {
    coarse = false;
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: coarse && query === '(pointer: coarse)',
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    remove = vi.spyOn(router, 'delete').mockImplementation(() => {});
    reload = vi
        .spyOn(
            router as unknown as {
                doReload: (options: VisitOptions) => void;
            },
            'doReload',
        )
        .mockImplementation(() => {});
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const photoInput = () => screen.getByLabelText('Photo de la carte');
const status = () => screen.getAllByRole('status')[0].textContent;

/** The line of the import shown with `heading`. */
const line = (heading: string) =>
    screen
        .getByRole('heading', { level: 3, name: heading })
        .closest('li') as HTMLElement;

/** The names of the actions of a line, links and buttons, in their order. */
const actions = (item: HTMLElement) =>
    [...item.querySelectorAll('a, button')].map((action) => action.textContent);

describe('Sending a photo', () => {
    it('on a touch screen, puts « Prendre une photo » first as the filled button, then « Choisir une photo »', () => {
        coarse = true;

        render(<ImportIndex imports={[]} readingEnabled />);

        const take = screen.getByLabelText('Prendre une photo');
        const takeButton = take.closest('label') as HTMLElement;
        const chooseButton = photoInput().closest('label') as HTMLElement;

        expect(take.getAttribute('capture')).toBe('environment');
        expect(takeButton.className).toContain('bg-primary');
        expect(chooseButton.textContent).toBe('Choisir une photo');
        expect(chooseButton.className).toContain('border-foreground');
        expect(takeButton.nextElementSibling).toBe(chooseButton);
        expect(screen.queryByText('Photo de la carte')).toBeNull();
    });

    it('on a computer, shows no « Prendre une photo » and keeps « Photo de la carte »', () => {
        render(<ImportIndex imports={[]} readingEnabled />);

        expect(screen.queryByLabelText('Prendre une photo')).toBeNull();
        expect(photoInput().getAttribute('type')).toBe('file');
    });

    it('shows « Préparation de la photo… » then « Envoi de la photo… », the buttons disabled', async () => {
        coarse = true;
        render(<ImportIndex imports={[]} readingEnabled />);

        await userEvent.upload(
            photoInput(),
            new File(['photo'], 'IMG_3064.HEIC', { type: 'image/heic' }),
        );

        expect(status()).toBe('Préparation de la photo…');
        expect(photoInput()).toBeDisabled();
        expect(screen.getByLabelText('Prendre une photo')).toBeDisabled();
        expect(post).not.toHaveBeenCalled();

        await act(async () => {
            decoding.resolve(new Blob(['jpeg'], { type: 'image/jpeg' }));
        });

        expect(post).toHaveBeenCalledTimes(1);
        const [action, data, options] = post.mock.calls[0];
        expect(action).toEqual({ url: '/imports', method: 'post' });
        const photo = (data as { photo: File }).photo;
        expect([photo.name, photo.type]).toEqual(['carte.jpg', 'image/jpeg']);
        expect(options?.forceFormData).toBe(true);

        act(() => options?.onStart?.({} as never));
        expect(status()).toBe('Envoi de la photo…');
        expect(photoInput()).toBeDisabled();

        act(() => options?.onFinish?.({} as never));
        expect(status()).toBe('');
        expect(photoInput()).not.toBeDisabled();
    });

    it('shows « Format de photo non lisible, exporte-la en JPEG » and « Saisie manuelle » for a file it cannot decode', async () => {
        render(<ImportIndex imports={[]} readingEnabled />);

        await userEvent.upload(
            photoInput(),
            new File(['not an image'], 'notes.pdf', { type: 'image/png' }),
        );
        await act(async () => {
            decoding.reject(
                new Error('The source image could not be decoded.'),
            );
        });

        expect(
            screen.getByText('Format de photo non lisible, exporte-la en JPEG'),
        ).toBeInTheDocument();
        expect(
            screen
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        expect(post).not.toHaveBeenCalled();
        expect(photoInput()).not.toBeDisabled();
    });

    it('without key, shows the reading disabled and « Saisie manuelle », without any sending action', () => {
        coarse = true;

        render(<ImportIndex imports={[failed]} readingEnabled={false} />);

        expect(
            screen.getByText(
                'Lecture des photos désactivée : aucune clé d’IA n’est configurée.',
            ),
        ).toBeInTheDocument();
        expect(
            screen
                .getByRole('link', { name: 'Saisie manuelle' })
                .getAttribute('href'),
        ).toBe('/rounds/new');
        expect(screen.queryByLabelText('Prendre une photo')).toBeNull();
        expect(screen.queryByLabelText('Photo de la carte')).toBeNull();
        expect(screen.queryByText('Choisir une photo')).toBeNull();
    });
});

describe('List of imports', () => {
    it('shows each import with its date, its state and the actions of its state', () => {
        render(
            <ImportIndex
                imports={[ready, failed, queued, reading, saved]}
                readingEnabled
            />,
        );

        const list = screen.getByRole('list', { name: 'Imports' });
        expect(
            within(list)
                .getAllByRole('heading', { level: 3 })
                .map((heading) => heading.textContent),
        ).toEqual([
            '17 octobre 2026 à 21:05',
            '17 octobre 2026 à 21:04',
            '17 octobre 2026 à 21:03',
            '17 octobre 2026 à 21:02',
            '17 octobre 2026 à 21:01',
        ]);
        expect(
            within(list)
                .getAllByTestId('import-status')
                .map((badge) => badge.textContent),
        ).toEqual([
            'Prêt à vérifier',
            'Échec',
            'En attente',
            'Lecture en cours',
            'Prêt à vérifier',
        ]);

        expect(actions(line('17 octobre 2026 à 21:05'))).toEqual([
            'Vérifier',
            'Supprimer',
        ]);
        expect(actions(line('17 octobre 2026 à 21:04'))).toEqual([
            "Ouvrir l'écran de correction vide",
            'Relancer la lecture',
            'Supprimer',
        ]);
        expect(actions(line('17 octobre 2026 à 21:03'))).toEqual(['Supprimer']);
        expect(actions(line('17 octobre 2026 à 21:02'))).toEqual([]);
        expect(actions(line('17 octobre 2026 à 21:01'))).toEqual([
            'Voir le round',
        ]);

        expect(
            within(line('17 octobre 2026 à 21:05'))
                .getByRole('link', { name: 'Vérifier' })
                .getAttribute('href'),
        ).toBe(`/imports/${READY}`);
        expect(
            within(line('17 octobre 2026 à 21:04'))
                .getByRole('link', {
                    name: "Ouvrir l'écran de correction vide",
                })
                .getAttribute('href'),
        ).toBe(`/imports/${FAILED}`);
        expect(
            within(line('17 octobre 2026 à 21:01'))
                .getByRole('link', { name: 'Voir le round' })
                .getAttribute('href'),
        ).toBe(`/rounds/${ROUND}`);
        expect(
            within(line('17 octobre 2026 à 21:04')).getByText(
                'Budget IA du mois atteint',
            ),
        ).toBeInTheDocument();
    });

    it('shows the animated orb next to the queued and reading imports only', () => {
        render(
            <ImportIndex
                imports={[ready, failed, queued, reading]}
                readingEnabled
            />,
        );

        const withOrb = within(screen.getByRole('list', { name: 'Imports' }))
            .getAllByRole('listitem')
            .map((item) => within(item).queryAllByTestId('orb').length);

        expect(withOrb).toEqual([0, 0, 1, 1]);
    });

    it('does not offer « Relancer la lecture » without key', () => {
        render(
            <ImportIndex
                imports={[{ ...failed, actions: ['correctEmpty', 'delete'] }]}
                readingEnabled={false}
            />,
        );

        expect(actions(line('17 octobre 2026 à 21:04'))).toEqual([
            "Ouvrir l'écran de correction vide",
            'Supprimer',
        ]);
    });

    it('reads a failed import again: POST to its reading, « Relance de la lecture… » and its actions disabled on its line only', async () => {
        const other = { ...failed, id: QUEUED, createdAt: '2026-10-17 21:00' };

        render(<ImportIndex imports={[failed, other]} readingEnabled />);

        const retried = line('17 octobre 2026 à 21:04');
        await userEvent.click(
            within(retried).getByRole('button', {
                name: 'Relancer la lecture',
            }),
        );

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toEqual({
            url: `/imports/${FAILED}/reading`,
            method: 'post',
        });

        act(() => post.mock.calls[0][2]?.onStart?.({} as never));

        expect(within(retried).getByRole('status').textContent).toBe(
            'Relance de la lecture…',
        );
        expect(
            within(retried).getByRole('button', {
                name: 'Relancer la lecture',
            }),
        ).toBeDisabled();
        expect(
            within(retried).getByRole('button', { name: 'Supprimer' }),
        ).toBeDisabled();
        expect(
            within(retried)
                .getByRole('link', {
                    name: "Ouvrir l'écran de correction vide",
                })
                .getAttribute('aria-disabled'),
        ).toBe('true');

        const untouched = line('17 octobre 2026 à 21:00');
        expect(within(untouched).getByRole('status').textContent).toBe('');
        expect(
            within(untouched).getByRole('button', {
                name: 'Relancer la lecture',
            }),
        ).not.toBeDisabled();
        expect(
            within(untouched)
                .getByRole('link', {
                    name: "Ouvrir l'écran de correction vide",
                })
                .getAttribute('aria-disabled'),
        ).toBeNull();

        act(() => post.mock.calls[0][2]?.onFinish?.({} as never));
        expect(within(retried).getByRole('status').textContent).toBe('');
    });

    it('reloads only the imports every 2 s while one waits, and stops once none waits', async () => {
        vi.useFakeTimers();

        const { rerender } = render(
            <ImportIndex imports={[queued, ready]} readingEnabled />,
        );

        await act(async () => {
            await vi.advanceTimersByTimeAsync(1999);
        });
        expect(reload).not.toHaveBeenCalled();

        await act(async () => {
            await vi.advanceTimersByTimeAsync(1);
        });
        expect(reload).toHaveBeenCalledTimes(1);
        expect(reload.mock.calls[0][0].only).toEqual(['imports']);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(2000);
        });
        expect(reload).toHaveBeenCalledTimes(2);

        rerender(
            <ImportIndex
                imports={[
                    { ...queued, status: 'ready', label: 'Prêt à vérifier' },
                    ready,
                ]}
                readingEnabled
            />,
        );
        await act(async () => {
            await vi.advanceTimersByTimeAsync(10_000);
        });
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('does not reload when no import waits', async () => {
        vi.useFakeTimers();

        render(<ImportIndex imports={[ready, failed]} readingEnabled />);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(10_000);
        });
        expect(reload).not.toHaveBeenCalled();
    });

    it('deletes an import after a confirmation, « Suppression de l’import… » while deleting', async () => {
        remove.mockImplementation((_url, options) => {
            (options as VisitOptions).onStart?.({} as never);
        });

        render(<ImportIndex imports={[ready]} readingEnabled />);

        await userEvent.click(
            within(line('17 octobre 2026 à 21:05')).getByRole('button', {
                name: 'Supprimer',
            }),
        );

        const dialog = screen.getByRole('alertdialog');
        const confirm = within(dialog).getByRole('button', {
            name: 'Supprimer',
        });
        expect(within(dialog).getByRole('heading').textContent).toBe(
            'Supprimer cet import ?',
        );
        expect(remove).not.toHaveBeenCalled();

        await act(async () => {
            await userEvent.click(confirm);
        });

        expect(remove).toHaveBeenCalledTimes(1);
        expect(remove.mock.calls[0][0]).toBe(`/imports/${READY}`);
        expect(within(dialog).getByRole('status').textContent).toBe(
            'Suppression de l’import…',
        );
        expect(
            within(dialog).getByRole('button', { name: 'Supprimer' }),
        ).toBeDisabled();
        expect(
            within(dialog).getByRole('button', { name: 'Annuler' }),
        ).toBeDisabled();
    });

    it('sends nothing when the deletion is cancelled', async () => {
        render(<ImportIndex imports={[ready]} readingEnabled />);

        await userEvent.click(
            within(line('17 octobre 2026 à 21:05')).getByRole('button', {
                name: 'Supprimer',
            }),
        );
        await userEvent.click(
            within(screen.getByRole('alertdialog')).getByRole('button', {
                name: 'Annuler',
            }),
        );

        expect(remove).not.toHaveBeenCalled();
        expect(line('17 octobre 2026 à 21:05')).toBeInTheDocument();
    });
});
