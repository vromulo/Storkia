<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Models\SellerProfile;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiMessageController extends Controller
{
    public function fetchContacts(Request $request)
    {
        try {
            $userId = $request->user()->id;

            // 1. Get unique conversation partners efficiently without pulling vast arrays into PHP memory
            $sentIds = Message::where('user_id', $userId)->distinct()->pluck('receiver_id')->toArray();
            $receivedIds = Message::where('receiver_id', $userId)->distinct()->pluck('user_id')->toArray();
            
            $contactIds = array_unique(array_merge($sentIds, $receivedIds));
            
            // Remove the authenticated user from the partner list
            $contactIds = array_diff($contactIds, [$userId]);

            $contacts = [];

            if (!empty($contactIds)) {
                // 2. Load the users and their seller profiles
                $users = User::with('sellerProfile')->whereIn('id', $contactIds)->get();

                foreach ($users as $partner) {
                    $latestMessage = Message::where(function ($q) use ($userId, $partner) {
                            $q->where('user_id', $userId)->where('receiver_id', $partner->id);
                        })
                        ->orWhere(function ($q) use ($userId, $partner) {
                            $q->where('user_id', $partner->id)->where('receiver_id', $userId);
                        })
                        ->latest()
                        ->first();

                    $unreadCount = Message::where('user_id', $partner->id)
                        ->where('receiver_id', $userId)
                        ->where('is_read', false)
                        ->count();

                    $businessName = null;
                    $logoPath = null;

                    if ($partner->role === 'Seller' && $partner->sellerProfile) {
                        $businessName = $partner->sellerProfile->business_name;
                        $logoPath = $partner->sellerProfile->logo_path;
                    }

                    $contacts[] = [
                        'id' => $partner->id,
                        'first_name' => $partner->first_name ?? 'User',
                        'last_name' => $partner->last_name ?? '',
                        'role' => $partner->role ?? 'Buyer',
                        'latest_message_at' => $latestMessage ? $latestMessage->created_at->toISOString() : null,
                        'unread_count' => $unreadCount,
                        'business_name' => $businessName,
                        'logo_path' => $logoPath,
                    ];
                }
            }

            // 3. Sort contacts by newest message descending
            usort($contacts, function ($a, $b) {
                $timeA = $a['latest_message_at'] ? strtotime($a['latest_message_at']) : 0;
                $timeB = $b['latest_message_at'] ? strtotime($b['latest_message_at']) : 0;
                return $timeB <=> $timeA;
            });

            // 4. Handle requested contact (if navigating directly from a product)
            $requestedContactId = $request->input('contact');

            if ($requestedContactId && $requestedContactId != $userId) {
                $found = false;
                foreach ($contacts as $c) {
                    if ($c['id'] == $requestedContactId) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $newPartner = User::with('sellerProfile')->find($requestedContactId);
                    if ($newPartner) {
                        $businessName = null;
                        $logoPath = null;
                        if ($newPartner->role === 'Seller' && $newPartner->sellerProfile) {
                            $businessName = $newPartner->sellerProfile->business_name;
                            $logoPath = $newPartner->sellerProfile->logo_path;
                        }
                        
                        array_unshift($contacts, [
                            'id' => $newPartner->id,
                            'first_name' => $newPartner->first_name ?? 'User',
                            'last_name' => $newPartner->last_name ?? '',
                            'role' => $newPartner->role ?? 'Buyer',
                            'latest_message_at' => now()->toISOString(),
                            'unread_count' => 0,
                            'business_name' => $businessName,
                            'logo_path' => $logoPath,
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values($contacts)
            ]);

        } catch (\Throwable $e) {
            Log::error("Fetch Contacts 500 Error: " . $e->getMessage() . " on line " . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Server configuration error.'
            ], 200); 
        }
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->user()->id;
        
        $count = Message::where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['count' => $count]
        ]);
    }

    public function fetchMessages(Request $request, $contactId)
    {
        $userId = $request->user()->id;

        Message::where('user_id', $contactId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = Message::where(function ($query) use ($userId, $contactId) {
            $query->where('user_id', $userId)->where('receiver_id', $contactId);
        })->orWhere(function ($query) use ($userId, $contactId) {
            $query->where('user_id', $contactId)->where('receiver_id', $userId);
        })->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => array_values($messages->toArray())
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|numeric',
            'message' => 'required|string',
        ]);

        $userId = $request->user()->id;
        $userRole = $request->user()->role ?? 'Buyer';

        try {
            $message = Message::create([
                'user_id' => $userId,
                'receiver_id' => (int)$validated['receiver_id'],
                'role' => $userRole, 
                'message' => $validated['message'],
                'is_read' => false,
            ]);

            $message->refresh();

            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                Log::error("Broadcast failed: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $message->toArray()
            ]);

        } catch (\Throwable $e) {
            Log::error("Message Store Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Database error'], 500);
        }
    }

    public function markAsRead(Request $request, $contactId)
    {
        $userId = $request->user()->id;

        Message::where('user_id', $contactId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}