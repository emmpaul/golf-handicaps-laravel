import { router } from '@inertiajs/react';
import { CameraIcon, ImageIcon } from 'lucide-react';
import { useState } from 'react';
import type { ChangeEvent, ReactNode } from 'react';
import { store } from '@/actions/App/Http/Controllers/ImportController';
import { buttonVariants } from '@/components/ui/button';
import { Pending } from '@/components/ui/pending';
import { useMediaQuery } from '@/hooks/use-media-query';
import { cn } from '@/lib/utils';
import { decodePhoto } from './decode-photo';
import { UnreadablePhoto } from './unreadable-photo';

/**
 * Libellé stylé en bouton autour d'un champ fichier masqué (`sr-only`) : le
 * champ garde le focus clavier, dont le contour s'affiche sur le bouton.
 */
const PICK_BUTTON =
    'cursor-pointer focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-solid focus-within:outline-ring has-disabled:cursor-not-allowed has-disabled:opacity-55';

type Step = 'pick' | 'preparing' | 'sending' | 'unreadable';

/** Attentes affichées dans la région `Pending`, rien aux autres étapes. */
const PENDING_LABEL: Partial<Record<Step, string>> = {
    preparing: 'Préparation de la photo…',
    sending: 'Envoi de la photo…',
};

function PickButton({
    className,
    icon,
    children,
    ...input
}: {
    className: string;
    icon: ReactNode;
    children: string;
    disabled: boolean;
    capture?: 'environment';
    'aria-label'?: string;
    onChange: (event: ChangeEvent<HTMLInputElement>) => void;
}) {
    return (
        <label className={cn(className, PICK_BUTTON)}>
            {icon}
            {children}
            <input
                type="file"
                accept="image/*"
                className="sr-only"
                {...input}
            />
        </label>
    );
}

/**
 * Envoi d'une photo (design D8) : décodage et réencodage JPEG dans le
 * navigateur, puis `POST /imports`. Le serveur met la lecture en file et
 * revient avec « Lecture en file d'attente… » ; la photo suivante peut partir
 * aussitôt. Sur écran tactile, « Prendre une photo » ouvre l'appareil photo
 * arrière ; « Choisir une photo » reste dessous, sans libellé entre les deux.
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

        router.post(
            store(),
            {
                photo: new File([photo], 'carte.jpg', { type: 'image/jpeg' }),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setStep('sending'),
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
            {touch ? (
                <PickButton
                    className={cn(buttonVariants({ size: 'lg' }), 'md:w-fit')}
                    icon={<CameraIcon aria-hidden="true" />}
                    capture="environment"
                    disabled={busy}
                    onChange={pickFrom}
                >
                    Prendre une photo
                </PickButton>
            ) : null}
            <PickButton
                className={cn(
                    buttonVariants({ variant: 'outline' }),
                    'md:w-fit',
                )}
                icon={<ImageIcon aria-hidden="true" />}
                aria-label="Photo de la carte"
                disabled={busy}
                onChange={pickFrom}
            >
                Choisir une photo
            </PickButton>
            <Pending
                label={PENDING_LABEL[step] ?? null}
                orb={step === 'sending' ? 'send' : 'prepare'}
            />
            {step === 'unreadable' ? <UnreadablePhoto /> : null}
        </div>
    );
}
