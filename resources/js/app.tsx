import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Every page sits in the shell unless it declares its own layout.
    layout: () => AppLayout,
    // The progress bar cannot read CSS variables: --color-ink of the light
    // palette, the single hard-coded colour outside tokens.css (design D6).
    progress: {
        color: '#0F1D3E',
    },
});
