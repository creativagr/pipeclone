<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\Company;
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
    {--company= : ID of an existing company to add the user to}
    {--new-company= : Name of a new company to create, with the user as its admin}
    {--admin : Make the user an admin of the existing company}')]
#[Description('Create a user in an existing company, or an admin with a new company')]
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

        $companyId = $this->stringOption('company');
        $companyName = $this->stringOption('new-company');
        $isAdmin = (bool) $this->option('admin');

        if ($companyId === null && $companyName === null) {
            $choice = search(
                __('Company'),
                fn (string $query): array => ['new' => __('New company')] + Company::query()
                    ->where('name', 'like', "%{$query}%")
                    ->orderBy('name')
                    ->limit(20)
                    ->pluck('name', 'id')
                    ->all(),
            );

            if ($choice === 'new') {
                $companyName = text(__('Company name'), default: $name, required: true, validate: ['company_name' => $this->nameRules()]);
            } else {
                $companyId = (string) $choice;
                $isAdmin = $isAdmin || select(__('Role'), ['user' => __('User'), 'admin' => __('Admin')], default: 'user') === 'admin';
            }
        }

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'company' => $companyId,
                'company_name' => $companyName,
            ],
            [
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
                'company' => ['nullable', 'integer', Rule::exists(Company::class, 'id'), 'prohibits:company_name'],
                'company_name' => $companyId === null ? $this->nameRules() : [],
            ],
            attributes: ['company' => '--company', 'company_name' => '--new-company'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $role = $companyId === null || $isAdmin ? UserRole::Admin : UserRole::User;

        $user = DB::transaction(function () use ($name, $email, $password, $companyId, $companyName, $role): User {
            $company = $companyId === null
                ? Company::create(['name' => $companyName])
                : Company::findOrFail($companyId);

            $user = $company->users()->create(['name' => $name, 'email' => $email, 'password' => $password])->assignRole($role);
            $user->markEmailAsVerified();

            return $user;
        });

        $this->components->info(__('Created :role :email in :company.', [
            'role' => $role->value,
            'email' => $user->email,
            'company' => $user->company->name,
        ]));

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
