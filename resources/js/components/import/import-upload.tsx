import { CameraIcon, ImageIcon } from 'lucide-react';
import { useState } from 'react';
import type { ChangeEvent } from 'react';
import { Pending } from '@/components/ui/pending';
import { useMediaQuery } from '@/hooks/use-media-query';
import { showFlashToast } from '@/lib/flash-toast';
import { KEPT_MESSAGE, uploadPhoto } from '@/lib/photo-sync';
import { decodePhoto } from './decode-photo';
import { ImportTile } from './import-tile';
import { UnreadablePhoto } from './unreadable-photo';

type Step = 'pick' | 'preparing' | 'sending' | 'unreadable';

/** Attentes affichées dans la région `Pending`, rien aux autres étapes. */
const PENDING_LABEL: Partial<Record<Step, string>> = {
    preparing: 'Préparation de la photo…',
    sending: 'Envoi de la photo…',
};

/**
 * Envoi d'une photo (design D8) : décodage et réencodage JPEG dans le
 * navigateur, puis la photo est gardée sur l'appareil et part par la file
 * (offline-pwa, design D7). Avec du réseau, « Envoi de la photo… » jusqu'à la
 * réponse, puis « Lecture en file d'attente… » ; sans réseau, ou si l'envoi
 * échoue en route, « Photo gardée sur le téléphone… » et la photo attend dans
 * « EN ATTENTE D'ENVOI ». Un `401` ouvre `/login` sans ce message (la photo
 * reste gardée) ; une photo que l'appareil n'a pas pu garder donne le toast
 * d'erreur « Photo non gardée sur le téléphone. Réessaie. » et ne part pas.
 * Un refus du serveur s'affiche sous la section des
 * photos en attente, avec « Saisie manuelle ». La photo suivante peut partir
 * aussitôt. Sur écran tactile, « Prendre une photo » (appareil photo arrière)
 * et « Choisir une photo » sont deux tuiles côte à côte, séparées d'un filet ;
 * sans écran tactile, « Choisir une photo » seule prend toute la largeur.
 */
export function ImportUpload() {
    const touch = useMediaQuery('(pointer: coarse)');
    const [step, setStep] = useState<Step>('pick');
    const busy = step === 'preparing' || step === 'sending';

    async function choose(file: File | undefined) {
        if (!file) {
            return;
        }

        // Décodage et réduction à 3000 px : une à deux secondes sur téléphone.
        setStep('preparing');
        let photo: Blob;

        try {
            photo = await decodePhoto(file);
        } catch {
            setStep('unreadable');

            return;
        }

        try {
            const fate = await uploadPhoto(photo, () => setStep('sending'));

            if (fate === 'kept') {
                showFlashToast({
                    toast: { type: 'success', message: KEPT_MESSAGE },
                });
            }
        } finally {
            setStep('pick');
        }
    }

    /** Passe le fichier reçu à `choose`, puis vide le champ pour pouvoir rechoisir le même. */
    function pickFrom(event: ChangeEvent<HTMLInputElement>) {
        void choose(event.currentTarget.files?.[0]);
        event.currentTarget.value = '';
    }

    return (
        <div className="flex flex-col gap-2">
            <p className="mt-6 font-mono text-section text-ink-2 uppercase">
                Nouvelle carte
            </p>
            <div className="flex gap-px overflow-hidden rounded-14 bg-rule">
                {touch ? (
                    <ImportTile
                        icon={
                            <CameraIcon
                                aria-hidden="true"
                                className="size-[22px] text-accent"
                            />
                        }
                        title="Prendre une photo"
                        source="Caméra"
                        aria-label="Prendre une photo"
                        capture="environment"
                        disabled={busy}
                        onChange={pickFrom}
                    />
                ) : null}
                <ImportTile
                    icon={
                        <ImageIcon
                            aria-hidden="true"
                            className="size-[22px] text-ink"
                        />
                    }
                    title="Choisir une photo"
                    source="Photothèque"
                    aria-label="Photo de la carte"
                    disabled={busy}
                    onChange={pickFrom}
                />
            </div>
            <Pending
                label={PENDING_LABEL[step] ?? null}
                orb={step === 'sending' ? 'send' : 'prepare'}
            />
            {step === 'unreadable' ? <UnreadablePhoto /> : null}
        </div>
    );
}
