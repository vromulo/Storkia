<?php

namespace App\Http\Controllers\Seller;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SellerMessageController extends Controller
{
    private function touchSellerOnlineStatus(?User $user): void
    {
        if (! $user || ! Schema::hasColumn('users', 'last_seen_at')) {
            return;
        }

        $user->forceFill([
            'is_online'    => true,
            'last_seen_at' => now(),
        ])->saveQuietly();
    }

    private function getContactsList($userId)
    {
        $contactIds = Message::where('user_id', $userId)->pluck('receiver_id')
            ->merge(Message::where('receiver_id', $userId)->pluck('user_id'))
            ->unique();

        $contacts = User::whereIn('id', $contactIds)->get();

        $contacts = $contacts->map(function (User $contact) use ($userId) {
            $latestMessage = Message::where(function ($q) use ($userId, $contact) {
                $q->where('user_id', $userId)->where('receiver_id', $contact->id);
            })->orWhere(function ($q) use ($userId, $contact) {
                $q->where('user_id', $contact->id)->where('receiver_id', $userId);
            })->latest()->first();

            $contact->latest_message_at = $latestMessage ? $latestMessage->created_at : null;
            $contact->latest_message_preview = $latestMessage
                ? ($latestMessage->message ?: ($latestMessage->attachment_path ? '[Photo Attachment]' : '[Product Inquiry]'))
                : 'Customer inquiry';

            $contact->unread_count = Message::where('user_id', $contact->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            $isRecentlyActive = $contact->last_seen_at && $contact->last_seen_at->gt(now()->subMinutes(5));
            $contact->online_status = ($contact->is_online || $isRecentlyActive) ? 'online' : 'offline';
            $contact->last_seen_label = $contact->last_seen_at
                ? 'Active ' . $contact->last_seen_at->diffForHumans()
                : 'Offline';

            return $contact;
        })->sortByDesc('latest_message_at')->values();

        return $contacts;
    }

    public function index(Request $request)
    {
        $this->touchSellerOnlineStatus($request->user());
        $contacts = $this->getContactsList(auth()->id());

        return view('seller.messages.seller-messages', compact('contacts'));
    }

    public function fetchContacts(Request $request)
    {
        $this->touchSellerOnlineStatus($request->user());
        $contacts = $this->getContactsList(auth()->id());

        return response()->json($contacts);
    }

    public function fetchMessages($contactId)
    {
        $userId = auth()->id();
        $this->touchSellerOnlineStatus(auth()->user());

        Message::where('user_id', $contactId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = Message::with('product')
            ->where(function ($query) use ($userId, $contactId) {
                $query->where('user_id', $userId)->where('receiver_id', $contactId);
            })
            ->orWhere(function ($query) use ($userId, $contactId) {
                $query->where('user_id', $contactId)->where('receiver_id', $userId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $this->touchSellerOnlineStatus($request->user());

        $rawMessage = trim((string) $request->input('message', ''));
        $request->merge(['message' => $rawMessage]);

        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'message'     => ['nullable', 'string', 'max:1000'],
            'product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'attachment'  => ['nullable', 'file', 'mimetypes:image/jpeg,image/png', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'message.max'          => 'Chat message cannot exceed 1,000 characters.',
            'attachment.mimetypes' => 'Only valid JPG and PNG image files are allowed.',
            'attachment.mimes'     => 'Only JPG and PNG image files are allowed.',
            'attachment.max'       => 'Attachment image size cannot exceed 5 MB.',
        ]);

        if ($rawMessage === '' && ! $request->hasFile('attachment') && empty($validated['product_id'])) {
            return response()->json([
                'message' => 'Please enter a message (1 to 1,000 characters) or attach a valid JPG/PNG photo.',
            ], 422);
        }

        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $realMime = $file->getMimeType();
                if (! in_array($realMime, ['image/jpeg', 'image/png'], true)) {
                    return response()->json([
                        'message' => 'Invalid image content. Only authentic JPG and PNG images up to 5 MB are allowed.',
                    ], 422);
                }
                $attachmentPath = $file->store('chat_attachments', 'public');
            }

            $payloadData = [
                'user_id'     => auth()->id(),
                'receiver_id' => (int) $validated['receiver_id'],
                'role'        => 'Seller',
                'message'     => $rawMessage !== '' ? $rawMessage : ($attachmentPath ? 'Sent a photo' : 'Product details'),
                'is_read'     => false,
            ];

            if (Schema::hasColumn('messages', 'product_id')) {
                $payloadData['product_id'] = $validated['product_id'] ?? null;
                $payloadData['attachment_path'] = $attachmentPath;
            }

            $message = Message::create($payloadData);
            $message->load('product');

            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                Log::error('Broadcast failed: ' . $e->getMessage());
            }

            return response()->json($message);
        } catch (\Throwable $e) {
            Log::error('Message Store Error: ' . $e->getMessage());
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function markAsRead($contactId)
    {
        Message::where('user_id', $contactId)
            ->where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}