<?php

namespace App\Services\Updates;

final class UpdatePaths
{
    /**
     * Top-level entries an update package may write.
     */
    public const ALLOWED_TOP_LEVEL = [
        '.editorconfig',
        '.env.example',
        'app',
        'artisan',
        'bootstrap',
        'composer.json',
        'composer.lock',
        'config',
        'database',
        'package.json',
        'package-lock.json',
        'phpunit.xml',
        'public',
        'README.md',
        'SECURITY.md',
        'UPDATING.md',
        'resources',
        'routes',
        'tests',
        'vendor',
        'vite.config.js',
    ];

    /**
     * Customer-owned data an update must never touch. The updater also refuses
     * to back up or restore these paths, so a malformed package cannot use the
     * restore path to overwrite them.
     */
    public const PROTECTED = [
        '.env',
        '.env.local',
        '.env.production',
        '.env.staging',
        '.htaccess',
        'php.ini',
        'storage',
        'modules',
        'public/uploads',
        'public/.htaccess',
        'public/.user.ini',
        'resources/views/theme/custom',
        'bootstrap/cache',
        'node_modules',
        'dist',
        '.git',
        'database/database.sqlite',
        'database/database.sqlite-journal',
        'database/database.sqlite-wal',
        'database/database.sqlite-shm',
    ];

    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^\./#', '', $path) ?: $path;

        return ltrim($path, '/');
    }

    public static function isSafe(string $path): bool
    {
        $path = self::normalize($path);

        if ($path === '' || strlen($path) > 512) {
            return false;
        }

        if (str_contains($path, "\0") || str_contains($path, ':')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    public static function isProtected(string $path): bool
    {
        $path = self::normalize($path);

        if ($path === '.env.example') {
            return false;
        }

        foreach (self::PROTECTED as $protected) {
            if ($path === $protected || str_starts_with($path, $protected.'/')) {
                return true;
            }
        }

        if (str_starts_with($path, '.env.')) {
            return true;
        }

        return str_starts_with($path, 'database/') && str_contains(basename($path), '.sqlite');
    }

    public static function isAllowed(string $path): bool
    {
        $path = self::normalize($path);

        if (! self::isSafe($path) || self::isProtected($path)) {
            return false;
        }

        $top = explode('/', $path)[0];

        return in_array($top, self::ALLOWED_TOP_LEVEL, true);
    }

    public static function absolute(string $root, string $path): string
    {
        return rtrim($root, '/\\').'/'.self::normalize($path);
    }
}
