# Livewire Starter Kit

A starter kit for new Laravel 13 projects, built on Livewire 4, Flux Pro, Fortify and Sanctum.

## Features

- **Companies:** every user belongs to one company and is either an **admin** or a **user**. Signing up creates a new company with the user as its admin.
- **Member management** (admins): create users, invite them by email, promote or demote, activate or deactivate, and remove them.
- **Company settings** (admins): rename or delete the company with all its users.
- **API tokens:** every user can create Sanctum tokens for the API.
- **Authentication:** login, registration, email verification, password reset, two-factor authentication and passkeys (Fortify).
- **Feature flags:** turn public sign-up and simple users on or off with environment variables.
- **Console commands:** create users and test the mail settings.
- **Tooling:** Pest, Pint, Larastan, Rector and Laravel Boost.

## Starting a new project

1. On GitHub, click **Use this template** on this repository to create the new project's repository, then clone it.
2. Rename the project:
   - `composer.json`: change `name` and `description`.
   - `.env.example`: set `APP_NAME`.
   - `README.md`: replace this file with the project's own.
3. Follow [Installation](#installation).

## Requirements

- PHP 8.4+ with the `sqlite3` (or your database's) extension
- Composer
- Node.js and npm
- A [Flux Pro](https://fluxui.dev) license

## Installation

1. Clone the repository and enter the project directory.

2. Add your Flux Pro credentials so Composer can download the package:

   ```bash
   composer config http-basic.composer.fluxui.dev "<your-email>" "<your-license-key>"
   ```

   This writes an `auth.json` file, which is git-ignored.

3. Run the setup script. It installs PHP and npm dependencies, creates `.env`, generates the app key, runs the migrations and builds the frontend assets:

   ```bash
   composer run setup
   ```

4. Review `.env` (see [Configuration](#configuration)), then create your first user:

   ```bash
   php artisan user:create
   ```

   For local development you can seed demo data instead (see [Local development](#local-development)).

5. Start the app:

   ```bash
   composer run dev
   ```

## Configuration

Besides the standard Laravel settings (`APP_URL`, `DB_*`, `MAIL_*`), these variables control the app:

| Variable | Default | Description |
| --- | --- | --- |
| `REGISTRATION_ENABLED` | `true` | Allows public sign-up. Each sign-up creates a new company with the user as its admin. When `false`, the sign-up page and link are removed. |
| `SIMPLE_USERS_ENABLED` | `true` | Allows admins to create, invite, or demote to simple (non-admin) users. When `false`, pending invitations can no longer be accepted. |

After changing `.env` on a server with cached config, run `php artisan config:clear` (or `config:cache` again).

Mail must be configured for invitations and email verification. Use `php artisan mail:test` to check it.

## Console commands

### `user:create`

Creates a user, either in an existing company or as the admin of a new company. Users created this way are already email verified. This command ignores `REGISTRATION_ENABLED` and `SIMPLE_USERS_ENABLED`.

Run it without options to be prompted for everything:

```bash
php artisan user:create
```

Or pass options:

| Option | Description |
| --- | --- |
| `--name=` | The user's name |
| `--email=` | The user's email address |
| `--password=` | The user's password |
| `--company=` | ID of an existing company to add the user to |
| `--new-company=` | Name of a new company to create, with the user as its admin |
| `--admin` | Make the user an admin of the existing company |

`--company` and `--new-company` cannot be used together. Without either, you are prompted to search for a company or create a new one.

Examples:

```bash
# New company with the user as admin
php artisan user:create --name="Jane Doe" --email=jane@example.com --new-company="Acme"

# Add a user to company 3
php artisan user:create --name="John Doe" --email=john@example.com --company=3

# Add an admin to company 3
php artisan user:create --name="John Doe" --email=john@example.com --company=3 --admin
```

Omit `--password` to type it at a hidden prompt, which keeps it out of your shell history.

### `mail:test`

Sends a test email with the current mail settings and reports the mailer used, or the error if sending fails.

```bash
php artisan mail:test you@example.com
```

With `MAIL_MAILER=log` the email is written to `storage/logs` instead of being delivered.

## Local development

Seed a demo company with an admin (`admin@example.com`) and a user (`user@example.com`), both with the password `password`:

```bash
php artisan db:seed
```

In the `local` environment the login page shows one-click login links for both accounts. The links only log in to these seeded accounts; they do not create missing users.

## Testing

Tests use Pest and an in-memory SQLite database (set in `phpunit.xml`). Create a `.env.testing` once, so tests never read your local `.env`:

```bash
cp .env.example .env.testing
php artisan key:generate --env=testing
```

Then run:

```bash
php artisan test --compact --parallel
```

Code quality tools:

```bash
vendor/bin/pint            # code style
vendor/bin/phpstan analyse # static analysis
vendor/bin/rector --dry-run
```
