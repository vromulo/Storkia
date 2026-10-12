<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SellerMessageController extends Controller
{
    private function getContactsList($userId)
    {
        $contactIds = Message::where('user_id', $userId)->pluck('receiver_id')
            ->merge(Message::where('receiver_id', $userId)->pluck('user_id'))
            ->unique();

        $contacts = User::whereIn('id', $contactIds)->get();

        $contacts = $contacts->map(function ($contact) use ($userId) {
            $latestMessage = Message::where(function ($q) use ($userId, $contact) {
                $q->where('user_id', $userId)->where('receiver_id', $contact->id);
            })->orWhere(function ($q) use ($userId, $contact) {
                $q->where('user_id', $contact->id)->where('receiver_id', $userId);
            })->latest()->first();

            $contact->latest_message_at = $latestMessage ? $latestMessage->created_at : null;
            
            $contact->unread_count = Message::where('user_id', $contact->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();
                
            return $contact;
        })->sortByDesc('latest_message_at')->values();

        return $contacts;
    }

    public function index()
    {
        $contacts = $this->getContactsList(auth()->id());
        return view('seller.messages.seller-messages', compact('contacts'));
    }

    public function fetchContacts()
    {
        $contacts = $this->getContactsList(auth()->id());
        return response()->json($contacts);
    }

    public function fetchMessages($contactId)
    {
        $userId = auth()->id();

        Message::where('user_id', $contactId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = Message::where(function ($query) use ($userId, $contactId) {
            $query->where('user_id', $userId)->where('receiver_id', $contactId);
        })->orWhere(function ($query) use ($userId, $contactId) {
            $query->where('user_id', $contactId)->where('receiver_id', $userId);
        })->orderBy('created_at', 'asc')->get();

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|numeric',
            'message' => 'required|string',
        ]);

        try {
            $message = Message::create([
                'user_id' => auth()->id(),
                'receiver_id' => (int)$validated['receiver_id'],
                'role' => 'Seller',
                'message' => $validated['message'],
                'is_read' => false,
            ]);

            $message->refresh(); 

            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                Log::error("Broadcast failed: " . $e->getMessage());
            }

            return response()->json($message);
        } catch (\Throwable $e) {
            Log::error("Message Store Error: " . $e->getMessage());
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