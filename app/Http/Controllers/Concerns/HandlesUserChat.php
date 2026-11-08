<?php

namespace App\Http\Controllers\Concerns;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatEligibilityService;
use App\Services\Chat\ChatPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Shared behaviour of the user-to-user chat controllers (Buyer, Seller and
 * Logistics). The three portals expose the same JSON actions the original
 * Buyer <-> Seller chat had, so they only differ in:
 *
 *   - which roles they may talk to       (chatContactRoles)
 *   - which routes the page should call  (chatEndpoints)
 *
 * Who may talk to whom is enforced by ChatEligibilityService, never by the
 * client: every store() call re-checks it.
 */
trait HandlesUserChat
{
    private ?ChatPresenter $chatPresenterInstance = null;

    private ?ChatEligibilityService $chatEligibilityInstance = null;

    /** @var array<int, string>|null */
    private ?array $chatMessageColumns = null;

    /**
     * URLs the page script calls. Message / read / shipment URLs contain a
     * "{id}" placeholder (use chatUrl()).
     *
     * @return array{contacts: string, messages: string, send: string, read: string, shipments: ?string}
     */
    abstract protected function chatEndpoints(): array;

    /**
     * Roles of the accounts that may appear in this portal's contact list.
     *
     * @return array<int, string>
     */
    abstract protected function chatContactRoles(): array;

    // ------------------------------------------------------------------
    // JSON actions
    // ------------------------------------------------------------------

    public function fetchContacts(Request $request): JsonResponse
    {
        $viewer = $this->chatViewer();
        $this->chatPresenter()->touchPresence($viewer);

        $include = $request->filled('contact') ? (int) $request->query('contact') : null;

        return response()->json($this->chatContacts($viewer, $include));
    }

    public function fetchMessages(Request $request, int $contactId): JsonResponse
    {
        $viewer = $this->chatViewer();
        $this->chatPresenter()->touchPresence($viewer);

        $this->chatMarkRead($viewer, $contactId);

        $messages = Message::between($viewer->id, $contactId)
            ->orderByDesc('id')
            ->limit($this->chatHistoryLimit())
            ->get()
            ->reverse()
            ->values();

        return response()->json($this->chatPresenter()->messages($messages, $viewer->role));
    }

    public function markAsRead(int $contactId): JsonResponse
    {
        $this->chatMarkRead($this->chatViewer(), $contactId);

        return response()->json(['status' => 'success', 'success' => true]);
    }

    /**
     * Shipments the viewer shares with this contact (Seller <-> Logistics),
     * for the "attach a shipment" picker. Empty for any other pair.
     */
    public function shipments(int $contactId): JsonResponse
    {
        $viewer = $this->chatViewer();
        $contact = User::findOrFail($contactId);

        abort_unless($this->chatEligibility()->canMessage($viewer, $contact), 403, 'You cannot message this account.');

        $cards = $this->chatEligibility()
            ->sharedShipments($viewer, $contact)
            ->map(fn ($shipment) => $this->chatPresenter()->shipmentCard($shipment, $viewer->role))
            ->values();

        return response()->json($cards);
    }

