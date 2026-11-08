<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Concerns\HandlesUserChat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Buyer <-> Seller chat. A buyer can write to any seller (from a product
 * page, a store page or this inbox) and only ever sees sellers in the list.
 *
 * The JSON actions (contacts, messages, send, read) come from HandlesUserChat
 * and are shared with the Seller and Logistics portals.
 */
class BuyerMessageController extends Controller
{
    use HandlesUserChat;

    public function index(Request $request)
    {
        $chatConfig = $this->chatConfig($this->chatViewer(), $request, [
            'labels' => [
                'title'           => 'Messages',
                'countNoun'       => 'Stores',
                'search'          => 'Search store conversations...',
                'emptyList'       => 'No active conversations',
                'emptyTitle'      => 'Your Store Messages',
                'emptyHint'       => 'Select a store on the left or tap "Chat Now" on any product page.',
                'placeholder'     => 'Type a message (max 1,000 chars)...',
                'defaultSubtitle' => 'Official Store',
            ],
            'quickReplies' => [
                'default' => [
                    'Hi, available pa po ba?',
                    'Can you ship this today?',
                    'Is there a store voucher available?',
                    'May I ask for actual product photos?',
                ],
            ],
            'groups'        => [],
            'allowShipment' => false,
        ]);

        return view('buyer.messages.buyer-messages', compact('chatConfig'));
    }

    protected function chatContactRoles(): array
    {
        return ['Seller'];
    }

    protected function chatEndpoints(): array
    {
        return [
            'contacts'  => route('user.messages.contacts'),
            'messages'  => $this->chatUrl('user.messages.fetch', 'contactId'),
            'send'      => route('user.messages.store'),
            'read'      => $this->chatUrl('user.messages.read', 'contactId'),
            'shipments' => null,
        ];
    }
}