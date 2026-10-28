import { act, fireEvent, render, screen } from '@testing-library/react';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';

import { ScrollFrame } from '@/components/scroll-frame';

// Spec app-shell « Contenu défilant signalé »: a 300 px frame shows 900 px of
// content; a fade appears on each edge where content remains. jsdom has no
// layout, so the widths and the ResizeObserver are simulated.
// Mutation: `scrollLeft >= 0` for the start fade → the frame shows a start
// fade at the beginning and the first test goes red.

let resize: () => void = () => {};
const disconnect = vi.fn();
let width = 300;

beforeEach(() => {
    width = 300;
    disconnect.mockClear();
    vi.stubGlobal(
        'ResizeObserver',
        class {
            constructor(callback: () => void) {
                resize = callback;
            }

            observe() {}

            disconnect = disconnect;
        },
    );
    vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockImplementation(
        () => width,
    );
    vi.spyOn(HTMLElement.prototype, 'scrollWidth', 'get').mockReturnValue(900);
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

function renderFrame() {
    const view = render(
        <ScrollFrame label="Scores">
            <table>
                <tbody>
                    <tr>
                        <td>18</td>
                    </tr>
                </tbody>
            </table>
        </ScrollFrame>,
    );
    const viewport = screen.getByRole('region', { name: 'Scores' });
    const frame = viewport.parentElement as HTMLElement;

    return { ...view, frame, viewport };
}

function fades(frame: HTMLElement): [boolean, boolean] {
    return [
        frame.hasAttribute('data-overflow-start'),
        frame.hasAttribute('data-overflow-end'),
    ];
}

function scrollTo(viewport: HTMLElement, left: number) {
    viewport.scrollLeft = left;
    fireEvent.scroll(viewport);
}

describe('ScrollFrame', () => {
    it('fades the right edge only at the beginning', () => {
        const { frame } = renderFrame();

        expect(frame).toHaveAttribute('data-slot', 'scroll-frame');
        expect(fades(frame)).toEqual([false, true]);
    });

    it('fades both edges after scrolling 200 px', () => {
        const { frame, viewport } = renderFrame();

        scrollTo(viewport, 200);

        expect(fades(frame)).toEqual([true, true]);
    });

    it('fades the left edge only at the end', () => {
        const { frame, viewport } = renderFrame();

        scrollTo(viewport, 600);

        expect(fades(frame)).toEqual([true, false]);
    });

    it('drops the left fade when scrolled back to the beginning', () => {
        const { frame, viewport } = renderFrame();

        scrollTo(viewport, 600);
        scrollTo(viewport, 0);

        expect(fades(frame)).toEqual([false, true]);
    });

    it('shows no fade once the frame holds the whole content', () => {
        const { frame } = renderFrame();

        act(() => {
            width = 900;
            resize();
        });

        expect(fades(frame)).toEqual([false, false]);
    });

    it('scrolls from the keyboard', () => {
        const { viewport } = renderFrame();

        expect(viewport).toHaveAttribute('tabindex', '0');
    });

    it('disconnects its observer when unmounted', () => {
        const { unmount } = renderFrame();

        unmount();

        expect(disconnect).toHaveBeenCalledTimes(1);
    });
});
