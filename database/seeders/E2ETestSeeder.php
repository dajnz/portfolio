<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * E2E test seeder (testing.md:550-552, authentication.md:427) — the sole
 * exception to the "no seeder creates admin credentials" rule. Idempotent:
 * truncates every table (`TRUNCATE ... RESTART IDENTITY CASCADE`) before
 * inserting, so a fresh DB and a re-run produce identical state.
 *
 * Playwright's `global-setup.ts` is the only caller (CI + local e2e). The
 * seeder refuses to run in production. Demo content (articles, projects,
 * skills, experience items) extends this class in the feature steps
 * (4.x-8.x) — this step seeds the admin account and the shared lookups.
 */
final class E2ETestSeeder extends Seeder
{
    /** Known credentials for `auth.setup.ts` (testing.md:550). */
    public const ADMIN_LOGIN = 'e2e-admin';

    public const ADMIN_EMAIL = 'e2e-admin@example.com';

    public const ADMIN_PASSWORD = 'E2e-Admin-Password-123';

    /**
     * Databases this seeder may truncate: the local e2e database
     * (`portfolio_e2e`, provisioned by `make test-e2e`) and the CI test
     * database (`portfolio_test`, devops.md CI job). Anything else —
     * notably the dev database — is refused, so a `npx playwright test`
     * run without the make-provided env overrides fails loudly instead of
     * truncating the dev data.
     */
    private const ALLOWED_DATABASES = ['portfolio_e2e', 'portfolio_test'];

    public function run(): void
    {
        $database = DB::connection()->getDatabaseName();

        if (app()->environment('production')) {
            throw new \RuntimeException('E2ETestSeeder must never run in production.');
        }

        if (! in_array($database, self::ALLOWED_DATABASES, true)) {
            throw new \RuntimeException(
                'E2ETestSeeder refuses to truncate database "'.$database.'" — run the e2e suite via `make test-e2e` (or point DB_DATABASE at an allowed e2e/test database: '.implode(', ', self::ALLOWED_DATABASES).').',
            );
        }

        $this->truncateAllTables();

        $this->call([
            SettingsSeeder::class,
            FeatureFlagSeeder::class,
        ]);

        $this->seedAdmin();
    }

    /**
     * Truncates every table with identity restart and cascade — the FK
     * order is irrelevant because CASCADE resolves dependencies, and the
     * idempotency contract (testing.md:552) survives any schema evolution.
     *
     * The `migrations` table is preserved: it tracks the applied schema,
     * and wiping it would make the next `php artisan migrate` re-create
     * tables that already exist (Duplicate table 42P07).
     */
    private function truncateAllTables(): void
    {
        $tables = DB::select(
            "SELECT tablename
             FROM pg_tables
             WHERE schemaname = 'public'
               AND tablename <> 'migrations'",
        );

        $names = array_map(
            static fn (object $row): string => '"'.$row->tablename.'"',
            $tables,
        );

        if ($names === []) {
            return;
        }

        DB::statement('TRUNCATE TABLE '.implode(', ', $names).' RESTART IDENTITY CASCADE');
    }

    private function seedAdmin(): void
    {
        AdminModel::query()->create([
            'name' => self::ADMIN_LOGIN,
            'login' => self::ADMIN_LOGIN,
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
            'totp_enabled' => false,
            'totp_failed_attempts' => 0,
            'session_version' => 0,
        ]);
    }
}
