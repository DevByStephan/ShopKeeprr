<?php

if (! function_exists('shopkeeprr_env')) {
    /**
     * Read a ShopKeeprr environment value while accepting the historical
     * NEXUSCART_* names so existing .env files keep working after the rebrand.
     */
    function shopkeeprr_env(string $key, mixed $default = null): mixed
    {
        return env('SHOPKEEPRR_'.$key, env('NEXUSCART_'.$key, $default));
    }
}

use App\Enums\StoreMode;
use App\Services\SettingsService;
use App\Services\ThemeService;
use Illuminate\Contracts\View\View;

if (! function_exists('nexus_demo')) {
    function nexus_demo(): bool
    {
        return (bool) config('nexuscart.demo.enabled', false);
    }
}

if (! function_exists('nexus_demo_label')) {
    function nexus_demo_label(): string
    {
        return (string) config('nexuscart.demo.label', 'Demo build');
    }
}

if (! function_exists('nexus_setting')) {
    function nexus_setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('nexus_money')) {
    function nexus_money(float|int|string|null $amount, ?string $currency = null, bool $withCode = false): string
    {
        $amount = (float) ($amount ?? 0);
        $symbol = (string) nexus_setting('store.currency_symbol', '$');
        $code = strtoupper($currency ?: (string) nexus_setting('store.currency', 'USD'));
        $formatted = $symbol.number_format($amount, 2);

        return $withCode ? $formatted.' '.$code : $formatted;
    }
}

if (! function_exists('nexus_mode')) {
    function nexus_mode(): ?StoreMode
    {
        $value = nexus_setting('store.mode');

        return $value ? StoreMode::tryFrom((string) $value) : null;
    }
}

if (! function_exists('nexus_store_is_live')) {
    function nexus_store_is_live(): bool
    {
        return (bool) nexus_setting('store.live', false) && nexus_mode() !== null;
    }
}

if (! function_exists('nexus_theme')) {
    /**
     * @return array<string, mixed>
     */
    function nexus_theme(): array
    {
        return app(ThemeService::class)->current();
    }
}

if (! function_exists('nexus_theme_variables')) {
    /**
     * @return array<string, string>
     */
    function nexus_theme_variables(): array
    {
        return app(ThemeService::class)->variables();
    }
}

if (! function_exists('nexus_view')) {
    function nexus_view(string $view, array $data = []): View
    {
        return view(app(ThemeService::class)->resolve($view), $data);
    }
}

if (! function_exists('nexus_partial')) {
    function nexus_partial(string $view): string
    {
        return app(ThemeService::class)->resolve($view);
    }
}

if (! function_exists('nexus_builder_image')) {
    function nexus_builder_image(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return asset(ltrim($value, '/'));
    }
}

if (! function_exists('nexus_builder_link')) {
    function nexus_builder_link(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '#';
        }

        if (preg_match('#^https?://#i', $value) || str_starts_with($value, '#')) {
            return $value;
        }

        return str_starts_with($value, '/') ? url($value) : '#';
    }
}

if (! function_exists('nexus_installed')) {
    function nexus_installed(): bool
    {
        return file_exists(storage_path('app/installed.lock'));
    }
}

if (! function_exists('nexus_page_content')) {
    /**
     * Render the small, safe subset of plain-text formatting used by pages.
     *
     * - "## Heading" and "### Heading" become headings.
     * - "- item" lines become a list.
     * - Blank lines become paragraph breaks.
     * - **text** becomes bold.
     */
    function nexus_page_content(string $content): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", trim($content)));
        $html = [];
        $paragraph = [];
        $inList = false;

        $inline = static function (string $text): string {
            return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($text)) ?? e($text);
        };

        $flushParagraph = static function () use (&$paragraph, &$html, $inline): void {
            if ($paragraph !== []) {
                $html[] = '<p>'.implode('<br>', array_map($inline, $paragraph)).'</p>';
                $paragraph = [];
            }
        };

        $closeList = static function () use (&$inList, &$html): void {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
        };

        foreach ($lines as $line) {
            $line = rtrim($line);

            if ($line === '') {
                $flushParagraph();
                $closeList();
                continue;
            }

            if (str_starts_with($line, '### ')) {
                $flushParagraph();
                $closeList();
                $html[] = '<h3>'.$inline(substr($line, 4)).'</h3>';
                continue;
            }

            if (str_starts_with($line, '## ')) {
                $flushParagraph();
                $closeList();
                $html[] = '<h2>'.$inline(substr($line, 3)).'</h2>';
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $flushParagraph();
                if (! $inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>'.$inline($matches[1]).'</li>';
                continue;
            }

            $closeList();
            $paragraph[] = $line;
        }

        $flushParagraph();
        $closeList();

        return implode("\n", $html);
    }
}

if (! function_exists('nexus_status_badge')) {
    function nexus_status_badge(string $status): string
    {
        return match ($status) {
            'paid', 'completed', 'active', 'approved', 'succeeded' => 'emerald',
            'processing', 'authorized', 'trialing', 'downloading', 'verifying', 'backing_up', 'applying', 'migrating', 'rolling_back' => 'blue',
            'pending', 'past_due', 'on_hold', 'rolled_back' => 'amber',
            'failed', 'cancelled', 'rejected', 'rollback_failed' => 'rose',
            'paused', 'partially_refunded' => 'violet',
            default => 'slate',
        };
    }
}
