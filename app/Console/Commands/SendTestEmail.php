<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('mail:test {email : The address to send the test email to}')]
#[Description('Send a test email to check the mail settings')]
class SendTestEmail extends Command implements PromptsForMissingInput
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $mailer = (string) config('mail.default');

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error(__('Invalid email address.'));

            return self::FAILURE;
        }

        try {
            Mail::raw(
                __('This is a test email from :app.', ['app' => config('app.name')]),
                fn (Message $message) => $message->to($email)->subject(__('Test email')),
            );
        } catch (Throwable $e) {
            $this->components->error(__('Sending with the ":mailer" mailer failed: :message', ['mailer' => $mailer, 'message' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->components->info(__('Test email sent to :email with the ":mailer" mailer.', ['email' => $email, 'mailer' => $mailer]));

        return self::SUCCESS;
    }
}
