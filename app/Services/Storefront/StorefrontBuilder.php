<?php

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontPage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class StorefrontBuilder
{
    public function __construct(private readonly BlockRegistry $registry) {}

    /**
     * The editable page record. Public requests never call this, so they do
     * not create rows while the builder is unused.
     */
    public function page(string $key = 'home'): StorefrontPage
    {
        return StorefrontPage::query()->firstOrCreate(
            ['key' => $key],
            [
                'title' => $key === 'home' ? 'Home' : ucfirst($key),
                'blocks' => $this->registry->defaultBlocks(),
                'is_enabled' => false,
            ]
        );
    }

    /**
     * Published blocks for the public homepage, or null when the theme's own
     * homepage should be used (builder never published, or switched off).
     *
     * @return list<array<string, mixed>>|null
     */
    public function homeBlocks(): ?array
    {
        try {
            $page = StorefrontPage::query()->where('key', 'home')->first();
        } catch (Throwable) {
            return null;
        }

        if (! $page || ! $page->isPublished()) {
            return null;
        }

        return $this->hydrate($page->published_blocks);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public function hydrate(array $blocks): array
    {
        $hydrated = [];

        foreach ($blocks as $block) {
            $definition = $this->registry->get((string) ($block['type'] ?? ''));

            if (! $definition) {
                continue;
            }

            $block['settings'] = array_merge(
                $this->registry->defaultsFor($block['type']),
                is_array($block['settings'] ?? null) ? $block['settings'] : []
            );
            $block['style'] = array_merge(
                ['background' => 'none', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
                $definition['style'] ?? [],
                is_array($block['style'] ?? null) ? $block['style'] : []
            );
            $block['label'] = $definition['label'];
            $block['data'] = $this->dataFor($block);

            $hydrated[] = $block;
        }

        return $hydrated;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function dataFor(array $block): array
    {
        $settings = $block['settings'];

        return match ($block['type']) {
            'products' => ['products' => $this->products($settings)],
            'categories' => ['categories' => $this->categories((int) ($settings['limit'] ?? 4))],
            'testimonials' => ['quotes' => $this->quotes((string) ($settings['items'] ?? ''))],
            'trust_bar' => ['items' => $this->pairs((string) ($settings['items'] ?? ''))],
            'carousel' => ['slides' => $this->slides((string) ($settings['slides'] ?? ''))],
            'countdown' => ['ends_at' => $this->date((string) ($settings['ends_at'] ?? ''))],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return Collection<int, Product>
     */
    private function products(array $settings): Collection
    {
        $limit = max(2, min(12, (int) ($settings['limit'] ?? 8)));
        $source = $settings['source'] ?? 'featured';

        $query = Product::query()
            ->visibleInMode()
            ->with(['images', 'category', 'activeVariants'])
            ->latest('published_at');

        if ($source === 'featured') {
            $featured = (clone $query)->featured()->take($limit)->get();

            if ($featured->isNotEmpty()) {
                return $featured;
            }

            return $query->take($limit)->get();
        }

        if ($source === 'sale') {
            return $query
                ->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price')
                ->take($limit)
                ->get();
        }

        return $query->take($limit)->get();
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(int $limit): Collection
    {
        return Category::query()
            ->active()
            ->withCount(['products' => fn ($query) => $query->visibleInMode()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->take(max(2, min(8, $limit)))
            ->get();
    }

    /**
     * @return list<array{quote: string, name: string, detail: string}>
     */
    private function quotes(string $value): array
    {
        $quotes = [];

        foreach ($this->lines($value) as $line) {
            $parts = array_map('trim', explode('|', $line, 3));

            if (($parts[0] ?? '') === '') {
                continue;
            }

            $quotes[] = [
                'quote' => $parts[0],
                'name' => $parts[1] ?? '',
                'detail' => $parts[2] ?? '',
            ];
        }

        return $quotes;
    }

    /**
     * @return list<array{title: string, copy: string}>
     */
    private function pairs(string $value): array
    {
        $items = [];

        foreach ($this->lines($value) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));

            if (($parts[0] ?? '') === '') {
                continue;
            }

            $items[] = ['title' => $parts[0], 'copy' => $parts[1] ?? ''];
        }

        return $items;
    }

    /**
     * @return list<array{image: string, eyebrow: string, heading: string, copy: string, url: string, label: string}>
     */
    private function slides(string $value): array
    {
        $slides = [];

        foreach ($this->lines($value) as $line) {
            $parts = array_map('trim', explode('|', $line, 6));

            if (trim(implode('', $parts)) === '') {
                continue;
            }

            $slides[] = [
                'image' => $parts[0] ?? '',
                'eyebrow' => $parts[1] ?? '',
                'heading' => $parts[2] ?? '',
                'copy' => $parts[3] ?? '',
                'url' => $parts[4] ?? '',
                'label' => $parts[5] ?? '',
            ];
        }

        return $slides;
    }

    /**
     * @return list<string>
     */
    private function lines(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));
    }

    private function date(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
