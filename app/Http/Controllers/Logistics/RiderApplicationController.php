<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RiderApplicationController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()->logisticsProfile;

        if (! $profile) {
            abort(403, 'Your logistics hub must be approved to manage rider applications.');
        }

        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = Rider::where('logistics_profile_id', $profile->id);

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('license_no', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        $applicants = $query->latest()->paginate(10)->withQueryString();

        $counts = [
            'all' => Rider::where('logistics_profile_id', $profile->id)->count(),
            'pending' => Rider::where('logistics_profile_id', $profile->id)->where('status', 'pending')->count(),
            'approved' => Rider::where('logistics_profile_id', $profile->id)->where('status', 'approved')->count(),
            'rejected' => Rider::where('logistics_profile_id', $profile->id)->where('status', 'rejected')->count(),
        ];

        return view('logistics.riders.applications', compact('applicants', 'counts', 'status', 'search', 'profile'));
    }

    public function approve(Request $request, Rider $rider)
    {
        $profile = $request->user()->logisticsProfile;

        if (! $profile || $rider->logistics_profile_id !== $profile->id) {
            abort(403);
        }

        $rider->update([
            'status' => 'approved',
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        return back()->with('success', "Rider application for {$rider->full_name} has been approved.");
    }

    public function reject(Request $request, Rider $rider)
    {
        $profile = $request->user()->logisticsProfile;

        if (! $profile || $rider->logistics_profile_id !== $profile->id) {
            abort(403);
        }

        $rider->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'approved_at' => null,
        ]);

        return back()->with('success', "Rider application for {$rider->full_name} has been rejected.");
    }

    public function document(Request $request, Rider $rider, string $type)
    {
        $profile = $request->user()->logisticsProfile;

        if (! $profile || $rider->logistics_profile_id !== $profile->id) {
            abort(403);
        }

        $path = match ($type) {
            'license' => $rider->license_path,
            'or_cr' => $rider->or_cr_path,
            default => null,
        };

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Document not found.');
        }

        return Storage::disk('public')->response($path);
    }
}