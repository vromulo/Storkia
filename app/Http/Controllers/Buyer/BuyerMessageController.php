<?php

namespace App\Http\Controllers\Buyer;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class BuyerMessageController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();

        if (Schema::hasColumn('users', 'last_seen_at')) {
            Auth::user()?->update(['is_online' => true, 'last_seen_at' => now()]);
        }

        $initialContactId = $request->query('contact');
        $inquiredProductId = $request->query('product_id');

        if ($initialContactId && $inquiredProductId) {
            $prod = Product::find($inquiredProductId);
            if ($prod) {
                $alreadyInquiredRecently = Message::where('user_id', $userId)
                    ->where('receiver_id', $initialContactId)
                    ->where('product_id', $prod->id)
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->exists();

                if (! $alreadyInquiredRecently) {
                    Message::create([
                        'user_id'     => $userId,
                        'receiver_id' => (int) $initialContactId,
                        'role'        => 'Buyer',
                        'product_id'  => $prod->id,
                        'message'     => "Hi! Is \"{$prod->name}\" still available?",
                        'is_read'     => false,
                    ]);
                }
            }
        }

        $contacts = $this->getContactsData($userId, $initialContactId);

        return view('buyer.messages.buyer-messages', compact('contacts', 'initialContactId'));
    }

    public function fetchContacts(Request $request)
    {
        $userId = Auth::id();
        $contacts = $this->getContactsData($userId, $request->query('contact'));

        return response()->json($contacts);
    }

    private function getContactsData($userId, $includeContactId = null)
    {
        $sentTo = Message::where('user_id', $userId)->pluck('receiver_id');
        $receivedFrom = Message::where('receiver_id', $userId)->pluck('user_id');

        $contactIds = $sentTo->merge($receivedFrom)->unique()->filter();
        if ($includeContactId && ! $contactIds->contains((int) $includeContactId)) {
            $contactIds->push((int) $includeContactId);
        }

        $contacts = User::with('sellerProfile')->whereIn('id', $contactIds)->get();

        foreach ($contacts as $contact) {
            $contact->store_name = $contact->sellerProfile?->business_name
                ?? trim($contact->first_name . ' ' . $contact->last_name);
            $contact->store_logo = $contact->sellerProfile?->logo_path
                ? asset('storage/' . $contact->sellerProfile->logo_path)
                : null;

            $contact->last_message = Message::where(function ($q) use ($userId, $contact) {
                $q->where('user_id', $userId)->where('receiver_id', $contact->id);
            })->orWhere(function ($q) use ($userId, $contact) {
                $q->where('user_id', $contact->id)->where('receiver_id', $userId);
            })->latest()->first();

            $contact->unread_count = Message::where('user_id', $contact->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            $lastSeen = $contact->last_seen_at ? Carbon::parse($contact->last_seen_at) : null;
            $isOnline = (bool) ($contact->is_online ?? false)
                && $lastSeen
                && $lastSeen->gt(now()->subMinutes(5));

            $contact->online_status_label = $isOnline
                ? 'Online now'
                : ($lastSeen ? 'Active ' . $lastSeen->diffForHumans() : 'Merchant');
            $contact->is_currently_online = $isOnline;
        }

        return $contacts->sortByDesc(function ($contact) {
            return $contact->last_message ? $contact->last_message->created_at : 0;
        })->values();
    }

    public function fetchMessages($contactId)
    {
        $userId = Auth::id();

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
                $prod = Product::withTrashed()->find($msg->product_id);
                if ($prod) {
                    $firstPic = (! empty($prod->pictures) && is_array($prod->pictures))
                        ? asset('storage/' . $prod->pictures[0])
                        : null;
                    $rawPrice = round((float) ($prod->price ?? 0), 2);
                    $stockCount = (int) ($prod->stock_quantity ?? 0);
                    $productCard = [
                        'id'              => $prod->id,
                        'name'            => $prod->name,
                        'price'           => $rawPrice,
                        'formatted_price' => number_format($rawPrice, 2),
                        'stock'           => $stockCount,
                        'stock_quantity'  => $stockCount,
                        'image'           => $firstPic,
                        'url'             => route('product.show', $prod->id),
                    ];
                }
            }

            return [
                'id'             => $msg->id,
                'user_id'        => $msg->user_id,
                'receiver_id'    => $msg->receiver_id,
                'role'           => $msg->role,
                'message'        => $msg->message,
                'product_id'     => $msg->product_id ?? null,
                'product_card'   => $productCard,
                'attachment_url' => ! empty($msg->attachment_path) ? asset('storage/' . $msg->attachment_path) : null,
                'is_read'        => (bool) $msg->is_read,
                'created_at'     => $msg->created_at,
            ];
        });

        return response()->json($messages);
    }

    public function markAsRead($contactId)
    {
        Message::where('user_id', $contactId)
            ->where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['status' => 'success', 'success' => true]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'message'     => ['nullable', 'string', 'max:2000'],
            'product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'attachment'  => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'attachment.mimetypes' => 'Chat image attachment must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'attachment.max'       => 'Chat image attachment must not exceed 5 MB.',
        ]);

        if (empty(trim((string) $request->input('message', ''))) && ! $request->hasFile('attachment') && ! $request->filled('product_id')) {
            throw ValidationException::withMessages([
                'message' => 'Please enter a message or attach an image (JPG, PNG, or WEBP).',
            ]);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = 'chat_attachments/chat_' . Auth::id() . '_' . uniqid('', true) . '.webp';
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
            'user_id'     => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'role'        => Auth::user()?->role ?? 'Buyer',
            'message'     => trim((string) ($request->message ?? '')) ?: ($attachmentPath ? '[Sent a photo]' : '[Product Inquiry]'),
            'is_read'     => false,
        ];

        if (Schema::hasColumn('messages', 'product_id')) {
            $payload['product_id'] = $request->input('product_id');
            $payload['attachment_path'] = $attachmentPath;
        }

        $message = Message::create($payload);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'id'             => $message->id,
            'user_id'        => $message->user_id,
            'receiver_id'    => $message->receiver_id,
            'role'           => $message->role,
            'message'        => $message->message,
            'product_id'     => $message->product_id ?? null,
            'attachment_url' => $attachmentPath ? asset('storage/' . $attachmentPath) : null,
            'is_read'        => (bool) $message->is_read,
            'created_at'     => $message->created_at,
        ]);
    }
}