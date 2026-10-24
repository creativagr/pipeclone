<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('user:create
    {--name= : The user\'s name}
    {--email= : The user\'s email address}
    {--password= : The user\'s password}
    {--organization= : ID of an existing organization to add the user to}
    {--new-organization= : Name of a new organization to create, with the user as its admin}
    {--admin : Make the user an admin of the existing organization}')]
#[Description('Create a user in an existing organization, or an admin with a new organization')]
class CreateUser extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Execute the console command. Options that are not given are prompted for. Console-created users are verified.
     */
    public function handle(): int
    {
        $name = $this->stringOption('name') ?? text(__('Name'), required: true, validate: ['name' => $this->nameRules()]);
        $email = $this->stringOption('email') ?? text(__('Email'), required: true, validate: ['email' => $this->emailRules()]);
        $password = $this->stringOption('password') ?? password(__('Password'), required: true, validate: ['password' => ['string', Password::default()]]);

        $organizationId = $this->stringOption('organization');
        $organizationName = $this->stringOption('new-organization');
        $isAdmin = (bool) $this->option('admin');

        if ($organizationId === null && $organizationName === null) {
            $choice = search(
                __('Organization'),
                fn (string $query): array => ['new' => __('New organization')] + Organization::query()
                    ->where('name', 'like', "%{$query}%")
                    ->orderBy('name')
                    ->limit(20)
                    ->pluck('name', 'id')
                    ->all(),
            );

            if ($choice === 'new') {
                $organizationName = text(__('Organization name'), default: $name, required: true, validate: ['organization_name' => $this->nameRules()]);
            } else {
                $organizationId = (string) $choice;
                $isAdmin = $isAdmin || select(__('Type'), ['user' => __('User'), 'admin' => __('Admin')], default: 'user') === 'admin';
            }
        }

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'organization' => $organizationId,
                'organization_name' => $organizationName,
            ],
            [
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
                'organization' => ['nullable', 'integer', Rule::exists(Organization::class, 'id'), 'prohibits:organization_name'],
                'organization_name' => $organizationId === null ? $this->nameRules() : [],
            ],
            attributes: ['organization' => '--organization', 'organization_name' => '--new-organization'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $type = $organizationId === null || $isAdmin ? UserType::Admin : UserType::User;

        $user = DB::transaction(function () use ($name, $email, $password, $organizationId, $organizationName, $type): User {
            $organization = $organizationId === null
                ? Organization::create(['name' => $organizationName])
                : Organization::findOrFail($organizationId);

            $user = $organization->users()->create(['name' => $name, 'email' => $email, 'password' => $password, 'type' => $type]);
            $user->markEmailAsVerified();

            return $user;
        });

        $this->components->info(__('Created :type :email in :organization.', [
            'type' => $user->type->value,
            'email' => $user->email,
            'organization' => $user->organization->name,
        ]));

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
