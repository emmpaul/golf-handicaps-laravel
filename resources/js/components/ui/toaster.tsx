import { Check, X } from 'lucide-react';
import type { CSSProperties } from 'react';
import { Toaster as Sonner, toast } from 'sonner';
import { cn } from '@/lib/utils';

/** 52 px sous le haut de la zone sûre, sur téléphone comme sur ordinateur. */
const OFFSET = { top: 'calc(var(--safe-top) + 52px)' };

/**
 * Pilule d'un message éphémère (spec app-shell « Message éphémère »,
 * décision Q7) : une confirmation claire avec la coche, annoncée poliment
 * (`role="status"`) ; une erreur en pilule rouge sans coche, annoncée aussitôt
 * (`role="alert"`). Un toucher sur la pilule la ferme ; le bouton « Fermer le
 * message », masqué tant qu'il n'a pas le focus, le permet au clavier.
 */
export function ToastPill({
    id,
    type,
    message,
}: {
    id: string | number;
    type: 'error' | 'success';
    message: string;
}) {
    const error = type === 'error';
    const close = () => toast.dismiss(id);

    return (
        <div className="flex w-full justify-center">
            {/* The tap target is a convenience; the button is the keyboard way. */}
            <div
                data-slot="toast-pill"
                onClick={close}
                className={cn(
                    'flex max-w-[calc(100vw-2*var(--gutter))] cursor-pointer items-center gap-2 rounded-pill px-4 py-3 text-label-14 text-bg shadow-float',
                    error ? 'bg-signal' : 'bg-ink',
                )}
            >
                {!error && (
                    <Check
                        aria-hidden
                        className="size-4 flex-none"
                        strokeWidth={3}
                    />
                )}
                <span role={error ? 'alert' : 'status'}>{message}</span>
                <button
                    type="button"
                    aria-label="Fermer le message"
                    onClick={(event) => {
                        event.stopPropagation();
                        close();
                    }}
                    className="sr-only focus-visible:not-sr-only"
                >
                    <X aria-hidden className="size-4" />
                </button>
            </div>
        </div>
    );
}

/**
 * Messages éphémères de sonner (spec app-shell « Message éphémère ») : pilule
 * du haut, centrée dans la colonne, 2,2 s. sonner ne dessine rien : chaque
 * message est une `ToastPill`. Monté une fois dans le layout.
 */
export function Toaster() {
    return (
        <Sonner
            position="top-center"
            offset={OFFSET}
            mobileOffset={OFFSET}
            containerAriaLabel="Messages"
            style={{ '--width': 'var(--column)' } as CSSProperties}
            toastOptions={{ duration: 2200, unstyled: true }}
        />
    );
}
