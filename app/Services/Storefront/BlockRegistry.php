<?php

namespace App\Services\Storefront;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The catalogue of storefront sections. Built-in blocks come from
 * config/storefront_blocks.php; modules may register additional blocks during
 * boot. Everything an administrator sends back is normalised here before it is
 * stored or rendered.
 */
class BlockRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $registered = [];

    public const FIELD_TYPES = [
        'text', 'textarea', 'url', 'number', 'color', 'select', 'toggle', 'image', 'datetime',
    ];

    public const BACKGROUNDS = ['none', 'surface', 'soft', 'card', 'ink', 'brand'];

    public const PADDINGS = ['none', 'sm', 'md', 'lg'];

    public const WIDTHS = ['wide', 'narrow'];

    /**
     * Register an extra block, typically from a module service provider.
     *
     * @param  array<string, mixed>  $definition
     */
    public function register(string $type, array $definition): void
    {
        if (! preg_match('/^[a-z][a-z0-9-]{1,48}$/', $type)) {
            throw new InvalidArgumentException('Block type must be a lowercase slug.');
        }

        if (empty($definition['label']) || empty($definition['view'])) {
            throw new InvalidArgumentException('A block needs a label and a view.');
        }

        $definition['fields'] = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];
        $definition['defaults'] = is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [];
        $definition['style'] = array_merge(
            ['background' => 'none', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
            is_array($definition['style'] ?? null) ? $definition['style'] : []
        );

        $this->registered[$type] = $definition;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $blocks = array_merge((array) config('storefront_blocks', []), $this->registered);

        $normalized = [];

        foreach ($blocks as $type => $definition) {
            if (! is_array($definition) || empty($definition['label']) || empty($definition['view'])) {
                continue;
            }

            $normalized[$type] = array_merge([
                'category' => 'Content',
                'description' => '',
                'icon' => 'text',
                'fields' => [],
                'defaults' => [],
                'style' => ['background' => 'none', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
            ], $definition, [
                'fields' => is_array($definition['fields'] ?? null) ? $definition['fields'] : [],
                'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
            ]);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $type): ?array
    {
        return $this->all()[$type] ?? null;
    }

    public function view(string $type): ?string
    {
        $view = $this->get($type)['view'] ?? null;

        return is_string($view) && view()->exists($view) ? $view : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->all() as $type => $definition) {
            $groups[$definition['category']][$type] = $definition;
        }

        return $groups;
    }

    /**
     * Field definitions safe to hand to the builder JavaScript.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitionsForBuilder(): array
    {
        $definitions = [];

        foreach ($this->all() as $type => $definition) {
            $defaults = $this->defaultsFor($type);

            $definitions[$type] = [
                'label' => $definition['label'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'category' => $definition['category'],
                'fields' => $definition['fields'],
                'defaults' => $defaults,
                'style' => $definition['style'],
            ];
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultsFor(string $type): array
    {
        $definition = $this->get($type);

        if (! $definition) {
            return [];
        }

        $defaults = $definition['defaults'];

        if ($type === 'countdown' && empty($defaults['ends_at'])) {
            $defaults['ends_at'] = now()->addDays(7)->format('Y-m-d\TH:i');
        }

        return $defaults;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function defaultBlocks(): array
    {
        $blocks = [];

        foreach (['hero', 'categories', 'products', 'promo', 'testimonials', 'newsletter'] as $type) {
            $blocks[] = $this->makeBlock($type);
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    public function makeBlock(string $type, ?string $id = null): array
    {
        $definition = $this->get($type);

        if (! $definition) {
            throw new InvalidArgumentException('Unknown storefront block type.');
        }

        return [
            'id' => $id ?: 'blk-'.Str::lower(Str::random(10)),
            'type' => $type,
            'settings' => $this->defaultsFor($type),
            'style' => array_merge([
                'background' => 'none',
                'padding' => 'md',
                'width' => 'wide',
                'divider' => false,
            ], $definition['style']),
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<array<string, mixed>>
     */
    public function sanitizeBlocks(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        if (count($raw) > 80) {
            throw new InvalidArgumentException('A storefront page can contain at most 80 sections.');
        }

        $definitions = $this->all();
        $blocks = [];
        $ids = [];

        foreach ($raw as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $type = is_string($candidate['type'] ?? null) ? $candidate['type'] : '';
            $definition = $definitions[$type] ?? null;

            if (! $definition) {
                continue;
            }

            $id = is_string($candidate['id'] ?? null) ? trim($candidate['id']) : '';

            if (! preg_match('/^[a-zA-Z0-9_-]{4,50}$/', $id) || isset($ids[$id])) {
                $id = 'blk-'.Str::lower(Str::random(10));
            }

            $ids[$id] = true;
            $settings = $this->sanitizeSettings($definition, is_array($candidate['settings'] ?? null) ? $candidate['settings'] : []);
            $settings = $this->applyDefaults($definition, $settings);

            $blocks[] = [
                'id' => $id,
                'type' => $type,
                'settings' => $settings,
                'style' => $this->sanitizeStyle($candidate['style'] ?? [], $definition),
            ];
        }

        if (strlen((string) json_encode($blocks)) > 524288) {
            throw new InvalidArgumentException('The page content is too large to store.');
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function sanitizeSettings(array $definition, array $settings): array
    {
        $clean = [];

        foreach ($definition['fields'] as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            $type = $field['type'] ?? 'text';
            $value = $settings[$key] ?? null;
            $clean[$key] = $this->sanitizeValue($type, $value, $field);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function sanitizeValue(string $type, mixed $value, array $field): mixed
    {
        $max = max(1, (int) ($field['max'] ?? match ($type) {
            'textarea' => 5000,
            'url', 'image' => 500,
            default => 300,
        }));

        if ($type === 'toggle') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($type === 'number') {
            $number = is_numeric($value) ? (float) $value : (float) ($field['min'] ?? 0);
            $min = $field['min'] ?? 0;
            $limit = $field['max'] ?? 100;

            return max($min, min($limit, $number));
        }

        if ($type === 'select') {
            $options = is_array($field['options'] ?? null) ? array_keys($field['options']) : [];

            return in_array((string) $value, array_map('strval', $options), true)
                ? (string) $value
                : (string) (array_key_first($field['options'] ?? []) ?? '');
        }

        if (! is_scalar($value) && $value !== null) {
            return '';
        }

        $text = trim((string) $value);

        if ($type === 'color') {
            return preg_match('/^#[0-9a-fA-F]{6}$/', $text) ? $text : '#0f766e';
        }

        if ($type === 'url') {
            return $this->safeUrl($text);
        }

        if ($type === 'image') {
            return $this->safeImage($text);
        }

        if ($type === 'datetime') {
            $timestamp = strtotime($text);

            return $timestamp ? now()->setTimestamp($timestamp)->format('Y-m-d\TH:i') : '';
        }

        // Strip control characters but keep line breaks for textareas.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '';
        $text = Str::limit($text, $max, '');

        return $text;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function applyDefaults(array $definition, array $settings): array
    {
        foreach ($definition['fields'] as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            if (! array_key_exists($key, $settings)) {
                $settings[$key] = $definition['defaults'][$key] ?? '';
            }
        }

        return $settings;
    }

    /**
     * @param  mixed  $style
     * @param  array<string, mixed>  $definition
     * @return array{background: string, padding: string, width: string, divider: bool}
     */
    private function sanitizeStyle(mixed $style, array $definition): array
    {
        $style = is_array($style) ? $style : [];
        $defaults = array_merge(
            ['background' => 'none', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
            $definition['style'] ?? []
        );

        $background = in_array($style['background'] ?? null, self::BACKGROUNDS, true)
            ? $style['background']
            : $defaults['background'];

        $padding = in_array($style['padding'] ?? null, self::PADDINGS, true)
            ? $style['padding']
            : $defaults['padding'];

        $width = in_array($style['width'] ?? null, self::WIDTHS, true)
            ? $style['width']
            : $defaults['width'];

        return [
            'background' => in_array($background, self::BACKGROUNDS, true) ? $background : 'none',
            'padding' => in_array($padding, self::PADDINGS, true) ? $padding : 'md',
            'width' => in_array($width, self::WIDTHS, true) ? $width : 'wide',
            'divider' => filter_var($style['divider'] ?? $defaults['divider'], FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function safeUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, '//')) {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return mb_substr($value, 0, 500);
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return mb_substr($value, 0, 500);
        }

        return '';
    }

    private function safeImage(string $value): string
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, '//')) {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return mb_substr($value, 0, 500);
        }

        if (preg_match('#^(/?images/|/?uploads/)#i', $value)) {
            return mb_substr(ltrim($value, '/'), 0, 500);
        }

        return '';
    }
}
