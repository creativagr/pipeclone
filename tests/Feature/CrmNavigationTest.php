<?php

use App\Models\User;

dataset('crm sections', [
    'contacts' => ['contacts.index', 'Contacts'],
    'organizations' => ['organizations.index', 'Organizations'],
    'deals' => ['deals.index', 'Deals'],
    'renewals' => ['renewals.index', 'Renewals'],
]);

test('guests are redirected from crm sections to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('crm sections');

test('users can open every crm section from the sidebar', function (string $route, string $title) {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertSee(route($route));
    $this->get(route($route))->assertOk()->assertSee($title);
})->with('crm sections');
