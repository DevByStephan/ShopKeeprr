<?php

/*
|--------------------------------------------------------------------------
| Storefront builder blocks
|--------------------------------------------------------------------------
|
| Each block is a self-contained section an administrator can add, reorder,
| duplicate or remove on the storefront. Definitions are also exposed to the
| builder UI: field types drive the settings panel and every default can be
| restored. Modules may register additional blocks at runtime.
|
| Field types: text, textarea, url, number, color, select, toggle, image,
| datetime. Marking a field "inline" lets the administrator edit its text
| directly inside the live preview.
|
*/

return [

    'hero' => [
        'label' => 'Hero banner',
        'description' => 'Big headline, intro copy, buttons and a lead image. The first thing shoppers see.',
        'icon' => 'hero',
        'category' => 'Headers',
        'view' => 'theme.builder.blocks.hero',
        'style' => ['background' => 'none', 'padding' => 'none', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'eyebrow' => 'The everyday edit',
            'heading' => 'Good design, made to last.',
            'subheading' => 'Considered pieces for your home, your desk and your everyday rituals — chosen to be used, not just displayed.',
            'primary_label' => 'Shop the collection',
            'primary_url' => '/shop',
            'secondary_label' => 'Our story',
            'secondary_url' => '/pages/about',
            'image' => 'images/builder/hero.svg',
            'image_alt' => 'A considered arrangement of objects',
            'layout' => 'split',
            'tone' => 'surface',
            'note' => 'Free delivery over $75 · 30-day returns',
        ],
        'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
            'heading' => ['type' => 'textarea', 'label' => 'Headline', 'max' => 160, 'rows' => 2, 'inline' => true, 'help' => 'Keep it short and concrete. Two lines work best.'],
            'subheading' => ['type' => 'textarea', 'label' => 'Intro copy', 'max' => 400, 'rows' => 3, 'inline' => true],
            'image' => ['type' => 'image', 'label' => 'Lead image'],
            'image_alt' => ['type' => 'text', 'label' => 'Image description', 'max' => 160, 'help' => 'Describes the image for screen readers.'],
            'primary_label' => ['type' => 'text', 'label' => 'Primary button', 'max' => 40],
            'primary_url' => ['type' => 'url', 'label' => 'Primary button link', 'max' => 500],
            'secondary_label' => ['type' => 'text', 'label' => 'Secondary button', 'max' => 40],
            'secondary_url' => ['type' => 'url', 'label' => 'Secondary button link', 'max' => 500],
            'layout' => ['type' => 'select', 'label' => 'Layout', 'options' => ['split' => 'Text beside image', 'centered' => 'Centered, image below', 'image-first' => 'Image first']],
            'tone' => ['type' => 'select', 'label' => 'Panel tone', 'options' => ['surface' => 'Soft surface', 'light' => 'White', 'brand' => 'Brand tint', 'dark' => 'Deep ink']],
            'note' => ['type' => 'text', 'label' => 'Small note', 'max' => 160, 'inline' => true],
        ],
    ],

    'carousel' => [
        'label' => 'Image carousel',
        'description' => 'A full-width slideshow for campaigns, seasonal edits or a few hero messages.',
        'icon' => 'gallery',
        'category' => 'Headers',
        'view' => 'theme.builder.blocks.carousel',
        'style' => ['background' => 'none', 'padding' => 'sm', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'slides' => "images/builder/hero.svg | The new season | Made slowly. Chosen well. | A short edit of pieces we reach for every day. | /shop | Shop the edit\nimages/builder/promo.svg | The workroom | Tools for clearer work. | Objects that earn their place on the desk. | /shop | Browse desk",
            'height' => 'tall',
            'autoplay' => true,
        ],
        'fields' => [
            'slides' => ['type' => 'textarea', 'label' => 'Slides', 'rows' => 6, 'max' => 4000, 'help' => 'One slide per line: image | eyebrow | heading | copy | link | button label. The last three parts are optional.'],
            'height' => ['type' => 'select', 'label' => 'Height', 'options' => ['tall' => 'Tall', 'medium' => 'Medium', 'short' => 'Short']],
            'autoplay' => ['type' => 'toggle', 'label' => 'Advance automatically'],
        ],
    ],

    'products' => [
        'label' => 'Featured products',
        'description' => 'A product grid pulled from the catalog: featured, newest, or reduced items.',
        'icon' => 'products',
        'category' => 'Commerce',
        'view' => 'theme.builder.blocks.products',
        'style' => ['background' => 'none', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'eyebrow' => 'Curated for you',
            'heading' => 'Featured pieces',
            'copy' => '',
            'source' => 'featured',
            'limit' => 8,
            'columns' => 4,
            'cta_label' => 'View all',
            'cta_url' => '/shop',
        ],
        'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
            'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 140, 'inline' => true],
            'copy' => ['type' => 'textarea', 'label' => 'Supporting copy', 'max' => 300, 'rows' => 2, 'inline' => true],
            'source' => ['type' => 'select', 'label' => 'Products to show', 'options' => ['featured' => 'Featured products', 'newest' => 'Newest arrivals', 'sale' => 'Reduced items']],
            'limit' => ['type' => 'number', 'label' => 'Number of products', 'min' => 2, 'max' => 12, 'step' => 1],
            'columns' => ['type' => 'select', 'label' => 'Products per row', 'options' => ['2' => 'Two', '3' => 'Three', '4' => 'Four']],
            'cta_label' => ['type' => 'text', 'label' => 'Link label', 'max' => 40],
            'cta_url' => ['type' => 'url', 'label' => 'Link target', 'max' => 500],
        ],
    ],

    'categories' => [
        'label' => 'Category tiles',
        'description' => 'A visual index of your active categories with product counts.',
        'icon' => 'categories',
        'category' => 'Commerce',
        'view' => 'theme.builder.blocks.categories',
        'style' => ['background' => 'none', 'padding' => 'sm', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'eyebrow' => 'Browse the shelves',
            'heading' => 'Shop by category',
            'limit' => 4,
            'cta_label' => 'All categories',
            'cta_url' => '/shop',
        ],
        'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
            'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 140, 'inline' => true],
            'limit' => ['type' => 'number', 'label' => 'Number of categories', 'min' => 2, 'max' => 8, 'step' => 1],
            'cta_label' => ['type' => 'text', 'label' => 'Link label', 'max' => 40],
            'cta_url' => ['type' => 'url', 'label' => 'Link target', 'max' => 500],
        ],
    ],

    'promo' => [
        'label' => 'Promo banner',
        'description' => 'A split image and copy block for a collection, story or seasonal offer.',
        'icon' => 'promo',
        'category' => 'Content',
        'view' => 'theme.builder.blocks.promo',
        'style' => ['background' => 'soft', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'eyebrow' => 'The workroom',
            'heading' => 'Tools that earn their place.',
            'copy' => 'A small collection for the desk: warm light, good paper and a place for everything. Built to be used every day, not saved for best.',
            'button_label' => 'Explore the edit',
            'button_url' => '/shop',
            'image' => 'images/builder/promo.svg',
            'image_alt' => 'A quiet desk arrangement',
            'layout' => 'image-left',
        ],
        'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
            'heading' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 160, 'rows' => 2, 'inline' => true],
            'copy' => ['type' => 'textarea', 'label' => 'Copy', 'max' => 600, 'rows' => 4, 'inline' => true],
            'image' => ['type' => 'image', 'label' => 'Image'],
            'image_alt' => ['type' => 'text', 'label' => 'Image description', 'max' => 160],
            'button_label' => ['type' => 'text', 'label' => 'Button label', 'max' => 40],
            'button_url' => ['type' => 'url', 'label' => 'Button link', 'max' => 500],
            'layout' => ['type' => 'select', 'label' => 'Layout', 'options' => ['image-left' => 'Image on the left', 'image-right' => 'Image on the right']],
        ],
    ],

    'testimonials' => [
        'label' => 'Testimonials',
        'description' => 'A row of customer quotes. Add one quote per line.',
        'icon' => 'quote',
        'category' => 'Content',
        'view' => 'theme.builder.blocks.testimonials',
        'style' => ['background' => 'surface', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'eyebrow' => 'Word of mouth',
            'heading' => 'Bought once. Kept for years.',
            'items' => "It arrived beautifully packed and feels even better in person. The kind of thing I will still be using in ten years. | Marta R. | Verified buyer\nYou can tell someone thought about every detail, from the weight of it to the note in the box. | James K. | Verified buyer\nThe perfect balance of useful and beautiful. I have already bought two more as gifts. | Aisha L. | Verified buyer",
            'columns' => '3',
        ],
        'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
            'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 140, 'inline' => true],
            'items' => ['type' => 'textarea', 'label' => 'Quotes', 'rows' => 6, 'max' => 4000, 'help' => 'One quote per line: quote | name | detail.'],
            'columns' => ['type' => 'select', 'label' => 'Quotes per row', 'options' => ['2' => 'Two', '3' => 'Three']],
        ],
    ],

    'newsletter' => [
        'label' => 'Newsletter',
        'description' => 'Collect email addresses for launches, restocks and store news.',
        'icon' => 'mail',
        'category' => 'Conversion',
        'view' => 'theme.builder.blocks.newsletter',
        'style' => ['background' => 'ink', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'eyebrow' => 'Stay close',
            'heading' => 'Notes from the workroom.',
            'copy' => 'New pieces, small restocks and the occasional story. No noise, and you can leave whenever you like.',
            'button_label' => 'Join the list',
            'placeholder' => 'you@example.com',
            'note' => 'We only use your email for store news. Read the privacy policy for the details.',
        ],
        'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
            'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 140, 'inline' => true],
            'copy' => ['type' => 'textarea', 'label' => 'Copy', 'max' => 400, 'rows' => 3, 'inline' => true],
            'button_label' => ['type' => 'text', 'label' => 'Button label', 'max' => 40],
            'placeholder' => ['type' => 'text', 'label' => 'Input placeholder', 'max' => 80],
            'note' => ['type' => 'text', 'label' => 'Small print', 'max' => 200],
        ],
    ],

    'rich_text' => [
        'label' => 'Text section',
        'description' => 'A heading and a block of formatted text. Useful for store stories and policies.',
        'icon' => 'text',
        'category' => 'Content',
        'view' => 'theme.builder.blocks.rich-text',
        'style' => ['background' => 'none', 'padding' => 'md', 'width' => 'narrow', 'divider' => false],
        'defaults' => [
            'eyebrow' => 'Our approach',
            'heading' => 'Fewer, better things.',
            'body' => "We work with a small group of makers who care about materials, repair and the long life of an object.\n\nEvery piece is chosen because it solves a real, everyday problem — and looks good doing it.",
        ],
        'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
            'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 160, 'inline' => true],
            'body' => ['type' => 'textarea', 'label' => 'Text', 'rows' => 8, 'max' => 5000, 'help' => 'Use ## for a heading, - for a list item, **text** for bold, and a blank line between paragraphs.'],
        ],
    ],

    'image' => [
        'label' => 'Image',
        'description' => 'A single wide or inset image with an optional caption and link.',
        'icon' => 'image',
        'category' => 'Content',
        'view' => 'theme.builder.blocks.image',
        'style' => ['background' => 'none', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'image' => 'images/builder/hero.svg',
            'alt' => '',
            'caption' => '',
            'link' => '',
            'ratio' => 'wide',
            'rounded' => true,
        ],
        'fields' => [
            'image' => ['type' => 'image', 'label' => 'Image'],
            'alt' => ['type' => 'text', 'label' => 'Image description', 'max' => 160],
            'caption' => ['type' => 'text', 'label' => 'Caption', 'max' => 200, 'inline' => true],
            'link' => ['type' => 'url', 'label' => 'Image link', 'max' => 500],
            'ratio' => ['type' => 'select', 'label' => 'Shape', 'options' => ['wide' => 'Wide', 'square' => 'Square', 'portrait' => 'Portrait', 'natural' => 'Original']],
            'rounded' => ['type' => 'toggle', 'label' => 'Rounded corners'],
        ],
    ],

    'countdown' => [
        'label' => 'Countdown banner',
        'description' => 'A deadline-led banner for launches, sales and limited drops.',
        'icon' => 'clock',
        'category' => 'Conversion',
        'view' => 'theme.builder.blocks.countdown',
        'style' => ['background' => 'brand', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
        'defaults' => [
            'eyebrow' => 'Coming soon',
            'heading' => 'The autumn edit opens in',
            'copy' => 'A short run of pieces, made once and not repeated. Join the list to be first through the door.',
            'ends_at' => '',
            'expired_text' => 'The edit is open. Come inside.',
            'button_label' => 'Shop the edit',
            'button_url' => '/shop',
        ],
        'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 80, 'inline' => true],
            'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 140, 'inline' => true],
            'copy' => ['type' => 'textarea', 'label' => 'Copy', 'max' => 300, 'rows' => 2, 'inline' => true],
            'ends_at' => ['type' => 'datetime', 'label' => 'Countdown ends'],
            'expired_text' => ['type' => 'text', 'label' => 'When the timer ends', 'max' => 140, 'inline' => true],
            'button_label' => ['type' => 'text', 'label' => 'Button label', 'max' => 40],
            'button_url' => ['type' => 'url', 'label' => 'Button link', 'max' => 500],
        ],
    ],

    'trust_bar' => [
        'label' => 'Reassurance bar',
        'description' => 'Short promises: delivery, returns, support, secure payment.',
        'icon' => 'check',
        'category' => 'Conversion',
        'view' => 'theme.builder.blocks.trust-bar',
        'style' => ['background' => 'none', 'padding' => 'sm', 'width' => 'wide', 'divider' => true],
        'defaults' => [
            'items' => "Free delivery | On orders over $75, anywhere we ship.\nEasy returns | Thirty days to change your mind.\nSecure checkout | Encrypted payments, no card details stored.\nReal support | A person answers, usually the same day.",
            'columns' => '4',
        ],
        'fields' => [
            'items' => ['type' => 'textarea', 'label' => 'Promises', 'rows' => 5, 'max' => 2000, 'help' => 'One per line: title | description.'],
            'columns' => ['type' => 'select', 'label' => 'Items per row', 'options' => ['2' => 'Two', '3' => 'Three', '4' => 'Four']],
        ],
    ],

];
