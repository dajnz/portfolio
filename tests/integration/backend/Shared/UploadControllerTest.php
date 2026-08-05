<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('UploadController auth', function (): void {
    beforeEach(function (): void {
        Storage::fake('rustfs');
    });

    it('requires authentication for store and delete', function (): void {
        $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->image('x.png'),
            'entity_type' => 'blog',
        ])->assertUnauthorized();

        $this->deleteJson('/api/upload', ['path' => 'blog/anything.png'])->assertUnauthorized();
    });
});

describe('UploadController', function (): void {
    beforeEach(function (): void {
        Storage::fake('rustfs');
        $this->loginAsAdmin($this->createAdmin());
    });

    it('stores an image under the entity-type prefix with a randomized name', function (): void {
        $response = $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->image('my-image.png'),
            'entity_type' => 'blog',
        ])->assertOk();

        $data = $response->json('data');

        expect($data['path'])->toMatch('/^blog\/[A-Za-z0-9]{40}\.png$/')
            ->and($data['url'])->toBe(rtrim((string) config('app.url'), '/').'/assets/'.$data['path'])
            ->and($data['name'])->toBe('my-image.png')
            ->and($data['size'])->toBeInt()
            ->and(Storage::disk('rustfs')->exists($data['path']))->toBeTrue();
    });

    it('supports the projects and about entity types', function (): void {
        foreach (['projects', 'about'] as $entityType) {
            $path = $this->postJson('/api/upload', [
                'file' => UploadedFile::fake()->image('p.webp'),
                'entity_type' => $entityType,
            ])->assertOk()->json('data.path');

            expect(Storage::disk('rustfs')->exists($path))->toBeTrue();
        }
    });

    it('rejects an unknown entity_type', function (): void {
        $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->image('x.png'),
            'entity_type' => 'avatar',
        ])->assertUnprocessable()->assertJsonValidationErrors('entity_type');
    });

    it('rejects an oversized image (over 3072 KB)', function (): void {
        $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->create('big.png', 4096, 'image/png'),
            'entity_type' => 'blog',
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    });

    it('rejects a PDF for a non-about entity_type', function (): void {
        $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            'entity_type' => 'projects',
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    });

    it('accepts an about PDF up to 5120 KB and rejects anything larger', function (): void {
        $okPath = $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->create('resume.pdf', 5000, 'application/pdf'),
            'entity_type' => 'about',
        ])->assertOk()->json('data.path');

        expect(Storage::disk('rustfs')->exists($okPath))->toBeTrue();

        $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->create('resume.pdf', 6000, 'application/pdf'),
            'entity_type' => 'about',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file may not be greater than 5120 kilobytes.']);
    });

    it('caps about images at 3072 KB even though the entity accepts PDFs', function (): void {
        $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->create('photo.png', 4000, 'image/png'),
            'entity_type' => 'about',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file may not be greater than 3072 kilobytes.']);
    });

    it('deletes an uploaded file and returns 200 with no body', function (): void {
        $path = $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->image('x.png'),
            'entity_type' => 'blog',
        ])->json('data.path');

        $this->deleteJson('/api/upload', ['path' => $path])->assertOk();

        expect(Storage::disk('rustfs')->exists($path))->toBeFalse();
    });

    it('returns 404 for a missing path and 422 for an invalid payload', function (): void {
        $this->deleteJson('/api/upload', ['path' => 'blog/never-uploaded.png'])->assertNotFound();

        $this->deleteJson('/api/upload', [])->assertUnprocessable()->assertJsonValidationErrors('path');
    });
});
