<?php

namespace App\Services\Updates;

use App\Models\UpdateRun;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackup
{
    private ?string $lastFileError = null;

    public function __construct(private readonly string $root) {}

    /**
     * @return array{path: string, format: string, size: int, tables: int, created_at: string}
     */
    public function dump(UpdateRun $run): array
    {
        $directory = $this->directory($run);
        $this->ensureDirectory($directory);

        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => $this->dumpSqlite($directory),
            'mysql', 'mariadb' => $this->dumpMysql($directory),
            default => throw new UpdateException("Database driver [{$driver}] is not supported by the updater."),
        };
    }

    public function restore(UpdateRun $run): void
    {
        if (! filled($run->database_backup_path) || ! filled($run->database_backup_format)) {
            throw new UpdateException('This update has no database backup to restore.');
        }

        $path = $this->root.'/'.ltrim((string) $run->database_backup_path, '/');

        if (! is_file($path)) {
            throw new UpdateException('The database backup file is missing: '.$run->database_backup_path);
        }

        match ($run->database_backup_format) {
            'sqlite' => $this->restoreSqlite($path),
            'mysql-sql' => $this->restoreMysqlDump($path),
            'jsonl', 'jsonl-gz' => $this->restoreJsonLines($path),
            default => throw new UpdateException('Unknown database backup format: '.$run->database_backup_format),
        };
    }

    public function directory(UpdateRun $run): string
    {
        return $this->root.'/storage/app/updates/backups/'.$run->id;
    }

    /**
     * @return array{path: string, format: string, size: int, tables: int, created_at: string}
     */
    private function dumpSqlite(string $directory): array
    {
        $source = (string) DB::connection()->getDatabaseName();

        if ($source === '' || $source === ':memory:') {
            throw new UpdateException('The SQLite database is in memory; there is nothing safe to back up.');
        }

        $target = $directory.'/database.sqlite';
        @unlink($target);

        $quoted = str_replace(["\\", "'"], ['/', "''"], $target);

        try {
            DB::statement("VACUUM INTO '{$quoted}'");
        } catch (Throwable) {
            // Older SQLite builds do not support VACUUM INTO. Checkpoint the
            // write-ahead log and copy the database file instead.
            try {
                DB::select('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (Throwable) {
                //
            }

            if (! @copy($source, $target)) {
                throw new UpdateException('Could not copy the SQLite database for backup.');
            }
        }

        $tables = $this->verifySqliteFile($target);

        return [
            'path' => $this->relative($target),
            'format' => 'sqlite',
            'size' => (int) filesize($target),
            'tables' => $tables,
            'created_at' => now()->toIso8601String(),
        ];
    }

    private function verifySqliteFile(string $path): int
    {
        try {
            $pdo = new \PDO('sqlite:'.$path);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $result = $pdo->query('PRAGMA integrity_check')->fetchColumn();

            if ($result !== 'ok') {
                throw new UpdateException('The SQLite backup failed its integrity check.');
            }

            return (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
        } catch (UpdateException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new UpdateException('The SQLite backup could not be verified: '.$exception->getMessage());
        }
    }

    /**
     * @return array{path: string, format: string, size: int, tables: int, created_at: string}
     */
    private function dumpMysql(string $directory): array
    {
        $native = $this->dumpWithMysqldump($directory);

        if ($native !== null) {
            return $native;
        }

        return $this->dumpJsonLines($directory);
    }

    /**
     * @return array{path: string, format: string, size: int, tables: int, created_at: string}|null
     */
    private function dumpWithMysqldump(string $directory): ?array
    {
        $binary = (new ExecutableFinder)->find('mysqldump');

        if ($binary === null || ! function_exists('proc_open')) {
            return null;
        }

        $config = $this->connectionConfig();
        $target = $directory.'/database.sql';
        @unlink($target);

        $command = [
            $binary,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--routines',
            '--events',
            '--default-character-set=utf8mb4',
            '--result-file='.$target,
        ];

        $this->appendMysqlConnectionArguments($command, $config);
        $command[] = (string) ($config['database'] ?? '');

        $environment = [];
        if ((string) ($config['password'] ?? '') !== '') {
            $environment['MYSQL_PWD'] = (string) $config['password'];
        }

        try {
            $process = new Process($command, $this->root, $environment);
            $process->setTimeout(1800);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($target) || filesize($target) < 100) {
                @unlink($target);

                return null;
            }
        } catch (Throwable) {
            @unlink($target);

            return null;
        }

        return [
            'path' => $this->relative($target),
            'format' => 'mysql-sql',
            'size' => (int) filesize($target),
            'tables' => $this->tableNames() === [] ? 0 : count($this->tableNames()),
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Portable, executable-free fallback. Each line is a JSON record so restore
     * never has to split SQL text or guess where a string ends.
     *
     * @return array{path: string, format: string, size: int, tables: int, created_at: string}
     */
    private function dumpJsonLines(string $directory): array
    {
        $gzip = function_exists('gzopen');
        $target = $directory.'/database.jsonl'.($gzip ? '.gz' : '');
        $handle = $gzip ? gzopen($target, 'wb9') : fopen($target, 'wb');

        if ($handle === false) {
            throw new UpdateException('Could not create the database backup file.');
        }

        $tables = $this->tableNames();

        $this->writeLine($handle, $gzip, [
            'type' => 'header',
            'format' => 'nexuscart-db-backup',
            'version' => 1,
            'driver' => DB::connection()->getDriverName(),
            'created_at' => now()->toIso8601String(),
        ]);
        $this->writeLine($handle, $gzip, ['type' => 'tables', 'names' => $tables]);

        try {
            foreach ($tables as $table) {
                $quoted = '`'.str_replace('`', '``', $table).'`';
                $create = DB::selectOne("SHOW CREATE TABLE {$quoted}");
                $create = is_object($create) ? (array) $create : (array) $create;
                $sql = (string) (array_values($create)[1] ?? '');

                if ($sql === '') {
                    throw new UpdateException("Could not read the schema for [{$table}].");
                }

                $this->writeLine($handle, $gzip, ['type' => 'table', 'name' => $table, 'sql' => $sql]);

                $batch = [];
                foreach (DB::table($table)->cursor() as $row) {
                    $batch[] = $this->encodeRow((array) $row);

                    if (count($batch) >= 200) {
                        $this->writeLine($handle, $gzip, ['type' => 'rows', 'name' => $table, 'rows' => $batch]);
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    $this->writeLine($handle, $gzip, ['type' => 'rows', 'name' => $table, 'rows' => $batch]);
                }
            }
        } finally {
            $gzip ? gzclose($handle) : fclose($handle);
        }

        if (! is_file($target) || filesize($target) === 0) {
            throw new UpdateException('The database backup is empty.');
        }

        return [
            'path' => $this->relative($target),
            'format' => $gzip ? 'jsonl-gz' : 'jsonl',
            'size' => (int) filesize($target),
            'tables' => count($tables),
            'created_at' => now()->toIso8601String(),
        ];
    }

    private function restoreSqlite(string $path): void
    {
        $live = (string) DB::connection()->getDatabaseName();

        if ($live === '' || $live === ':memory:') {
            throw new UpdateException('Cannot restore a SQLite backup over an in-memory database.');
        }

        // Windows keeps the database file locked until every connection in this
        // process is closed, including connections opened by other code paths.
        $this->closeConnections();

        // Stage and verify the replacement before touching the live file, so a
        // failed copy can never leave the store without a database.
        $temporary = $live.'.restore-'.bin2hex(random_bytes(4));

        if (! @copy($path, $temporary)) {
            DB::reconnect();
            throw new UpdateException('Could not stage the SQLite database restore.');
        }

        try {
            $this->verifySqliteFile($temporary);
        } catch (Throwable $exception) {
            @unlink($temporary);
            DB::reconnect();
            throw $exception;
        }

        // Stale journals belong to the database being replaced.
        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            $this->deleteFile($live.$suffix);
        }

        if (! $this->replaceFile($temporary, $live)) {
            @unlink($temporary);
            DB::reconnect();

            throw new UpdateException(
                'Could not replace the SQLite database with its backup.'
                .($this->lastFileError !== null ? ' '.$this->lastFileError : '')
                .' The backup is still at '.$this->relative($path).'.'
            );
        }

        DB::reconnect();
        $this->verifySqliteFile($live);
    }

    /**
     * Close every configured connection, not only the default one.
     */
    private function closeConnections(): void
    {
        foreach (array_keys((array) config('database.connections', [])) as $name) {
            try {
                DB::disconnect($name);
            } catch (Throwable) {
                //
            }

            try {
                DB::purge($name);
            } catch (Throwable) {
                //
            }
        }

        gc_collect_cycles();
        clearstatcache(true);
    }

    private function deleteFile(string $path): bool
    {
        if (! file_exists($path)) {
            return true;
        }

        for ($attempt = 0; $attempt < 6; $attempt++) {
            if (@unlink($path)) {
                return true;
            }

            usleep(200000);
            clearstatcache(true, $path);
        }

        return ! file_exists($path);
    }

    /**
     * Replace a file in place, tolerating antivirus and indexer locks on Windows.
     */
    private function replaceFile(string $source, string $target): bool
    {
        $this->lastFileError = null;
        $size = (int) @filesize($source);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            error_clear_last();

            if (is_file($target) && ! $this->deleteFile($target)) {
                $this->lastFileError = $this->lastError();
            } elseif (@rename($source, $target)) {
                return true;
            } else {
                $this->lastFileError = $this->lastError();
            }

            error_clear_last();

            // On some Windows configurations rename() refuses to overwrite even
            // after the target is gone. An explicit copy is the reliable path.
            if (@copy($source, $target)) {
                @unlink($source);

                return true;
            }

            $this->lastFileError = $this->lastError() ?: $this->lastFileError;
            usleep(250000);
            clearstatcache(true, $target);
        }

        return is_file($target) && @filesize($target) === $size;
    }

    private function lastError(): ?string
    {
        $error = error_get_last();

        return is_array($error) && isset($error['message']) ? (string) $error['message'] : null;
    }

    private function restoreMysqlDump(string $path): void
    {
        $binary = (new ExecutableFinder)->find('mysql');

        if ($binary === null) {
            throw new UpdateException(
                'The database backup is a MySQL dump, but the mysql command was not found. '
                .'Restore the file manually: '.$path
            );
        }

        $config = $this->connectionConfig();
        $command = [$binary, '--default-character-set=utf8mb4'];
        $this->appendMysqlConnectionArguments($command, $config);
        $command[] = (string) ($config['database'] ?? '');

        $environment = [];
        if ((string) ($config['password'] ?? '') !== '') {
            $environment['MYSQL_PWD'] = (string) $config['password'];
        }

        $input = fopen($path, 'rb');
        if ($input === false) {
            throw new UpdateException('Could not read the MySQL backup file.');
        }

        try {
            $process = new Process($command, $this->root, $environment);
            $process->setInput($input);
            $process->setTimeout(1800);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new UpdateException('The MySQL restore failed: '.trim($process->getErrorOutput()));
            }
        } finally {
            fclose($input);
        }
    }

    private function restoreJsonLines(string $path): void
    {
        $gzip = str_ends_with($path, '.gz');
        $handle = $gzip ? gzopen($path, 'rb') : fopen($path, 'rb');

        if ($handle === false) {
            throw new UpdateException('Could not open the database backup.');
        }

        $driver = DB::connection()->getDriverName();
        $this->disableForeignKeys($driver);

        try {
            $line = $gzip ? gzgets($handle) : fgets($handle);
            $header = json_decode((string) $line, true);

            if (! is_array($header) || ($header['format'] ?? '') !== 'nexuscart-db-backup') {
                throw new UpdateException('The database backup header is invalid.');
            }

            $current = $this->tableNames();
            foreach (array_reverse($current) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.str_replace('`', '``', $table).'`');
            }

            while (true) {
                $line = $gzip ? gzgets($handle) : fgets($handle);

                if ($line === false) {
                    break;
                }

                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $record = json_decode($line, true);
                if (! is_array($record)) {
                    throw new UpdateException('A record in the database backup is corrupt.');
                }

                if (($record['type'] ?? '') === 'table') {
                    $sql = (string) ($record['sql'] ?? '');
                    if ($sql === '') {
                        throw new UpdateException('A table definition in the database backup is empty.');
                    }

                    DB::statement($sql);
                    continue;
                }

                if (($record['type'] ?? '') === 'rows') {
                    $rows = $record['rows'] ?? [];
                    if (! is_array($rows) || $rows === []) {
                        continue;
                    }

                    $decoded = array_map(fn (array $row): array => $this->decodeRow($row), $rows);
                    DB::table((string) $record['name'])->insert($decoded);
                }
            }
        } finally {
            $gzip ? gzclose($handle) : fclose($handle);
            $this->enableForeignKeys($driver);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        return (array) config('database.connections.'.DB::getDefaultConnection(), []);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, mixed>  $config
     */
    private function appendMysqlConnectionArguments(array &$command, array $config): void
    {
        if (filled($config['host'] ?? null)) {
            $command[] = '--host='.$config['host'];
        }

        if (filled($config['port'] ?? null)) {
            $command[] = '--port='.$config['port'];
        }

        if (filled($config['username'] ?? null)) {
            $command[] = '--user='.$config['username'];
        }

        if (filled($config['unix_socket'] ?? null)) {
            $command[] = '--socket='.$config['unix_socket'];
        }
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return array_map(
                static fn (object $row): string => (string) $row->name,
                DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            );
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $tables = [];
            foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
                $values = array_values((array) $row);
                if (isset($values[0])) {
                    $tables[] = (string) $values[0];
                }
            }

            return $tables;
        }

        return [];
    }

    private function disableForeignKeys(string $driver): void
    {
        try {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            }
        } catch (Throwable) {
            //
        }
    }

    private function enableForeignKeys(string $driver): void
    {
        try {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
        } catch (Throwable) {
            //
        }
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $record
     */
    private function writeLine($handle, bool $gzip, array $record): void
    {
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new UpdateException('A row in the database could not be encoded for backup.');
        }

        $gzip ? gzwrite($handle, $json."\n") : fwrite($handle, $json."\n");
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function encodeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $row[$key] = ['__nexus_b64' => base64_encode($value)];
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function decodeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_array($value) && isset($value['__nexus_b64']) && is_string($value['__nexus_b64'])) {
                $decoded = base64_decode($value['__nexus_b64'], true);
                $row[$key] = $decoded === false ? '' : $decoded;
            }
        }

        return $row;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new UpdateException('Could not create the update backup directory.');
        }
    }

    private function relative(string $path): string
    {
        $root = str_replace('\\', '/', rtrim($this->root, '/\\')).'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
