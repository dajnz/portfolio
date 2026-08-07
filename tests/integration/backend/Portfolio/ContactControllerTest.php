<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Portfolio;

use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Portfolio\Infra\Models\ContactTranslationModel;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

describe('ContactController', function (): void {
    it('requires authentication for the list', function (): void {
        $this->get('/admin/contacts')->assertRedirect('/admin/login');
    });

    it('lists contacts with the cursor meta and per-locale names', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $a = ContactModel::factory()->create(['value' => 'octocat', 'sort_order' => 0]);
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $a->id, 'name' => 'GitHub']);
        ContactTranslationModel::factory()->forLocale('ru')->create(['contact_id' => $a->id, 'name' => 'Гитхаб']);
        $b = ContactModel::factory()->create(['value' => '@alex', 'sort_order' => 1]);
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $b->id, 'name' => 'Telegram']);

        $this->get('/admin/contacts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/ContactsListPage')
                ->has('contacts', 2)
                ->where('contacts.0.name', 'Telegram')
                ->where('contacts.1.name', 'GitHub')
                ->where('contacts.0.include_in_resume', true)
                ->where('meta.total', 2)
                ->where('meta.per_page', 12)
                ->where('meta.current_page', 1)
                ->where('meta.sort_column', 'id')
                ->where('meta.cursors.first.id', $b->id)
                ->where('meta.cursors.last.id', $a->id));
    });

    it('stores a contact with per-locale names', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/contacts', [
            'name' => ['en' => 'GitHub', 'ru' => 'Гитхаб'],
            'value' => 'octocat',
            'icon' => 'simple-icons:github',
            'url' => 'https://github.com/alex',
            'include_in_resume' => true,
            'sort_order' => 3,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = ContactModel::query()->first();
        expect($row)->not->toBeNull()
            ->and($row->value)->toBe('octocat')
            ->and($row->icon)->toBe('simple-icons:github')
            ->and($row->url)->toBe('https://github.com/alex')
            ->and($row->include_in_resume)->toBeTrue()
            ->and($row->sort_order)->toBe(3)
            ->and(ContactTranslationModel::query()->where('locale', 'en')->first()->name)->toBe('GitHub')
            ->and(ContactTranslationModel::query()->where('locale', 'ru')->first()->name)->toBe('Гитхаб');
    });

    it('updates a contact and reconciles its translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $contact = ContactModel::factory()->create(['value' => 'old']);
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $contact->id, 'name' => 'Old']);

        $this->put("/admin/contacts/{$contact->id}", [
            'name' => ['en' => 'New', 'ru' => 'Новый'],
            'value' => 'new-value',
            'icon' => null,
            'include_in_resume' => false,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = $contact->fresh();
        expect($row->value)->toBe('new-value')
            ->and($row->icon)->toBeNull()
            ->and($row->include_in_resume)->toBeFalse()
            ->and(ContactTranslationModel::query()->where('contact_id', $contact->id)->pluck('name')->all())
            ->toBe(['New', 'Новый']);
    });

    it('deletes a contact with its translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $contact = ContactModel::factory()->create();
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $contact->id, 'name' => 'X']);

        $this->delete("/admin/contacts/{$contact->id}")->assertRedirect();

        expect(ContactModel::query()->count())->toBe(0)
            ->and(ContactTranslationModel::query()->count())->toBe(0);
    });

    it('reorders contacts by the zero-indexed payload positions (A4)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $a = ContactModel::factory()->create(['sort_order' => 0]);
        $b = ContactModel::factory()->create(['sort_order' => 1]);
        $c = ContactModel::factory()->create(['sort_order' => 2]);

        $this->post('/admin/contacts/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(ContactModel::query()->find($c->id)->sort_order)->toBe(0)
            ->and(ContactModel::query()->find($a->id)->sort_order)->toBe(1)
            ->and(ContactModel::query()->find($b->id)->sort_order)->toBe(2);
    });

    it('rejects a malformed reorder payload', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/contacts/reorder', ['ids' => ['x']])->assertRedirect()
            ->assertSessionHasErrors('ids.0');

        $this->post('/admin/contacts/reorder', [])->assertRedirect()
            ->assertSessionHasErrors('ids');
    });

    it('validates the contact fields', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/contacts', [
            'name' => ['en' => 'GitHub'],
            'value' => '',
            'url' => 'not-a-url',
        ])->assertRedirect()
            ->assertSessionHasErrors(['value', 'url', 'name.ru']);
    });
});
