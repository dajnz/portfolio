<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Portfolio;

use App\Features\Portfolio\Infra\Models\FeedbackModel;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

describe('FeedbackController (admin)', function (): void {
    it('requires authentication for the list', function (): void {
        $this->get('/admin/feedback')->assertRedirect('/admin/login');
    });

    it('lists feedback newest first with the cursor meta', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        FeedbackModel::factory()->create(['sender_name' => 'Old', 'created_at' => now()->subDay()]);
        FeedbackModel::factory()->create(['sender_name' => 'New', 'is_read' => true]);

        $this->get('/admin/feedback')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/FeedbackListPage')
                ->has('feedback', 2)
                ->where('feedback.0.sender_name', 'New')
                ->where('feedback.0.is_read', true)
                ->where('feedback.1.sender_name', 'Old')
                ->where('feedback.1.is_read', false)
                ->where('meta.total', 2)
                ->where('meta.sort_column', 'id')
                ->where('meta.sort_direction', 'desc'));
    });

    it('shows a single feedback item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $item = FeedbackModel::factory()->create(['message' => 'Hello there!']);

        $this->get("/admin/feedback/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/FeedbackDetailPage')
                ->where('item.id', $item->id)
                ->where('item.message', 'Hello there!')
                ->where('item.is_read', false)
                ->where('item.is_forwarded', false));
    });

    it('returns 404 for a missing feedback item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->get('/admin/feedback/99999')->assertNotFound();
    });

    it('marks a feedback item as read and forwarded', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $item = FeedbackModel::factory()->create();

        $this->patchJson("/admin/feedback/{$item->id}/read")->assertOk()
            ->assertJson(['data' => ['id' => $item->id, 'is_read' => true]]);

        $this->patchJson("/admin/feedback/{$item->id}/forwarded")->assertOk()
            ->assertJson(['data' => ['id' => $item->id, 'is_forwarded' => true]]);

        expect($item->fresh()->is_read)->toBeTrue()
            ->and($item->fresh()->is_forwarded)->toBeTrue();
    });

    it('deletes a feedback item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $item = FeedbackModel::factory()->create();

        $this->delete("/admin/feedback/{$item->id}")->assertRedirect();

        expect(FeedbackModel::query()->count())->toBe(0);
    });
});
