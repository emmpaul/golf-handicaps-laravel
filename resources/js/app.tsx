import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { flushPrefetchAfterWrites } from '@/lib/prefetch';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Toute écriture réussie oublie les pages préchargées (design D5).
flushPrefetchAfterWrites();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Every page sits in the shell unless it declares its own layout.
    layout: () => AppLayout,
    // The progress bar cannot read CSS variables: --color-ink of the dark
    // theme, the single hard-coded colour outside tokens.css (DO16).
    progress: {
        color: '#F4F5F7',
    },
});
