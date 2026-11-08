<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\SuperDealPricingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Turns messages and contacts into the JSON shape the shared chat component
 * expects. Every portal (Buyer, Seller, Logistics, Help Center, Admin) and the
 * real-time events go through this class, so the payloads never drift apart.
 *
 * Contacts and message lists are built in bulk (a handful of queries no matter
 * how many conversations there are) instead of one query per contact.
 */
class ChatPresenter
{
    public const PLACEHOLDER_PHOTO = '[Sent a photo]';
    public const PLACEHOLDER_PRODUCT = '[Product Inquiry]';
    public const PLACEHOLDER_SHIPMENT = '[Shipment Inquiry]';

    /** Relations needed to name a user and pick their logo without N+1 queries. */
    public const USER_RELATIONS = [
        'sellerProfile',
        'buyerProfile',
        'ownedLogisticsProfile',
        'branchLogisticsHub.parentLogisticsProfile',
    ];

    private const ROLE_LABELS = [
        'Buyer'     => 'Customer',
        'Seller'    => 'Seller',
        'Logistics' => 'Logistics Hub',
    ];

    /** @var array<int, array> */
    private array $productCards = [];

    private static ?bool $hasPresenceColumns = null;

    public function __construct(private ChatAttachmentService $attachments)
    {
    }

    // ------------------------------------------------------------------
    // Presence
    // ------------------------------------------------------------------

    /**
     * Marks the user as active. Throttled to one write per minute so the
     * polling requests do not hammer the users table.
     */
    public function touchPresence(?User $user): void
    {
        if (! $user) {
            return;
        }

        self::$hasPresenceColumns ??= Schema::hasColumn('users', 'last_seen_at');
        if (! self::$hasPresenceColumns) {
            return;
        }

        $seen = $user->last_seen_at;
        if (! $user->is_online || ! $seen || $seen->lt(now()->subSeconds(60))) {
            $user->forceFill(['is_online' => true, 'last_seen_at' => now()])->saveQuietly();
        }
    }

    /**
     * @return array{status: string, label: string}
     */
    public function presence(User $user): array
    {
        $seen = $user->last_seen_at ? Carbon::parse($user->last_seen_at) : null;
        $online = (bool) ($user->is_online ?? false) && $seen && $seen->gt(now()->subMinutes(5));

        return [
            'status' => $online ? 'online' : 'offline',
            'label'  => $online ? 'Online now' : ($seen ? 'Active ' . $seen->diffForHumans() : 'Offline'),
        ];
    }

    // ------------------------------------------------------------------
    // Contacts (user <-> user chat)
    // ------------------------------------------------------------------

    /**
     * @param  Collection<int, int>  $contactIds
     * @return Collection<int, array>
     */
    public function contacts(User $viewer, Collection $contactIds): Collection
    {
        $ids = $contactIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $users = User::with(self::USER_RELATIONS)->whereIn('id', $ids)->get();
        $lastByPartner = $this->lastMessagesByPartner($viewer->id);
        $unreadByPartner = Message::unreadFor($viewer->id)
            ->selectRaw('user_id, COUNT(*) as unread')
            ->groupBy('user_id')
            ->pluck('unread', 'user_id');

        return $users->map(function (User $user) use ($viewer, $lastByPartner, $unreadByPartner) {
            $presence = $this->presence($user);
            $last = $lastByPartner->get($user->id);

            return $this->identity($user) + [
                'online_status'          => $presence['status'],
                'last_seen_label'        => $presence['label'],
                'unread_count'           => (int) $unreadByPartner->get($user->id, 0),
                'latest_message_at'      => $last?->created_at?->toIso8601String(),
                'latest_message_preview' => $last ? $this->preview($last, $viewer->id) : null,
            ];
        })->sort(function (array $a, array $b) {
            return strcmp((string) ($b['latest_message_at'] ?? ''), (string) ($a['latest_message_at'] ?? ''))
                ?: strcasecmp($a['display_name'], $b['display_name']);
        })->values();
    }

