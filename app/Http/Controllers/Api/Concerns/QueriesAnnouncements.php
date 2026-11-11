<?php

namespace App\Http\Controllers\Api\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait QueriesAnnouncements
{
    protected function announcementCategories(): array
    {
        return ['announcement', 'policy_update', 'promotion', 'maintenance'];
    }

    protected function buyerAnnouncementsQuery(?string $category = null): ?Builder
    {
        if (! Schema::hasTable('platform_announcements')) {
            return null;
        }

        $query = DB::table('platform_announcements')
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (Schema::hasColumn('platform_announcements', 'target_audience')) {
            $query->whereIn('target_audience', ['all', 'buyers']);
        }

        if ($category !== null && in_array($category, $this->announcementCategories(), true)) {
            $query->where('category', $category);
        }

        return $query;
    }

    protected function buyerReadAnnouncementIds(int $userId): array
    {
        if (! Schema::hasTable('buyer_announcement_reads')) {
            return [];
        }

        return DB::table('buyer_announcement_reads')
            ->where('user_id', $userId)
            ->pluck('announcement_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function unreadAnnouncementCount(array $readIds): int
    {
        $query = $this->buyerAnnouncementsQuery();

        if (! $query) {
            return 0;
        }

        return $query->pluck('id')
            ->reject(fn ($id) => in_array((int) $id, $readIds, true))
            ->count();
    }

    protected function formatAnnouncement(object $row, array $readIds): array
    {
        $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;

        return [
            'id'              => (int) $row->id,
            'title'           => $row->title,
            'body'            => $row->body,
            'category'        => $row->category,
            'target_audience' => $row->target_audience ?? 'all',
            'attachment_url'  => ! empty($row->attachment_path) ? asset('storage/' . ltrim($row->attachment_path, '/')) : null,
            'is_read'         => in_array((int) $row->id, $readIds, true),
            'created_at'      => $createdAt?->toIso8601String(),
            'created_label'   => $createdAt?->format('M d, Y h:i A'),
        ];
    }
}