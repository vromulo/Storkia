<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ReturnShipment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait StoresReturnPhotos
{
    protected function storeReturnPhoto(UploadedFile $file, string $folder, string $prefix, int $returnShipmentId): string
    {
        $path = "{$folder}/{$prefix}_{$returnShipmentId}_" . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(1600, 1600)->toWebp(82);
            Storage::disk('public')->put($path, (string) $encoded);

            return $path;
        } catch (\Throwable $e) {
            return $file->store($folder, 'public');
        }
    }

    protected function discardReturnPhoto(string $path): void
    {
        Storage::disk('public')->delete($path);
    }

    protected function discardReturnPhotoIfUnused(int $returnShipmentId, string $column, string $path): void
    {
        $stored = ReturnShipment::whereKey($returnShipmentId)->value($column);

        if ($stored !== $path) {
            Storage::disk('public')->delete($path);
        }
    }
}