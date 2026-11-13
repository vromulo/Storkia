<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IdentityVerificationController extends Controller
{
    protected const ID_TYPES = [
        'drivers_license' => ['label' => "Driver's License", 'requires_back' => false],
        'passport'        => ['label' => 'Passport', 'requires_back' => false],
        'identity_card'   => ['label' => 'National / Government ID', 'requires_back' => false],
    ];

    public function show(Request $request): JsonResponse
    {
        $latest = $this->latestFor((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => $this->payload($latest),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_type'     => ['required', Rule::in(array_keys(self::ID_TYPES))],
            'method'      => ['required', Rule::in(['upload', 'camera'])],
            'front_image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'back_image'  => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ], [
            'id_type.required'     => 'Please choose the type of ID you want to submit.',
            'id_type.in'           => 'Please choose one of the listed ID types.',
            'method.required'      => 'Please choose how the ID was captured.',
            'front_image.required' => 'Please provide the front of your document.',
            'front_image.max'      => 'The front image must not exceed 10MB.',
            'back_image.max'       => 'The back image must not exceed 10MB.',
        ]);

        $userId = (int) $request->user()->id;
        $storedPaths = [];

        try {
            $record = DB::transaction(function () use ($request, $validated, $userId, &$storedPaths) {
                $latest = IdentityVerification::where('user_id', $userId)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($latest && $latest->status === 'verified') {
                    abort(response()->json([
                        'success' => false,
                        'code'    => 'already_verified',
                        'message' => 'Your identity is already verified.',
                    ], 422));
                }

                if ($latest && $latest->status === 'pending') {
                    abort(response()->json([
                        'success' => false,
                        'code'    => 'already_pending',
                        'message' => 'Your identity verification is already under review.',
                    ], 422));
                }

                $frontPath = $request->file('front_image')->store('identity_documents/fronts', 'local');
                $storedPaths[] = $frontPath;

                $backPath = null;
                if ($request->hasFile('back_image')) {
                    $backPath = $request->file('back_image')->store('identity_documents/backs', 'local');
                    $storedPaths[] = $backPath;
                }

                return IdentityVerification::create([
                    'user_id'          => $userId,
                    'id_type'          => $validated['id_type'],
                    'method'           => $validated['method'],
                    'front_image_path' => $frontPath,
                    'back_image_path'  => $backPath,
                    'status'           => 'pending',
                    'rejection_reason' => null,
                    'verified_at'      => null,
                ]);
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Your ID was submitted and is now under review.',
            'data'    => $this->payload($record),
        ], 201);
    }

    protected function latestFor(int $userId): ?IdentityVerification
    {
        return IdentityVerification::where('user_id', $userId)->latest('id')->first();
    }

    protected function payload(?IdentityVerification $latest): array
    {
        $status = $latest?->status ?? 'not_started';

        return [
            'status'       => $status,
            'can_submit'   => $latest === null || $status === 'failed' || $status === 'rejected',
            'verification' => $latest ? [
                'id'               => (int) $latest->id,
                'id_type'          => $latest->id_type,
                'method'           => $latest->method,
                'status'           => $latest->status,
                'rejection_reason' => $latest->rejection_reason,
                'verified_at'      => $latest->verified_at?->toIso8601String(),
                'submitted_at'     => $latest->created_at?->toIso8601String(),
            ] : null,
            'id_types'     => collect(self::ID_TYPES)
                ->map(fn (array $type, string $key) => [
                    'key'           => $key,
                    'label'         => $type['label'],
                    'requires_back' => $type['requires_back'],
                ])
                ->values()
                ->all(),
        ];
    }
}