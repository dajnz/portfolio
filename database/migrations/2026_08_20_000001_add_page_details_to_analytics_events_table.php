<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The frontend now sends the page context with every analytics
     * event (ADR-0017): the page type and the entity id (blog article /
     * project detail) the event happened on — replacing the backend
     * URL matching for the blog/projects report attribution. The
     * columns are nullable: pages without an entity (landing, lists,
     * experience) carry a type only, and rows from before this
     * migration carry neither.
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
