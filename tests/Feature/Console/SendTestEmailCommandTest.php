<?php

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

test('it sends a test email to the given address', function () {
    $this->artisan('mail:test', ['email' => 'jane@example.com'])->assertSuccessful();

    $messages = Mail::mailer()->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->getEnvelope()->getRecipients()[0]->getAddress())->toBe('jane@example.com');
});

test('it rejects an invalid email address', function () {
    $this->artisan('mail:test', ['email' => 'not-an-email'])
        ->expectsOutputToContain(__('Invalid email address.'))
        ->assertFailed();

    expect(Mail::mailer()->getSymfonyTransport()->messages())->toBeEmpty();
});

test('it reports a failure when the email cannot be sent', function () {
    Mail::shouldReceive('raw')->andThrow(new TransportException('Connection refused'));

    $this->artisan('mail:test', ['email' => 'jane@example.com'])
        ->expectsOutputToContain('Connection refused')
        ->assertFailed();
});
