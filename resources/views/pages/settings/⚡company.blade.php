<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Livewire\Actions\Logout;
use App\Support\AdminCompany;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Company settings')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules;

    public string $name = '';

    public string $password = '';

    public function mount(): void
    {
        $this->name = AdminCompany::resolve()->name;
    }

    public function updateName(): void
    {
        $validated = $this->validate(['name' => $this->nameRules()]);

        AdminCompany::resolve()->update($validated);

        Flux::toast(variant: 'success', text: __('Company updated.'));
    }

    /**
     * Delete the company along with all its users and invitations.
     */
    public function deleteCompany(Logout $logout): void
    {
        $company = AdminCompany::resolve();

        $this->validate(['password' => $this->currentPasswordRules()]);

        $logout();
        $company->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Company settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Company')" :subheading="__('Update your company name')">
        <form wire:submit="updateName" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required />

            <flux:button variant="primary" type="submit" data-test="update-company-button">
                {{ __('Save') }}
            </flux:button>
        </form>

        <section class="mt-10 space-y-6">
            <div class="relative mb-5">
                <flux:heading>{{ __('Delete company') }}</flux:heading>
                <flux:subheading>{{ __('Permanently delete the company and all of its users') }}</flux:subheading>
            </div>

            <flux:modal.trigger name="confirm-company-deletion">
                <flux:button variant="danger" data-test="delete-company-button">
                    {{ __('Delete company') }}
                </flux:button>
            </flux:modal.trigger>
        </section>
    </x-pages::settings.layout>

    <flux:modal name="confirm-company-deletion" :show="$errors->has('password')" focusable class="max-w-lg">
        <form wire:submit="deleteCompany" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete the company?') }}</flux:heading>
                <flux:subheading>
                    {{ __('All users, including you, and all pending invitations will be permanently deleted. Please enter your password to confirm.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" data-test="confirm-delete-company-button">
                    {{ __('Delete company') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
