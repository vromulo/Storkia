<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsStorefrontProducts;
use App\Http\Controllers\Api\Concerns\QueriesAnnouncements;
use App\Http\Controllers\Controller;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnnouncementController extends Controller
{
    use FormatsStorefrontProducts;
    use QueriesAnnouncements;

    public function index(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $categoryParam = $request->query('category', 'all');
        $category = is_string($categoryParam) && in_array($categoryParam, $this->announcementCategories(), true)
            ? $categoryParam
            : 'all';

        $userId = (int) $request->user()->id;
        $readIds = $this->buyerReadAnnouncementIds($userId);

        $query = $this->buyerAnnouncementsQuery($category === 'all' ? null : $category);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $items = [];
        $pagination = [
            'current_page' => 1,
            'last_page'    => 1,
            'per_page'     => $perPage,
            'total'        => 0,
        ];

        if ($query) {
            $paginator = $query->paginate($perPage);

            $items = $paginator->getCollection()
                ->map(fn ($row) => $this->formatAnnouncement($row, $readIds))
                ->values()
                ->all();

            $pagination = [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'announcements' => $items,
                'unread_count'  => $this->unreadAnnouncementCount($readIds),
                'category'      => $category,
                'categories'    => $this->announcementCategories(),
                'pagination'    => $pagination,
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $readIds = $this->buyerReadAnnouncementIds((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'unread_count' => $this->unreadAnnouncementCount($readIds),
            ],
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $query = $this->buyerAnnouncementsQuery();

        if (! $query || ! Schema::hasTable('buyer_announcement_reads') || ! $query->where('id', $id)->exists()) {
            return $this->storefrontFailure('This announcement is no longer available.', 404, 'announcement_not_found');
        }

        $userId = (int) $request->user()->id;

        DB::table('buyer_announcement_reads')->updateOrInsert(
            [
                'announcement_id' => $id,
                'user_id'         => $userId,
            ],
            [
                'read_at'    => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcement marked as read.',
            'data'    => [
                'unread_count' => $this->unreadAnnouncementCount($this->buyerReadAnnouncementIds($userId)),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $query = $this->buyerAnnouncementsQuery();

        if (! $query || ! Schema::hasTable('buyer_announcement_reads')) {
            return response()->json([
                'success' => true,
                'message' => 'All platform announcements marked as read.',
                'data'    => ['unread_count' => 0],
            ]);
        }

        $userId = (int) $request->user()->id;
        $readIds = $this->buyerReadAnnouncementIds($userId);

        $now = now();
        $rows = $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => in_array($id, $readIds, true))
            ->map(fn ($id) => [
                'announcement_id' => $id,
                'user_id'         => $userId,
                'read_at'         => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ])
            ->values()
            ->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('buyer_announcement_reads')->insertOrIgnore($chunk);
        }

        return response()->json([
            'success' => true,
            'message' => 'All platform announcements marked as read.',
            'data'    => ['unread_count' => 0],
        ]);
    }
}