<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::livewire('invitations/{invitation}/accept', 'pages::auth.accept-invitation')
    ->middleware(['guest', 'signed'])
    ->name('invitations.accept');

require __DIR__.'/settings.php';
