import { act, fireEvent, render, screen, within } from '@testing-library/react';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { AppNav } from '@/components/app-nav';
import { DifferentialBars } from '@/components/handicaps/differential-bars';
import { ScoringStats } from '@/components/handicaps/scoring-stats';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import HandicapsIndex from '@/pages/handicaps/index';
import { GAB, MANU, SEAN } from '@/tests/reference-round';

// Spec visual-theme « Animations du design » (« Changement d'onglet »,
// « Changement de joueur sur la page Handicaps », « Barres à l'ouverture »,
// « Case à cocher », « Même onglet touché ») and « Mouvement des contrôles »
// (« Entrée d'une page », mouvement réduit); design D8. The two frames of the
// prototype are played by hand: requestAnimationFrame is queued. jsdom has no
// matchMedia: it is stubbed for reduced motion only.
// Mutations: `transition-all` on a tab → the tab test goes red (and
// theme-sources names the file); the bars without their initial state (height
// computed at once) → the bars test goes red; reduced motion ignored (the
// entered state waits for the frames anyway) → both reduced motion tests go
// red; the entrance keyed on the whole address (query included) → the change
// of player test goes red; the index swapped at once → the swap test goes red;
// the current tab touched without preventDefault (Inertia visits it) → the
// same tab test goes red.

const page = vi.hoisted(() => ({ url: '/rounds' }));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const inertia = await importOriginal<typeof import('@inertiajs/react')>();

    return {
        ...inertia,
        Head: () => null,
        usePage: () => ({
            url: page.url,
            props: { app: { version: '0.6.0', commit: null } },
        }),
        router: { on: () => () => {} },
    };
});

let frames: FrameRequestCallback[] = [];
let frameIds = 0;

/** Plays the frames queued so far, as the browser paints one image. */
function nextFrame() {
    const queued = frames;
    frames = [];
    act(() => {
        queued.forEach((callback) => callback(0));
    });
}

function reduceMotion() {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: query === '(prefers-reduced-motion: reduce)',
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
}

beforeEach(() => {
    page.url = '/rounds';
    frames = [];
    frameIds = 0;
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
        frames.push(callback);
        frameIds += 1;

        return frameIds;
    });
    vi.stubGlobal('cancelAnimationFrame', vi.fn());
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

const history = [
    {
        roundId: '0190a6b2-7c3e-7000-8000-0000000000a3',
        playedOn: '2026-10-17',
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        differentialT: 110,
        indexAfterT: 20,
        countsInCurrent: true,
    },
    {
        roundId: '0190a6b2-7c3e-7000-8000-0000000000a2',
        playedOn: '2026-10-10',
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        differentialT: 55,
        indexAfterT: 20,
        countsInCurrent: true,
    },
    {
        roundId: '0190a6b2-7c3e-7000-8000-0000000000a1',
        playedOn: '2026-10-01',
        courseName: 'BETHPAGE BLACK',
        tees: 'Red',
        differentialT: 20,
        indexAfterT: 20,
        countsInCurrent: true,
    },
];

const stats = {
    rounds: 1,
    holes: 18,
    averageGrossT: 720,
    bestGross: 72,
    parOrBetterPercent: 78,
    classes: [0, 3, 11, 4, 0],
    classPercents: [0, 17, 61, 22, 0],
    pars: [],
};

/** Gab with an index, Manu with another, Sean without any. */
const swapPlayers = [
    {
        id: GAB,
        name: 'Gab',
        indexT: 100,
        provisional: false,
        deltaT: null,
        rounds: 3,
        colorSlot: 0,
    },
    {
        id: MANU,
        name: 'Manu',
        indexT: 158,
        provisional: false,
        deltaT: null,
        rounds: 3,
        colorSlot: 1,
    },
    {
        id: SEAN,
        name: 'Sean',
        indexT: null,
        provisional: false,
        deltaT: null,
        rounds: 0,
        colorSlot: 2,
    },
];

function barHeights(): string[] {
    return Array.from(
        document.querySelectorAll<HTMLElement>('[data-part="bar"]'),
    ).map((bar) => bar.style.height);
}

function segmentWidths(): string[] {
    return Array.from(
        document.querySelectorAll<HTMLElement>('[data-part="segment"]'),
    ).map((segment) => segment.style.width);
}

