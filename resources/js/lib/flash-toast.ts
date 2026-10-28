import { createElement } from 'react';
import { toast } from 'sonner';

/** Message éphémère joint par le serveur (`Inertia::flash('toast', …)`). */
export type FlashToast = {
    type: 'error' | 'success';
    message: string;
};

/**
 * Affiche la donnée flash `toast`, rien si elle manque. Une erreur est
 * annoncée aussitôt (`role="alert"`), une confirmation poliment
 * (`role="status"`) : sonner ne pose aucun rôle sur ses messages.
 */
export function showFlashToast(flash: { toast?: FlashToast }): void {
    if (flash.toast === undefined) {
        return;
    }

    const { type, message } = flash.toast;

    if (type === 'error') {
        toast.error(createElement('span', { role: 'alert' }, message));
    } else {
        toast.success(createElement('span', { role: 'status' }, message));
    }
}
