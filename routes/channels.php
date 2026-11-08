<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Buyer <-> Seller and Seller <-> Logistics chat: each account listens to its own inbox.
Broadcast::channel('chat.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('cart.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Help Center, member side: a Buyer / Seller / Logistics account only hears its own thread.
Broadcast::channel('support.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['web']]);

// Help Center, support side: admins sign in through the separate "admin" guard.
Broadcast::channel('support.admin', function ($admin) {
    return $admin instanceof Admin;
}, ['guards' => ['admin']]);