    /**
     * Public identity of an account as shown in a chat header / contact row.
     * Callers must eager load self::USER_RELATIONS.
     */
    public function identity(User $user): array
    {
        return [
            'id'             => $user->id,
            'role'           => $user->role,
            'role_label'     => self::ROLE_LABELS[$user->role] ?? (string) $user->role,
            'group'          => strtolower((string) $user->role),
            'display_name'   => $this->displayName($user),
            'first_name'     => $user->first_name,
            'last_name'      => $user->last_name,
            'logo_url'       => $this->logoUrl($user),
            'is_on_vacation' => $user->role === 'Seller' && (bool) ($user->sellerProfile?->is_on_vacation ?? false),
        ];
    }

    public function displayName(User $user): string
    {
        $fullName = trim($user->first_name . ' ' . $user->last_name);

        if ($user->role === 'Seller') {
            $name = $user->sellerProfile?->business_name ?: $fullName;
        } elseif ($user->role === 'Logistics') {
            $name = $user->logisticsProfile?->business_name ?: $fullName;
            $hub = $user->branchLogisticsHub;
            if ($hub && ! empty($hub->hub_name)) {
                $name = trim($name . ' - ' . $hub->hub_name, ' -');
            }
        } else {
            $name = $fullName;
        }

        return $name !== '' ? $name : 'User #' . $user->id;
    }

    public function logoUrl(User $user): ?string
    {
        $path = match ($user->role) {
            'Seller'    => $user->sellerProfile?->logo_path,
            'Logistics' => $user->logisticsProfile?->logo_path,
            default     => $user->buyerProfile?->avatar_path,
        };

        return $path ? asset('storage/' . $path) : null;
    }

    /**
     * Latest message of each conversation the viewer is part of, keyed by the
     * other person's user id. Two queries regardless of the number of chats.
     *
     * @return Collection<int, Message>
     */
    private function lastMessagesByPartner(int $viewerId): Collection
    {
        $lastIds = Message::query()
            ->selectRaw('MAX(id) as last_id')
            ->where(function ($q) use ($viewerId) {
                $q->where('user_id', $viewerId)->orWhere('receiver_id', $viewerId);
            })
            ->groupByRaw('CASE WHEN user_id = ? THEN receiver_id ELSE user_id END', [$viewerId])
            ->pluck('last_id');

        if ($lastIds->isEmpty()) {
            return collect();
        }

        return Message::whereIn('id', $lastIds)->get()->keyBy(
            fn (Message $m) => (int) $m->user_id === $viewerId ? (int) $m->receiver_id : (int) $m->user_id
        );
    }

    private function preview(Message $message, int $viewerId): string
    {
        $text = trim((string) $message->message);

        if ($text === self::PLACEHOLDER_PHOTO || ($text === '' && ! empty($message->attachment_path))) {
            $body = 'Sent a photo';
        } elseif ($text === self::PLACEHOLDER_PRODUCT) {
            $body = 'Product inquiry';
        } elseif ($text === self::PLACEHOLDER_SHIPMENT) {
            $body = 'Shipment inquiry';
        } else {
            $body = Str::limit($text, 60);
        }

        return ((int) $message->user_id === $viewerId ? 'You: ' : '') . $body;
    }

    // ------------------------------------------------------------------
    // Messages (user <-> user chat)
    // ------------------------------------------------------------------

    public function message(Message $message, string $viewerRole): array
    {
        return $this->messages(collect([$message]), $viewerRole)[0];
    }

