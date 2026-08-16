<?php

declare(strict_types=1);

namespace AML\View;

final class TokenSigner
{
    public function __construct(private string $secret, private int $lifetime = 3600)
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('AML View interaction secrets must contain at least 32 characters.');
        }
    }

    /** @param array<string, mixed> $payload */
    public function sign(array $payload): string
    {
        $payload['issued_at'] = time();
        $payload['expires_at'] = time() + $this->lifetime;
        $payload['nonce'] = bin2hex(random_bytes(16));
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $encoded = self::encode($json);
        $signature = self::encode(hash_hmac('sha256', $encoded, $this->secret, true));
        return $encoded . '.' . $signature;
    }

    /** @return array<string, mixed> */
    public function verify(string $token): array
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, '');
        $expected = self::encode(hash_hmac('sha256', $encoded, $this->secret, true));
        if ($encoded === '' || !hash_equals($expected, $signature)) {
            throw new \UnexpectedValueException('Invalid AML View interaction token.');
        }

        $payload = json_decode(self::decode($encoded), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)
            || !isset($payload['issued_at'], $payload['expires_at'], $payload['nonce'])
            || !is_int($payload['issued_at'])
            || !is_int($payload['expires_at'])
            || !is_string($payload['nonce'])) {
            throw new \UnexpectedValueException('Malformed AML View interaction token.');
        }
        if ($payload['expires_at'] < time() || $payload['expires_at'] > $payload['issued_at'] + $this->lifetime) {
            throw new \UnexpectedValueException('Expired AML View interaction token.');
        }

        return $payload;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) {
            throw new \UnexpectedValueException('Malformed AML View token encoding.');
        }
        return $decoded;
    }
}
