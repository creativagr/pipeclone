<?php

use App\Models\Invitation;
use App\Models\Organization;
use App\Notifications\InvitationNotification;

test('the invitation email links to the accept page', function () {
    $invitation = Invitation::factory()->create();

    $mail = (new InvitationNotification)->toMail($invitation);

    $this->get($mail->actionUrl)->assertOk();
});

test('the invitation email escapes the organization name', function () {
    $invitation = Invitation::factory()
        ->for(Organization::factory()->state(['name' => '<script>alert(1)</script>']))
        ->create();

    $content = (string) (new InvitationNotification)->toMail($invitation)->render();

    expect($content)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});
