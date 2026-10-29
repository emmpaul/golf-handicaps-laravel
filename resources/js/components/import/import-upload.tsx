import { router } from '@inertiajs/react';
import { CameraIcon, ImageIcon } from 'lucide-react';
import { useState } from 'react';
import type { ChangeEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/ImportController';
import { Pending } from '@/components/ui/pending';
import { useMediaQuery } from '@/hooks/use-media-query';
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
 * navigateur, puis `POST /imports`. Le serveur met la lecture en file et
 * revient avec « Lecture en file d'attente… » ; la photo suivante peut partir
 * aussitôt. Sur écran tactile, « Prendre une photo » (appareil photo arrière)
 * et « Choisir une photo » sont deux tuiles côte à côte, séparées d'un filet ;
 * sans écran tactile, « Choisir une photo » seule prend toute la largeur.
 */
export function ImportUpload() {
    const touch = useMediaQuery('(pointer: coarse)');
    const [step, setStep] = useState<Step>('pick');
    // Refus de validation du serveur (« La photo dépasse 20 Mo »).
    const [refusal, setRefusal] = useState<string | null>(null);
    const busy = step === 'preparing' || step === 'sending';

    async function choose(file: File | undefined) {
        if (!file) {
            return;
        }

        // Décodage et réduction à 3000 px : une à deux secondes sur téléphone.
        setRefusal(null);
        setStep('preparing');
        let photo: Blob;

        try {
            photo = await decodePhoto(file);
        } catch {
            setStep('unreadable');

            return;
        }

        router.post(
            store(),
            {
                photo: new File([photo], 'carte.jpg', { type: 'image/jpeg' }),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setStep('sending'),
                onError: (errors) => setRefusal(errors.photo ?? null),
                onFinish: () => setStep('pick'),
            },
        );
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
            {refusal ? (
                <p role="alert" className="text-body-13 text-signal">
                    {refusal}
                </p>
            ) : null}
            {step === 'unreadable' ? <UnreadablePhoto /> : null}
        </div>
    );
}
