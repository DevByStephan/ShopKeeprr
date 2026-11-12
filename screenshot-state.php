<?php

use App\Services\SettingsService;
use App\Services\ThemeService;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$command = $argv[1] ?? '';
$value = $argv[2] ?? '';
$settings = app(SettingsService::class);

if ($command === 'theme') {
    $theme = app(ThemeService::class)->preset($value);

    if (! $theme) {
        fwrite(STDERR, "Unknown theme: {$value}\n");
        exit(1);
    }

    $settings->setMany([
        'theme.preset' => $theme['key'],
        'theme.primary_color' => $theme['defaults']['primary_color'],
        'theme.accent_color' => $theme['defaults']['accent_color'],
        'theme.heading_font' => $theme['defaults']['heading_font'],
        'theme.body_font' => $theme['defaults']['body_font'],
        'theme.product_columns' => (int) $theme['defaults']['product_columns'],
    ]);

    echo "theme={$theme['key']}\n";
    exit(0);
}

if ($command === 'moq') {
    \App\Models\Product::query()->update(['min_order_quantity' => max(1, (int) $value)]);
    echo "moq={$value}\n";
    exit(0);
}

if ($command === 'mode') {
    if (! in_array($value, ['retail', 'b2b', 'subscription'], true)) {
        fwrite(STDERR, "Unknown mode: {$value}\n");
        exit(1);
    }

    $settings->setMany([
        'store.mode' => $value,
        'store.live' => true,
        'store.setup_completed' => true,
    ]);

    echo "mode={$value}\n";
    exit(0);
}

fwrite(STDERR, "Usage: screenshot-state.php theme <key> | mode <retail|b2b|subscription>\n");
exit(1);
