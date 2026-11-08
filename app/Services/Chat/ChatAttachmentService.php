<?php

namespace App\Services\Chat;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Single place for chat image uploads, so the Buyer, Seller, Logistics,
 * Admin and Help Center chats all validate and store photos identically
 * (same rules as the original Buyer <-> Seller chat).
 */
class ChatAttachmentService
{
    public const MAX_KB = 5120;

    /**
     * Validation rules for an optional chat photo (JPG, PNG or WEBP, max 5 MB).
     */
    public static function rules(): array
    {
        return [
            'nullable',
            'file',
            'mimetypes:image/jpeg,image/png,image/webp',
            'mimes:jpg,jpeg,png,webp',
            'max:' . self::MAX_KB,
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'attachment.mimetypes' => 'Chat image attachment must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'attachment.mimes'     => 'Chat image attachment must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'attachment.max'       => 'Chat image attachment must not exceed 5 MB.',
        ];
    }

    /**
     * Re-encodes the photo as a WEBP (max 1600px) on the public disk and
     * returns its relative path. Falls back to storing the original file
     * when the image driver is unavailable.
     *
     * @param  string  $ownerKey   e.g. the user id, or "admin_3" for support staff
     * @param  string  $directory  folder on the public disk
     */
    public function store(UploadedFile $file, string $ownerKey, string $directory = 'chat_attachments'): string
    {
        $filename = $directory . '/chat_' . $ownerKey . '_' . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(1600, 1600)->toWebp(82);
            Storage::disk('public')->put($filename, (string) $encoded);

            return $filename;
        } catch (\Throwable $e) {
            return (string) $file->store($directory, 'public');
        }
    }

    public function url(?string $path): ?string
    {
        return empty($path) ? null : asset('storage/' . $path);
    }
}