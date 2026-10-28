import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** Les fondus restent au bord du cadre ; le contenu et ses libellés collants défilent dedans. */
export function ScrollFrame({
    children,
    label,
    labelWidth = '6rem',
    className,
}: {
    children: ReactNode;
    label: string;
    labelWidth?: string;
    className?: string;
}) {
    const viewport = useRef<HTMLElement>(null);
    const [edges, setEdges] = useState({ start: false, end: false });

    useEffect(() => {
        const element = viewport.current;

        if (!element) {
            return;
        }

        const measure = () => {
            const overflow = element.scrollWidth > element.clientWidth;
            const start = overflow && element.scrollLeft > 0;
            const end =
                overflow &&
                element.scrollLeft + element.clientWidth <
                    element.scrollWidth - 1;
            setEdges((previous) =>
                previous.start === start && previous.end === end
                    ? previous
                    : { start, end },
            );
        };
        const observer = new ResizeObserver(measure);
        observer.observe(element);

        if (element.firstElementChild) {
            observer.observe(element.firstElementChild);
        }

        element.addEventListener('scroll', measure, { passive: true });
        measure();

        return () => {
            observer.disconnect();
            element.removeEventListener('scroll', measure);
        };
    }, []);

    return (
        <div
            data-slot="scroll-frame"
            data-overflow-start={edges.start || undefined}
            data-overflow-end={edges.end || undefined}
            style={{ '--scroll-label-width': labelWidth } as CSSProperties}
            className={cn(
                'scroll-frame relative max-w-full min-w-0 overflow-hidden rounded-lg border',
                className,
            )}
        >
            {/* Focusable so that keyboard users can scroll the table. */}
            <section
                ref={viewport}
                data-slot="scroll-viewport"
                aria-label={label}
                tabIndex={0}
                className="overflow-x-auto focus-visible:outline-2 focus-visible:outline-ring"
            >
                {children}
            </section>
        </div>
    );
}
