<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    protected const DEFAULT_PER_PAGE = 20;

    protected const MAX_PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min(max((int) $request->query('per_page', self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);

        $query = $request->query('filter') === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => collect($page->items())->map(fn (DatabaseNotification $n) => $this->format($n))->values(),
            'meta'    => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data'    => $this->format($notification->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $marked = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'data'    => ['marked' => (int) $marked, 'unread_count' => 0],
        ]);
    }

    protected function format(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;

        return [
            'id'               => $notification->id,
            'type'             => (string) ($data['type'] ?? 'info'),
            'title'            => (string) ($data['title'] ?? 'Notification'),
            'message'          => (string) ($data['message'] ?? ''),
            'url'              => $data['url'] ?? null,
            'target'           => $this->target($data),
            'is_read'          => $notification->read_at !== null,
            'read_at'          => $notification->read_at?->toISOString(),
            'created_at'       => $notification->created_at?->toISOString(),
            'created_at_human' => $notification->created_at?->diffForHumans(),
        ];
    }

    protected function target(array $data): array
    {
        $path = (string) (parse_url((string) ($data['url'] ?? ''), PHP_URL_PATH) ?? '');

        if (! empty($data['seller_order_id'])) {
            return ['type' => 'order', 'id' => (int) $data['seller_order_id']];
        }

        if (! empty($data['report_id'])) {
            return ['type' => 'seller_report', 'id' => (int) $data['report_id']];
        }

        if (! empty($data['verification_id'])) {
            return ['type' => 'identity_verification', 'id' => null];
        }

        if (preg_match('#/orders/(\d+)(?:/|$)#', $path, $match)) {
            return ['type' => 'order', 'id' => (int) $match[1]];
        }

        if (str_contains($path, 'help-center')) {
            return ['type' => 'help_center', 'id' => null];
        }

        if (preg_match('#/points/?$#', $path)) {
            return ['type' => 'rewards', 'id' => null];
        }

        return ['type' => 'none', 'id' => null];
    }
}