describe('page entrance', () => {
    it('starts 10 px lower at opacity 0 and sits in place after two frames', () => {
        render(<AppLayout>Contenu</AppLayout>);
        const main = screen.getByRole('main');

        expect(main.style.opacity).toBe('0');
        expect(main.style.transform).toBe('translateY(10px)');
        expect(main.style.transition).toBe(
            'opacity 320ms ease, transform 420ms cubic-bezier(0.2, 0.8, 0.2, 1)',
        );

        nextFrame();
        expect(main.style.opacity).toBe('0');

        nextFrame();
        expect(main.style.opacity).toBe('1');
        expect(main.style.transform).toBe('translateY(0)');
    });

    it('plays again on another section, not on a change of player', () => {
        page.url = '/handicaps';
        const { rerender } = render(<AppLayout>Contenu</AppLayout>);
        nextFrame();
        nextFrame();

        page.url = `/handicaps?joueur=${MANU}`;
        rerender(<AppLayout>Contenu</AppLayout>);
        expect(screen.getByRole('main').style.opacity).toBe('1');

        page.url = '/strokes';
        rerender(<AppLayout>Contenu</AppLayout>);
        expect(screen.getByRole('main').style.opacity).toBe('0');
    });

    it('is in place from the first image under reduced motion', () => {
        reduceMotion();
        render(<AppLayout>Contenu</AppLayout>);
        const main = screen.getByRole('main');

        expect(main.style.opacity).toBe('1');
        expect(main.style.transform).toBe('translateY(0)');
        expect(main.style.transition).toBe('');
    });
});

describe('tab bar', () => {
    it('opens the label of the current tab to 90 px only, on named properties', () => {
        page.url = '/handicaps';
        render(<AppNav />);

        const labels = Array.from(
            document.querySelectorAll<HTMLElement>('[data-slot="tab-label"]'),
        );
        const classes = labels.map((label) => label.className);

        expect(
            labels.map((label) => label.classList.contains('max-w-[90px]')),
        ).toEqual([false, true, false, false]);
        expect(
            labels.map((label) => label.classList.contains('max-w-0')),
        ).toEqual([true, false, true, true]);
        expect(labels[1]).toHaveClass(
            '[transition:max-width_var(--dur-nav)_var(--ease-design),opacity_var(--dur-fade)_ease,margin-left_var(--dur-nav)_var(--ease-design)]',
        );
        expect(
            [
                ...classes,
                ...labels.map((label) => label.parentElement!.className),
            ].some((name) => /\btransition-all\b|transition:all/.test(name)),
        ).toBe(false);
    });

    it('scrolls the page back to the top when the current tab is touched again, without a visit', async () => {
        const { router } =
            await vi.importActual<typeof import('@inertiajs/react')>(
                '@inertiajs/react',
            );
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const scrollTo = vi.fn();
        vi.stubGlobal('scrollTo', scrollTo);
        render(<AppNav />);

        const rounds = within(
            screen.getByRole('navigation', { name: 'Sections' }),
        ).getByRole('link', { name: 'Rounds' });
        const event = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });
        rounds.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
        expect(visit).not.toHaveBeenCalled();
        visit.mockRestore();
    });
});

describe('Handicaps page', () => {
    it('starts the bars at 4 px and grows them after two frames', () => {
        render(<DifferentialBars history={history} colorSlot={0} />);

        expect(barHeights()).toEqual(['4px', '4px', '4px']);

        nextFrame();
        nextFrame();
        expect(barHeights()).toEqual(['20px', '55px', '110px']);
    });

    it('starts the segments of the distribution at a null width, over 700 ms', () => {
        render(<ScoringStats stats={stats} />);

        expect(segmentWidths()).toEqual(['0%', '0%', '0%', '0%']);

        nextFrame();
        nextFrame();
        expect(segmentWidths()).toEqual(['16.7%', '61.1%', '22.2%', '0%']);
        document
            .querySelectorAll<HTMLElement>('[data-part="segment"]')
            .forEach((segment) => {
                expect(segment).toHaveClass(
                    'transition-[width]',
                    'duration-700',
                );
            });
    });

    it('gives the bars and the segments their final size at once under reduced motion', () => {
        reduceMotion();
        render(
            <>
                <DifferentialBars history={history} colorSlot={0} />
                <ScoringStats stats={stats} />
            </>,
        );

        expect(barHeights()).toEqual(['20px', '55px', '110px']);
        expect(segmentWidths()).toEqual(['16.7%', '61.1%', '22.2%', '0%']);
    });

    it('fades the index of the former player out 10 px up, then brings the new one 140 ms later', () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        page.url = '/handicaps';
        const players = [
            {
                id: GAB,
                name: 'Gab',
                indexT: 100,
                provisional: false,
                deltaT: null,
                rounds: 3,
                colorSlot: 0,
            },
            {
                id: MANU,
                name: 'Manu',
                indexT: 158,
                provisional: false,
                deltaT: null,
                rounds: 3,
                colorSlot: 1,
            },
        ];
        const { rerender } = render(
            <HandicapsIndex
                players={players}
                selectedPlayerId={GAB}
                history={[]}
            />,
        );
        const index = () => screen.getByTestId('player-index');

        expect(index().textContent).toBe('10,0');
        expect(index().style.opacity).toBe('1');

        rerender(
            <HandicapsIndex
                players={players}
                selectedPlayerId={MANU}
                history={[]}
            />,
        );

        expect(index().textContent).toBe('10,0');
        expect(index().style.opacity).toBe('0');
        expect(index().style.transform).toBe('translateY(-10px)');
        expect(index().style.transition).toBe(
            'opacity 250ms ease, transform 350ms cubic-bezier(0.2, 0.8, 0.2, 1)',
        );

        act(() => {
            vi.advanceTimersByTime(140);
        });

        expect(index().textContent).toBe('15,8');
        expect(index().style.opacity).toBe('1');
        expect(index().style.transform).toBe('translateY(0)');
    });
});

