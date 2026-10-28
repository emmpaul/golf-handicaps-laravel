import type { CSSProperties } from 'react';
import { Toaster as Sonner } from 'sonner';

/** Au-dessus de la barre d'onglets et de la barre d'accueil, sur téléphone comme sur ordinateur. */
const OFFSET = { bottom: 'calc(var(--bottom-chrome) + 1rem)' };

/**
 * Messages éphémères de sonner, aux couleurs des tokens : fond de surface,
 * texte d'encre, erreur en couleur destructive, coins droits, bouton de
 * fermeture. Monté une fois dans le layout.
 */
export function Toaster() {
    return (
        <Sonner
            position="bottom-center"
            offset={OFFSET}
            mobileOffset={OFFSET}
            closeButton
            containerAriaLabel="Messages"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--border-radius': '0',
                } as CSSProperties
            }
            toastOptions={{
                closeButtonAriaLabel: 'Fermer le message',
                classNames: {
                    toast: 'font-sans text-sm',
                    error: 'text-destructive border-destructive',
                    closeButton:
                        'bg-popover text-foreground border-border',
                },
            }}
        />
    );
}
