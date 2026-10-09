<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Throwable;

/**
 * The mail transport for the 3rd-party REST API approach (ADR-0016,
 * Mailgun/Mailchimp-style): a single bearer token + custom API endpoint.
 *
 * The message is serialized into a generic JSON payload
 * `{from, to, subject, html, text}` and POSTed to the configured
 * `endpoint` with an `Authorization: Bearer {token}` header. Any non-2xx
 * response or connection error surfaces as a `TransportException`, which
 * fails the send like any other transport would.
 *
 * Registered via `Mail::extend('api', ...)` in
 * `SettingsMailConfigProvider::boot()`; the mailer config array
 * (`config('mail.mailers.api')`) supplies the endpoint and the token.
 */
final class ApiMailTransport extends AbstractTransport
{
    public function __construct(
        private readonly array $config,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        try {
            Http::withToken($this->config['token'] ?? '')
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint(), $this->payload($email, $envelope))
                ->throw();
        } catch (TransportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TransportException(
                sprintf('Request to the mail API failed: %s.', $exception->getMessage()),
                is_int($exception->getCode()) ? $exception->getCode() : 0,
                $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $email, Envelope $envelope): array
    {
        return [
            'from' => $envelope->getSender()->toString(),
            'to' => $this->stringifyAddresses($this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
        ];
    }

    private function endpoint(): string
    {
        return (string) ($this->config['endpoint'] ?? '');
    }

    /**
     * @return Address[]
     */
    private function getRecipients(Email $email, Envelope $envelope): array
    {
        return array_filter(
            $envelope->getRecipients(),
            static fn (Address $address): bool => ! in_array($address, array_merge($email->getCc(), $email->getBcc()), true),
        );
    }

    public function __toString(): string
    {
        return 'api';
    }
}
