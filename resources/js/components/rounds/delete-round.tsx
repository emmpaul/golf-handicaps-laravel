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
import { Pending } from '@/components/ui/pending';
import { formatShortPlayedOn } from '@/lib/format';

/**
 * Suppression d'un round après confirmation (spec `rounds`, « Suppression d'un
 * round ») : « Supprimer » de la rangée du haut ouvre la feuille, qui rappelle
 * le parcours et la date ; sa photo part avec lui, ses joueurs restent. Le
 * serveur ouvre la liste des rounds avec « Round supprimé. » ; l'attente reste
 * affichée jusqu'à la page suivante.
 */
export function DeleteRound({
    roundId,
    courseName,
    playedOn,
}: {
    roundId: string;
    courseName: string;
    playedOn: string;
}) {
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
            {/* Lien d'action de la rangée du haut : Geist 500, 14 px, rouge, 44 px de toucher. */}
            <AlertDialogTrigger className="flex min-h-11 items-center px-2 text-link text-signal">
                Supprimer
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Supprimer ce round ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        {`${courseName} · ${formatShortPlayedOn(playedOn)}. Les index de tous les joueurs seront recalculés.`}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogAction
                        variant="destructive"
                        disabled={deleting}
                        onClick={remove}
                    >
                        Supprimer
                    </AlertDialogAction>
                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                    <Pending
                        label={deleting ? 'Suppression du round…' : null}
                        orb="send"
                    />
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