    public function store(Request $request): JsonResponse
    {
        $viewer = $this->chatViewer();

        $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'message'     => ['nullable', 'string', 'max:1000'],
            'product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'shipment_id' => ['nullable', 'integer'],
            'attachment'  => ChatAttachmentService::rules(),
        ], ChatAttachmentService::validationMessages());

        $text = trim((string) $request->input('message', ''));

        if ($text === ''
            && ! $request->hasFile('attachment')
            && ! $request->filled('product_id')
            && ! $request->filled('shipment_id')) {
            throw ValidationException::withMessages([
                'message' => 'Please enter a message or attach an image (JPG, PNG, or WEBP).',
            ]);
        }

        $receiver = User::findOrFail((int) $request->input('receiver_id'));

        if (! $this->chatEligibility()->canMessage($viewer, $receiver)) {
            throw ValidationException::withMessages([
                'receiver_id' => 'You cannot message this account.',
            ]);
        }

        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;
        if ($productId) {
            $sellerId = $this->chatSellerIdFor($viewer, $receiver);
            $involvesBuyer = $viewer->role === 'Buyer' || $receiver->role === 'Buyer';

            if (! $sellerId || ! $involvesBuyer
                || ! Product::where('id', $productId)->where('user_id', $sellerId)->exists()) {
                throw ValidationException::withMessages([
                    'product_id' => 'That product cannot be attached to this conversation.',
                ]);
            }
        }

        $shipmentId = $request->filled('shipment_id') ? (int) $request->input('shipment_id') : null;
        if ($shipmentId && ! $this->chatEligibility()->findSharedShipment($viewer, $receiver, $shipmentId)) {
            throw ValidationException::withMessages([
                'shipment_id' => 'That shipment cannot be attached to this conversation.',
            ]);
        }

        $attachmentPath = $request->hasFile('attachment')
            ? app(ChatAttachmentService::class)->store($request->file('attachment'), (string) $viewer->id)
            : null;

        $body = $text !== '' ? $text : match (true) {
            $attachmentPath !== null => ChatPresenter::PLACEHOLDER_PHOTO,
            $productId !== null      => ChatPresenter::PLACEHOLDER_PRODUCT,
            default                  => ChatPresenter::PLACEHOLDER_SHIPMENT,
        };

        $payload = [
            'user_id'     => $viewer->id,
            'receiver_id' => $receiver->id,
            'role'        => $viewer->role,
            'message'     => $body,
            'is_read'     => false,
        ];

        $columns = $this->chatColumns();
        if (in_array('product_id', $columns, true)) {
            $payload['product_id'] = $productId;
        }
        if (in_array('attachment_path', $columns, true)) {
            $payload['attachment_path'] = $attachmentPath;
        }
        if (in_array('shipment_id', $columns, true)) {
            $payload['shipment_id'] = $shipmentId;
        }

        $message = Message::create($payload);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            // Real-time is best effort; the receiver's page falls back to polling.
        }

        return response()->json($this->chatPresenter()->message($message, $viewer->role), 201);
    }

    // ------------------------------------------------------------------
    // Helpers for the page (index) action of each portal
    // ------------------------------------------------------------------

    /**
     * Everything the shared chat component needs to boot.
     *
     * @param  array<string, mixed>  $extra  portal specific keys (labels, tabs, quick replies ...)
     */
    protected function chatConfig(User $viewer, Request $request, array $extra = []): array
    {
        $this->chatPresenter()->touchPresence($viewer);

        $requested = $request->filled('contact') ? (int) $request->query('contact') : null;

        return array_replace_recursive([
            'mode'               => 'direct',
            'authId'             => $viewer->id,
            'selfKey'            => 'u:' . $viewer->id,
            'endpoints'          => $this->chatEndpoints(),
            'realtime'           => [
                'channel' => 'chat.' . $viewer->id,
                'event'   => '.MessageSent',
            ],
            'requestedContactId' => $requested,
            'draft'              => $this->chatDraftFromRequest($viewer, $request),
            'contacts'           => $this->chatContacts($viewer, $requested)->all(),
        ], $extra);
    }

    /**
     * Contact list: every conversation, plus accounts the viewer may start a
     * chat with (shipment partners), plus the one requested through ?contact=.
     *
     * @return Collection<int, array>
     */
    protected function chatContacts(User $viewer, ?int $includeContactId = null): Collection
    {
        return $this->chatPresenter()->contacts($viewer, $this->chatContactIds($viewer, $includeContactId));
    }

    /**
     * @return Collection<int, int>
     */
    protected function chatContactIds(User $viewer, ?int $includeContactId = null): Collection
    {
        $ids = Message::where('user_id', $viewer->id)->distinct()->pluck('receiver_id')
            ->merge(Message::where('receiver_id', $viewer->id)->distinct()->pluck('user_id'))
            ->merge($this->chatEligibility()->startableContactIds($viewer));

        if ($includeContactId) {
            $target = User::find($includeContactId);
            if ($target && $this->chatEligibility()->canMessage($viewer, $target)) {
                $ids->push($includeContactId);
            }
        }

        $ids = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return $ids;
        }

        return User::notArchived()
            ->whereIn('id', $ids)
            ->whereIn('role', $this->chatContactRoles())
            ->pluck('id');
    }

    /**
     * A product (Buyer <-> Seller) or shipment (Seller <-> Logistics) card
     * waiting in the composer, from ?contact=ID&product_id=ID or
     * ?contact=ID&shipment_id=ID. Nothing is sent until the user confirms.
     *
     * @return array{type: string, data: array}|null
     */
    protected function chatDraftFromRequest(User $viewer, Request $request): ?array
    {
        $contactId = (int) $request->query('contact');
        if (! $contactId) {
            return null;
        }

        $contact = User::find($contactId);
        if (! $contact || ! $this->chatEligibility()->canMessage($viewer, $contact)) {
            return null;
        }

        if ($request->filled('product_id')) {
            $sellerId = $this->chatSellerIdFor($viewer, $contact);
            $product = $sellerId
                ? Product::where('id', (int) $request->query('product_id'))->where('user_id', $sellerId)->first()
                : null;

            if ($product) {
                return ['type' => 'product', 'data' => $this->chatPresenter()->productCard($product)];
            }
        }

        if ($request->filled('shipment_id')) {
            $shipment = $this->chatEligibility()->findSharedShipment($viewer, $contact, (int) $request->query('shipment_id'));

            if ($shipment) {
                return ['type' => 'shipment', 'data' => $this->chatPresenter()->shipmentCard($shipment, $viewer->role)];
            }
        }

        return null;
    }

    /**
     * Builds a URL with a "{id}" placeholder the page script fills in.
     */
    protected function chatUrl(string $routeName, string $parameter): string
    {
        return str_replace('__ID__', '{id}', route($routeName, [$parameter => '__ID__']));
    }

    protected function chatHistoryLimit(): int
    {
        return 200;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function chatViewer(): User
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $user;
    }

    protected function chatPresenter(): ChatPresenter
    {
        return $this->chatPresenterInstance ??= app(ChatPresenter::class);
    }

    protected function chatEligibility(): ChatEligibilityService
    {
        return $this->chatEligibilityInstance ??= app(ChatEligibilityService::class);
    }

    private function chatMarkRead(User $viewer, int $contactId): void
    {
        Message::where('user_id', $contactId)
            ->where('receiver_id', $viewer->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    private function chatSellerIdFor(User $a, User $b): ?int
    {
        return match (true) {
            $a->role === 'Seller' => $a->id,
            $b->role === 'Seller' => $b->id,
            default               => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function chatColumns(): array
    {
        return $this->chatMessageColumns ??= Schema::getColumnListing('messages');
    }
}