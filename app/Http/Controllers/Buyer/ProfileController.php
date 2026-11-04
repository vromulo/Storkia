<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProfileController extends Controller
{
    private function optimizeAndStoreAvatar($file, string $folder = 'buyer_avatars'): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['svg', 'gif'], true)) {
            return $file->store($folder, 'public');
        }

        $filename = uniqid('avatar_') . '_' . time() . '.webp';
        $path = $folder . '/' . $filename;

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(600, 600)->toWebp(85);
            Storage::disk('public')->put($path, (string) $encoded);
            return $path;
        } catch (\Throwable $e) {
            return $file->store($folder, 'public');
        }
    }

    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $profile = $user->buyerProfile ?: BuyerProfile::create([
            'user_id'  => $user->id,
            'username' => BuyerProfile::generateUniqueUsername($user->email),
        ]);

        return view('buyer.option.account.profile', compact('user', 'profile'));
    }

    public function updateUsername(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->buyerProfile ?: BuyerProfile::create([
            'user_id'  => $user->id,
            'username' => BuyerProfile::generateUniqueUsername($user->email),
        ]);

        if (! $profile->canChangeUsername()) {
            $availableIn = $profile->username_changed_at->copy()->addDays(7)->diffForHumans();
            return response()->json([
                'success' => false,
                'message' => "You can only change your username once a week. Available {$availableIn}.",
                'errors'  => [
                    'username' => ["You can only change your username once a week. Available {$availableIn}."],
                ],
            ], 422);
        }

        $cleanUsername = strtolower(trim((string) $request->input('username')));
        $request->merge(['username' => $cleanUsername]);

        $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30'],
        ], [
            'username.required' => 'Please enter a username.',
            'username.min'      => 'Username must be at least 3 characters long.',
            'username.max'      => 'Username cannot exceed 30 characters.',
        ]);

        if ($cleanUsername === strtolower(trim($profile->username))) {
            return response()->json([
                'success' => false,
                'message' => 'This is already your current username.',
                'errors'  => ['username' => ['This is already your current username.']],
            ], 422);
        }

        if (BuyerProfile::whereRaw('LOWER(TRIM(username)) = ?', [$cleanUsername])
            ->where('id', '!=', $profile->id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This username is already taken by another user.',
                'errors'  => ['username' => ['This username is already taken by another user.']],
            ], 422);
        }

        $profile->update([
            'username'            => $cleanUsername,
            'username_changed_at' => now(),
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'Username updated successfully.',
            'username'      => $cleanUsername,
            'can_change'    => false,
            'cooldown_text' => 'Available ' . now()->addDays(7)->diffForHumans(),
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'avatar.required' => 'Please select an image file to upload.',
            'avatar.image'    => 'The file must be an image.',
            'avatar.mimes'    => 'Supported formats: JPG, JPEG, PNG, WEBP.',
            'avatar.max'      => 'Avatar size cannot exceed 5MB.',
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->buyerProfile ?: BuyerProfile::create([
            'user_id'  => $user->id,
            'username' => BuyerProfile::generateUniqueUsername($user->email),
        ]);

        if ($profile->avatar_path && Storage::disk('public')->exists($profile->avatar_path)) {
            Storage::disk('public')->delete($profile->avatar_path);
        }

        $path = $this->optimizeAndStoreAvatar($request->file('avatar'));
        $profile->update(['avatar_path' => $path]);

        return response()->json([
            'success'    => true,
            'message'    => 'Avatar updated successfully.',
            'avatar_url' => asset('storage/' . $path),
        ]);
    }

    public function removeAvatar()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->buyerProfile;

        if ($profile && $profile->avatar_path && Storage::disk('public')->exists($profile->avatar_path)) {
            Storage::disk('public')->delete($profile->avatar_path);
            $profile->update(['avatar_path' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar removed successfully.',
        ]);
    }
}