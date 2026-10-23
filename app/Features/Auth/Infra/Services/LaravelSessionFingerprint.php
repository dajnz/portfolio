<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Services;

use App\Features\Auth\App\Contracts\SessionFingerprintInterface;

/**
 * Keyed-HMAC session fingerprint over `user_agent|ip_network`. The IP is
 * reduced to its network (IPv4 /24, IPv6 /64 by default) so address changes
 * within the same network do not break the binding, while a different
 * network does.
 */
final class LaravelSessionFingerprint implements SessionFingerprintInterface
{
    public function __construct(
        private readonly string $key,
        private readonly int $ipv4Prefix = 24,
        private readonly int $ipv6Prefix = 64,
    ) {
    }

    public function for(string $ip, ?string $userAgent): string
    {
        return hash_hmac('sha256', ($userAgent ?? '').'|'.$this->network($ip), $this->key);
    }

    private function network(string $ip): string
    {
        $binary = inet_pton($ip);

        if ($binary === false) {
            return $ip;
        }

        // IPv4-mapped IPv6 addresses (::ffff:192.0.2.1) carry an IPv4 client.
        if (strlen($binary) === 16 && str_starts_with($binary, str_repeat("\0", 10)."\xff\xff")) {
            $binary = substr($binary, 12);
        }

        $prefix = strlen($binary) === 4 ? $this->ipv4Prefix : $this->ipv6Prefix;
        $network = inet_ntop($this->mask($binary, $prefix));

        return $network === false ? $ip : $network;
    }

    private function mask(string $address, int $prefix): string
    {
        $bits = min($prefix, strlen($address) * 8);
        $fullBytes = intdiv($bits, 8);
        $partialBits = $bits % 8;

        $mask = str_repeat("\xff", $fullBytes);

        if ($partialBits > 0) {
            $mask .= chr((0xff << (8 - $partialBits)) & 0xff);
        }

        return $address & str_pad($mask, strlen($address), "\0");
    }
}
