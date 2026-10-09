<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Removes the `analytics_report_frequency` settings row: daily
     * metrics are always generated, so no frequency setting exists. The
     * `SettingsSeeder` provides no default for this key; this migration
     * cleans up existing installs.
     */
    public function up(): void
    {
        DB::table('settings')->where('key', 'analytics_report_frequency')->delete();
    }

    /**
     * The row is not restored: the setting is obsolete and the seeder
     * provides no default value.
     */
    public function down(): void
    {
    }
};
