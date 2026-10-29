import { router } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ImportController';
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
 * Suppression d'un import pas encore enregistré en round, après confirmation
 * (spec « Suppression d'un import »). Le serveur revient sur la liste, sans
 * l'import, ou avec le refus (« Lecture en cours ») en message.
 */
export function DeleteImport({
    importId,
    disabled = false,
}: {
    importId: string;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    function remove() {
        router.delete(destroy.url(importId), {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onSuccess: () => setOpen(false),
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger
                disabled={disabled}
                render={
                    <Button
                        variant="ghost"
                        size="sm"
                        className="max-md:min-h-11"
                    />
                }
            >
                Supprimer
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Supprimer cet import ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        La photo et la correction en cours seront effacées.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <Pending
                        label={deleting ? 'Suppression de l’import…' : null}
                        orb="send"
                    />
                    <AlertDialogCancel disabled={deleting}>
                        Annuler
                    </AlertDialogCancel>
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
