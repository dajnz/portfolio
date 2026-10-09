<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Commands;

use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\Infra\Services\PasswordGenerator;
use Illuminate\Console\Command;

/**
 * `admin:reset-password` — generates a new random password, hashes it,
 * clears the TOTP lockout state and bumps the session version (invalidating
 * all existing sessions). Runs without prompts when `--login` and
 * `--password` are both provided (CI/scripted setups).
 */
final class ResetAdminPasswordCommand extends Command
{
    protected $signature = 'admin:reset-password
        {--login= : Login of the admin to reset (defaults to the sole admin record)}
        {--password= : Explicit password instead of generating a random one}';

    protected $description = 'Reset the admin password (also clears the TOTP lockout and logs out all sessions)';

    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly AdminTimedOtpRepositoryInterface $otpRepository,
        private readonly PasswordGenerator $passwordGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $admin = $this->option('login') !== null
            ? $this->repository->findByLoginOrEmail((string) $this->option('login'))
            : $this->repository->findFirst();

        if ($admin === null) {
            $this->error('No admin account found.');

            return self::FAILURE;
        }

        $explicitPassword = $this->option('password');
        $scripted = $this->option('login') !== null && $explicitPassword !== null;

        if ($this->input->isInteractive() && ! $scripted) {
            if (! $this->confirm('Reset the admin password? This clears the TOTP lockout and logs out all sessions.', false)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $password = $explicitPassword ?? $this->passwordGenerator->generate();

        $this->repository->changePassword($admin->toId(), $password);
        $this->otpRepository->saveForAdmin($admin->toId(), $admin->toTimedOtp()->withFailuresReset());
        $this->repository->incrementSessionVersion($admin->toId());

        $this->newLine();
        $this->line('╔'.str_repeat('═', 42).'╗');
        $this->line('║  '.str_pad('Admin password reset', 40).'║');
        $this->line('║  '.str_pad('Login:    '.$admin->toLogin(), 40).'║');
        $this->line('║  '.str_pad('Password: '.$password, 40).'║');
        $this->line('╚'.str_repeat('═', 42).'╝');
        $this->newLine();

        return self::SUCCESS;
    }
}
