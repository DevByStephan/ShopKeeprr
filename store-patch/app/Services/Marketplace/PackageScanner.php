<?php

namespace App\Services\Marketplace;

use App\Support\VersionConstraint;
use Throwable;
use ZipArchive;

/**
 * Inspects marketplace ZIP archives without extracting or executing them.
 *
 * The scanner is deliberately conservative: critical patterns block a release
 * from becoming the current download, while warnings are surfaced to the
 * marketplace team and the developer but do not stop distribution.
 */
class PackageScanner
{
    public const ENGINE = 'shopkeeprr-package-scanner/1.1';

    public const STATUS_CLEAN = 'clean';

    public const STATUS_WARNING = 'warning';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * The first-party core package legitimately needs process control for the
     * browser installer and the updater. These findings stay visible but do
     * not quarantine a signed core build; all other critical rules still do.
     */
    private const CORE_ALLOWED_CRITICAL_RULES = ['php_shell'];

    private const MAX_ENTRIES = 20000;

    private const MAX_UNCOMPRESSED_BYTES = 268435456;

    private const MAX_FILE_BYTES = 2097152;

    private const MAX_TOTAL_READ_BYTES = 33554432;

    private const MAX_FINDINGS = 150;

    private const CRITICAL_FUNCTIONS = [
        'exec' => 'shell command execution',
        'shell_exec' => 'shell command execution',
        'system' => 'shell command execution',
        'passthru' => 'shell command execution',
        'popen' => 'shell command execution',
        'proc_open' => 'shell command execution',
        'pcntl_exec' => 'shell command execution',
    ];

    private const LEGACY_FUNCTIONS = [
        'create_function' => 'create_function() was removed in PHP 8',
        'each' => 'each() was removed in PHP 8',
        'split' => 'split() was removed in PHP 7',
        'ereg' => 'ereg() was removed in PHP 8',
        'eregi' => 'eregi() was removed in PHP 8',
        'mysql_query' => 'the original mysql extension was removed in PHP 7',
        'mysql_connect' => 'the original mysql extension was removed in PHP 7',
        'mcrypt_encrypt' => 'mcrypt was removed from PHP',
        'mcrypt_decrypt' => 'mcrypt was removed from PHP',
    ];

    /** @var list<array{severity: string, rule: string, message: string, file: string|null, line: int|null}> */
    private array $findings = [];

    /** @var array<string, true> */
    private array $findingKeys = [];

    private int $score = 0;

    private int $filesInspected = 0;

    private bool $dependenciesSkipped = false;

    private bool $trustedCore = false;

    private bool $nestedArchives = false;

    private bool $scanTruncated = false;

    private ?array $composer = null;

    private ?string $composerFile = null;

    private ?array $manifest = null;

    private ?string $manifestFile = null;

    /**
     * @return array{status: string, score: int, findings: list<array<string, mixed>>, engine: string, scanned_at: string, files_inspected: int}
     */
    public function scan(string $archivePath, bool $trustedCore = false): array
    {
        $this->reset();
        $this->trustedCore = $trustedCore;

        if (! is_file($archivePath) || ! is_readable($archivePath)) {
            $this->add('critical', 'archive_missing', 'The uploaded ZIP could not be read for scanning.');

            return $this->result();
        }

        if (! class_exists(ZipArchive::class)) {
            $this->add('critical', 'scanner_unavailable', 'The server does not have the ZIP extension, so the package could not be inspected.');

            return $this->result();
        }

        $zip = new ZipArchive;
        $opened = $zip->open($archivePath, ZipArchive::RDONLY);

        if ($opened !== true) {
            $this->add('critical', 'invalid_zip', 'The uploaded archive is not a valid ZIP file.');

            return $this->result();
        }

        try {
            $this->inspectArchive($zip);
        } catch (Throwable $exception) {
            $this->add('critical', 'scan_failed', 'The archive could not be fully inspected: '.$exception->getMessage());
        } finally {
            $zip->close();
        }

        return $this->result();
    }

