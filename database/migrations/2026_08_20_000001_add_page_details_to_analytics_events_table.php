<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Analytics events carry the page context: the page type and the
     * entity id (blog article / project detail) the event happened on,
     * used for blog/projects report attribution. The columns are
     * nullable: pages without an entity (landing, lists, experience)
     * carry a type only, and older rows carry neither.
     */
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('page_type', 50)->nullable()->after('event_type');
            $table->bigInteger('page_entity_id')->nullable()->after('page_type');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropColumn(['page_type', 'page_entity_id']);
        });
    }
};
