<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Services\AuditLoggerService;
use App\Services\FormalReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlatformSettingsController extends Controller
{
    public function __construct(
        protected FormalReportService $formalReportService
    ) {}

    public function auditLogs(Request $request)
    {
        $actorType = $request->query('actor_type', 'all');
        $actionFilter = $request->query('action_type', 'all');
        $ipSearch = trim((string) $request->query('ip', ''));
        $search = trim((string) $request->query('search', ''));
        $fromDate = $request->query('from_date', now()->subDays(30)->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());

        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $query = PlatformAuditLog::whereBetween('occurred_at', [$start, $end]);

        if ($actorType !== 'all') {
            $query->where('actor_type', $actorType);
        }

        if ($actionFilter !== 'all') {
            $query->where('action', $actionFilter);
        }

        if ($ipSearch !== '') {
            $query->where('ip_address', 'like', "%{$ipSearch}%");
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('actor_name', 'like', "%{$search}%")
                  ->orWhere('target_reference', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        $logs = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $distinctActions = PlatformAuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.logs.index', compact(
            'logs',
            'distinctActions',
            'actorType',
            'actionFilter',
            'ipSearch',
            'search',
            'fromDate',
            'toDate'
        ));
    }

    public function index()
    {
        $heroBanners = DB::table('hero_banners')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $announcements = DB::table('platform_announcements')
            ->orderByDesc('id')
            ->get();

        return view('admin.settings.index', compact('heroBanners', 'announcements'));
    }

    public function storeHeroBanner(Request $request)
    {
        $validated = $request->validate([
            'title'      => ['required', 'string', 'min:3', 'max:150'],
            'subtitle'   => ['nullable', 'string', 'max:255'],
            'badge_text' => ['nullable', 'string', 'max:60'],
            'cta_text'   => ['required', 'string', 'max:60'],
            'cta_url'    => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:20'],
            'image'      => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('hero_banners', 'public');
        }

        $bannerId = DB::table('hero_banners')->insertGetId([
            'title'      => trim($validated['title']),
            'subtitle'   => $validated['subtitle'] ? trim($validated['subtitle']) : null,
            'badge_text' => $validated['badge_text'] ? trim($validated['badge_text']) : 'Featured Offer',
            'cta_text'   => trim($validated['cta_text']),
            'cta_url'    => trim($validated['cta_url']),
            'image_path' => $imagePath,
            'sort_order' => (int) $validated['sort_order'],
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLoggerService::log(
            action: 'hero_banner_created',
            targetType: 'HeroBanner',
            targetId: $bannerId,
            targetReference: $validated['title'],
            newValues: $validated,
            reason: 'Admin published a new storefront Hero Carousel slide.'
        );

        return back()->with('success', "Hero Banner \"{$validated['title']}\" published to the Home Page Carousel!");
    }

    public function toggleHeroBanner(int $id)
    {
        $banner = DB::table('hero_banners')->where('id', $id)->first();
        if (! $banner) {
            abort(404);
        }

        $nextState = ! $banner->is_active;
        DB::table('hero_banners')->where('id', $id)->update([
            'is_active'  => $nextState,
            'updated_at' => now(),
        ]);

        AuditLoggerService::log(
            action: 'hero_banner_toggled',
            targetType: 'HeroBanner',
            targetId: $id,
            targetReference: $banner->title,
            oldValues: ['is_active' => (bool) $banner->is_active],
            newValues: ['is_active' => $nextState],
            reason: 'Admin toggled Hero Carousel slide visibility.'
        );

        return back()->with('success', 'Hero Banner visibility updated.');
    }

    public function destroyHeroBanner(int $id)
    {
        $banner = DB::table('hero_banners')->where('id', $id)->first();
        if (! $banner) {
            abort(404);
        }

        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        DB::table('hero_banners')->where('id', $id)->delete();

        AuditLoggerService::log(
            action: 'hero_banner_deleted',
            targetType: 'HeroBanner',
            targetId: $id,
            targetReference: $banner->title,
            oldValues: (array) $banner,
            reason: 'Admin deleted Hero Carousel slide.'
        );

        return back()->with('success', 'Hero Banner removed.');
    }

    public function storeAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title'    => ['required', 'string', 'min:3', 'max:150'],
            'category' => ['required', 'in:announcement,policy_update,maintenance'],
            'body'     => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $annId = DB::table('platform_announcements')->insertGetId([
            'title'               => trim($validated['title']),
            'category'            => $validated['category'],
            'body'                => trim($validated['body']),
            'is_active'           => true,
            'created_by_admin_id' => auth('admin')->id(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        AuditLoggerService::log(
            action: 'platform_announcement_posted',
            targetType: 'PlatformAnnouncement',
            targetId: $annId,
            targetReference: $validated['title'],
            newValues: $validated,
            reason: 'Admin posted a platform announcement / policy update.'
        );

        return back()->with('success', 'Platform announcement / policy published successfully!');
    }

    public function toggleAnnouncement(int $id)
    {
        $ann = DB::table('platform_announcements')->where('id', $id)->first();
        if (! $ann) {
            abort(404);
        }

        DB::table('platform_announcements')->where('id', $id)->update([
            'is_active'  => ! $ann->is_active,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Announcement status updated.');
    }

    public function formalSalesReport(Request $request)
    {
        $report = $this->formalReportService->buildRoleReportPayload(
            role: 'Admin',
            entityId: 1,
            entityName: 'Storkia Platform Administration',
            request: $request
        );

        return view('reports.formal-pdf-report', compact('report'));
    }
}