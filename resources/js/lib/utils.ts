import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

/**
 * tailwind-merge told about the scale of tokens.css (D2, DO13): without it,
 * `text-label` passes for a colour and `cn('text-bg', 'text-label')` drops
 * the colour.
 */
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: [
                'display-xl',
                'display-l',
                'display',
                'kpi',
                'title',
                'num-l',
                'name-l',
                'name-l-course',
                'name-m',
                'name-m-17',
                'num-m',
                'num-m-18',
                'num-m-17',
                'num-m-16',
                'num-m-15',
                'body-l',
                'label',
                'label-14',
                'label-13',
                'body',
                'body-13',
                'body-12',
                'link',
                'link-13',
                'meta',
                'meta-12',
                'meta-round',
                'section',
                'caption',
                'caption-9',
            ],
            radius: [
                'pill',
                '22',
                '14',
                '12',
                '10',
                '9',
                '8',
                '6',
                '4',
                '3',
                '2',
            ],
            shadow: ['float'],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Lien d'action isolé (« Saisie manuelle », « Modifier »…) : garde son style de lien, avec
 * une zone de toucher de 44 px de haut sous `md`.
 */
export const ACTION_LINK =
    'inline-flex items-center underline underline-offset-3 max-md:min-h-11';

/**
 * Lien de la rangée d'un titre de page (« Parcours », « Saisie manuelle ») : Geist 500, 14 px,
 * bas aligné sur le titre, 44 px de toucher (design, pages Rounds et Importer).
 */
export const TITLE_LINK = 'flex min-h-11 items-end pb-1 text-link';
