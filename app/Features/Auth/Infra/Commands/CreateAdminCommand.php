<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Commands;

use App\Features\Auth\App\UseCases\CreateAdminUseCase;
use App\Features\Auth\Domain\Exceptions\AdminAlreadyExistsException;
use App\Features\Auth\Infra\Services\PasswordGenerator;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * `admin:create` — the sole path to initial credential creation. Creates
 * the single admin record (or, with `--force`, overwrites the existing
 * admin's login/email only — the password is preserved and must be reset
 * via `admin:reset-password`). The password is a random 24-char string
 * validated against the password rules (uncompromised() with up to 10
 * retries). Credentials are output to the terminal only — never persisted
 * outside the bcrypt hash.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
        {--force : Overwrite an existing admin\'s login/email (password is preserved — use admin:reset-password)}
        {--login= : Admin login (required in non-interactive mode)}
        {--email= : Admin email (required in non-interactive mode)}
        {--password= : Admin password (required in non-interactive mode unless --force; generated when omitted interactively)}';

    protected $description = 'Create the initial admin account';

    public function __construct(
        private readonly CreateAdminUseCase $createAdmin,
        private readonly PasswordGenerator $passwordGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $adminExists = $this->createAdmin->exists();

        if ($adminExists && ! $this->option('force')) {
            $this->error('An admin account already exists. Use --force to overwrite login/email, or run admin:reset-password to reset the password.');

            return self::FAILURE;
        }

        $login = $this->option('login');
        $email = $this->option('email');
        $password = $this->option('password');

        if (! $this->input->isInteractive()) {
            $missing = [];
            $login === null && $missing[] = '--login';
            $email === null && $missing[] = '--email';
            ! $adminExists && $password === null && $missing[] = '--password';

            if ($missing !== []) {
                $this->error('Missing required options in non-interactive mode: '.implode(', ', $missing).'.');

                return self::FAILURE;
            }
        } else {
            $login ??= $this->ask('Login', 'admin');
            $email ??= $this->ask('Email');

            if ($login === null || $email === null || $login === '' || $email === '') {
                $this->error('Login and email are required.');

                return self::FAILURE;
            }

            $password ??= $this->passwordGenerator->generate();
        }

        try {
            $created = $this->createAdmin->create(
                (string) $login,
                (string) $email,
                $password === null ? null : (string) $password,
                (bool) $this->option('force'),
            );
        } catch (AdminAlreadyExistsException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($created->created) {
            $this->outputCredentialsBox(
                'Admin account created',
                $created->login,
                (string) $password,
                (string) $email,
            );

            return self::SUCCESS;
        }

        $this->outputCredentialsBox('Admin account overwritten', $created->login, 'unchanged', (string) $email);

        return self::SUCCESS;
    }

    private function outputCredentialsBox(string $title, string $login, string $password, string $email): void
    {
        $this->newLine();

        $this->line('╔'.str_repeat('═', 42).'╗');
        $this->line($this->boxLine($title));
        $this->line($this->boxLine('Login:    '.$login));
        $this->line($this->boxLine('Password: '.$password));
        $this->line($this->boxLine('Email:    '.$email));
        $this->line('╚'.str_repeat('═', 42).'╝');

        $this->newLine();
    }

    private function boxLine(string $text): string
    {
        return '║  '.str_pad($text, 40).'║';
    }
}
