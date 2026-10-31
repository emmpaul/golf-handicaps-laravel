import { useEffect, useState, useSyncExternalStore } from 'react';
import { SectionLabel } from '@/components/section-label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Pending } from '@/components/ui/pending';
import { formatClockTime } from '@/lib/format';
import { list, remove, sendingKey, subscribe } from '@/lib/photo-queue';
import { clearRefusals, subscribeSync, syncSnapshot } from '@/lib/photo-sync';
import { ManualEntryLink } from './unreadable-photo';

/** Photo gardée, prête à l'affichage : sa miniature est une adresse `blob:`. */
type ShownPhoto = { key: string; takenAt: string; src: string };

/** « Envoi d'1 photo… », « Envoi de 2 photos… » */
const sendingLabel = (count: number) =>
    count === 1 ? 'Envoi d’1 photo…' : `Envoi de ${count} photos…`;

/**
 * Photos gardées sur l'appareil, lues dans la file et relues à chaque
 * changement (dans cet onglet ou un autre). Les adresses des miniatures sont
 * libérées dès qu'une nouvelle lecture les remplace, et au démontage.
 */
function usePendingPhotos(): ShownPhoto[] {
    const [photos, setPhotos] = useState<ShownPhoto[]>([]);

    useEffect(() => {
        let active = true;
        let latest = 0;
        let shown: string[] = [];

        const load = () => {
            const reading = (latest += 1);

            void list().then((items) => {
                if (!active || reading !== latest) {
                    return;
                }

                const next = items.map((photo) => ({
                    key: photo.key,
                    takenAt: photo.takenAt,
                    src: URL.createObjectURL(photo.blob),
                }));

                shown.forEach((src) => URL.revokeObjectURL(src));
                shown = next.map((photo) => photo.src);
                setPhotos(next);
            });
        };

        load();
        const unsubscribe = subscribe(load);

        return () => {
            active = false;
            unsubscribe();
            shown.forEach((src) => URL.revokeObjectURL(src));
        };
    }, []);

    return photos;
}

/**
 * Section « EN ATTENTE D'ENVOI » de l'écran Importer (spec scorecard-import
 * « Liste des photos en attente d'envoi », loading-feedback « Envoi des photos
 * en attente signalé »), au-dessus de « IMPORTS » : le nombre de photos
 * gardées, puis chacune, de la plus ancienne à la plus récente, avec sa
 * miniature, l'heure de la prise (« 21:04 ») et « Retirer ». Absente sans photo. La photo
 * que les tuiles d'envoi sont en train d'envoyer n'y figure pas : l'attente
 * « Envoi de la photo… » la montre déjà. Pendant l'envoi des photos en
 * attente, « Envoi de N photos… » et « Retirer » désactivé sur la photo en
 * cours. Sous la section, les refus du serveur, chacun avec « Saisie
 * manuelle » (spec « Photo refusée à l'envoi »), effacés quand l'écran se
 * ferme.
 */
export function PendingPhotos() {
    const photos = usePendingPhotos();
    const sending = useSyncExternalStore(subscribe, sendingKey);
    const { uploading, refusals } = useSyncExternalStore(
        subscribeSync,
        syncSnapshot,
    );
    const shown = photos.filter((photo) => photo.key !== uploading);
    const messages = [...new Set(refusals.map((refusal) => refusal.message))];
    const sendingShown = shown.some((photo) => photo.key === sending);

    useEffect(() => clearRefusals, []);

    return (
        <>
            {shown.length > 0 ? (
                <section
                    aria-labelledby="pending-photos-title"
                    className="mt-[30px] flex flex-col"
                >
                    <SectionLabel
                        id="pending-photos-title"
                        value={shown.length}
                    >
                        En attente d'envoi
                    </SectionLabel>
                    <ul aria-labelledby="pending-photos-title">
                        {shown.map((photo) => (
                            <li
                                key={photo.key}
                                className="flex items-center gap-3 border-b border-rule-soft py-3"
                            >
                                <div className="h-[42px] w-14 flex-none overflow-hidden rounded-6 bg-surface-2">
                                    <img
                                        src={photo.src}
                                        alt=""
                                        className="size-full object-cover object-[50%_55%]"
                                    />
                                </div>
                                <p className="min-w-0 text-label">
                                    <time dateTime={photo.takenAt}>
                                        {formatClockTime(photo.takenAt)}
                                    </time>
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="ml-auto font-medium"
                                    disabled={photo.key === sending}
                                    onClick={() => void remove(photo.key)}
                                >
                                    Retirer
                                </Button>
                            </li>
                        ))}
                    </ul>
                    <span className={sendingShown ? 'mt-3' : undefined}>
                        <Pending
                            label={
                                sendingShown ? sendingLabel(shown.length) : null
                            }
                            orb="send"
                        />
                    </span>
                </section>
            ) : null}
            {messages.map((message) => (
                <Alert key={message} variant="destructive" className="mt-6">
                    <AlertTitle>{message}</AlertTitle>
                    <AlertDescription>
                        <p>
                            Saisis le round à la main : <ManualEntryLink />
                        </p>
                    </AlertDescription>
                </Alert>
            ))}
        </>
    );
}
