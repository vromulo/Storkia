<?php

namespace App\Http\Controllers\Buyer;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BuyerMessageController extends Controller
{
    private function touchUserOnlineStatus(?User $user): void
    {
        if (! $user || ! Schema::hasColumn('users', 'last_seen_at')) {
            return;
        }

        $user->forceFill([
            'is_online'    => true,
            'last_seen_at' => now(),
        ])->saveQuietly();
    }

    private function formatContactPayload(User $contact, int $userId): User
    {
        $contact->loadMissing('sellerProfile');

        $latestMessage = Message::where(function ($q) use ($userId, $contact) {
            $q->where('user_id', $userId)->where('receiver_id', $contact->id);
        })->orWhere(function ($q) use ($userId, $contact) {
            $q->where('user_id', $contact->id)->where('receiver_id', $userId);
        })->latest()->first();

        $contact->latest_message_at = $latestMessage ? $latestMessage->created_at : null;
        $contact->latest_message_preview = $latestMessage
            ? ($latestMessage->message ?: ($latestMessage->attachment_path ? '[Photo Attachment]' : '[Product Inquiry]'))
            : 'Tap to start chatting';

        $contact->unread_count = Message::where('user_id', $contact->id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();

        $contact->display_name = $contact->sellerProfile?->business_name
            ?: trim($contact->first_name . ' ' . $contact->last_name);

        $contact->logo_url = $contact->sellerProfile?->logo_path
            ? asset('storage/' . $contact->sellerProfile->logo_path)
            : null;

        $isRecentlyActive = $contact->last_seen_at && $contact->last_seen_at->gt(now()->subMinutes(5));
        $contact->online_status = ($contact->is_online || $isRecentlyActive) ? 'online' : 'offline';
        $contact->last_seen_label = $contact->last_seen_at
            ? 'Active ' . $contact->last_seen_at->diffForHumans()
            : 'Offline';
        $contact->is_on_vacation = (bool) ($contact->sellerProfile?->is_on_vacation ?? false);

        return $contact;
    }

    private function getContactsList($userId, $requestedContactId = null)
    {
        $contactIds = Message::where('user_id', $userId)->pluck('receiver_id')
            ->merge(Message::where('receiver_id', $userId)->pluck('user_id'))
            ->unique();

        $contacts = User::with('sellerProfile')
            ->whereIn('id', $contactIds)
            ->where('role', 'Seller')
            ->get()
            ->map(fn (User $c) => $this->formatContactPayload($c, (int) $userId))
            ->sortByDesc('latest_message_at')
            ->values();

        if ($requestedContactId) {
            $contactId = (int) $requestedContactId;
            if (! $contacts->contains('id', $contactId)) {
                $newContact = User::with('sellerProfile')->find($contactId);
                if ($newContact && $newContact->role === 'Seller') {
                    $formatted = $this->formatContactPayload($newContact, (int) $userId);
                    $formatted->latest_message_at = now();
                    $contacts->prepend($formatted);
                }
            }
        }

        return $contacts;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->touchUserOnlineStatus($user);

        $requestedContactId = $request->input('contact');
        $requestedProductId = $request->input('product_id');

        $draftProduct = null;
        if ($requestedProductId) {
            $product = Product::with('user.sellerProfile')->find($requestedProductId);
            if ($product) {
                if (! $requestedContactId && $product->user_id) {
                    $requestedContactId = $product->user_id;
                }

                $firstPic = (! empty($product->pictures) && is_array($product->pictures))
                    ? asset('storage/' . $product->pictures[0])
                    : null;

                $basePrice = (float) ($product->price ?? 0);
                $discount = (float) ($product->discount ?? 0);
                $effectivePrice = $discount > 0
                    ? round($basePrice - ($basePrice * ($discount / 100)), 2)
                    : round($basePrice, 2);

                $draftProduct = [
                    'id'              => $product->id,
                    'name'            => $product->name,
                    'price'           => $basePrice,
                    'effective_price' => $effectivePrice,
                    'discount'        => $discount,
                    'stock_quantity'  => (int) ($product->stock_quantity ?? 0),
                    'image_url'       => $firstPic,
                    'url'             => route('product.show', $product->id),
                ];
            }
        }

        $contacts = $this->getContactsList($user->id, $requestedContactId);

        return view('buyer.messages.buyer-messages', compact(
            'contacts',
            'draftProduct',
            'requestedContactId'
        ));
    }

    public function fetchContacts(Request $request)
    {
        $this->touchUserOnlineStatus($request->user());
        $contacts = $this->getContactsList(auth()->id());

        return response()->json($contacts);
    }

    public function fetchMessages($contactId)
    {
        $userId = auth()->id();
        $this->touchUserOnlineStatus(auth()->user());

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
        $this->touchUserOnlineStatus($request->user());

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
                'role'        => 'Buyer',
                'message'     => $rawMessage !== '' ? $rawMessage : ($attachmentPath ? 'Sent a photo' : 'Product inquiry'),
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