<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserType;
use App\Models\Invitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth'), Title('Accept invitation')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules;

    public Invitation $invitation;

    public string $name = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function boot(): void
    {
        abort_unless(config('app.simple_users_enabled'), 403);
    }

    /**
     * Create the invited user in the inviting organization and log them in.
     */
    public function accept(): void
    {
        $validated = $this->validate([
            'name' => $this->nameRules(),
            'password' => $this->passwordRules(),
        ]);

        Validator::make(['email' => $this->invitation->email], ['email' => $this->emailRules()])->validate();

        $user = DB::transaction(function () use ($validated) {
            $user = $this->invitation->organization->users()->create([
                'name' => $validated['name'],
                'email' => $this->invitation->email,
                'password' => $validated['password'],
                'type' => UserType::User,
            ]);

            $user->markEmailAsVerified();
            $this->invitation->delete();

            return $user;
        });

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Join :organization', ['organization' => $invitation->organization->name])"
        :description="__('Choose your name and password to accept the invitation')"
    />

    <form wire:submit="accept" class="flex flex-col gap-6">
        <flux:input :label="__('Email address')" :value="$invitation->email" type="email" readonly />

        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

        <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable />

        <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />

        @error('email')
            <flux:text class="!text-red-600">{{ $message }}</flux:text>
        @enderror

        <flux:button type="submit" variant="primary" class="w-full" data-test="accept-invitation-button">
            {{ __('Accept invitation') }}
        </flux:button>
    </form>
</div>
