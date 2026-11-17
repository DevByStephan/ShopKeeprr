<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WooCommerceConnection;
use App\Models\WooCommerceMigration;
use App\Services\Import\CsvReader;
use App\Services\Import\WooCommerceApiClient;
use App\Services\Import\WooCommerceApiMigrationRunner;
use App\Services\Import\WooCommerceArchive;
use App\Services\Import\WooCommerceImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WooCommerceMigrationController extends Controller
{
    public function index(): View
    {
        return view('admin.migrations.woocommerce.index', [
            'migrations' => WooCommerceMigration::query()->with(['user', 'connection'])->orderByDesc('id')->paginate(15),
            'connections' => WooCommerceConnection::query()->withCount('migrations')->orderByDesc('id')->get(),
        ]);
    }

    public function storeConnection(Request $request, WooCommerceApiClient $client): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'url:https', 'max:255'],
            'consumer_key' => ['required', 'string', 'max:1000'],
            'consumer_secret' => ['required', 'string', 'max:1000'],
        ]);

        $connection = WooCommerceConnection::query()->create([
            'user_id' => $request->user()?->id,
            'name' => trim($data['name']),
            'base_url' => rtrim(trim($data['base_url']), '/'),
            'consumer_key' => trim($data['consumer_key']),
            'consumer_secret' => trim($data['consumer_secret']),
            'status' => 'untested',
        ]);

        try {
            $counts = $client->testConnection($connection);
            $connection->update([
                'status' => 'connected',
                'last_tested_at' => now(),
                'last_error' => null,
                'metadata' => ['counts' => $counts, 'tested_at' => now()->toIso8601String()],
            ]);
        } catch (Throwable $exception) {
            $connection->update(['status' => 'failed', 'last_tested_at' => now(), 'last_error' => $exception->getMessage()]);
            $this->auditConnection($request, 'woocommerce.connection.failed', $connection);

            return redirect()->route('admin.migrations.woocommerce.index')
                ->withErrors(['connection' => $exception->getMessage()]);
        }

        $this->auditConnection($request, 'woocommerce.connection.created', $connection, ['counts' => $counts]);

        return redirect()->route('admin.migrations.woocommerce.index')
            ->with('status', 'WooCommerce store connected. '.$counts['products'].' products, '.$counts['customers'].' customers, '.$counts['orders'].' orders and '.$counts['taxes'].' tax rates found.');
    }

    public function testConnection(Request $request, WooCommerceConnection $connection, WooCommerceApiClient $client): RedirectResponse
    {
        try {
            $counts = $client->testConnection($connection);
            $connection->update([
                'status' => 'connected',
                'last_tested_at' => now(),
                'last_error' => null,
                'metadata' => ['counts' => $counts, 'tested_at' => now()->toIso8601String()],
            ]);
            $this->auditConnection($request, 'woocommerce.connection.tested', $connection, ['counts' => $counts]);

            return back()->with('status', 'Connection verified. '.$counts['products'].' products, '.$counts['customers'].' customers, '.$counts['orders'].' orders and '.$counts['taxes'].' tax rates found.');
        } catch (Throwable $exception) {
            $connection->update(['status' => 'failed', 'last_tested_at' => now(), 'last_error' => $exception->getMessage()]);
            $this->auditConnection($request, 'woocommerce.connection.failed', $connection);

            return back()->withErrors(['connection' => $exception->getMessage()]);
        }
    }

    public function destroyConnection(Request $request, WooCommerceConnection $connection): RedirectResponse
    {
        $this->auditConnection($request, 'woocommerce.connection.deleted', $connection);
        $connection->delete();

        return back()->with('status', 'WooCommerce connection removed. Existing migrations are kept.');
    }

    public function startApi(Request $request, WooCommerceConnection $connection, WooCommerceImporter $importer): RedirectResponse
    {
        $migration = WooCommerceMigration::query()->create([
            'user_id' => $request->user()?->id,
            'connection_id' => $connection->id,
            'source' => 'api',
            'original_filename' => Str::limit('API: '.$connection->name, 255, ''),
            'status' => 'ready',
            'options' => [
                'update_existing' => true,
                'create_categories' => true,
                'download_images' => false,
                'continue_on_errors' => true,
            ],
            'stats' => $importer->initialStats(),
        ]);

        $this->audit($request, 'woocommerce.migration.api_created', $migration, ['connection_id' => $connection->id]);

        return redirect()->route('admin.migrations.woocommerce.show', $migration)
            ->with('status', 'Connection selected. Run a dry run before importing.');
    }

    public function store(Request $request, WooCommerceArchive $archive): RedirectResponse
    {
        $request->validate([
            'archive' => ['required', 'file', 'mimes:zip,csv,txt', 'max:51200'],
        ]);

        $file = $request->file('archive');

        $migration = WooCommerceMigration::query()->create([
            'user_id' => $request->user()?->id,
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'uploaded',
            'options' => [
                'update_existing' => true,
                'create_categories' => true,
                'download_images' => false,
                'continue_on_errors' => true,
            ],
        ]);

        try {
            $archive->prepare($migration, $file);
        } catch (Throwable $exception) {
            $migration->update([
                'status' => 'failed',
                'stats' => [
                    'warnings' => [],
                    'errors' => [['file' => $file->getClientOriginalName(), 'row' => 0, 'entity' => 'archive', 'message' => $exception->getMessage()]],
                ],
            ]);

            $this->audit($request, 'woocommerce.migration.failed', $migration);

            return redirect()->route('admin.migrations.woocommerce.show', $migration)
                ->withErrors(['archive' => $exception->getMessage()]);
        }

        $this->audit($request, 'woocommerce.migration.uploaded', $migration);

        return redirect()->route('admin.migrations.woocommerce.show', $migration)
            ->with('status', 'WooCommerce files detected. Review the preview, then run a dry run before importing.');
    }

    public function show(WooCommerceMigration $migration, CsvReader $reader): View
    {
        $migration->loadMissing('connection');
        $previews = [];

        foreach ($migration->files ?? [] as $index => $file) {
            if (($file['type'] ?? 'unknown') === 'unknown' || ! is_file((string) ($file['path'] ?? ''))) {
                continue;
            }

            try {
                $preview = $reader->preview(
                    $file['path'],
                    (string) $file['encoding'],
                    (string) $file['delimiter'],
                    6,
                );

                $previews[$index] = [
                    'headers' => $preview['headers'],
                    'rows' => array_map(function (array $row) use ($preview): array {
                        $row = array_slice(array_pad($row, count($preview['headers']), ''), 0, count($preview['headers']));

                        return array_combine($preview['headers'], $row) ?: [];
                    }, $preview['rows']),
                ];
            } catch (Throwable $exception) {
                $previews[$index] = ['headers' => [], 'rows' => [], 'error' => $exception->getMessage()];
            }
        }

        return view('admin.migrations.woocommerce.show', [
            'migration' => $migration,
            'previews' => $previews,
        ]);
    }

    public function runApi(Request $request, WooCommerceMigration $migration, WooCommerceApiMigrationRunner $runner): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:dry_run,apply'],
            'update_existing' => ['nullable', 'boolean'],
            'create_categories' => ['nullable', 'boolean'],
            'download_images' => ['nullable', 'boolean'],
            'continue_on_errors' => ['nullable', 'boolean'],
            'confirm_import' => ['nullable', 'boolean'],
        ]);

        if (! $migration->isApi() || $migration->connection === null) {
            return back()->withErrors(['migration' => 'This is not a WooCommerce API migration.']);
        }

        if ($migration->status === 'running') {
            return back()->withErrors(['migration' => 'This migration is already running.']);
        }

        if ($data['mode'] === 'apply' && ! $request->boolean('confirm_import')) {
            return back()->withErrors(['confirm_import' => 'Confirm that you have a backup and want to write the imported records.']);
        }

        $options = [
            'update_existing' => $request->boolean('update_existing'),
            'create_categories' => $request->boolean('create_categories'),
            'download_images' => $request->boolean('download_images'),
            'continue_on_errors' => $request->boolean('continue_on_errors'),
        ];

        try {
            $runner->start($migration, $data['mode'], $options);
        } catch (Throwable $exception) {
            return back()->withErrors(['migration' => $exception->getMessage()]);
        }

        $this->audit($request, 'woocommerce.migration.api_'.($data['mode'] === 'dry_run' ? 'dry_run' : 'imported'), $migration, ['connection_id' => $migration->connection_id]);

        return redirect()->route('admin.migrations.woocommerce.show', $migration)
            ->with('status', $data['mode'] === 'dry_run' ? 'Dry run started. No records will be written.' : 'WooCommerce API import started.');
    }

    public function apiBatch(WooCommerceMigration $migration, WooCommerceApiMigrationRunner $runner): JsonResponse
    {
        if (! $migration->isApi()) {
            abort(404);
        }

        if (! in_array($migration->status, ['running', 'failed'], true)) {
            return response()->json(['message' => 'This migration is not running.'], 409);
        }

        if ($migration->status === 'failed') {
            $migration->update(['status' => 'running', 'finished_at' => null]);
            $migration->refresh();
        }

        try {
            return response()->json($runner->batch($migration));
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => 'failed',
                'progress' => $migration->fresh()?->progress ?? [],
            ], 500);
        }
    }

    public function run(Request $request, WooCommerceMigration $migration, WooCommerceImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:dry_run,apply'],
            'update_existing' => ['nullable', 'boolean'],
            'create_categories' => ['nullable', 'boolean'],
            'download_images' => ['nullable', 'boolean'],
            'continue_on_errors' => ['nullable', 'boolean'],
            'confirm_import' => ['nullable', 'boolean'],
        ]);

        if ($data['mode'] === 'apply' && ! $request->boolean('confirm_import')) {
            return back()->withErrors(['confirm_import' => 'Confirm that you have a backup and want to write the imported records.']);
        }

        if (empty($migration->files)) {
            return back()->withErrors(['migration' => 'Upload a WooCommerce ZIP or CSV before running the migration.']);
        }

        if ($migration->status === 'running') {
            return back()->withErrors(['migration' => 'This migration is already running.']);
        }

        $options = [
            'update_existing' => $request->boolean('update_existing'),
            'create_categories' => $request->boolean('create_categories'),
            'download_images' => $request->boolean('download_images'),
            'continue_on_errors' => $request->boolean('continue_on_errors'),
        ];

        $migration->update([
            'status' => 'running',
            'mode' => $data['mode'],
            'options' => $options,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        @set_time_limit(0);

        try {
            $stats = $importer->run($migration, $data['mode'], $options);
        } catch (Throwable $exception) {
            $migration->update([
                'status' => 'failed',
                'finished_at' => now(),
                'stats' => [
                    'warnings' => [],
                    'errors' => [['file' => $migration->original_filename, 'row' => 0, 'entity' => 'migration', 'message' => $exception->getMessage()]],
                ],
            ]);

            $this->audit($request, 'woocommerce.migration.failed', $migration);

            return back()->withErrors(['migration' => $exception->getMessage()]);
        }

        $migration->update([
            'status' => $data['mode'] === 'dry_run' ? 'previewed' : 'completed',
            'stats' => $stats,
            'error_report_path' => $stats['error_report_path'] ?? null,
            'finished_at' => now(),
        ]);

        $this->audit($request, 'woocommerce.migration.'.($data['mode'] === 'dry_run' ? 'dry_run' : 'imported'), $migration, [
            'products' => $stats['products'] ?? [],
            'variations' => $stats['variations'] ?? [],
            'customers' => $stats['customers'] ?? [],
            'orders' => $stats['orders'] ?? [],
            'taxes' => $stats['taxes'] ?? [],
        ]);

        return redirect()->route('admin.migrations.woocommerce.show', $migration)
            ->with('status', $data['mode'] === 'dry_run'
                ? 'Dry run complete. Nothing was written; review the counts and warnings.'
                : 'WooCommerce migration complete. Review the counts and any warnings.');
    }

    public function errors(WooCommerceMigration $migration): Response|StreamedResponse
    {
        $path = $migration->error_report_path;
        $root = realpath(storage_path('app/migrations'));
        $real = is_string($path) ? realpath($path) : false;

        if (! is_string($path) || ! is_file($path) || $root === false || $real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($path): void {
            $handle = fopen($path, 'rb');

            if ($handle !== false) {
                fpassthru($handle);
                fclose($handle);
            }
        }, 'woocommerce-migration-errors-'.$migration->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function audit(Request $request, string $action, WooCommerceMigration $migration, array $extra = []): void
    {
        $this->writeAudit($request, $action, WooCommerceMigration::class, $migration->id, $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function auditConnection(Request $request, string $action, WooCommerceConnection $connection, array $extra = []): void
    {
        $this->writeAudit($request, $action, WooCommerceConnection::class, $connection->id, $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function writeAudit(Request $request, string $action, string $modelType, int $modelId, array $extra = []): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'new_values' => $extra,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
