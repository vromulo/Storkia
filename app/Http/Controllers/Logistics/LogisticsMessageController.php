<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Concerns\HandlesUserChat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Logistics <-> Seller chat.
 *
 * The contact list holds the sellers the signed-in hub ships for. A main
 * logistics account sees every seller of its company; a branch hub account
 * only sees sellers whose parcels passed through its own hub. A shipment card
 * can be attached to a message.
 */
class LogisticsMessageController extends Controller
{
    use HandlesUserChat;

    public function index(Request $request)
    {
        $chatConfig = $this->chatConfig($this->chatViewer(), $request, [
            'labels' => [
                'title'       => 'Seller Messages',
                'countNoun'   => 'Sellers',
                'search'      => 'Search sellers...',
                'emptyList'   => 'No sellers yet',
                'emptyTitle'  => 'Your Seller Messages',
                'emptyHint'   => 'Sellers whose parcels you handle appear here. Select one to start chatting.',
                'placeholder' => 'Type a message (max 1,000 chars)...',
            ],
            'groups'        => [],
            'quickReplies'  => [
                'default' => [
                    'Our courier is on the way for pickup.',
                    'Your parcel was received at our hub.',
                    'Please prepare the parcel for pickup.',
                    'Please check the packaging and shipping label.',
                ],
            ],
            'allowShipment' => true,
        ]);

        return view('logistics.messages.logistics-messages', compact('chatConfig'));
    }

    protected function chatContactRoles(): array
    {
        return ['Seller'];
    }

    protected function chatEndpoints(): array
    {
        return [
            'contacts'  => route('logistics.messages.contacts'),
            'messages'  => $this->chatUrl('logistics.messages.fetch', 'contactId'),
            'send'      => route('logistics.messages.store'),
            'read'      => $this->chatUrl('logistics.messages.read', 'contactId'),
            'shipments' => $this->chatUrl('logistics.messages.shipments', 'contactId'),
        ];
    }
}