    /**
     * @param  Collection<int, Message>  $messages
     * @return array<int, array>
     */
    public function messages(Collection $messages, string $viewerRole): array
    {
        $productIds = $messages->pluck('product_id')->filter()->unique()->values();
        $shipmentIds = $messages->pluck('shipment_id')->filter()->unique()->values();

        $products = $productIds->isEmpty()
            ? collect()
            : Product::withTrashed()->whereIn('id', $productIds)->get()->keyBy('id');

        $shipments = ($shipmentIds->isEmpty() || ! Schema::hasTable('shipments'))
            ? collect()
            : Shipment::whereIn('id', $shipmentIds)->get()->keyBy('id');

        return $messages->map(function (Message $m) use ($products, $shipments, $viewerRole) {
            $text = trim((string) $m->message);
            $product = $m->product_id ? $products->get($m->product_id) : null;
            $shipment = $m->shipment_id ? $shipments->get($m->shipment_id) : null;

            return [
                'id'             => $m->id,
                'sender_key'     => 'u:' . $m->user_id,
                'user_id'        => $m->user_id,
                'receiver_id'    => $m->receiver_id,
                'role'           => $m->role,
                'message'        => $this->isPlaceholder($text) ? '' : $text,
                'product_id'     => $m->product_id ?? null,
                'product_card'   => $product ? $this->productCard($product) : null,
                'shipment_id'    => $m->shipment_id ?? null,
                'shipment_card'  => $shipment ? $this->shipmentCard($shipment, $viewerRole) : null,
                'attachment_url' => $this->attachments->url($m->attachment_path ?? null),
                'is_read'        => (bool) $m->is_read,
                'created_at'     => $m->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    public function productCard(Product $product): array
    {
        if (isset($this->productCards[$product->id])) {
            return $this->productCards[$product->id];
        }

        $pictures = $product->pictures;
        if (is_string($pictures)) {
            $pictures = json_decode($pictures, true) ?: [];
        }
        $firstPicture = (is_array($pictures) && ! empty($pictures[0]))
            ? asset('storage/' . $pictures[0])
            : null;

        $basePrice = round((float) ($product->price ?? 0), 2);
        $effectivePrice = $basePrice;
        try {
            $pricing = app(SuperDealPricingService::class)->resolveDisplayPricing($product, $basePrice);
            $effectivePrice = round((float) ($pricing['unit_price'] ?? $basePrice), 2);
        } catch (\Throwable $e) {
            // Fall back to the regular price when promotion tables are unavailable.
        }

        $stock = (int) ($product->stock_quantity ?? 0);

        return $this->productCards[$product->id] = [
            'id'              => $product->id,
            'name'            => $product->name,
            'price'           => $basePrice,
            'formatted_price' => number_format($basePrice, 2),
            'effective_price' => $effectivePrice,
            'stock'           => $stock,
            'stock_quantity'  => $stock,
            'image_url'       => $firstPicture,
            'image'           => $firstPicture,
            'url'             => Route::has('product.show') ? route('product.show', $product->id) : null,
            'is_unavailable'  => method_exists($product, 'trashed') && $product->trashed(),
        ];
    }

    public function shipmentCard(Shipment $shipment, string $viewerRole): array
    {
        $destination = (array) ($shipment->delivery_address_snapshot ?? []);
        $place = collect([$destination['municipality'] ?? null, $destination['province'] ?? null])
            ->filter()
            ->implode(', ');

        $url = null;
        if ($viewerRole === 'Seller' && Route::has('seller.orders.show')) {
            $url = route('seller.orders.show', $shipment->seller_order_id);
        } elseif ($viewerRole === 'Logistics' && Route::has('logistics.shipments.show')) {
            $url = route('logistics.shipments.show', $shipment->id);
        }

        return [
            'id'              => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'status'          => (string) $shipment->status,
            'status_label'    => Str::headline((string) $shipment->status),
            'cod_amount'      => (float) $shipment->cod_amount,
            'formatted_cod'   => number_format((float) $shipment->cod_amount, 2),
            'destination'     => $place !== '' ? $place : null,
            'url'             => $url,
        ];
    }

    // ------------------------------------------------------------------
    // Help Center (Admin <-> user)
    // ------------------------------------------------------------------

    /**
     * @param  string  $audience  "user" (the member) or "admin" (support staff).
     *                            Agent names are only revealed to admins.
     */
    public function supportMessage(SupportMessage $message, string $audience = 'user'): array
    {
        return $this->supportMessages(collect([$message]), $audience)[0];
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     * @return array<int, array>
     */
    public function supportMessages(Collection $messages, string $audience = 'user'): array
    {
        if ($audience === 'admin') {
            $messages->each(fn (SupportMessage $m) => $m->loadMissing('senderAdmin:id,name'));
        }

        return $messages->map(function (SupportMessage $m) use ($audience) {
            $fromAdmin = $m->isFromAdmin();
            $text = trim((string) $m->message);

            return [
                'id'              => $m->id,
                'conversation_id' => $m->conversation_id,
                'sender_key'      => $fromAdmin ? 'a:' . $m->sender_admin_id : 'u:' . $m->sender_user_id,
                'sender_type'     => $m->sender_type,
                'role'            => $fromAdmin ? 'Admin' : null,
                'sender_label'    => $fromAdmin ? 'Storkia Support' : null,
                'agent_name'      => ($fromAdmin && $audience === 'admin') ? $m->senderAdmin?->name : null,
                'message'         => $this->isPlaceholder($text) ? '' : $text,
                'attachment_url'  => $this->attachments->url($m->attachment_path),
                'is_read'         => (bool) $m->is_read,
                'created_at'      => $m->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    public function supportContact(SupportConversation $conversation): ?array
    {
        return $this->supportContacts(collect([$conversation]))->first();
    }

    /**
     * Admin inbox rows: one per conversation, with the member's identity.
     *
     * @param  Collection<int, SupportConversation>  $conversations
     * @return Collection<int, array>
     */
    public function supportContacts(Collection $conversations): Collection
    {
        if ($conversations->isEmpty()) {
            return collect();
        }

        // loadMissing() only exists on Eloquent collections (callers may pass a plain one).
        $conversations = new EloquentCollection($conversations->all());
        $conversations->loadMissing(
            collect(self::USER_RELATIONS)->map(fn ($r) => 'user.' . $r)->push('latestMessage')->all()
        );

        $unreadByConversation = SupportMessage::unreadByAdmin()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->selectRaw('conversation_id, COUNT(*) as unread')
            ->groupBy('conversation_id')
            ->pluck('unread', 'conversation_id');

        return $conversations->map(function (SupportConversation $c) use ($unreadByConversation) {
            $user = $c->user;
            if (! $user) {
                return null;
            }

            $presence = $this->presence($user);
            $last = $c->latestMessage;
            $lastAt = $c->last_message_at ?? $last?->created_at;

            return [
                'id'                     => $c->id,
                'user_id'                => $user->id,
                'status'                 => $c->status,
                'status_label'           => $c->isResolved() ? 'Resolved' : 'Open',
                'email'                  => $user->email,
                'online_status'          => $presence['status'],
                'last_seen_label'        => $presence['label'],
                'unread_count'           => (int) $unreadByConversation->get($c->id, 0),
                'latest_message_at'      => $lastAt?->toIso8601String(),
                'latest_message_preview' => $last ? $this->supportPreview($last) : null,
            ] + $this->identity($user);
        })->filter()->values();
    }

    private function supportPreview(SupportMessage $message): string
    {
        $text = trim((string) $message->message);
        $body = ($text === self::PLACEHOLDER_PHOTO || ($text === '' && ! empty($message->attachment_path)))
            ? 'Sent a photo'
            : Str::limit($text, 60);

        return ($message->isFromAdmin() ? 'Support: ' : '') . $body;
    }

    private function isPlaceholder(string $text): bool
    {
        return in_array($text, [
            self::PLACEHOLDER_PHOTO,
            self::PLACEHOLDER_PRODUCT,
            self::PLACEHOLDER_SHIPMENT,
        ], true);
    }
}