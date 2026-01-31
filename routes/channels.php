<?php

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

// Public channel - all authenticated users can listen
Broadcast::channel('notifications', function () {
    // Public channel for all notifications
    return true;
});

// Private channel for specific notification
Broadcast::channel('notification.{id}', function ($user, $id) {
    // Allow any authenticated user to listen to any notification
    // In production, you might want to check ownership
    return true;
});