    private function inspectArchive(ZipArchive $zip): void
    {
        if ($zip->numFiles <= 0) {
            $this->add('critical', 'empty_archive', 'The archive contains no files.');

            return;
        }

        if ($zip->numFiles > self::MAX_ENTRIES) {
            $this->add('critical', 'archive_too_many_files', 'The archive contains more than '.number_format(self::MAX_ENTRIES).' files and was not inspected.');

            return;
        }

        $totalBytes = 0;
        $compressedBytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);

            if (! is_array($stat)) {
                continue;
            }

            $name = $this->normaliseEntryName((string) ($stat['name'] ?? ''));

            if ($name === '' || str_ends_with($name, '/') || $this->ignoredPath($name)) {
                continue;
            }

            if ($this->hasTraversal($name)) {
                $this->add('critical', 'zip_slip', 'The archive contains a path that escapes the install directory.', $name);

                continue;
            }

            if ($this->dependencyPath($name)) {
                $this->dependenciesSkipped = true;

                continue;
            }

            if ($this->nestedArchivePath($name)) {
                $this->nestedArchives = true;

                continue;
            }

            $totalBytes += max(0, (int) ($stat['size'] ?? 0));
            $compressedBytes += max(0, (int) ($stat['comp_size'] ?? 0));
        }

        if ($compressedBytes > 0 && $totalBytes > 10485760 && ($totalBytes / $compressedBytes) > 100) {
            $this->add('critical', 'zip_bomb', 'The archive has an unsafe compression ratio and was not extracted or inspected.');

            return;
        }

        if ($totalBytes > self::MAX_UNCOMPRESSED_BYTES) {
            $this->add('critical', 'archive_too_large', 'The uncompressed package is larger than 256 MB and was not inspected.');

            return;
        }

        $readBytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);

            if (! is_array($stat)) {
                continue;
            }

            $name = $this->normaliseEntryName((string) ($stat['name'] ?? ''));

            if ($name === '' || str_ends_with($name, '/') || $this->ignoredPath($name) || $this->dependencyPath($name) || $this->hasTraversal($name)) {
                continue;
            }

            $size = max(0, (int) ($stat['size'] ?? 0));

            if ($size > self::MAX_FILE_BYTES) {
                if ($this->codeExtension($name)) {
                    $this->add('warning', 'file_too_large', 'A code file is larger than 1 MB and was not fully inspected.', $name);
                }

                continue;
            }

            if ($readBytes + $size > self::MAX_TOTAL_READ_BYTES) {
                $this->scanTruncated = true;

                break;
            }

            $content = $zip->getFromIndex($index, self::MAX_FILE_BYTES + 1);

            if (! is_string($content)) {
                $this->add('warning', 'unreadable_file', 'A file in the archive could not be read for inspection.', $name);

                continue;
            }

            $readBytes += strlen($content);
            $this->filesInspected++;
            $this->inspectFile($name, $content);
        }

        if ($this->dependenciesSkipped) {
            $this->add('warning', 'bundled_dependencies', 'Bundled vendor/ or node_modules/ code was not inspected. Review those dependencies manually before approval.');
        }

        if ($this->nestedArchives) {
            $this->add('warning', 'nested_archives', 'The package contains nested archives, which were not unpacked or inspected.');
        }

        if ($this->scanTruncated) {
            $this->add('warning', 'scan_truncated', 'The package exceeded the scanner read limit. Later files in the archive were not inspected.');
        }

        $this->inspectCompatibility();
    }

    private function inspectFile(string $name, string $content): void
    {
        $lower = strtolower($name);
        $extension = pathinfo($lower, PATHINFO_EXTENSION);
        $base = basename($lower);

        if ($this->isRootMetadata($name, 'composer.json')) {
            $this->inspectComposer($name, $content);
        }

        foreach (['shopkeeprr.json', 'nexuscart.json', 'module.json', 'plugin.json', 'theme.json'] as $manifestName) {
            if ($this->isRootMetadata($name, $manifestName)) {
                $this->inspectManifest($name, $content);

                break;
            }
        }

        if (in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'inc'], true)) {
            $this->inspectPhp($name, $content);

            return;
        }

        if ($base === '.htaccess' || $base === 'htaccess') {
            $this->inspectHtaccess($name, $content);

            return;
        }

        if (in_array($extension, ['js', 'mjs', 'cjs'], true)) {
            $this->inspectJavascript($name, $content);

            return;
        }

        if (str_contains($content, '<?php') || str_contains($content, '<?=')) {
            $image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp'], true);
            $this->add(
                $image ? 'critical' : 'warning',
                $image ? 'php_in_image' : 'php_in_non_php',
                $image
                    ? 'PHP code was found inside an image file. This is a common webshell disguise.'
                    : 'PHP code was found inside a file that is not normally executed as PHP.',
                $name
            );
        }
    }

    private function inspectPhp(string $name, string $content): void
    {
        try {
            $tokens = token_get_all($content);
        } catch (Throwable) {
            $this->add('warning', 'php_unreadable', 'The PHP file could not be tokenised for inspection.', $name);

            return;
        }

        $significant = [];
        $line = 1;

        foreach ($tokens as $token) {
            $tokenLine = is_array($token) ? (int) ($token[2] ?? $line) : $line;
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;

            if (! in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $significant[] = ['id' => $id, 'text' => $text, 'line' => $tokenLine];
            }

            $line = $tokenLine + substr_count($text, "\n");
        }

        foreach ($significant as $position => $token) {
            if ($token['id'] === T_EVAL) {
                $this->add('critical', 'php_eval', 'eval() executes arbitrary code and is not allowed in a marketplace package.', $name, $token['line']);
            }

            if ($token['text'] === '`') {
                $this->add('critical', 'php_backtick', 'Backtick shell execution was found in the package.', $name, $token['line']);
            }

            if (in_array($token['id'], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
                $literal = $this->nextLiteral($significant, $position + 1);

                if ($literal !== null && preg_match('#(?:https?|ftp)://#i', $literal)) {
                    $this->add('critical', 'php_remote_include', 'The package includes code from a remote URL.', $name, $token['line']);
                }
            }

            $isName = in_array($token['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
                || $token['id'] === null;

            if (! $isName) {
                continue;
            }

            $next = $significant[$position + 1] ?? null;

            if (($next['text'] ?? null) !== '(') {
                continue;
            }

            $previous = $significant[$position - 1] ?? null;

            if (in_array($previous['id'] ?? null, [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }

            $function = strtolower(ltrim($token['text'], '\\'));
            $arguments = $this->callArguments($significant, $position + 1);

            if (isset(self::CRITICAL_FUNCTIONS[$function])) {
                $this->add(
                    'critical',
                    'php_shell',
                    $function.'() can execute shell commands ('.self::CRITICAL_FUNCTIONS[$function].').',
                    $name,
                    $token['line']
                );

                continue;
            }

            if (isset(self::LEGACY_FUNCTIONS[$function])) {
                $this->add('warning', 'php_legacy_api', self::LEGACY_FUNCTIONS[$function].'. This package may stop working on a current server.', $name, $token['line']);
            }

            if ($function === 'extract') {
                if (preg_match('/\$_(?:GET|POST|REQUEST|COOKIE|SERVER|FILES)/i', $arguments)) {
                    $this->add('critical', 'php_extract_superglobal', 'extract() is being fed request data, which can overwrite application variables.', $name, $token['line']);
                } else {
                    $this->add('warning', 'php_extract', 'extract() creates variables from an array and can hide data flow.', $name, $token['line']);
                }
            }

            if ($function === 'unserialize') {
                $this->add('warning', 'php_unserialize', 'unserialize() can lead to object injection if the input is not trusted.', $name, $token['line']);
            }

            if ($function === 'assert') {
                $this->add('warning', 'php_assert', 'assert() can execute a string on older PHP versions.', $name, $token['line']);
            }

            if ($function === 'move_uploaded_file') {
                $this->add('warning', 'php_upload_move', 'move_uploaded_file() was found. Verify the destination cannot receive executable files.', $name, $token['line']);
            }

            if ($function === 'chmod' && preg_match('/\b0?777\b/', $arguments)) {
                $this->add('warning', 'php_world_writable', 'chmod() grants world-writable permissions.', $name, $token['line']);
            }

            if (in_array($function, ['file_put_contents', 'fopen'], true) && preg_match('/\.php\b/i', $arguments)) {
                $this->add('warning', 'php_writes_php', $function.'() can write PHP files, which can become a backdoor if the input is not trusted.', $name, $token['line']);
            }

            if ($function === 'base64_decode' && preg_match('/[A-Za-z0-9+\/]{200,}={0,2}/', $arguments)) {
                $this->add('warning', 'php_encoded_payload', 'base64_decode() is decoding a large encoded string.', $name, $token['line']);
            }

            if ($function === 'parse_str' && ! str_contains($arguments, ',')) {
                $this->add('warning', 'php_parse_str', 'parse_str() without a result array writes variables into the local scope.', $name, $token['line']);
            }

            if ($function === 'preg_replace' && $this->hasPregEvalModifier($arguments)) {
                $this->add('warning', 'php_preg_eval', 'preg_replace() uses the removed /e modifier.', $name, $token['line']);
            }
        }

        if (preg_match_all('/[\'"]([A-Za-z0-9+\/]{400,}={0,2})[\'"]/', $content, $matches)) {
            $this->add('warning', 'php_long_encoded_string', 'A long encoded string was found. Confirm it is data and not an obfuscated payload.', $name);
        }

    }

    private function hasPregEvalModifier(string $arguments): bool
    {
        if (! preg_match('/[\'"](.+?)[\'"]/', $arguments, $matches)) {
            return false;
        }

        $pattern = $matches[1];

        if (! preg_match('~^(.).*\1([a-zA-Z]*)$~s', $pattern, $delimiters)) {
            return false;
        }

        return str_contains(strtolower($delimiters[2]), 'e');
    }

    private function inspectHtaccess(string $name, string $content): void
    {
        $rules = [
            '/\b(?:AddType|AddHandler|SetHandler)\b[^\n]*\bphp/i' => 'An .htaccess rule changes how PHP files are handled.',
            '/\b(?:php_value|php_flag|php_admin_value|php_admin_flag)\b/i' => 'An .htaccess rule injects PHP configuration.',
            '/\bauto_(?:prepend|append)_file\b/i' => 'An .htaccess rule injects a PHP file into every request.',
            '/\bOptions\b[^\n]*\bExecCGI\b/i' => 'An .htaccess rule enables CGI execution.',
        ];

        foreach ($rules as $pattern => $message) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = (int) ($matches[0][1] ?? 0);
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                $this->add('critical', 'htaccess_execution', $message, $name, $line);
            }
        }
    }

    private function inspectJavascript(string $name, string $content): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        foreach ($lines as $index => $line) {
            if (preg_match('/\beval\s*\(/', $line)) {
                $this->add('warning', 'js_eval', 'eval() was found in JavaScript. Prefer parsed data or a safe expression evaluator.', $name, $index + 1);
            }

            if (preg_match('/\bnew\s+Function\s*\(/', $line)) {
                $this->add('warning', 'js_dynamic_function', 'new Function() builds executable code from a string.', $name, $index + 1);
            }

            if (preg_match('/\bchild_process\b/', $line)) {
                $this->add('warning', 'js_child_process', 'The package references Node child_process, which can spawn system commands.', $name, $index + 1);
            }
        }
    }

    private function inspectComposer(string $name, string $content): void
    {
        $data = json_decode($content, true);

        if (! is_array($data)) {
            $this->add('warning', 'composer_invalid', 'composer.json is not valid JSON, so compatibility could not be checked.', $name);

            return;
        }

        if ($this->composer === null) {
            $this->composer = $data;
            $this->composerFile = $name;
        }

        if (! empty($data['scripts']) && is_array($data['scripts'])) {
            $this->add('warning', 'composer_scripts', 'composer.json defines install scripts. They run shell commands during composer install and should be reviewed.', $name);
        }
    }

    private function inspectManifest(string $name, string $content): void
    {
        $data = json_decode($content, true);

        if (! is_array($data)) {
            $this->add('warning', 'manifest_invalid', 'The package descriptor is not valid JSON, so compatibility could not be checked.', $name);

            return;
        }

        if ($this->manifest === null) {
            $this->manifest = $data;
            $this->manifestFile = $name;
        }
    }

    private function inspectCompatibility(): void
    {
        $phpConstraint = $this->firstString([
            data_get($this->composer, 'require.php'),
            data_get($this->manifest, 'requires.php'),
        ]);

        if ($phpConstraint !== null && ! VersionConstraint::allows($phpConstraint, PHP_VERSION)) {
            $this->add(
                'warning',
                'php_incompatible',
                'The package declares PHP '.$phpConstraint.', but this server runs PHP '.PHP_VERSION.'. It may not install or run.',
                $this->manifestFile ?: $this->composerFile
            );
        }

        $laravelConstraint = $this->firstString([
            data_get($this->composer, 'require.laravel/framework'),
        ]);

        if ($laravelConstraint !== null && ! VersionConstraint::allows($laravelConstraint, app()->version())) {
            $this->add(
                'warning',
                'laravel_incompatible',
                'The package targets Laravel '.$laravelConstraint.', but this marketplace runs Laravel '.app()->version().'.',
                $this->composerFile
            );
        }

        $nexusConstraint = $this->firstString([
            data_get($this->manifest, 'requires.shopkeeprr'),
            data_get($this->manifest, 'requires.nexus'),
            data_get($this->manifest, 'requires.nexuscart'),
            data_get($this->manifest, 'requires.core'),
            data_get($this->manifest, 'compatibility.shopkeeprr'),
            data_get($this->manifest, 'compatibility.nexuscart'),
            data_get($this->composer, 'extra.shopkeeprr.requires'),
            data_get($this->composer, 'extra.nexuscart.requires'),
        ]);

        $currentNexus = (string) config('site.shopkeeprr_version', config('site.nexuscart_version', '1.0.0'));

        if ($nexusConstraint !== null && ! VersionConstraint::allows($nexusConstraint, $currentNexus)) {
            $this->add(
                'warning',
                'nexuscart_incompatible',
                'The package declares ShopKeeprr '.$nexusConstraint.', but the current release is '.$currentNexus.'. It may need an update before it can be installed.',
                $this->manifestFile ?: $this->composerFile
            );
        }

        if ($this->composer === null && $this->manifest === null) {
            $this->add('info', 'compatibility_metadata_missing', 'No composer.json or module/theme descriptor was found. Compatibility was not checked automatically.');
        }
    }

    private function add(string $severity, string $rule, string $message, ?string $file = null, ?int $line = null): void
    {
        $severity = $this->downgradeForPath($severity, $file, $rule);
        $key = implode('|', [$severity, $rule, $file ?? '', $line ?? '', $message]);

        if (isset($this->findingKeys[$key]) || count($this->findings) >= self::MAX_FINDINGS) {
            return;
        }

        $this->findingKeys[$key] = true;
        $this->findings[] = [
            'severity' => $severity,
            'rule' => $rule,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ];

        $this->score += match ($severity) {
            'critical' => 50,
            'warning' => 10,
            default => 1,
        };

        $this->score = min(100, $this->score);
    }

    private function downgradeForPath(string $severity, ?string $file, string $rule): string
    {
        if ($file === null) {
            return $severity;
        }

        $file = '/'.ltrim(strtolower($file), '/');

        /*
         * A console command may legitimately shell out to a first-party CLI
         * (backups, image tools, imports). It is still executable code, so it
         * remains a visible warning, but it should not block an otherwise
         * clean package on the same footing as shell execution in a controller
         * or route.
         */
        if ($rule === 'php_shell' && preg_match('#/(console|commands?)/#', $file)) {
            return 'warning';
        }

        if (! preg_match('#/(tests?|docs?|examples?|samples?)/#', $file)) {
            return $severity;
        }

        return match ($severity) {
            'critical' => 'warning',
            'warning' => 'info',
            default => 'info',
        };
    }

    /**
     * @return array{status: string, score: int, findings: list<array<string, mixed>>, engine: string, scanned_at: string, files_inspected: int}
     */
    private function result(): array
    {
        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];

        usort($this->findings, function (array $left, array $right) use ($rank): int {
            return ($rank[$left['severity']] ?? 3) <=> ($rank[$right['severity']] ?? 3)
                ?: strcmp((string) ($left['file'] ?? ''), (string) ($right['file'] ?? ''))
                ?: ((int) ($left['line'] ?? 0)) <=> ((int) ($right['line'] ?? 0));
        });

        $findings = $this->findings;

        if ($this->trustedCore) {
            foreach ($findings as &$finding) {
                if ($finding['severity'] === 'critical' && in_array($finding['rule'], self::CORE_ALLOWED_CRITICAL_RULES, true)) {
                    $finding['severity'] = 'warning';
                    $finding['message'] .= ' Reviewed and allowed in the signed, first-party ShopKeeprr core package.';
                }
            }
            unset($finding);
        }

        $hasCritical = collect($findings)->contains(fn (array $finding) => $finding['severity'] === 'critical');
        $hasWarning = collect($findings)->contains(fn (array $finding) => $finding['severity'] === 'warning');

        return [
            'status' => $hasCritical ? self::STATUS_BLOCKED : ($hasWarning ? self::STATUS_WARNING : self::STATUS_CLEAN),
            'score' => $this->score,
            'findings' => $findings,
            'engine' => self::ENGINE,
            'scanned_at' => now()->toIso8601String(),
            'files_inspected' => $this->filesInspected,
        ];
    }

    private function reset(): void
    {
        $this->findings = [];
        $this->findingKeys = [];
        $this->score = 0;
        $this->filesInspected = 0;
        $this->dependenciesSkipped = false;
        $this->trustedCore = false;
        $this->nestedArchives = false;
        $this->scanTruncated = false;
        $this->composer = null;
        $this->composerFile = null;
        $this->manifest = null;
        $this->manifestFile = null;
    }

    private function normaliseEntryName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = ltrim($name, '/');

        while (str_starts_with($name, './')) {
            $name = substr($name, 2);
        }

        return $name;
    }

    private function hasTraversal(string $name): bool
    {
        return str_contains($name, "\0")
            || str_starts_with($name, '/')
            || preg_match('#^[A-Za-z]:#', $name) === 1
            || preg_match('#(?:^|/)\.\.(?:/|$)#', $name) === 1;
    }

    private function ignoredPath(string $name): bool
    {
        return str_starts_with($name, '__MACOSX/')
            || str_starts_with($name, '.git/')
            || in_array($name, ['.DS_Store', 'Thumbs.db'], true);
    }

    private function dependencyPath(string $name): bool
    {
        return str_starts_with($name, 'vendor/')
            || str_contains($name, '/vendor/')
            || str_starts_with($name, 'node_modules/')
            || str_contains($name, '/node_modules/');
    }

    private function nestedArchivePath(string $name): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($extension, ['zip', 'tar', 'gz', 'tgz', 'rar', '7z', 'phar'], true);
    }

    private function codeExtension(string $name): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'inc', 'js', 'mjs', 'cjs'], true);
    }

    private function isRootMetadata(string $name, string $basename): bool
    {
        return strtolower(basename($name)) === $basename && substr_count($name, '/') <= 1;
    }

    private function nextLiteral(array $tokens, int $start): ?string
    {
        $limit = min(count($tokens), $start + 12);

        for ($index = $start; $index < $limit; $index++) {
            $token = $tokens[$index];

            if ($token['text'] === ';') {
                return null;
            }

            if (($token['id'] ?? null) === T_CONSTANT_ENCAPSED_STRING) {
                return $token['text'];
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int|null, text: string, line: int}>  $tokens
     */
    private function callArguments(array $tokens, int $openPosition): string
    {
        $depth = 0;
        $arguments = '';
        $limit = min(count($tokens), $openPosition + 80);

        for ($index = $openPosition; $index < $limit; $index++) {
            $text = $tokens[$index]['text'];

            if ($text === '(') {
                $depth++;
            }

            if ($text === ')') {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            }

            if ($index > $openPosition) {
                $arguments .= $text.' ';
            }
        }

        return $arguments;
    }

    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
