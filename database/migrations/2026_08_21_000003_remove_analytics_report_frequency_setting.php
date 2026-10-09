<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Removes the `analytics_report_frequency` settings row (ADR-0017,
     * step 11.3.1): the periodic report cadence setting died with the
     * old report pipeline — daily metrics are always generated, so no
     * frequency setting exists anymore (the `SettingsSeeder` stops
     * seeding the key in the same step; this migration cleans up
     * existing installs).
     */
    public function up(): void
    {
        DB::table('settings')->where('key', 'analytics_report_frequency')->delete();
    }

    /**
     * Reverse the migrations. The row is not restored: the setting no
     * longer exists and the seeder no longer provides a default value.
     */
    public function down(): void
    {
    }
};
