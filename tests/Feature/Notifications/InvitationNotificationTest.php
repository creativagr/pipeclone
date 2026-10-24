<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Notifications\InvitationNotification;

test('the invitation email links to the accept page', function () {
    $invitation = Invitation::factory()->create();

    $mail = (new InvitationNotification)->toMail($invitation);

    $this->get($mail->actionUrl)->assertOk();
});

test('the invitation email escapes the company name', function () {
    $invitation = Invitation::factory()
        ->for(Company::factory()->state(['name' => '<script>alert(1)</script>']))
        ->create();

    $content = (string) (new InvitationNotification)->toMail($invitation)->render();

    expect($content)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});
