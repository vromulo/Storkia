<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ApiMessageController extends Controller
{
    public function contacts(Request $request)
    {
        $userId = $request->user()->id;

        $sentTo = Message::where('user_id', $userId)->pluck('receiver_id');
        $receivedFrom = Message::where('receiver_id', $userId)->pluck('user_id');

        $contactIds = $sentTo->merge($receivedFrom)->unique()->filter();

        $activeContactId = $request->query('contact');
        if ($activeContactId && ! $contactIds->contains($activeContactId)) {
            $contactIds->push($activeContactId);
        }

        $contacts = User::with('sellerProfile')->whereIn('id', $contactIds)->get();

        $formatted = $contacts->map(function ($contact) use ($userId) {
            $lastMessage = Message::where(function ($q) use ($userId, $contact) {
                $q->where('user_id', $userId)->where('receiver_id', $contact->id);
            })->orWhere(function ($q) use ($userId, $contact) {
                $q->where('user_id', $contact->id)->where('receiver_id', $userId);
            })->latest()->first();

            $unreadCount = Message::where('user_id', $contact->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            $displayName = $contact->sellerProfile
                ? $contact->sellerProfile->business_name
                : trim($contact->first_name . ' ' . $contact->last_name);

            $logoUrl = ($contact->sellerProfile && $contact->sellerProfile->logo_path)
                ? asset('storage/' . $contact->sellerProfile->logo_path)
                : $contact->avatar_url;

            return [
                'id'           => $contact->id,
                'name'         => $displayName,
                'role'         => $contact->role,
                'avatar_url'   => $logoUrl,
                'unread_count' => $unreadCount,
                'last_message' => $lastMessage ? [
                    'id'             => $lastMessage->id,
                    'message'        => $lastMessage->message,
                    'attachment_url' => ! empty($lastMessage->attachment_path) ? asset('storage/' . $lastMessage->attachment_path) : null,
                    'sender_id'      => $lastMessage->user_id,
                    'created_at'     => $lastMessage->created_at?->toIso8601String(),
                ] : null,
                '_sort_ts'     => $lastMessage?->created_at?->timestamp ?? 0,
            ];
        })->sortByDesc('_sort_ts')->values()->map(function ($item) {
            unset($item['_sort_ts']);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data'    => $formatted,
        ]);
    }

    public function messages(Request $request, int $contactId)
    {
        $userId = $request->user()->id;

        Message::where('user_id', $contactId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = Message::where(function ($q) use ($userId, $contactId) {
            $q->where('user_id', $userId)->where('receiver_id', $contactId);
        })->orWhere(function ($q) use ($userId, $contactId) {
            $q->where('user_id', $contactId)->where('receiver_id', $userId);
        })->orderBy('created_at', 'asc')->get()->map(function ($msg) {
            $productCard = null;
            if (! empty($msg->product_id)) {
                $prod = Product::find($msg->product_id);
                if ($prod) {
                    $firstPic = (! empty($prod->pictures) && is_array($prod->pictures))
                        ? asset('storage/' . $prod->pictures[0])
                        : null;
                    $productCard = [
                        'id'    => $prod->id,
                        'name'  => $prod->name,
                        'price' => number_format((float) $prod->price, 2),
                        'image' => $firstPic,
                    ];
                }
            }

            return [
                'id'             => $msg->id,
                'user_id'        => $msg->user_id,
                'receiver_id'    => $msg->receiver_id,
                'message'        => $msg->message,
                'product_id'     => $msg->product_id ?? null,
                'product_card'   => $productCard,
                'attachment_url' => ! empty($msg->attachment_path) ? asset('storage/' . $msg->attachment_path) : null,
                'is_read'        => (bool) $msg->is_read,
                'created_at'     => $msg->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'message'     => ['nullable', 'string', 'max:2000'],
            'product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'attachment'  => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (empty(trim((string) ($validated['message'] ?? ''))) && ! $request->hasFile('attachment') && empty($validated['product_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a message or attach an image (JPG, PNG, or WEBP).',
            ], 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = 'chat_attachments/chat_' . $request->user()->id . '_' . uniqid('', true) . '.webp';
            try {
                $manager = new ImageManager(new Driver());
                $encoded = $manager->read($file->getRealPath())->scaleDown(1600, 1600)->toWebp(82);
                Storage::disk('public')->put($filename, (string) $encoded);
                $attachmentPath = $filename;
            } catch (\Throwable $e) {
                $attachmentPath = $file->store('chat_attachments', 'public');
            }
        }

        $payload = [
            'user_id'     => $request->user()->id,
            'receiver_id' => $validated['receiver_id'],
            'message'     => trim((string) ($validated['message'] ?? '')) ?: ($attachmentPath ? '[Sent a photo]' : '[Product Inquiry]'),
            'is_read'     => false,
        ];

        if (Schema::hasColumn('messages', 'product_id')) {
            $payload['product_id'] = $validated['product_id'] ?? null;
            $payload['attachment_path'] = $attachmentPath;
        }

        $message = Message::create($payload);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $message->id,
                'user_id'        => $message->user_id,
                'receiver_id'    => $message->receiver_id,
                'message'        => $message->message,
                'product_id'     => $message->product_id ?? null,
                'attachment_url' => $attachmentPath ? asset('storage/' . $attachmentPath) : null,
                'is_read'        => (bool) $message->is_read,
                'created_at'     => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function markAsRead(Request $request, int $contactId)
    {
        Message::where('user_id', $contactId)
            ->where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read.',
        ]);
    }
}