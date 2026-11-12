<?php

namespace App\Services;

/**
 * Resolves the active storefront theme preset, its design tokens and the
 * view overrides that belong to it.
 */
class ThemeService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function presets(): array
    {
        $presets = config('themes', []);

        return is_array($presets) ? $presets : [];
    }

    public function currentKey(): string
    {
        $key = (string) app(SettingsService::class)->get('theme.preset', 'editorial');

        return array_key_exists($key, $this->presets()) ? $key : 'editorial';
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $key = $this->currentKey();

        return array_merge($this->presets()[$key] ?? [], ['key' => $key]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function preset(string $key): ?array
    {
        $preset = $this->presets()[$key] ?? null;

        return $preset ? array_merge($preset, ['key' => $key]) : null;
    }

    /**
     * Build the CSS custom properties used by the storefront layout.
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        $theme = $this->current();
        $palette = $theme['palette'] ?? [];
        $defaults = $theme['defaults'] ?? [];
        $settings = app(SettingsService::class);

        $primary = (string) ($settings->get('theme.primary_color') ?: ($defaults['primary_color'] ?? '#0f766e'));
        $accent = (string) ($settings->get('theme.accent_color') ?: ($defaults['accent_color'] ?? '#f59e0b'));
        $heading = (string) ($settings->get('theme.heading_font') ?: ($defaults['heading_font'] ?? 'Inter'));
        $body = (string) ($settings->get('theme.body_font') ?: ($defaults['body_font'] ?? 'Inter'));

        $displayFallback = (string) ($palette['display-fallback'] ?? 'Georgia, serif');
        $sansFallback = (string) ($palette['sans-fallback'] ?? "ui-sans-serif, system-ui, sans-serif");

        $display = $heading === 'System UI' ? $sansFallback : "'".$heading."', ".$displayFallback;
        $sans = $body === 'System UI' ? $sansFallback : "'".$body."', ".$sansFallback;

        $variables = [
            'brand' => $primary,
            'accent' => $accent,
            'link' => (string) ($palette['link'] ?? $primary),
            'brand-soft' => (string) ($palette['brand-soft'] ?? '#e7f5f0'),
            'ink' => (string) ($palette['ink'] ?? '#17231f'),
            'ink-strong' => (string) ($palette['ink-strong'] ?? '#102522'),
            'muted' => (string) ($palette['muted'] ?? '#687771'),
            'surface' => (string) ($palette['surface'] ?? '#f6f3ec'),
            'surface-2' => (string) ($palette['surface-2'] ?? '#eef2ef'),
            'card' => (string) ($palette['card'] ?? '#ffffff'),
            'line' => (string) ($palette['line'] ?? '#e3e9e5'),
            'header-bg' => (string) ($palette['header-bg'] ?? '#f6f3ec'),
            'header-ink' => (string) ($palette['header-ink'] ?? '#17231f'),
            'header-muted' => (string) ($palette['header-muted'] ?? '#5d6d66'),
            'footer-bg' => (string) ($palette['footer-bg'] ?? '#102522'),
            'footer-ink' => (string) ($palette['footer-ink'] ?? '#ffffff'),
            'footer-muted' => (string) ($palette['footer-muted'] ?? 'rgba(255,255,255,.58)'),
            'price' => (string) ($palette['price'] ?? $primary),
            'sale' => (string) ($palette['sale'] ?? '#b91c1c'),
            'success' => (string) ($palette['success'] ?? '#047857'),
            'button-ink' => (string) ($palette['button-ink'] ?? '#ffffff'),
            'card-radius' => (string) ($theme['card_radius'] ?? '1rem'),
            'btn-radius' => (string) ($theme['button_radius'] ?? '999px'),
            'font-display' => $display,
            'font-sans' => $sans,
        ];

        return $variables;
    }

    /**
     * View candidates in priority order: administrator override, theme
     * override, then the shared default.
     *
     * @return list<string>
     */
    public function candidates(string $view): array
    {
        return [
            'theme.custom.'.$view,
            'theme.presets.'.$this->currentKey().'.'.$view,
            'theme.'.$view,
        ];
    }

    /**
     * Resolve a theme view name without rendering it.
     */
    public function resolve(string $view): string
    {
        foreach ($this->candidates($view) as $candidate) {
            if (view()->exists($candidate)) {
                return $candidate;
            }
        }

        return 'theme.'.$view;
    }

    public function isPreset(string $key): bool
    {
        return array_key_exists($key, $this->presets());
    }
}