describe('swap towards a player without index', () => {
    // L13, DO38: switching to a player without index never writes the former
    // index beside « Pas encore d'index » (even transparent, it stays in the
    // text of the page). Mutation: the branch without index rendering the
    // swapped value (`index.shown`) → « 10,0 » is found, this test goes red.
    it('writes « — » at once, in its entrance, and never the former index', () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        page.url = '/handicaps';
        const { rerender } = render(
            <HandicapsIndex
                players={swapPlayers}
                selectedPlayerId={GAB}
                history={[]}
            />,
        );

        rerender(
            <HandicapsIndex
                players={swapPlayers}
                selectedPlayerId={SEAN}
                history={[]}
            />,
        );

        const index = screen.getByTestId('player-index');
        expect(index.textContent).toBe('—');
        expect(index.style.opacity).toBe('0');
        expect(screen.queryByText('10,0')).toBeNull();

        act(() => {
            vi.advanceTimersByTime(140);
        });

        expect(index.textContent).toBe('—');
        expect(index.style.opacity).toBe('1');
    });
});

describe('cancellation on unmount', () => {
    // M8: an animation left behind by a component gone would set the state
    // of an unmounted tree. Mutation: the cleanup of useEntered without
    // `cancelAnimationFrame(second)` → the frame test goes red; the cleanup
    // of useSwap without `clearTimeout` → the swap test goes red.
    it('cancels the second frame of the entrance when unmounted between the two frames', () => {
        const { unmount } = render(
            <DifferentialBars history={history} colorSlot={0} />,
        );

        nextFrame();
        unmount();

        expect(cancelAnimationFrame).toHaveBeenCalledWith(2);
    });

    it('clears the swap of the index when unmounted before 140 ms', () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        page.url = '/handicaps';
        const { rerender, unmount } = render(
            <HandicapsIndex
                players={swapPlayers}
                selectedPlayerId={GAB}
                history={[]}
            />,
        );
        const setTimer = vi.spyOn(globalThis, 'setTimeout');
        const clearTimer = vi.spyOn(globalThis, 'clearTimeout');

        rerender(
            <HandicapsIndex
                players={swapPlayers}
                selectedPlayerId={MANU}
                history={[]}
            />,
        );
        const swap = setTimer.mock.results.at(-1)?.value;
        unmount();

        expect(setTimer.mock.calls.at(-1)?.[1]).toBe(140);
        expect(clearTimer).toHaveBeenCalledWith(swap);
        expect(vi.getTimerCount()).toBe(0);
    });
});

describe('check box', () => {
    it('scales the check from 0.4 unchecked to 1 checked on the pop curve', () => {
        render(<Checkbox aria-label="Sean" />);

        const check = document.querySelector(
            '[data-slot="checkbox-wrapper"] svg',
        ) as SVGElement;

        expect(check).toHaveClass(
            'scale-40',
            'peer-checked:scale-100',
            '[transition:opacity_200ms_ease,scale_var(--dur-swap)_var(--ease-pop)]',
        );

        fireEvent.click(screen.getByRole('checkbox', { name: 'Sean' }));
        expect(
            (screen.getByRole('checkbox', { name: 'Sean' }) as HTMLInputElement)
                .checked,
        ).toBe(true);
    });
});
