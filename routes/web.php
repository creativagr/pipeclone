<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('contacts', 'crm.placeholder', ['title' => 'Contacts'])->name('contacts.index');
    Route::view('organizations', 'crm.placeholder', ['title' => 'Organizations'])->name('organizations.index');
    Route::view('deals', 'crm.placeholder', ['title' => 'Deals'])->name('deals.index');
    Route::view('renewals', 'crm.placeholder', ['title' => 'Renewals'])->name('renewals.index');
});

Route::middleware('guest')->controller(GoogleLoginController::class)->group(function () {
    Route::get('auth/google/redirect', 'redirect')->name('auth.google.redirect');
    Route::get('auth/google/callback', 'callback')->name('auth.google.callback');
});

Route::livewire('invitations/{invitation}/accept', 'pages::auth.accept-invitation')
    ->middleware(['guest', 'signed'])
    ->name('invitations.accept');

require __DIR__.'/settings.php';
