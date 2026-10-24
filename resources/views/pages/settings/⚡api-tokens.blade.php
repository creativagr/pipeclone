<?php

use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API tokens')] class extends Component {
    public string $tokenName = '';

    /**
     * The newly created token, shown once so the user can copy it.
     */
    public ?string $plainTextToken = null;

    public function createToken(): void
    {
        $this->validate(['tokenName' => ['required', 'string', 'max:255']], attributes: ['tokenName' => __('name')]);

        $this->plainTextToken = Auth::user()->createToken($this->tokenName)->plainTextToken;

        $this->reset('tokenName');
        Flux::modal('show-token')->show();
    }

    public function deleteToken(int $tokenId): void
    {
        Auth::user()->tokens()->findOrFail($tokenId)->delete();

        Flux::toast(text: __('Token deleted.'));
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    #[Computed]
    public function tokens(): Collection
    {
        return Auth::user()->tokens()->latest()->get();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('API tokens') }}</flux:heading>

    <x-pages::settings.layout :heading="__('API tokens')" :subheading="__('Create tokens to access the API on your behalf')">
        <form wire:submit="createToken" class="my-6 flex items-end gap-2">
            <flux:input wire:model="tokenName" :label="__('Token name')" :placeholder="__('e.g. CI server')" required class="flex-1" />

            <flux:button variant="primary" type="submit" data-test="create-token-button">
                {{ __('Create') }}
            </flux:button>
        </form>

        @if ($this->tokens->isNotEmpty())
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Last used') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->tokens as $token)
                        <flux:table.row :key="$token->id">
                            <flux:table.cell variant="strong">{{ $token->name }}</flux:table.cell>
                            <flux:table.cell>{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="deleteToken({{ $token->id }})"
                                    wire:confirm="{{ __('Delete the :name token? Anything using it will lose access.', ['name' => $token->name]) }}"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-pages::settings.layout>

    <flux:modal name="show-token" class="max-w-lg" @close="$wire.set('plainTextToken', null)">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Your new API token') }}</flux:heading>
                <flux:subheading>{{ __('Copy it now. For your security, it will not be shown again.') }}</flux:subheading>
            </div>

            <flux:input :value="$plainTextToken" readonly copyable />

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
