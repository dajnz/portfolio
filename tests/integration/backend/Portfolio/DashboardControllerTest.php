<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Portfolio;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

describe('DashboardController', function (): void {
    it('requires authentication', function (): void {
        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('renders the stats per B32', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $publishedId = DB::table('blog_articles')->insertGetId([
            'slug' => 'published', 'is_published' => true, 'is_featured' => false,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $unpublishedId = DB::table('blog_articles')->insertGetId([
            'slug' => 'unpublished', 'is_published' => false, 'is_featured' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $trashedId = DB::table('blog_articles')->insertGetId([
            'slug' => 'trashed', 'is_published' => true, 'is_featured' => false,
            'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('article_views')->insert([
            ['article_id' => $publishedId, 'views' => 10],
            ['article_id' => $unpublishedId, 'views' => 7],
            ['article_id' => $trashedId, 'views' => 3],
        ]);

        DB::table('projects')->insert([
            ['slug' => 'p1', 'is_published' => true, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now(), 'tech_tags_count' => 0],
            ['slug' => 'p2', 'is_published' => false, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now(), 'tech_tags_count' => 0],
            ['slug' => 'p3', 'is_published' => true, 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'tech_tags_count' => 0],
        ]);

        $this->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/DashboardPage')
                ->where('stats.total_views', 20)
                ->where('stats.blog_views', 10)
                ->where('stats.projects_count', 2)
                ->where('stats.articles_count', 2));
    });
});
