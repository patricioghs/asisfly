<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SecretVault
{
    public function encrypt(string $plainText): string
    {
        $plainText = trim($plainText);
        if ($plainText === '') {
            throw new RuntimeException('La clave no puede estar vacia.');
        }

        $appKey = $this->appKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $appKey, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipherText === false) {
            throw new RuntimeException('No se pudo cifrar la credencial.');
        }

        return base64_encode(json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'value' => base64_encode($cipherText),
        ], JSON_THROW_ON_ERROR));
    }

    public function decrypt(?string $payload): ?string
    {
        if (!$payload) {
            return null;
        }

        try {
            $decoded = json_decode((string) base64_decode($payload, true), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return null;
            }

            $plainText = openssl_decrypt(
                (string) base64_decode((string) ($decoded['value'] ?? ''), true),
                'aes-256-gcm',
                $this->appKey(false),
                OPENSSL_RAW_DATA,
                (string) base64_decode((string) ($decoded['iv'] ?? ''), true),
                (string) base64_decode((string) ($decoded['tag'] ?? ''), true)
            );

            return is_string($plainText) && $plainText !== '' ? $plainText : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function last4(string $plainText): string
    {
        $plainText = trim($plainText);
        return substr($plainText, -4) ?: '****';
    }

    private function appKey(bool $strict = true): string
    {
        $raw = trim((string) \env('APP_KEY', ''));
        if ($strict && ($raw === '' || $raw === 'change-me' || str_contains($raw, 'change-this'))) {
            throw new RuntimeException('Configura APP_KEY en el .env antes de guardar claves API.');
        }

        return hash('sha256', $raw !== '' ? $raw : 'asisfly-local-development-key', true);
    }
}
