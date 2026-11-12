<?php

namespace Modules\AnnouncementBars;

use App\Services\Storefront\BlockRegistry;
use Illuminate\Support\ServiceProvider;

class AnnouncementBarsServiceProvider extends ServiceProvider
{
    public function boot(BlockRegistry $blocks): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'announcement-bars');

        $blocks->register('promo-bar', [
            'label' => 'Announcement bar',
            'description' => 'A dismissed-able strip for shipping notices and short promotions.',
            'category' => 'Conversion',
            'icon' => 'megaphone',
            'view' => 'announcement-bars::blocks.bar',
            'defaults' => [
                'message' => 'Free shipping on orders over $75',
                'link_label' => 'Shop the edit',
                'link_url' => '/shop',
                'tone' => 'brand',
                'dismissible' => true,
            ],
            'fields' => [
                'message' => ['type' => 'text', 'label' => 'Message', 'max' => 180, 'inline' => true],
                'link_label' => ['type' => 'text', 'label' => 'Link label', 'max' => 50],
                'link_url' => ['type' => 'url', 'label' => 'Link URL'],
                'tone' => ['type' => 'select', 'label' => 'Tone', 'options' => ['brand' => 'Brand', 'ink' => 'Dark', 'soft' => 'Soft']],
                'dismissible' => ['type' => 'toggle', 'label' => 'Let shoppers dismiss it'],
            ],
            'style' => ['background' => 'none', 'padding' => 'none', 'width' => 'wide'],
        ]);

        $blocks->register('promo-countdown', [
            'label' => 'Countdown promotion',
            'description' => 'A deadline section with a live day/hour/minute timer.',
            'category' => 'Conversion',
            'icon' => 'clock',
            'view' => 'announcement-bars::blocks.countdown',
            'defaults' => [
                'eyebrow' => 'Ends soon',
                'heading' => 'The launch offer closes in',
                'copy' => 'Take the launch price while it lasts.',
                'ends_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
                'button_label' => 'Shop the offer',
                'button_url' => '/shop',
            ],
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 140, 'inline' => true],
                'copy' => ['type' => 'textarea', 'label' => 'Copy', 'max' => 300, 'inline' => true],
                'ends_at' => ['type' => 'datetime', 'label' => 'Ends at'],
                'button_label' => ['type' => 'text', 'label' => 'Button label', 'max' => 60],
                'button_url' => ['type' => 'url', 'label' => 'Button URL'],
            ],
            'style' => ['background' => 'brand', 'padding' => 'md', 'width' => 'wide'],
        ]);
    }
}
