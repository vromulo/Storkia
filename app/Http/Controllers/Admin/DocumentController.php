<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use App\Models\LogisticsApplication;
use App\Models\SellerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function view(Request $request, string $entity, int $id, string $type): BinaryFileResponse
    {
        $application = match ($entity) {
            'identity'  => IdentityVerification::findOrFail($id),
            'seller'    => SellerApplication::findOrFail($id),
            'logistics' => LogisticsApplication::findOrFail($id),
            default     => abort(404),
        };

        $path = match ($type) {
            'id', 'front' => $application->front_image_path ?? $application->id_path,
            'back'        => $application->back_image_path,
            'permit'      => $application->permit_path,
            default       => abort(404),
        };

        if (! $path) {
            abort(404, 'File not found');
        }

        $cleanPath = ltrim((string) $path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, strlen('storage/'));
        }

        $disk = null;
        if ($entity === 'identity') {
            if (Storage::disk('local')->exists($cleanPath)) {
                $disk = 'local';
            } elseif (Storage::disk('public')->exists($cleanPath)) {
                $disk = 'public';
            }
        } else {
            if (Storage::disk('public')->exists($cleanPath)) {
                $disk = 'public';
            } elseif (Storage::disk('local')->exists($cleanPath)) {
                $disk = 'local';
            }
        }

        if (! $disk) {
            abort(404, 'File not found');
        }

        $fullPath = Storage::disk($disk)->path($cleanPath);
        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($cleanPath) . '"',
        ]);
    }
}