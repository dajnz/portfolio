<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\App\UseCases\UpdateSettingsUseCase;
use App\Features\Settings\Infra\Providers\SettingsMailConfigProvider;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mime\Address;

describe('SettingsMailConfigProvider', function (): void {
    it('registers the api transport and switches the default mailer when an endpoint is configured', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        app(UpdateSettingsUseCase::class)->update([
            'mail_api_endpoint' => 'https://api.example.com/v1/mail',
            'mail_api_token' => 'super-secret-token',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name' => 'Portfolio',
        ]);

        (new SettingsMailConfigProvider($this->app))->configureMail();

        expect(config('mail.default'))->toBe('api')
            ->and(config('mail.mailers.api.endpoint'))->toBe('https://api.example.com/v1/mail')
            ->and(config('mail.mailers.api.token'))->toBe('super-secret-token')
            ->and(config('mail.from.address'))->toBe('noreply@example.com')
            ->and(config('mail.from.name'))->toBe('Portfolio');
    });

    it('keeps the env-driven default mailer when no endpoint is configured', function (): void {
        $defaultDefault = config('mail.default');

        $this->seed(\Database\Seeders\SettingsSeeder::class);

        (new SettingsMailConfigProvider($this->app))->configureMail();

        expect(config('mail.default'))->toBe($defaultDefault)
            ->and(config('mail.default'))->not->toBe('api');
    });

    it('skips the merge without touching config when the settings table is missing', function (): void {
        $defaultDefault = config('mail.default');
        $defaultEndpoint = config('mail.mailers.api.endpoint');

        Schema::drop('settings');

        (new SettingsMailConfigProvider($this->app))->configureMail();

        expect(config('mail.default'))->toBe($defaultDefault)
            ->and(config('mail.mailers.api.endpoint'))->toBe($defaultEndpoint);
    });

    it('delivers mail through the api transport as a JSON POST with the bearer token', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        app(UpdateSettingsUseCase::class)->update([
            'mail_api_endpoint' => 'https://api.example.com/v1/mail',
            'mail_api_token' => 'super-secret-token',
        ]);

        (new SettingsMailConfigProvider($this->app))->configureMail();

        Http::fake([
            'https://api.example.com/v1/mail' => Http::response('', 200),
        ]);

        Mail::to('admin@example.com')->send(new class () extends Mailable {
            public function envelope(): Envelope
            {
                return new Envelope(subject: 'Test subject');
            }

            public function content(): Content
            {
                return new Content(htmlString: '<p>Hello</p>');
            }
        });

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.example.com/v1/mail'
                && $request->hasHeader('Authorization', 'Bearer super-secret-token')
                && $payload['from'] === (new Address(config('mail.from.address'), (string) config('mail.from.name')))->toString()
                && $payload['to'] === ['admin@example.com']
                && $payload['subject'] === 'Test subject'
                && $payload['html'] === '<p>Hello</p>';
        });
    });
});
