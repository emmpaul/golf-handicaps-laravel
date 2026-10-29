import { createElement } from 'react';
import { toast } from 'sonner';
import { ToastPill } from '@/components/ui/toaster';

/** Message éphémère joint par le serveur (`Inertia::flash('toast', …)`). */
export type FlashToast = {
    type: 'error' | 'success';
    message: string;
};

/**
 * Affiche la donnée flash `toast` dans sa pilule, rien si elle manque. La
 * pilule pose le rôle : `alert` pour une erreur, `status` pour une
 * confirmation.
 */
export function showFlashToast(flash: { toast?: FlashToast }): void {
    if (flash.toast === undefined) {
        return;
    }

    const { type, message } = flash.toast;

    toast.custom((id) => createElement(ToastPill, { id, type, message }));
}
