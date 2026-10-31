import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { installOfflineHandling } from '@/lib/offline';
import { startPhotoSync } from '@/lib/photo-sync';
import { flushPrefetchAfterWrites } from '@/lib/prefetch';
import { registerServiceWorker } from '@/lib/service-worker';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Toute écriture réussie oublie les pages préchargées (design D5).
flushPrefetchAfterWrites();

// Mode hors ligne (offline-pwa) : branchés avant la première page, pour ne
// manquer ni sa navigation initiale ni le message d'une copie servie à froid.
// Le service worker ne s'enregistre qu'avec les assets compilés (D1, D2) ; la
// page réagit aux pages sans copie et aux écritures hors ligne (D4) et tient
// l'état du bandeau (D5) ; les photos gardées partent à l'ouverture, au retour
// du réseau, au premier plan et après la saisie du code (D7).
registerServiceWorker();
installOfflineHandling();
startPhotoSync();

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
