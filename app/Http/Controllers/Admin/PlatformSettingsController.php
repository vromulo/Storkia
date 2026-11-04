<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Services\AuditLoggerService;
use App\Services\FormalReportService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PlatformSettingsController extends Controller
{
    public function __construct(
        protected FormalReportService $formalReportService
    ) {}

    public function index()
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $banners = Schema::hasTable('hero_banners')
            ? collect(DB::table('hero_banners')->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get())
            : collect();

        $announcements = Schema::hasTable('platform_announcements')
            ? collect(DB::table('platform_announcements')->orderByDesc('created_at')->get())
            : collect();

        $platformGcash = Schema::hasTable('platform_gcash_settings')
            ? DB::table('platform_gcash_settings')->orderByDesc('id')->first()
            : null;

        return view('admin.settings.index', compact(
            'banners',
            'announcements',
            'platformGcash'
        ));
    }

    public function storeHeroBanner(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $validated = $request->validate([
            'title'      => ['required', 'string', 'min:3', 'max:150'],
            'subtitle'   => ['nullable', 'string', 'max:255'],
            'badge_text' => ['nullable', 'string', 'max:60'],
            'cta_text'   => ['nullable', 'string', 'max:60'],
            'cta_url'    => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'image'      => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'image.required'  => 'Please upload a banner image (JPG, PNG, or WEBP up to 5 MB).',
            'image.mimetypes' => 'Banner image must be a valid JPG, PNG, or WEBP file.',
        ]);

        $file = $request->file('image');
        $filename = 'hero_banners/banner_' . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(1920, 900)->toWebp(85);
            Storage::disk('public')->put($filename, (string) $encoded);
        } catch (\Throwable $e) {
            $filename = $file->store('hero_banners', 'public');
        }

        DB::table('hero_banners')->insert([
            'title'      => trim($validated['title']),
            'subtitle'   => $validated['subtitle'] ?? null,
            'badge_text' => $validated['badge_text'] ?? 'Featured',
            'cta_text'   => $validated['cta_text'] ?: 'Shop Now',
            'cta_url'    => $validated['cta_url'] ?: '/shop',
            'image_path' => $filename,
            'sort_order' => (int) ($validated['sort_order'] ?? 1),
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Hero banner slide published and optimized to WebP!');
    }

    public function toggleHeroBanner(int $id)
    {
        $banner = DB::table('hero_banners')->where('id', $id)->first();
        if (! $banner) {
            abort(404);
        }

        DB::table('hero_banners')->where('id', $id)->update([
            'is_active'  => ! $banner->is_active,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Hero banner visibility updated.');
    }

    public function deleteHeroBanner(int $id)
    {
        $banner = DB::table('hero_banners')->where('id', $id)->first();
        if ($banner) {
            if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
                Storage::disk('public')->delete($banner->image_path);
            }
            DB::table('hero_banners')->where('id', $id)->delete();
        }

        return back()->with('success', 'Hero banner deleted.');
    }

    public function storeAnnouncement(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'title'           => ['required', 'string', 'min:3', 'max:150'],
            'body'            => ['required', 'string', 'min:10', 'max:5000'],
            'category'        => ['required', 'in:announcement,policy_update,maintenance,promotion'],
            'target_audience' => ['nullable', 'in:all,buyers,sellers,logistics'],
            'attachment'      => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = 'announcements/ann_' . uniqid('', true) . '.webp';

            try {
                $manager = new ImageManager(new Driver());
                $encoded = $manager->read($file->getRealPath())->scaleDown(1400, 1400)->toWebp(84);
                Storage::disk('public')->put($filename, (string) $encoded);
                $attachmentPath = $filename;
            } catch (\Throwable $e) {
                $attachmentPath = $file->store('announcements', 'public');
            }
        }

        $insertData = [
            'title'               => trim($validated['title']),
            'body'                => trim($validated['body']),
            'category'            => $validated['category'],
            'is_active'           => true,
            'created_by_admin_id' => $admin?->id,
            'created_at'          => now(),
            'updated_at'          => now(),
        ];

        if (Schema::hasColumn('platform_announcements', 'target_audience')) {
            $insertData['target_audience'] = $validated['target_audience'] ?? 'all';
            $insertData['attachment_path'] = $attachmentPath;
        }

        $id = DB::table('platform_announcements')->insertGetId($insertData);

        AuditLoggerService::log(
            action: 'platform_announcement_published',
            targetType: 'PlatformAnnouncement',
            targetId: $id,
            targetReference: $validated['title'],
            newValues: ['category' => $validated['category'], 'audience' => $validated['target_audience'] ?? 'all'],
            reason: 'Published by Admin'
        );

        return back()->with('success', 'Platform announcement published to the Buyer & Portal Announcements feed!');
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

    public function deleteAnnouncement(int $id)
    {
        $ann = DB::table('platform_announcements')->where('id', $id)->first();
        if ($ann) {
            if (! empty($ann->attachment_path) && Storage::disk('public')->exists($ann->attachment_path)) {
                Storage::disk('public')->delete($ann->attachment_path);
            }
            DB::table('platform_announcements')->where('id', $id)->delete();
        }

        return back()->with('success', 'Announcement deleted.');
    }

    public function savePlatformGcashSettings(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $admin = Auth::guard('admin')->user();

        $digits = preg_replace('/\D/', '', (string) $request->input('gcash_number', ''));
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        $request->merge([
            'account_name' => trim((string) $request->input('account_name', '')),
            'gcash_number' => $digits,
        ]);

        $validated = $request->validate([
            'account_name' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'gcash_number' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'qr_image'     => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'is_active'    => ['nullable', 'boolean'],
        ], [
            'account_name.regex' => 'GCash Account Name may only contain letters, spaces, hyphens, and periods.',
            'gcash_number.regex' => 'Please enter a valid 11-digit Philippine GCash mobile number starting with 09.',
        ]);

        $existing = DB::table('platform_gcash_settings')->orderByDesc('id')->first();
        $qrPath = $existing->qr_image_path ?? null;

        if ($request->hasFile('qr_image')) {
            $file = $request->file('qr_image');
            $filename = 'platform_gcash_qr/storkia_admin_gcash_' . uniqid('', true) . '.webp';

            try {
                $manager = new ImageManager(new Driver());
                $encoded = $manager->read($file->getRealPath())->scaleDown(1000, 1000)->toWebp(86);
                Storage::disk('public')->put($filename, (string) $encoded);
                $qrPath = $filename;
            } catch (\Throwable $e) {
                $qrPath = $file->store('platform_gcash_qr', 'public');
            }
        }

        $payload = [
            'account_name'        => $validated['account_name'],
            'gcash_number'        => $validated['gcash_number'],
            'qr_image_path'       => $qrPath,
            'is_active'           => (bool) ($validated['is_active'] ?? true),
            'instructions'        => $validated['instructions'] ?? 'Send the exact checkout total to Storkia Admin Escrow GCash and upload your receipt screenshot.',
            'updated_by_admin_id' => $admin?->id,
            'updated_at'          => now(),
        ];

        if ($existing) {
            DB::table('platform_gcash_settings')->where('id', $existing->id)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('platform_gcash_settings')->insert($payload);
        }

        AuditLoggerService::log(
            action: 'platform_gcash_escrow_updated',
            targetType: 'PlatformGcashSetting',
            targetId: (int) ($existing->id ?? 1),
            targetReference: $validated['gcash_number'],
            newValues: [
                'account_name' => $validated['account_name'],
                'gcash_number' => $validated['gcash_number'],
                'is_active'    => (bool) ($validated['is_active'] ?? true),
            ],
            reason: 'Updated centralized Admin GCash Escrow account'
        );

        return back()->with('success', 'Centralized Storkia Admin GCash Escrow account saved! All Buyer online GCash checkouts now route through this account.');
    }

    public function removePlatformGcashQr()
    {
        $existing = DB::table('platform_gcash_settings')->orderByDesc('id')->first();
        if ($existing && $existing->qr_image_path) {
            if (Storage::disk('public')->exists($existing->qr_image_path)) {
                Storage::disk('public')->delete($existing->qr_image_path);
            }
            DB::table('platform_gcash_settings')->where('id', $existing->id)->update([
                'qr_image_path' => null,
                'updated_at'    => now(),
            ]);
        }

        return back()->with('success', 'Platform GCash QR Code image removed.');
    }

    public function auditLogsIndex(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $actorType = (string) $request->query('actor_type', 'all');
        $actorFilter = $actorType;
        $actionFilter = (string) $request->query('action_type', 'all');
        $ipSearch = trim((string) $request->query('ip', ''));
        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));
        $search = trim((string) $request->query('search', ''));

        $distinctActions = Schema::hasTable('platform_audit_logs')
            ? PlatformAuditLog::select('action')->distinct()->orderBy('action')->pluck('action')->all()
            : [];

        $query = PlatformAuditLog::orderByDesc('occurred_at')->orderByDesc('id');

        if ($actorType !== 'all' && $actorType !== '') {
            $query->where('actor_type', $actorType);
        }

        if ($actionFilter !== 'all' && $actionFilter !== '') {
            $query->where('action', $actionFilter);
        }

        if ($ipSearch !== '') {
            $query->where('ip_address', 'like', "%{$ipSearch}%");
        }

        if ($fromDate !== '') {
            try {
                $query->where('occurred_at', '>=', Carbon::parse($fromDate)->startOfDay());
            } catch (\Throwable $e) {
            }
        }

        if ($toDate !== '') {
            try {
                $query->where('occurred_at', '<=', Carbon::parse($toDate)->endOfDay());
            } catch (\Throwable $e) {
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('actor_name', 'like', "%{$search}%")
                  ->orWhere('target_reference', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $logs = Schema::hasTable('platform_audit_logs')
            ? $query->paginate(25)->withQueryString()
            : collect();

        return view()->first(
            ['admin.logs.index', 'admin.audit-logs.index'],
            compact(
                'logs',
                'actorType',
                'actorFilter',
                'distinctActions',
                'actionFilter',
                'ipSearch',
                'fromDate',
                'toDate',
                'search'
            )
        );
    }

    public function formalPdfReport(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $verifierName = trim((string) ($admin?->name ?: 'Storkia System Administrator'));
        $roleScope = (string) $request->query('role', 'Admin');
        $entityId = (int) $request->query('entity_id', (int) ($admin?->id ?? 1));
        $entityName = (string) $request->query('entity_name', 'Storkia Marketplace Platform');

        $report = $this->formalReportService->buildRoleReportPayload(
            role: $roleScope,
            entityId: $entityId,
            entityName: $entityName,
            request: $request
        );

        if ($roleScope === 'Admin') {
            $report['verifier_name'] = $verifierName;
            $report['account_holder_name'] = $verifierName;
            $report['business_name'] = 'Storkia E-Commerce Platform';
        }

        return view('reports.formal-pdf-report', compact('report'));
    }
}