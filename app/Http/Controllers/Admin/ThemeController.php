<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Services\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ThemeController extends Controller
{
    private const EDITABLE = [
        'layouts/app',
        'home',
        'shop/index',
        'product/show',
        'cart/index',
        'checkout/index',
        'checkout/confirmation',
    ];

    public function index(): View
    {
        $themes = app(ThemeService::class);

        return view('admin.theme.index', [
            'files' => $this->editableFiles(),
            'settings' => app(SettingsService::class)->all(),
            'presets' => $themes->presets(),
            'activePreset' => $themes->currentKey(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preset' => ['required', Rule::in(array_keys(app(ThemeService::class)->presets()))],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'heading_font' => ['required', 'in:Fraunces,DM Serif Display,Playfair Display,Georgia,Inter'],
            'body_font' => ['required', 'in:Inter,DM Sans,Manrope,System UI,Georgia'],
            'product_columns' => ['required', 'integer', 'in:2,3,4'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
        ]);

        app(SettingsService::class)->setMany([
            'theme.preset' => $data['preset'],
            'theme.primary_color' => $data['primary_color'],
            'theme.accent_color' => $data['accent_color'],
            'theme.heading_font' => $data['heading_font'],
            'theme.body_font' => $data['body_font'],
            'theme.product_columns' => (int) $data['product_columns'],
            'theme.custom_css' => $data['custom_css'] ?? '',
        ]);

        return back()->with('status', 'Theme settings saved.');
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preset' => ['required', Rule::in(array_keys(app(ThemeService::class)->presets()))],
        ]);

        $theme = app(ThemeService::class)->preset($data['preset']);
        abort_unless($theme, 404);

        $defaults = $theme['defaults'];

        app(SettingsService::class)->setMany([
            'theme.preset' => $theme['key'],
            'theme.primary_color' => $defaults['primary_color'],
            'theme.accent_color' => $defaults['accent_color'],
            'theme.heading_font' => $defaults['heading_font'],
            'theme.body_font' => $defaults['body_font'],
            'theme.product_columns' => (int) $defaults['product_columns'],
        ]);

        return back()->with('status', $theme['label'].' activated. The storefront now uses this theme.');
    }

    public function edit(string $file): View
    {
        $path = $this->resolveFile($file);
        abort_unless($path, 404);

        return view('admin.theme.editor', [
            'file' => $file,
            'contents' => is_file($path) ? file_get_contents($path) : file_get_contents(resource_path('views/theme/'.$file.'.blade.php')),
            'isOverride' => is_file($path),
        ]);
    }

    public function save(Request $request, string $file): RedirectResponse
    {
        $path = $this->resolveFile($file);
        abort_unless($path, 404);

        $data = $request->validate(['contents' => ['required', 'string', 'max:200000']]);

        try {
            \Illuminate\Support\Facades\Blade::compileString($data['contents']);
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['contents' => 'This template could not be compiled: '.$exception->getMessage()]);
        }

        $backupDir = storage_path('app/theme-backups');
        if (! is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        if (is_file($path)) {
            @copy($path, $backupDir.'/'.str_replace('/', '-', $file).'-'.date('YmdHis').'.blade.php');
        }

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        if (file_put_contents($path, $data['contents']) === false) {
            return back()->withErrors(['contents' => 'The template could not be saved. Check storage permissions.']);
        }

        return redirect()->route('admin.theme.edit', $file)->with('status', 'Template saved.');
    }

    public function reset(string $file): RedirectResponse
    {
        $path = $this->resolveFile($file);
        abort_unless($path, 404);

        if (is_file($path)) {
            @unlink($path);
        }

        return redirect()->route('admin.theme.edit', $file)->with('status', 'Template reset to the default.');
    }

    private function editableFiles(): array
    {
        return self::EDITABLE;
    }

    private function resolveFile(string $file): ?string
    {
        $file = trim(str_replace('..', '', $file), '/');

        if (! in_array($file, self::EDITABLE, true)) {
            return null;
        }

        return resource_path('views/theme/custom/'.$file.'.blade.php');
    }
}
