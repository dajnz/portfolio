<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use Illuminate\Database\Seeder;

/**
 * Seed the application's database (authentication.md:425,
 * database.md:617): composes SettingsSeeder + FeatureFlagSeeder + the
 * single-row entities (via SingleRowEntitySeeder) + the rich bilingual
 * demo content (via DemoContentSeeder). Must never touch the `admins`
 * table.
 *
 * Idempotent: the settings/flags seeders refresh values, the single-row
 * entities are refreshed to their demo state (SingleRowEntitySeeder
 * upserts, incl. the RustFS demo-photo provisioning), and the demo
 * content is only inserted into an empty content database — re-running
 * `db:seed` on a database that already holds content leaves the content
 * untouched.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            FeatureFlagSeeder::class,
            SingleRowEntitySeeder::class,
        ]);

        if ($this->contentDatabaseIsEmpty()) {
            $this->call(DemoContentSeeder::class);
        }
    }

    private function contentDatabaseIsEmpty(): bool
    {
        return ! ArticleModel::query()->exists()
            && ! ProjectModel::query()->exists()
            && ! SkillGroupModel::query()->exists();
    }
}
