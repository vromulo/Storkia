<?php

namespace App\Http\Controllers;

use App\Models\SellerApplication;
use App\Models\LogisticsApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminDocumentController extends Controller
{
    public function view(Request $request, string $entity, int $id, string $type): BinaryFileResponse
    {
        $application = match ($entity) {
            'seller'    => SellerApplication::findOrFail($id),
            'logistics' => LogisticsApplication::findOrFail($id),
            default     => abort(404),
        };

        $path = $type === 'id' ? $application->id_path : $application->permit_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'File not found');
        }

        $fullPath = Storage::disk('public')->path($path);
        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }
}