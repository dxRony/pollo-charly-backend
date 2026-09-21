<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado para la pantalla de cocina y seguimiento de comandas en tiempo real
Broadcast::channel('cocina', function ($user) {
    return $user->hasRole(\App\Models\Role::COCINERO, \App\Models\Role::ADMINISTRADOR, \App\Models\Role::MESERO_CAJERO);
});
