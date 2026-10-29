import { Form } from '@inertiajs/react';
import { logout } from '@/routes';

/**
 * « Se déconnecter », à droite de la rangée du haut de l'accueil (design) :
 * ferme la session de cet appareil et renvoie sur /login. Lien texte en
 * Geist 500, 13 px, gris, 44 px de toucher.
 */
export function LogoutButton() {
    return (
        <Form action={logout()}>
            <button
                type="submit"
                className="-mr-2 flex min-h-11 items-center px-2 text-link-13 text-ink-2 focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-solid"
            >
                Se déconnecter
            </button>
        </Form>
    );
}
