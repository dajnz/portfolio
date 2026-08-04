<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Composes SettingsSeeder + FeatureFlagSeeder (per assumption B7).
     * The single-row entities (about_info, resume_config) and demo content
     * land with their feature phases (4.x+). Must never touch the `admins`
     * table.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            FeatureFlagSeeder::class,
        ]);
    }
}
