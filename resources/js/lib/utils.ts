import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Lien d'action isolé (« Saisie manuelle », « Modifier »…) : garde son style de lien, avec
 * une zone de toucher de 44 px de haut sous `md`.
 */
export const ACTION_LINK =
    'inline-flex items-center underline underline-offset-3 max-md:min-h-11';
