<?php

namespace App\Support;

final class VersionConstraint
{
    /**
     * Check a version against a constraint such as:
     *   ">=1.0.0 <2.0.0"
     *   "^1.2"
     *   "~1.2.3"
     *   "1.2.*"
     *   ">=1.0.0 || >=1.3.0"
     *
     * An empty constraint means "any version". Invalid constraints fail closed.
     */
    public static function satisfies(string $version, ?string $constraint): bool
    {
        $constraint = trim((string) $constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        // Allow ">= 1.2" with a space after the operator.
        $constraint = preg_replace('/(>=|<=|==|=|>|<|\^|~)\s+/', '$1', $constraint) ?: $constraint;

        foreach (preg_split('/\s*\|\|?\s*/', $constraint) ?: [] as $alternative) {
            if ($alternative === '') {
                continue;
            }

            if (self::satisfiesAll($version, $alternative)) {
                return true;
            }
        }

        return false;
    }

    private static function satisfiesAll(string $version, string $group): bool
    {
        $tokens = preg_split('/[\s,]+/', trim($group), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (! self::satisfiesToken(Version::full($version), $token)) {
                return false;
            }
        }

        return true;
    }

    private static function satisfiesToken(string $version, string $token): bool
    {
        if ($token === '*' || $token === '') {
            return true;
        }

        if (str_starts_with($token, '^')) {
            [$lower, $upper] = self::caretRange(substr($token, 1));

            return $lower !== null
                && version_compare($version, $lower, '>=')
                && version_compare($version, $upper, '<');
        }

        if (str_starts_with($token, '~')) {
            [$lower, $upper] = self::tildeRange(substr($token, 1));

            return $lower !== null
                && version_compare($version, $lower, '>=')
                && version_compare($version, $upper, '<');
        }

        if (str_contains($token, '*')) {
            return self::wildcard($version, $token);
        }

        if (preg_match('/^(>=|<=|==|=|>|<)(.+)$/', $token, $matches)) {
            $target = Version::full($matches[2]);

            return match ($matches[1]) {
                '>=' => version_compare($version, $target, '>='),
                '<=' => version_compare($version, $target, '<='),
                '>' => version_compare($version, $target, '>'),
                '<' => version_compare($version, $target, '<'),
                default => version_compare($version, $target, '=='),
            };
        }

        if (! preg_match('/^\d+(\.\d+){0,2}$/', $token)) {
            return false;
        }

        return version_compare($version, Version::full($token), '==');
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private static function caretRange(string $raw): array
    {
        [$lower, $parts] = self::lowerBound($raw);

        if ($lower === null) {
            return [null, '0.0.0'];
        }

        [$major, $minor, $patch] = $parts;

        $upper = match (true) {
            $major > 0 => ($major + 1).'.0.0',
            $minor > 0 => '0.'.($minor + 1).'.0',
            default => '0.0.'.($patch + 1),
        };

        return [$lower, $upper];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private static function tildeRange(string $raw): array
    {
        [$lower, $parts, $given] = self::lowerBound($raw);

        if ($lower === null) {
            return [null, '0.0.0'];
        }

        [$major, $minor] = $parts;

        $upper = $given >= 2
            ? $major.'.'.($minor + 1).'.0'
            : ($major + 1).'.0.0';

        return [$lower, $upper];
    }

    /**
     * @return array{0: string|null, 1: array{0:int,1:int,2:int}, 2:int}
     */
    private static function lowerBound(string $raw): array
    {
        $raw = Version::normalize($raw);

        if (! preg_match('/^\d+(\.\d+){0,2}$/', $raw)) {
            return [null, [0, 0, 0], 0];
        }

        $given = explode('.', $raw);
        $parts = array_pad(array_map('intval', $given), 3, 0);

        return [implode('.', $parts), $parts, count($given)];
    }

    private static function wildcard(string $version, string $token): bool
    {
        $token = Version::normalize($token);
        $parts = explode('.', $token);
        $numeric = [];

        foreach ($parts as $part) {
            if ($part === '*') {
                break;
            }

            if (! ctype_digit($part)) {
                return false;
            }

            $numeric[] = (int) $part;
        }

        if ($numeric === []) {
            return true;
        }

        $lower = implode('.', array_pad($numeric, 3, 0));

        if (count($numeric) === 1) {
            $upper = ($numeric[0] + 1).'.0.0';
        } elseif (count($numeric) === 2) {
            $upper = $numeric[0].'.'.($numeric[1] + 1).'.0';
        } else {
            $upper = $numeric[0].'.'.$numeric[1].'.'.($numeric[2] + 1);
        }

        return version_compare($version, $lower, '>=')
            && version_compare($version, $upper, '<');
    }
}
