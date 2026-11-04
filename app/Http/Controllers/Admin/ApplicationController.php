<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\LogisticsApplicationDecisionMail;
use App\Mail\SellerApplicationDecisionMail;
use App\Models\IdentityVerification;
use App\Models\LogisticsApplication;
use App\Models\LogisticsProfile;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use App\Notifications\Buyer\IdentityVerificationNotification;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function identityIndex(Request $request)
    {
        $status = $request->query('status', $request->query('activeTab', 'pending'));
        $activeTab = $status;
        $search = trim((string) $request->query('search', ''));

        $counts = [
            'all'      => IdentityVerification::count(),
            'pending'  => IdentityVerification::where('status', 'pending')->count(),
            'verified' => IdentityVerification::where('status', 'verified')->count(),
            'failed'   => IdentityVerification::where('status', 'failed')->count(),
        ];

        $query = IdentityVerification::with('user')->orderBy('created_at', 'asc');
        if ($status !== 'all' && in_array($status, ['pending', 'verified', 'failed'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->whereHas('user', function ($uq) use ($search) {
                $uq->where('first_name', 'like', "%{$search}%")
                   ->orWhere('last_name', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $verifications = $query->paginate(20)->withQueryString();
        $applications = $verifications;
        $detailModalOpen = false;
        $docModalOpen = false;
        $rejectModalOpen = false;
        $currentItem = null;
        $history = collect();

        return view()->first(
            ['admin.applications.identity', 'admin.identity-verifications', 'livewire.admin.identity-verifications'],
            compact(
                'verifications',
                'applications',
                'status',
                'activeTab',
                'search',
                'counts',
                'detailModalOpen',
                'docModalOpen',
                'rejectModalOpen',
                'currentItem',
                'history'
            )
        );
    }

    public function approveIdentity(Request $request, IdentityVerification $verification)
    {
        $verification->loadMissing('user');

        $verification->update([
            'status'           => 'verified',
            'rejection_reason' => null,
            'verified_at'      => now(),
        ]);

        try {
            $verification->user?->notify(new IdentityVerificationNotification($verification, 'verified'));
        } catch (\Throwable $e) {
        }

        AuditLoggerService::log(
            action: 'identity_verification_approved',
            targetType: 'IdentityVerification',
            targetId: (int) $verification->id,
            targetReference: $verification->user?->email
        );

        return back()->with('success', 'Buyer identity verification approved.');
    }

    public function rejectIdentity(Request $request, IdentityVerification $verification)
    {
        $verification->loadMissing('user');

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
            'remarks'          => ['nullable', 'string', 'max:500'],
        ]);

        $reason = trim((string) ($validated['rejection_reason'] ?? $validated['remarks'] ?? 'Submitted ID image was unclear or invalid.'));

        $verification->update([
            'status'           => 'failed',
            'rejection_reason' => $reason,
            'verified_at'      => null,
        ]);

        try {
            $verification->user?->notify(new IdentityVerificationNotification($verification, 'failed', $reason));
        } catch (\Throwable $e) {
        }

        AuditLoggerService::log(
            action: 'identity_verification_rejected',
            targetType: 'IdentityVerification',
            targetId: (int) $verification->id,
            targetReference: $verification->user?->email,
            reason: $reason
        );

        return back()->with('success', 'Buyer identity verification rejected.');
    }

    public function viewIdentityDocument(Request $request, IdentityVerification $verification, string $side)
    {
        $path = $side === 'back' ? $verification->back_image_path : $verification->front_image_path;
        if (! $path) {
            abort(404);
        }

        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';
        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk($disk)->path($path));
    }

    public function sellerIndex(Request $request)
    {
        $status = $request->query('status', $request->query('activeTab', 'pending'));
        $activeTab = $status;
        $search = trim((string) $request->query('search', ''));

        $counts = [
            'all'      => SellerApplication::count(),
            'pending'  => SellerApplication::where('status', 'pending')->count(),
            'approved' => SellerApplication::where('status', 'approved')->count(),
            'rejected' => SellerApplication::where('status', 'rejected')->count(),
        ];

        $query = SellerApplication::with('user')->orderBy('created_at', 'asc');
        if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('municipality', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $applications = $query->paginate(20)->withQueryString();
        $detailModalOpen = false;
        $docModalOpen = false;
        $rejectModalOpen = false;
        $currentApp = null;
        $history = collect();

        return view()->first(
            ['admin.applications.sellers', 'admin.seller-applications', 'livewire.admin.seller-applications'],
            compact(
                'applications',
                'status',
                'activeTab',
                'search',
                'counts',
                'detailModalOpen',
                'docModalOpen',
                'rejectModalOpen',
                'currentApp',
                'history'
            )
        );
    }

    public function approveSeller(Request $request, SellerApplication $application)
    {
        $application->loadMissing('user');
        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($application, $adminId) {
            $application->update([
                'status'           => 'approved',
                'rejection_reason' => null,
                'reviewed_by'      => $adminId,
                'reviewed_at'      => now(),
            ]);

            SellerProfile::updateOrCreate(
                ['user_id' => $application->user_id],
                [
                    'contact_no'       => $application->contact_no,
                    'province'         => $application->province,
                    'municipality'     => $application->municipality,
                    'barangay'         => $application->barangay,
                    'street'           => $application->street,
                    'house_details'    => $application->house_details,
                    'business_name'    => $application->business_name,
                    'line_of_business' => $application->line_of_business,
                    'id_path'          => $application->id_path,
                    'permit_path'      => $application->permit_path,
                ]
            );

            $application->user?->update(['role' => 'Seller']);
        });

        try {
            if ($application->user && class_exists(SellerApplicationDecisionMail::class)) {
                Mail::to($application->user->email)->send(new SellerApplicationDecisionMail($application, 'approved'));
            }
            $application->user?->notify(new OrderLifecycleNotification(
                title: 'Seller Application Approved!',
                message: "Your merchant application for {$application->business_name} has been approved! Your Seller Portal is now unlocked.",
                type: 'success',
                url: route('seller.seller-dashboard')
            ));
        } catch (\Throwable $e) {
        }

        AuditLoggerService::log(
            action: 'seller_application_approved',
            targetType: 'SellerApplication',
            targetId: (int) $application->id,
            targetReference: $application->business_name
        );

        return back()->with('success', "Seller application for {$application->business_name} approved!");
    }

    public function rejectSeller(Request $request, SellerApplication $application)
    {
        $application->loadMissing('user');

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
            'remarks'          => ['nullable', 'string', 'max:500'],
        ]);

        $reason = trim((string) ($validated['rejection_reason'] ?? $validated['remarks'] ?? 'Incomplete business documents.'));

        $application->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by'      => Auth::guard('admin')->id(),
            'reviewed_at'      => now(),
        ]);

        try {
            if ($application->user && class_exists(SellerApplicationDecisionMail::class)) {
                Mail::to($application->user->email)->send(new SellerApplicationDecisionMail($application, 'rejected', $reason));
            }
            $application->user?->notify(new OrderLifecycleNotification(
                title: 'Seller Application Requires Updates',
                message: "Your seller application for {$application->business_name} was not approved. Reason: {$reason}",
                type: 'danger',
                url: route('seller.reapply')
            ));
        } catch (\Throwable $e) {
        }

        AuditLoggerService::log(
            action: 'seller_application_rejected',
            targetType: 'SellerApplication',
            targetId: (int) $application->id,
            targetReference: $application->business_name,
            reason: $reason
        );

        return back()->with('success', "Seller application for {$application->business_name} rejected.");
    }

    public function viewSellerDocument(Request $request, SellerApplication $application, string $type)
    {
        $path = $type === 'permit' ? $application->permit_path : $application->id_path;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    public function logisticsIndex(Request $request)
    {
        $status = $request->query('status', $request->query('activeTab', 'pending'));
        $activeTab = $status;
        $search = trim((string) $request->query('search', ''));

        $counts = [
            'all'      => LogisticsApplication::count(),
            'pending'  => LogisticsApplication::where('status', 'pending')->count(),
            'approved' => LogisticsApplication::where('status', 'approved')->count(),
            'rejected' => LogisticsApplication::where('status', 'rejected')->count(),
        ];

        $query = LogisticsApplication::with('user')->orderBy('created_at', 'asc');
        if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('municipality', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $applications = $query->paginate(20)->withQueryString();
        $detailModalOpen = false;
        $docModalOpen = false;
        $rejectModalOpen = false;
        $currentApp = null;
        $history = collect();

        return view()->first(
            ['admin.applications.logistics', 'admin.logistics-applications', 'livewire.admin.logistics-applications'],
            compact(
                'applications',
                'status',
                'activeTab',
                'search',
                'counts',
                'detailModalOpen',
                'docModalOpen',
                'rejectModalOpen',
                'currentApp',
                'history'
            )
        );
    }

    public function approveLogistics(Request $request, LogisticsApplication $application)
    {
        $application->loadMissing('user');
        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($application, $adminId) {
            $application->update([
                'status'           => 'approved',
                'rejection_reason' => null,
                'reviewed_by'      => $adminId,
                'reviewed_at'      => now(),
            ]);

            $profile = LogisticsProfile::updateOrCreate(
                ['user_id' => $application->user_id],
                [
                    'contact_no'    => $application->contact_no,
                    'province'      => $application->province,
                    'municipality'  => $application->municipality,
                    'barangay'      => $application->barangay,
                    'street'        => $application->street,
                    'house_details' => $application->house_details,
                    'business_name' => $application->business_name,
                    'id_path'       => $application->id_path,
                    'permit_path'   => $application->permit_path,
                ]
            );

            $application->user?->update(['role' => 'Logistics']);

            if (method_exists($profile, 'ensureMainHubExists')) {
                $profile->ensureMainHubExists();
            }
        });

        try {
            if ($application->user && class_exists(LogisticsApplicationDecisionMail::class)) {
                Mail::to($application->user->email)->send(new LogisticsApplicationDecisionMail($application, 'approved'));
            }
            $application->user?->notify(new OrderLifecycleNotification(
                title: 'Logistics Sorting Hub Approved!',
                message: "Your logistics hub application for {$application->business_name} has been approved! Your Main Hub is now active.",
                type: 'success',
                url: route('logistics.logistics-dashboard')
            ));
        } catch (\Throwable $e) {
        }

        AuditLoggerService::log(
            action: 'logistics_application_approved',
            targetType: 'LogisticsApplication',
            targetId: (int) $application->id,
            targetReference: $application->business_name
        );

        return back()->with('success', "Logistics application for {$application->business_name} approved!");
    }

    public function rejectLogistics(Request $request, LogisticsApplication $application)
    {
        $application->loadMissing('user');

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
            'remarks'          => ['nullable', 'string', 'max:500'],
        ]);

        $reason = trim((string) ($validated['rejection_reason'] ?? $validated['remarks'] ?? 'Incomplete logistics hub documents.'));

        $application->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by'      => Auth::guard('admin')->id(),
            'reviewed_at'      => now(),
        ]);

        try {
            if ($application->user && class_exists(LogisticsApplicationDecisionMail::class)) {
                Mail::to($application->user->email)->send(new LogisticsApplicationDecisionMail($application, 'rejected', $reason));
            }
            $application->user?->notify(new OrderLifecycleNotification(
                title: 'Logistics Hub Application Requires Updates',
                message: "Your logistics application for {$application->business_name} was not approved. Reason: {$reason}",
                type: 'danger',
                url: route('logistics.reapply')
            ));
        } catch (\Throwable $e) {
        }

        AuditLoggerService::log(
            action: 'logistics_application_rejected',
            targetType: 'LogisticsApplication',
            targetId: (int) $application->id,
            targetReference: $application->business_name,
            reason: $reason
        );

        return back()->with('success', "Logistics application for {$application->business_name} rejected.");
    }

    public function viewLogisticsDocument(Request $request, LogisticsApplication $application, string $type)
    {
        $path = $type === 'permit' ? $application->permit_path : $application->id_path;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}