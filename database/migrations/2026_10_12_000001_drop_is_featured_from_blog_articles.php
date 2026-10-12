<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Drops the obsolete `is_featured` column: no blog surface ever
     * consumed the flag, so it is removed.
     */
    public function up(): void
    {
        Schema::table('blog_articles', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }

    /**
     * Re-adds the column empty: the previous values are not restored.
     */
    public function down(): void
    {
        Schema::table('blog_articles', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
        });
    }
};
