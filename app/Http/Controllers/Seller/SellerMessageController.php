<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Concerns\HandlesUserChat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Seller inbox with two kinds of conversations:
 *
 *   - Customers  (Buyers who wrote to the store, or who ordered from it)
 *   - Logistics  (the main logistics account and the branch hub accounts that
 *                 handled the seller's shipments; a shipment card can be
 *                 attached to a message)
 *
 * Who the seller may write to is decided by ChatEligibilityService.
 */
class SellerMessageController extends Controller
{
    use HandlesUserChat;

    public function index(Request $request)
    {
        $chatConfig = $this->chatConfig($this->chatViewer(), $request, [
            'labels' => [
                'title'       => 'Messages',
                'countNoun'   => 'Chats',
                'search'      => 'Search customers or logistics...',
                'emptyList'   => 'No conversations yet',
                'emptyTitle'  => 'Your Inbox',
                'emptyHint'   => 'Customers who message your store and the logistics partners that ship your parcels appear here.',
                'placeholder' => 'Type a message (max 1,000 chars)...',
            ],
            'groups' => [
                ['key' => 'all', 'label' => 'All'],
                ['key' => 'buyer', 'label' => 'Customers'],
                ['key' => 'logistics', 'label' => 'Logistics'],
            ],
            'quickReplies' => [
                'default'   => [
                    'Hello! How can I help you today?',
                ],
                'buyer'     => [
                    'Hello! How can I help you today?',
                    'Yes, the item is still available.',
                    'Your order is being prepared.',
                    'Thank you for shopping with us!',
                ],
                'logistics' => [
                    'Hi, when will the courier pick up the parcel?',
                    'Is there an update on this shipment?',
                    'Please confirm the parcel was received at the hub.',
                    'Thank you for the update!',
                ],
            ],
            'allowShipment' => true,
        ]);

        return view('seller.messages.seller-messages', compact('chatConfig'));
    }

    protected function chatContactRoles(): array
    {
        return ['Buyer', 'Logistics'];
    }

    protected function chatEndpoints(): array
    {
        return [
            'contacts'  => route('seller.messages.contacts'),
            'messages'  => $this->chatUrl('seller.messages.fetch', 'contactId'),
            'send'      => route('seller.messages.store'),
            'read'      => $this->chatUrl('seller.messages.read', 'contactId'),
            'shipments' => $this->chatUrl('seller.messages.shipments', 'contactId'),
        ];
    }
}