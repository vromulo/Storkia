<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerAccountSanction;
use App\Models\BuyerBanAppeal;
use App\Models\User;
use App\Services\AuthService;
use App\Services\BuyerAbandonmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected BuyerAbandonmentService $buyerAbandonmentService
    ) {}

    public function showLogin()
    {
        return view('buyer.auth.login');
    }

    public function login(Request $request)
    {
        $response = $this->authService->authenticate($request, expectedRole: 'Buyer');

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            $sanction = BuyerAccountSanction::where('buyer_id', $user->id)->first();

            if ($sanction && $sanction->isBlockedFromMarketplace()) {
                Auth::logout();
                $request->session()->put('suspended_buyer_id', $user->id);

                return redirect()->route('buyer.suspended');
            }
        }

        return $response;
    }

    public function showRegister()
    {
        return view('buyer.auth.register');
    }

    public function showSuspendedNotice(Request $request)
    {
        $buyerId = $request->session()->get('suspended_buyer_id') ?: Auth::id();
        if (! $buyerId) {
            return redirect()->route('login');
        }

        $buyer = User::findOrFail($buyerId);
        $sanction = $this->buyerAbandonmentService->getOrCreateSanction((int) $buyer->id);

        if (! $sanction->isBlockedFromMarketplace()) {
            $request->session()->forget('suspended_buyer_id');
            return redirect()->route('login')->with('success', 'Your account has been reactivated! You may now sign in.');
        }

        if (Auth::check()) {
            Auth::logout();
            $request->session()->put('suspended_buyer_id', $buyer->id);
        }

        $latestAppeal = BuyerBanAppeal::where('buyer_id', $buyer->id)->latest('id')->first();

        return view('buyer.auth.suspended', compact('buyer', 'sanction', 'latestAppeal'));
    }

    public function submitSuspensionAppeal(Request $request)
    {
        $buyerId = $request->session()->get('suspended_buyer_id');
        if (! $buyerId) {
            return redirect()->route('login');
        }

        $buyer = User::findOrFail($buyerId);

        $validated = $request->validate([
            'appeal_explanation' => ['required', 'string', 'min:15', 'max:1500'],
            'supporting_photo'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'appeal_explanation.required' => 'Please explain why you were unable to receive your COD parcel and how you will prevent future abandonment.',
            'appeal_explanation.min'      => 'Your appeal explanation must be at least 15 characters.',
        ]);

        $photoPath = null;
        if ($request->hasFile('supporting_photo')) {
            $photoPath = $request->file('supporting_photo')->store('buyer_ban_appeals', 'public');
        }

        $this->buyerAbandonmentService->submitBuyerAppeal(
            buyer: $buyer,
            explanation: $validated['appeal_explanation'],
            photoPath: $photoPath
        );

        return redirect()
            ->route('buyer.suspended')
            ->with('success', 'Your reactivation appeal has been submitted to Storkia Admin for review.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('info', 'Successfully signed out.');
    }
}