<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The photo of a card, 20 MB at most, with the upload key drawn on the device (offline-pwa,
 * design D8); whether it is an image is decided by its decoding
 * (UnreadablePhoto), not by its declared type.
 */
class StoreImportRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'max:20480'],
            'upload_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Choisis une photo',
            'photo.file' => 'Photo illisible',
            'photo.uploaded' => 'La photo dépasse 20 Mo',
            'photo.max' => 'La photo dépasse 20 Mo',
            'upload_key.required' => 'Clé d’envoi manquante',
            'upload_key.uuid' => 'Clé d’envoi invalide',
        ];
    }

    public function photo(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('photo');
    }

    public function uploadKey(): string
    {
        return $this->string('upload_key')->value();
    }
}
