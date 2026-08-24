<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Commands;

use App\Features\Backup\App\UseCases\GenerateEncryptionKeyUseCase;
use Illuminate\Console\Command;

/**
 * `backup:generate-key` (backup.md:463,510): creates a fresh active
 * encryption key (32 random bytes, base64 — 44 chars) and prints the
 * raw value exactly once to the terminal. The admin saves it offline
 * (backup.md:500); the value is never shown again.
 */
final class GenerateEncryptionKeyCommand extends Command
{
    protected $signature = 'backup:generate-key
        {--name= : Key name (default: initial-key)}';

    protected $description = 'Generate a new active backup encryption key and print the raw value once';

    public function handle(GenerateEncryptionKeyUseCase $generate): int
    {
        $name = (string) ($this->option('name') ?? 'initial-key');

        $key = $generate->generate($name);

        $this->info(sprintf('Encryption key "%s" (#%d) created and activated.', $key->toName(), $key->toId()));
        $this->warn('Save the raw key value below offline — it will never be shown again:');
        $this->line($key->toKeyValue());

        return self::SUCCESS;
    }
}
