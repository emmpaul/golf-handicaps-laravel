import { router } from '@inertiajs/react';

/** Composant Inertia de la page de connexion : la seule page sans session. */
const LOGIN_COMPONENT = 'login';

/**
 * Enregistre le service worker de `public/sw.js` (offline-pwa, design D1,
 * D2). Seulement avec les assets compilés : sous le serveur Vite, les assets
 * viennent d'une autre origine et le service worker ne saurait pas les
 * garder. Le fichier est un script classique, à la racine : portée `/`, sans
 * option. À la première page affichée en ligne avec une session, l'écran
 * Importer est gardé même sans y être allé : le service worker demande
 * `/import` sous ses deux formes (le JSON Inertia exige la version des
 * assets), et l'application charge le morceau JavaScript de la page, qui
 * passe ainsi dans le cache `assets`.
 */
export function registerServiceWorker(): void {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.register('/sw.js').catch(() => undefined);

    const stop = router.on('navigate', (event) => {
        const { page } = event.detail;

        if (page.component === LOGIN_COMPONENT || !navigator.onLine) {
            return;
        }

        stop();
        keepImportScreen(page.version).catch(() => undefined);
    });
}

async function keepImportScreen(version: string | null): Promise<void> {
    const registration = await navigator.serviceWorker.ready;

    (navigator.serviceWorker.controller ?? registration.active)?.postMessage({
        type: 'cache-import',
        version,
    });
    await import('@/pages/import/index');
}
