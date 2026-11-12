<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public static function getApkCatalog(): array
    {
        $definitions = [
            'buyer' => [
                'key'           => 'buyer',
                'name'          => 'Storkia',
                'tagline'       => 'Buyer Marketplace App',
                'badge'         => 'Shopper & Rewards App',
                'version'       => 'v1.2.0',
                'min_android'   => 'Android 8.0+ (Oreo & newer)',
                'logo_url'      => asset('assets/icon.webp'),
                'accent_color'  => '#CF4173',
                'download_name' => 'Storkia-Shopping.apk',
                'candidates'    => [
                    'apks/storkia.apk',
                    'apks/storkia-buyer.apk',
                    'apks/storkia_mobile.apk',
                    'assets/storkia.apk',
                    'assets/storkia-buyer.apk',
                ],
                'description'   => 'Shop curated local stores, play the 24h Daily Spin Wheel & 3-Tap Treasure Chest, redeem Storkia Coins, and track deliveries live on the map.',
                'features'      => [
                    'Daily Spin Wheel & 3-Tap Treasure Chest Coins',
                    'Multi-Store Checkout with Voucher & Coin Stacking',
                    'Real-Time Map Tracking & Direct Rider/Seller Chat',
                    'Instant Storkia E-Wallet Top-Ups & Refunds',
                ],
            ],
            'rider' => [
                'key'           => 'rider',
                'name'          => 'Storkia Rider',
                'tagline'       => 'Last-Mile Delivery Fleet App',
                'badge'         => 'Last-Mile Rider App',
                'version'       => 'v1.2.0',
                'min_android'   => 'Android 8.0+ (Oreo & newer)',
                'logo_url'      => asset('assets/s-rider-foreground.png.png'),
                'accent_color'  => '#5D3140',
                'download_name' => 'Storkia-Rider.apk',
                'candidates'    => [
                    'apks/storkia-rider.apk',
                    'apks/storkia_rider.apk',
                    'apks/rider.apk',
                    'assets/storkia-rider.apk',
                ],
                'description'   => 'Dedicated companion app for approved Last-Mile Delivery Riders covering local Barangays. Scan QR waybills, capture Proof of Delivery photos, and remit COD.',
                'features'      => [
                    'Barangay-Scoped Last-Mile Delivery Queue (FIFO)',
                    'In-App QR Waybill Scanner & Camera Proof of Delivery',
                    'Live GPS Route Navigation & Buyer Delivery Chat',
                    '45%–55% Shipping Pool Earnings & GCash Withdrawals',
                ],
            ],
            'express' => [
                'key'           => 'express',
                'name'          => 'Storkia Express',
                'tagline'       => 'First-Mile Courier & Haulage App',
                'badge'         => 'First-Mile Courier App',
                'version'       => 'v1.2.0',
                'min_android'   => 'Android 8.0+ (Oreo & newer)',
                'logo_url'      => asset('assets/s-express_2.png'),
                'accent_color'  => '#0D9488',
                'download_name' => 'Storkia-Express.apk',
                'candidates'    => [
                    'apks/storkia-express.apk',
                    'apks/storkia-courier.apk',
                    'apks/storkia_courier.apk',
                    'apks/courier.apk',
                    'assets/storkia-express.apk',
                ],
                'description'   => 'Built for First-Mile Couriers hauling merchant parcels from Seller storefronts to Municipality Sorting Hubs, plus Return-to-Seller (RTS) deliveries.',
                'features'      => [
                    'First-Mile Seller Pickup & Sorting Hub Handover Queue',
                    'Multi-Package Manifest & QR Verification Lookup',
                    'Pre-Trip Vehicle Checklist & Incident Reporting',
                    '25% Haulage Commission Pool & Instant E-Wallet Ledger',
                ],
            ],
        ];

        foreach ($definitions as $key => &$app) {
            $resolvedPath = null;
            $resolvedRel = $app['candidates'][0];

            foreach ($app['candidates'] as $relPath) {
                $full = public_path($relPath);
                if (is_file($full) && is_readable($full)) {
                    $resolvedPath = $full;
                    $resolvedRel = $relPath;
                    break;
                }
            }

            $app['is_available'] = $resolvedPath !== null;
            $app['expected_path'] = 'public/' . $app['candidates'][0];
            $app['relative_path'] = $resolvedRel;
            $app['file_size_label'] = $resolvedPath
                ? number_format(max(0.1, filesize($resolvedPath) / 1048576), 1) . ' MB'
                : 'APK Ready Slot';
            $app['updated_at_label'] = $resolvedPath
                ? Carbon::createFromTimestamp(filemtime($resolvedPath))->timezone('Asia/Manila')->format('M d, Y h:i A')
                : 'Place APK in ' . $app['expected_path'];
            $app['download_url'] = route('download-app.apk', ['app' => $key]);
        }
        unset($app);

        return $definitions;
    }

    public function showRegister()
    {
        if (Auth::check() && Auth::user()->role === 'Logistics') {
            return redirect()->route('logistics.riders.create');
        }

        return redirect()->route('logistics.login');
    }

    public function downloadApp()
    {
        $apps = self::getApkCatalog();

        return view('rider.download-app', compact('apps'));
    }

    public function downloadApk(string $app)
    {
        $catalog = self::getApkCatalog();
        $normalized = strtolower(trim($app));

        if ($normalized === 'courier') {
            $normalized = 'express';
        } elseif ($normalized === 'storkia') {
            $normalized = 'buyer';
        }

        if (! isset($catalog[$normalized])) {
            abort(404, 'Requested application package does not exist.');
        }

        $item = $catalog[$normalized];

        foreach ($item['candidates'] as $relPath) {
            $fullPath = public_path($relPath);
            if (is_file($fullPath) && is_readable($fullPath)) {
                return response()->download($fullPath, $item['download_name'], [
                    'Content-Type'        => 'application/vnd.android.package-archive',
                    'Content-Disposition' => 'attachment; filename="' . $item['download_name'] . '"',
                    'Cache-Control'       => 'no-cache, must-revalidate',
                ]);
            }
        }

        return redirect()
            ->route('download-app')
            ->with(
                'apk_notice',
                "The {$item['name']} installer ({$item['download_name']}) has not been uploaded to {$item['expected_path']} yet. Build your release APK and place it in that folder to enable direct download."
            );
    }

    public function logout(Request $request)
    {
        Auth::guard('rider')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.login');
    }
}