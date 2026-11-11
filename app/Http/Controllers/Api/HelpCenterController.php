<?php

namespace App\Http\Controllers\Api;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HelpCenterController extends Controller
{
    private const MAX_MESSAGE_LENGTH = 1000;
    private const THREAD_LIMIT = 200;
    private const POLL_SECONDS = 5;

    private const QUICK_REPLIES = [
        'I need help with my order',
        'I have a payment problem',
        'I want to report a seller',
        "I can't access my account",
    ];

    public function __construct(
        private ChatPresenter $presenter,
        private ChatAttachmentService $attachments,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->presenter->touchPresence($user);
        $this->clearReplyAlerts($user);

        $conversation = SupportConversation::where('user_id', $user->id)->first();

        $unread = $conversation
            ? $conversation->messages()->unreadByUser()->count()
            : 0;
        $last = $conversation?->latestMessage;

        return response()->json([
            'success' => true,
            'data'    => [
                'title'                 => 'Storkia Support',
                'subtitle'              => 'Help Center',
                'status'                => $conversation?->status,
                'unread_count'          => $unread,
                'last_message_at'       => $conversation?->last_message_at?->toIso8601String(),
                'last_message_preview'  => $last ? $this->preview($last) : null,
                'quick_replies'         => self::QUICK_REPLIES,
                'max_message_length'    => self::MAX_MESSAGE_LENGTH,
                'max_attachment_kb'     => ChatAttachmentService::MAX_KB,
                'poll_interval_seconds' => self::POLL_SECONDS,
                'empty_title'           => 'Storkia Help Center',
                'empty_hint'            => 'Tell us what you need help with. Our support team will reply here.',
            ],
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->presenter->touchPresence($user);

        $afterId = max((int) $request->query('after_id', 0), 0);

        if ($afterId === 0) {
            $this->clearReplyAlerts($user);
        }

        $conversation = SupportConversation::where('user_id', $user->id)->first();

        if (! $conversation) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'messages'     => [],
                    'status'       => null,
                    'unread_count' => 0,
                ],
            ]);
        }

        $conversation->messages()->unreadByUser()->update(['is_read' => true, 'read_at' => now()]);

        $query = SupportMessage::where('conversation_id', $conversation->id);

        $rows = $afterId > 0
            ? $query->where('id', '>', $afterId)->orderBy('id')->limit(self::THREAD_LIMIT)->get()
            : $query->orderByDesc('id')->limit(self::THREAD_LIMIT)->get()->reverse()->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'messages'     => $this->presenter->supportMessages($rows, 'user'),
                'status'       => $conversation->status,
                'unread_count' => 0,
            ],
        ]);
    }

    public function markAsRead(Request $request): JsonResponse
    {
        $conversation = SupportConversation::where('user_id', $request->user()->id)->first();

        $conversation?->messages()->unreadByUser()->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as read.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'message'    => ['nullable', 'string', 'max:' . self::MAX_MESSAGE_LENGTH],
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

        return response()->json([
            'success' => true,
            'data'    => $this->presenter->supportMessage($message, 'user'),
        ], 201);
    }

    protected function clearReplyAlerts(User $user): void
    {
        $user->unreadNotifications()
            ->where('type', SupportReplyNotification::class)
            ->update(['read_at' => now()]);
    }

    protected function preview(SupportMessage $last): string
    {
        $text = trim((string) $last->message);
        $isPhoto = $text === '' || $text === ChatPresenter::PLACEHOLDER_PHOTO;

        return ($last->isFromAdmin() ? 'Support: ' : 'You: ') . ($isPhoto ? 'Sent a photo' : $text);
    }
}