<?php

namespace App\Http\Controllers\Support;

use App\Events\SupportMessageSent;
use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportReplyNotification;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Help Center, member side: a Buyer, Seller or Logistics account chatting with
 * the Storkia support team.
 *
 * Every member has ONE running conversation. It is created the first time they
 * write in (admins never start conversations) and reopened automatically when
 * they write again after it was marked resolved.
 *
 * The same controller serves the three portals; only the route name prefix and
 * the page layout change (see ROUTE_PREFIX / LAYOUTS).
 */
class HelpCenterController extends Controller
{
    /** Route name prefix per role: "{prefix}.index|messages|store|read". */
    public const ROUTE_PREFIX = [
        'Buyer'     => 'user.help-center',
        'Seller'    => 'seller.help-center',
        'Logistics' => 'logistics.help-center',
    ];

    private const LAYOUTS = [
        'Buyer'     => 'layouts.app',
        'Seller'    => 'layouts.seller',
        'Logistics' => 'layouts.logistics',
    ];

    private const QUICK_REPLIES = [
        'Buyer' => [
            'I need help with my order',
            'I have a payment problem',
            'I want to report a seller',
            "I can't access my account",
        ],
        'Seller' => [
            'I have a question about my payout',
            'My product was disapproved',
            'I need help with a dispute',
            "I can't update my store settings",
        ],
        'Logistics' => [
            'I need help with a shipment issue',
            'I have a COD remittance question',
            'I need help with a rider or courier account',
            "I can't update my hub settings",
        ],
    ];

    public function __construct(
        private ChatPresenter $presenter,
        private ChatAttachmentService $attachments,
    ) {
    }

    public function index(Request $request)
    {
        $user = $this->member();
        $prefix = self::ROUTE_PREFIX[$user->role];

        $this->presenter->touchPresence($user);

        // The member is looking at the thread now: clear the "support replied" alerts.
        $user->unreadNotifications()
            ->where('type', SupportReplyNotification::class)
            ->update(['read_at' => now()]);

        $conversation = SupportConversation::where('user_id', $user->id)->first();

        $unread = $conversation
            ? $conversation->messages()->unreadByUser()->count()
            : 0;
        $last = $conversation?->latestMessage;

        $chatConfig = [
            'mode'               => 'support-user',
            'single'             => true,
            'authId'             => $user->id,
            'selfKey'            => 'u:' . $user->id,
            'endpoints'          => [
                'contacts'  => null,
                'messages'  => route($prefix . '.messages'),
                'send'      => route($prefix . '.store'),
                'read'      => route($prefix . '.read'),
                'shipments' => null,
                'status'    => null,
            ],
            'realtime'           => [
                'channel' => 'support.user.' . $user->id,
                'event'   => '.SupportMessageSent',
            ],
            'requestedContactId' => 'support',
            'draft'              => null,
            'supportStatus'      => $conversation?->status,
            'contacts'           => [[
                'id'                     => 'support',
                'display_name'           => 'Storkia Support',
                'role_label'             => 'Help Center',
                'group'                  => 'support',
                'logo_url'               => null,
                'is_on_vacation'         => false,
                'online_status'          => 'offline',
                'last_seen_label'        => 'Storkia Help Center',
                'unread_count'           => $unread,
                'latest_message_at'      => $conversation?->last_message_at?->toIso8601String(),
                'latest_message_preview' => $last
                    ? ($last->isFromAdmin() ? 'Support: ' : 'You: ')
                        . (($last->message === '' || $last->message === ChatPresenter::PLACEHOLDER_PHOTO) ? 'Sent a photo' : $last->message)
                    : null,
            ]],
            'labels'             => [
                'title'           => 'Help Center',
                'countNoun'       => 'Conversation',
                'search'          => '',
                'emptyList'       => '',
                'emptyTitle'      => 'Storkia Help Center',
                'emptyHint'       => 'Tell us what you need help with. Our support team will reply here.',
                'placeholder'     => 'Describe your concern (max 1,000 chars)...',
                'defaultSubtitle' => 'Storkia Help Center',
            ],
            'groups'             => [],
            'quickReplies'       => ['default' => self::QUICK_REPLIES[$user->role]],
            'allowShipment'      => false,
        ];

        return view('support.help-center', [
            'chatConfig' => $chatConfig,
            'layout'     => self::LAYOUTS[$user->role],
        ]);
    }

    /**
     * The member's thread. Opening it marks the support team's replies as read.
     */
    public function messages(): JsonResponse
    {
        $user = $this->member();
        $this->presenter->touchPresence($user);

        $conversation = SupportConversation::where('user_id', $user->id)->first();
        if (! $conversation) {
            return response()->json(['messages' => [], 'status' => null]);
        }

        $conversation->messages()->unreadByUser()->update(['is_read' => true, 'read_at' => now()]);

        $rows = SupportMessage::where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'messages' => $this->presenter->supportMessages($rows, 'user'),
            'status'   => $conversation->status,
        ]);
    }

    public function markAsRead(): JsonResponse
    {
        $conversation = SupportConversation::where('user_id', $this->member()->id)->first();

        $conversation?->messages()->unreadByUser()->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['status' => 'success', 'success' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->member();

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
            ? $this->attachments->store($request->file('attachment'), (string) $user->id, 'support_attachments')
            : null;

        $message = DB::transaction(function () use ($user, $text, $attachmentPath) {
            $conversation = SupportConversation::firstOrCreate(
                ['user_id' => $user->id],
                ['status' => SupportConversation::STATUS_OPEN]
            );

            // Writing again after "resolved" opens the thread back up for the team.
            $conversation->reopen();

            $message = SupportMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => SupportMessage::SENDER_USER,
                'sender_user_id'  => $user->id,
                'message'         => $text !== '' ? $text : ChatPresenter::PLACEHOLDER_PHOTO,
                'attachment_path' => $attachmentPath,
                'is_read'         => false,
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            return $message;
        });

        SupportMessageSent::dispatchToAll($message);

        return response()->json($this->presenter->supportMessage($message, 'user'), 201);
    }

    private function member(): User
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        abort_unless($user && isset(self::ROUTE_PREFIX[$user->role]), 403, 'The Help Center is only available to Buyer, Seller and Logistics accounts.');

        return $user;
    }
}