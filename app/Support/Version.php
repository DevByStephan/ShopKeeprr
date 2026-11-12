<?php

namespace App\Support;

final class Version
{
    /**
     * Version of the code that is currently booted.
     */
    public static function current(): string
    {
        return (string) config('nexuscart.version', '1.0.0');
    }

    /**
     * Module contract version this release implements.
     */
    public static function moduleApi(): string
    {
        return (string) config('nexuscart.module_api', '1.0');
    }

    /**
     * The canonical payload covered by a release signature.
     *
     * Changing this format breaks every previously signed release, so it is
     * versioned explicitly.
     */
    public static function signaturePayload(string $version, string $sha256, int $size): string
    {
        return "nexuscart-update-v1\n{$version}\n".strtolower($sha256)."\n{$size}";
    }

    public static function normalize(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }

    public static function compare(string $left, string $right): int
    {
        return version_compare(self::normalize($left), self::normalize($right));
    }

    /**
     * Pad a version to major.minor.patch so "1.2" and "1.2.0" compare equally.
     */
    public static function full(string $version): string
    {
        $version = self::normalize($version);
        $version = preg_replace('/[-+].*$/', '', $version) ?: '0';

        $parts = array_map(
            static fn (string $part): int => (int) $part,
            explode('.', $version)
        );

        return implode('.', array_pad(array_slice($parts, 0, 3), 3, 0));
    }

    public static function isNewer(string $candidate, string $current): bool
    {
        return self::compare($candidate, $current) > 0;
    }
}
