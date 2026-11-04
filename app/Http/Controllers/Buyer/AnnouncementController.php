<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $categoryFilter = $request->query('category', 'all');
        $user = Auth::user();

        $query = DB::table('platform_announcements')
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (Schema::hasColumn('platform_announcements', 'target_audience')) {
            $query->whereIn('target_audience', ['all', 'buyers']);
        }

        if ($categoryFilter !== 'all' && in_array($categoryFilter, ['announcement', 'policy_update', 'promotion', 'maintenance'], true)) {
            $query->where('category', $categoryFilter);
        }

        $announcements = Schema::hasTable('platform_announcements')
            ? collect($query->get())
            : collect();

        $readIds = [];
        if ($user && Schema::hasTable('buyer_announcement_reads')) {
            $readIds = DB::table('buyer_announcement_reads')
                ->where('user_id', $user->id)
                ->pluck('announcement_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $unreadCount = $user
            ? $announcements->reject(fn ($a) => in_array((int) $a->id, $readIds, true))->count()
            : 0;

        return view('buyer.announcements.index', compact(
            'announcements',
            'readIds',
            'unreadCount',
            'categoryFilter'
        ));
    }

    public function markAsRead(Request $request, int $id)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        if (! $user || ! Schema::hasTable('buyer_announcement_reads')) {
            return back();
        }

        DB::table('buyer_announcement_reads')->updateOrInsert(
            [
                'announcement_id' => $id,
                'user_id'         => $user->id,
            ],
            [
                'read_at'    => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Announcement marked as read.');
    }

    public function markAllAsRead(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        if (! $user || ! Schema::hasTable('platform_announcements') || ! Schema::hasTable('buyer_announcement_reads')) {
            return back();
        }

        $activeIds = DB::table('platform_announcements')
            ->where('is_active', true)
            ->pluck('id');

        foreach ($activeIds as $annId) {
            DB::table('buyer_announcement_reads')->updateOrInsert(
                [
                    'announcement_id' => (int) $annId,
                    'user_id'         => $user->id,
                ],
                [
                    'read_at'    => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return back()->with('success', 'All platform announcements marked as read.');
    }
}