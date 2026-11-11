<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProfileController extends Controller
{
    protected const USERNAME_COOLDOWN_DAYS = 7;

    protected const USERNAME_MIN = 3;

    protected const USERNAME_MAX = 30;

    public function show(Request $request): JsonResponse
    {
        $profile = $this->profileFor($request->user());

        return response()->json([
            'success' => true,
            'data'    => $this->payload($profile),
        ]);
    }

    public function updateUsername(Request $request): JsonResponse
    {
        $profile = $this->profileFor($request->user());

        $availableAt = $this->availableAt($profile);

        if ($availableAt !== null) {
            return $this->failure(
                'You can only change your username once a week. Available ' . $availableAt->diffForHumans() . '.',
                'username'
            );
        }

        $cleanUsername = strtolower(trim((string) $request->input('username')));
        $request->merge(['username' => $cleanUsername]);

        $request->validate([
            'username' => ['required', 'string', 'min:' . self::USERNAME_MIN, 'max:' . self::USERNAME_MAX],
        ], [
            'username.required' => 'Please enter a username.',
            'username.min'      => 'Username must be at least ' . self::USERNAME_MIN . ' characters long.',
            'username.max'      => 'Username cannot exceed ' . self::USERNAME_MAX . ' characters.',
        ]);

        if ($cleanUsername === strtolower(trim((string) $profile->username))) {
            return $this->failure('This is already your current username.', 'username');
        }

        $taken = BuyerProfile::whereRaw('LOWER(TRIM(username)) = ?', [$cleanUsername])
            ->where('id', '!=', $profile->id)
            ->exists();

        if ($taken) {
            return $this->failure('This username is already taken by another user.', 'username');
        }

        $profile->update([
            'username'            => $cleanUsername,
            'username_changed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Username updated successfully.',
            'data'    => $this->payload($profile->fresh()),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'avatar.required' => 'Please select an image file to upload.',
            'avatar.image'    => 'The file must be an image.',
            'avatar.mimes'    => 'Supported formats: JPG, JPEG, PNG, WEBP.',
            'avatar.max'      => 'Avatar size cannot exceed 5MB.',
        ]);

        $profile = $this->profileFor($request->user());
        $previousPath = $profile->avatar_path;

        $path = $this->storeAvatar($request->file('avatar'));
        $profile->update(['avatar_path' => $path]);

        if ($previousPath && $previousPath !== $path && Storage::disk('public')->exists($previousPath)) {
            Storage::disk('public')->delete($previousPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar updated successfully.',
            'data'    => $this->payload($profile->fresh()),
        ]);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        $profile = $this->profileFor($request->user());

        if ($profile->avatar_path) {
            if (Storage::disk('public')->exists($profile->avatar_path)) {
                Storage::disk('public')->delete($profile->avatar_path);
            }

            $profile->update(['avatar_path' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar removed successfully.',
            'data'    => $this->payload($profile->fresh()),
        ]);
    }

    protected function profileFor(User $user): BuyerProfile
    {
        return $user->buyerProfile ?? BuyerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['username' => BuyerProfile::generateUniqueUsername((string) $user->email)]
        );
    }

    protected function availableAt(BuyerProfile $profile): ?Carbon
    {
        if ($profile->canChangeUsername()) {
            return null;
        }

        return $profile->username_changed_at->copy()->addDays(self::USERNAME_COOLDOWN_DAYS);
    }

    protected function payload(BuyerProfile $profile): array
    {
        $availableAt = $this->availableAt($profile);

        return [
            'user_id'                => (int) $profile->user_id,
            'username'               => $profile->username,
            'avatar_url'             => $profile->avatar_url,
            'username_changed_at'    => $profile->username_changed_at?->toISOString(),
            'can_change_username'    => $availableAt === null,
            'username_available_at'  => $availableAt?->toISOString(),
            'username_cooldown_text' => $availableAt ? 'Available ' . $availableAt->diffForHumans() : null,
            'username_min_length'    => self::USERNAME_MIN,
            'username_max_length'    => self::USERNAME_MAX,
        ];
    }

    protected function failure(string $message, string $field): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => [$field => [$message]],
        ], 422);
    }

    protected function storeAvatar(UploadedFile $file): string
    {
        $path = 'buyer_avatars/' . uniqid('avatar_') . '_' . time() . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(600, 600)->toWebp(85);
            Storage::disk('public')->put($path, (string) $encoded);

            return $path;
        } catch (\Throwable $e) {
            return $file->store('buyer_avatars', 'public');
        }
    }
}