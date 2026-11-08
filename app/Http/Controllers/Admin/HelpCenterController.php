<?php

namespace App\Http\Controllers\Admin;

use App\Events\SupportMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Support\HelpCenterController as MemberHelpCenter;
use App\Models\Admin;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Notifications\SupportReplyNotification;
use App\Services\AuditLoggerService;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Help Center, support side: a shared inbox for every signed-in admin.
 *
 * Admins only ANSWER conversations that members opened (there is no "new
 * conversation" action). Each conversation can be marked Resolved / Open.
 *
 *   GET   /admin/help-center                          inbox page
 *   GET   /admin/help-center/conversations            inbox rows (JSON, filterable)
 *   GET   /admin/help-center/{conversation}/messages  thread (JSON)
 *   POST  /admin/help-center/{conversation}/messages  reply
 *   PATCH /admin/help-center/{conversation}/read      mark the member's messages as read
 *   PATCH /admin/help-center/{conversation}/status    resolve / reopen
 */
class HelpCenterController extends Controller
{
    private const INBOX_LIMIT = 200;

    public function __construct(
        private ChatPresenter $presenter,
        private ChatAttachmentService $attachments,
    ) {
    }

    public function index(Request $request)
    {
        $admin = $this->admin();

        $contacts = $this->presenter->supportContacts(
            $this->inboxQuery($request)->limit(self::INBOX_LIMIT)->get()
        );

        $chatConfig = [
            'mode'               => 'support-admin',
            'authId'             => $admin->id,
            'selfKey'            => 'a:' . $admin->id,
            'endpoints'          => [
                'contacts'  => route('admin.help-center.conversations'),
                'messages'  => $this->url('admin.help-center.messages'),
                'send'      => $this->url('admin.help-center.store'),
                'read'      => $this->url('admin.help-center.read'),
                'shipments' => null,
                'status'    => $this->url('admin.help-center.status'),
            ],
            'realtime'           => [
                'channel' => 'support.admin',
                'event'   => '.SupportMessageSent',
            ],
            'requestedContactId' => $request->filled('conversation') ? (int) $request->query('conversation') : null,
            'draft'              => null,
            'serverSearch'       => true,
            'contacts'           => $contacts->all(),
            'labels'             => [
                'title'       => 'Help Center',
                'countNoun'   => 'Conversations',
                'search'      => 'Search name, store or email...',
                'emptyList'   => 'No conversations match',
                'emptyTitle'  => 'Help Center Inbox',
                'emptyHint'   => 'Members write in from their Help Center page. Select a conversation to reply.',
                'placeholder' => 'Reply to the member (max 1,000 chars)...',
            ],
            'groups'             => [
                ['key' => 'all', 'label' => 'All'],
                ['key' => 'buyer', 'label' => 'Customers'],
                ['key' => 'seller', 'label' => 'Sellers'],
                ['key' => 'logistics', 'label' => 'Logistics'],
            ],
            'statusFilters'      => [
                ['key' => 'open', 'label' => 'Open'],
                ['key' => 'resolved', 'label' => 'Resolved'],
                ['key' => 'all', 'label' => 'All'],
            ],
            'defaultStatus'      => 'open',
            'quickReplies'       => [
                'default' => [
                    'Thanks for reaching out to Storkia Support. How can we help?',
                    "We're looking into this and will update you shortly.",
                    'Could you share a screenshot or your order / tracking number?',
                    'This has been resolved. Please let us know if you need anything else.',
                ],
            ],
            'allowShipment'      => false,
        ];

        return view('admin.help-center.index', compact('chatConfig'));
    }

    /**
     * Inbox rows. Optional filters: status (open|resolved), role (Buyer|Seller|Logistics),
     * q (member name, email, store or hub name).
     */
    public function conversations(Request $request): JsonResponse
    {
        $rows = $this->presenter->supportContacts(
            $this->inboxQuery($request)->limit(self::INBOX_LIMIT)->get()
        );

        return response()->json($rows);
    }

    /**
     * One thread. Opening it marks the member's messages as read for the whole team.
     */
    public function messages(SupportConversation $conversation): JsonResponse
    {
        $conversation->messages()->unreadByAdmin()->update(['is_read' => true, 'read_at' => now()]);

        $rows = SupportMessage::where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'messages' => $this->presenter->supportMessages($rows, 'admin'),
            'status'   => $conversation->status,
            'contact'  => $this->presenter->supportContact($conversation),
        ]);
    }

    public function markAsRead(SupportConversation $conversation): JsonResponse
    {
        $conversation->messages()->unreadByAdmin()->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['status' => 'success', 'success' => true]);
    }

    public function store(Request $request, SupportConversation $conversation): JsonResponse
    {
        $admin = $this->admin();

        $request->validate([
            'message'    => ['nullable', 'string', 'max:1000'],
            'attachment' => ChatAttachmentService::rules(),
        ], ChatAttachmentService::validationMessages());

        $text = trim((string) $request->input('message', ''));

        if ($text === '' && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'message' => 'Please enter a message or attach an image (JPG, PNG, or WEBP).',
            ]);
        }

        $attachmentPath = $request->hasFile('attachment')
            ? $this->attachments->store($request->file('attachment'), 'admin_' . $admin->id, 'support_attachments')
            : null;

        // Alert the member only when their previous reply is still unread, so a
        // burst of messages produces one notification instead of many.
        $memberAlreadyAlerted = $conversation->messages()->unreadByUser()->exists();

        $message = DB::transaction(function () use ($conversation, $admin, $text, $attachmentPath) {
            $message = SupportMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => SupportMessage::SENDER_ADMIN,
                'sender_admin_id' => $admin->id,
                'message'         => $text !== '' ? $text : ChatPresenter::PLACEHOLDER_PHOTO,
                'attachment_path' => $attachmentPath,
                'is_read'         => false,
            ]);

            // Answering means the member's messages were seen.
            $conversation->messages()->unreadByAdmin()->update(['is_read' => true, 'read_at' => now()]);
            $conversation->forceFill(['last_message_at' => now()])->save();

            return $message;
        });

        if (! $memberAlreadyAlerted) {
            $this->notifyMember($conversation);
        }

        SupportMessageSent::dispatchToAll($message);

        return response()->json($this->presenter->supportMessage($message, 'admin'), 201);
    }

    public function updateStatus(Request $request, SupportConversation $conversation): JsonResponse
    {
        $admin = $this->admin();

        $validated = $request->validate([
            'status' => ['required', Rule::in([SupportConversation::STATUS_OPEN, SupportConversation::STATUS_RESOLVED])],
        ]);

        $old = $conversation->status;
        $new = $validated['status'];

        if ($old !== $new) {
            $conversation->forceFill($new === SupportConversation::STATUS_RESOLVED
                ? [
                    'status'               => $new,
                    'resolved_at'          => now(),
                    'resolved_by_admin_id' => $admin->id,
                ]
                : [
                    'status'               => $new,
                    'resolved_at'          => null,
                    'resolved_by_admin_id' => null,
                ])->save();

            AuditLoggerService::log(
                action: $new === SupportConversation::STATUS_RESOLVED ? 'help_center_resolved' : 'help_center_reopened',
                targetType: 'SupportConversation',
                targetId: (int) $conversation->id,
                targetReference: 'Help Center #' . $conversation->id,
                oldValues: ['status' => $old],
                newValues: ['status' => $new]
            );
        }

        return response()->json([
            'status'  => $conversation->status,
            'contact' => $this->presenter->supportContact($conversation->fresh()),
        ]);
    }

    // ------------------------------------------------------------------

    private function inboxQuery(Request $request): Builder
    {
        $query = SupportConversation::query()->whereHas('user');

        $status = (string) $request->query('status', '');
        if (in_array($status, [SupportConversation::STATUS_OPEN, SupportConversation::STATUS_RESOLVED], true)) {
            $query->where('status', $status);
        }

        $role = (string) $request->query('role', '');
        if (in_array($role, ['Buyer', 'Seller', 'Logistics'], true)) {
            $query->whereHas('user', fn (Builder $u) => $u->where('role', $role));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';

            $query->whereHas('user', function (Builder $u) use ($like) {
                $u->where(function (Builder $w) use ($like) {
                    $w->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('sellerProfile', fn (Builder $s) => $s->where('business_name', 'like', $like))
                        ->orWhereHas('ownedLogisticsProfile', fn (Builder $l) => $l->where('business_name', 'like', $like));
                });
            });
        }

        return $query
            ->orderByRaw('last_message_at IS NULL')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    private function notifyMember(SupportConversation $conversation): void
    {
        try {
            $member = $conversation->user;
            $prefix = $member ? (MemberHelpCenter::ROUTE_PREFIX[$member->role] ?? null) : null;

            if ($member && $prefix) {
                $member->notify(new SupportReplyNotification(route($prefix . '.index')));
            }
        } catch (\Throwable $e) {
            // A failed alert must never block the reply itself.
        }
    }

    /**
     * Route URL with a "{id}" placeholder the page script fills in.
     */
    private function url(string $routeName): string
    {
        return str_replace('__ID__', '{id}', route($routeName, ['conversation' => '__ID__']));
    }

    private function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }
}