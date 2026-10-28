import { router } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/RoundController';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Pending } from '@/components/ui/pending';

/**
 * Suppression d'un round après confirmation (spec `rounds`) : sa photo part
 * avec lui, ses joueurs restent. Le serveur ouvre la liste des rounds avec
 * « Round supprimé. » ; l'attente reste affichée jusqu'à la page suivante.
 */
export function DeleteRound({ roundId }: { roundId: string }) {
    const [open, setOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    function remove() {
        router.delete(destroy.url(roundId), {
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger
                render={<Button variant="destructive" size="lg" />}
            >
                Supprimer
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Supprimer ce round ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Il sera retiré de l’historique, avec sa photo. Ses
                        joueurs restent.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <Pending
                        label={deleting ? 'Suppression du round…' : null}
                        orb="send"
                    />
                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        disabled={deleting}
                        onClick={remove}
                    >
                        Supprimer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
