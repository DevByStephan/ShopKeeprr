# Storefront builder

**Admin → Storefront builder** replaces the theme homepage with a page assembled from sections. Drag a block from the library into the preview, or click it to append it. Reorder blocks in the layer list or directly on the page.

## Built-in sections

- Hero banner
- Image carousel
- Featured products
- Category tiles
- Promo banner
- Testimonials
- Newsletter signup
- Text section
- Image
- Countdown banner
- Reassurance bar

Product sections read the live catalog. Category tiles count visible products. Every block uses the active theme's colour tokens and product-card presentation.

## Drafts and previews

Every change is saved to a draft. Shoppers continue to see the last published layout until an administrator selects **Publish**.

The builder includes desktop, tablet and mobile preview widths. **Use theme homepage** returns to the active theme's own homepage while keeping the draft layout saved. **Starter sections** restores a default page.

## Safe input

Saving a layout is normalized on the server:

- Unknown block types are dropped.
- Links are restricted to `http(s)`, `/` or `#`.
- Images must be uploads or absolute URLs.
- Text is escaped before display.
- A page is capped at 80 sections and 512 KB.

## Extending the builder

A component is a module of type `component`. Its service provider registers a section with `App\Services\Storefront\BlockRegistry`:

```php
use App\Services\Storefront\BlockRegistry;
use Illuminate\Support\ServiceProvider;

class ServiceProvider extends ServiceProvider
{
    public function boot(BlockRegistry $blocks): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'countdown-strip');

        $blocks->register('countdown-strip', [
            'label' => 'Countdown strip',
            'description' => 'A slim deadline banner for launches.',
            'category' => 'Conversion',
            'icon' => 'clock',
            'view' => 'countdown-strip::block',
            'defaults' => [
                'heading' => 'Doors open in',
                'ends_at' => '',
            ],
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120, 'inline' => true],
                'ends_at' => ['type' => 'datetime', 'label' => 'Countdown ends'],
            ],
            'style' => ['background' => 'brand', 'padding' => 'sm', 'width' => 'wide'],
        ]);
    }
}
```

Supported field types include `text`, `textarea`, `url`, `number`, `color`, `select`, `toggle`, `image` and `datetime`. Add `inline` to a text field to allow direct editing in the preview.

Use a vendor-prefixed block key, keep the Blade view self-contained and render with the storefront CSS variables so the section follows the active theme. Package the module as a normal versioned release; the security scan and review process are the same as for plugins.

> Components are modules. They run with the same privileges as the store and are not sandboxed. Review the code before installing it.
