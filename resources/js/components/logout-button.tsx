import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

/**
 * Bas de l'accueil : ferme la session de cet appareil et renvoie sur /login.
 * Lien texte (variante `link`), taille par défaut : 44 px de haut sous 768 px,
 * comme tout contrôle de `main`.
 */
export function LogoutButton() {
    return (
        <Form action={logout()}>
            <Button type="submit" variant="link" className="w-fit px-0">
                Se déconnecter
            </Button>
        </Form>
    );
}
