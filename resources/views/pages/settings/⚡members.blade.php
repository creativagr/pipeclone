<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserType;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\AdminOrganization;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Members')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $inviteEmail = '';

    /**
     * Create a user in the admin's organization with the given password and send them a verification email.
     */
    public function createUser(): void
    {
        abort_unless(config('app.simple_users_enabled'), 403);

        $validated = $this->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        AdminOrganization::resolve()->users()
            ->create([...$validated, 'type' => UserType::User])
            ->sendEmailVerificationNotification();

        $this->reset('name', 'email', 'password', 'password_confirmation');
        Flux::modal('create-user')->close();
        Flux::toast(variant: 'success', text: __('User created.'));
    }

    /**
     * Invite a user by email. Re-inviting the same email resends the invitation.
     */
    public function invite(): void
    {
        abort_unless(config('app.simple_users_enabled'), 403);

        $this->validate(['inviteEmail' => $this->emailRules()], attributes: ['inviteEmail' => __('email')]);

        $invitation = AdminOrganization::resolve()->invitations()->createOrFirst(['email' => $this->inviteEmail]);
        $invitation->touch();
        $invitation->notify(new InvitationNotification);

        $this->reset('inviteEmail');
        Flux::modal('invite-user')->close();
        Flux::toast(variant: 'success', text: __('Invitation sent.'));
    }

    public function revokeInvitation(int $invitationId): void
    {
        AdminOrganization::resolve()->invitations()->findOrFail($invitationId)->delete();

        Flux::toast(text: __('Invitation revoked.'));
    }

    /**
     * Toggle a member between admin and user.
     */
    public function toggleType(int $userId): void
    {
        $member = $this->otherMember($userId);

        abort_if($member->isAdmin() && ! config('app.simple_users_enabled'), 403);

        $member->update(['type' => $member->isAdmin() ? UserType::User : UserType::Admin]);
    }

    /**
     * Toggle a member between active and deactivated. Deactivated members cannot sign in.
     */
    public function toggleActive(int $userId): void
    {
        $member = $this->otherMember($userId);

        $member->forceFill(['deactivated_at' => $member->isActive() ? now() : null])->save();
    }

    public function removeMember(int $userId): void
    {
        $this->otherMember($userId)->delete();

        Flux::toast(text: __('Member removed.'));
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return AdminOrganization::resolve()->users()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Invitation>
     */
    #[Computed]
    public function invitations(): Collection
    {
        return AdminOrganization::resolve()->invitations()->latest('updated_at')->get();
    }

    /**
     * Find a member of the admin's organization, excluding the admin themselves.
     */
    private function otherMember(int $userId): User
    {
        return AdminOrganization::resolve()->users()->whereKeyNot(Auth::id())->findOrFail($userId);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Members') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Members')" :subheading="__('Manage the users of your organization')" wide>
        @if (config('app.simple_users_enabled'))
            <div class="mb-4 flex justify-end gap-2">
                <flux:modal.trigger name="invite-user">
                    <flux:button icon="envelope">{{ __('Invite user') }}</flux:button>
                </flux:modal.trigger>

                <flux:modal.trigger name="create-user">
                    <flux:button variant="primary" icon="plus">{{ __('Create user') }}</flux:button>
                </flux:modal.trigger>
            </div>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->members as $member)
                    <flux:table.row :key="$member->id">
                        <flux:table.cell variant="strong">{{ $member->name }}</flux:table.cell>
                        <flux:table.cell>{{ $member->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$member->isAdmin() ? 'indigo' : 'zinc'">
                                {{ $member->isAdmin() ? __('Admin') : __('User') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$member->isActive() ? 'green' : 'red'">
                                {{ $member->isActive() ? __('Active') : __('Deactivated') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($member->isNot(auth()->user()))
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

                                    <flux:menu>
                                        @if (! $member->isAdmin() || config('app.simple_users_enabled'))
                                            <flux:menu.item icon="arrows-up-down" wire:click="toggleType({{ $member->id }})">
                                                {{ $member->isAdmin() ? __('Make user') : __('Make admin') }}
                                            </flux:menu.item>
                                        @endif

                                        <flux:menu.item :icon="$member->isActive() ? 'no-symbol' : 'check-circle'" wire:click="toggleActive({{ $member->id }})">
                                            {{ $member->isActive() ? __('Deactivate') : __('Activate') }}
                                        </flux:menu.item>

                                        <flux:menu.item
                                            icon="trash"
                                            variant="danger"
                                            wire:click="removeMember({{ $member->id }})"
                                            wire:confirm="{{ __('Remove :name from the organization?', ['name' => $member->name]) }}"
                                        >
                                            {{ __('Remove') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($this->invitations->isNotEmpty())
            <flux:heading size="lg" class="mt-10 mb-2">{{ __('Pending invitations') }}</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Last sent') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->invitations as $invitation)
                        <flux:table.row :key="'invitation-'.$invitation->id">
                            <flux:table.cell>{{ $invitation->email }}</flux:table.cell>
                            <flux:table.cell>{{ $invitation->updated_at?->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button variant="ghost" size="sm" wire:click="revokeInvitation({{ $invitation->id }})">
                                    {{ __('Revoke') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-pages::settings.layout>

    @if (config('app.simple_users_enabled'))
        <flux:modal name="create-user" focusable class="md:w-96">
            <form wire:submit="createUser" class="space-y-6">
                <flux:heading size="lg">{{ __('Create user') }}</flux:heading>

                <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="off" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" />
                <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable />
                <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="invite-user" focusable class="md:w-96">
            <form wire:submit="invite" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Invite user') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('We will email them a link to join your organization.') }}</flux:text>
                </div>

                <flux:input wire:model="inviteEmail" :label="__('Email')" type="email" required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Send invitation') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
