import type { FlashToast } from '@/lib/flash-toast';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            sidebarOpen: boolean;
            app: {
                version: string;
                commit: string | null;
            };
            /** Imports prêts à vérifier sans round, lus avec la page ; absent sans session (D7, DO35). */
            pendingImports?: number;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            /** A saved round that the card sent looks like (RoundController, design D5). */
            duplicate?: { roundId: string };
        };
    }
}
