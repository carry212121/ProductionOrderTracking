<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
// Prevent early class resolution when Pusher class is not ready
app()->booted(function () {
    Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
        Log::info("🔑 Broadcast channel auth check: User {$user->id} == {$id}");
        return (int) $user->id === (int) $id;
    });
});

