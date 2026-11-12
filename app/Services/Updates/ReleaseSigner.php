<?php

namespace App\Services\Updates;

final class ReleaseSigner
{
    public static function verify(Release $release, ?string $publicKey = null): bool
    {
        if ($release->signatureAlgorithm !== 'rsa-sha256') {
            return false;
        }

        $publicKey ??= (string) config('nexuscart.updates.public_key');
        $signature = base64_decode($release->signature, true);

        if ($publicKey === '' || $signature === false) {
            return false;
        }

        $pem = self::publicKeyPem($publicKey);

        if ($pem === '') {
            return false;
        }

        $key = @openssl_pkey_get_public($pem);

        if ($key === false) {
            return false;
        }

        $result = openssl_verify($release->signaturePayload(), $signature, $key, OPENSSL_ALGO_SHA256);

        // Drain errors so a failed verification does not leak into the next one.
        while (openssl_error_string() !== false) {
            //
        }

        return $result === 1;
    }

    /**
     * Accept either a PEM document or base64-encoded SubjectPublicKeyInfo DER.
     */
    public static function publicKeyPem(string $key): string
    {
        $key = trim($key);

        if (str_contains($key, 'BEGIN PUBLIC KEY')) {
            return $key;
        }

        $der = base64_decode(preg_replace('/\s+/', '', $key) ?: '', true);

        if ($der === false || $der === '') {
            return '';
        }

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }
}
