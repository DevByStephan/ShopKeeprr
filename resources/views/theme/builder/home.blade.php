@extends('theme.layouts.app')

@section('title', nexus_setting('store.name', 'ShopKeeprr').' — '.nexus_setting('store.tagline', ''))
@section('description', nexus_setting('store.tagline', ''))

@section('content')
@php
    $registry = app(\App\Services\Storefront\BlockRegistry::class);
@endphp

@foreach($blocks as $block)
    @php
        $definition = $registry->get((string) ($block['type'] ?? ''));
        $view = $definition ? $registry->view($block['type']) : null;
        $style = $block['style'] ?? [];
        $background = match ($style['background'] ?? 'none') {
            'surface' => 'bg-[var(--surface)]',
            'soft' => 'bg-[var(--surface-2)]',
            'card' => 'bg-[var(--card)] border-y border-[var(--line)]',
            'ink' => 'bg-[var(--ink-strong)] text-white',
            'brand' => 'bg-[var(--brand-soft)]',
            default => '',
        };
        $padding = match ($style['padding'] ?? 'md') {
            'none' => '',
            'sm' => 'py-7 sm:py-9',
            'lg' => 'py-14 sm:py-20',
            default => 'py-10 sm:py-14',
        };
        $container = ($style['width'] ?? 'wide') === 'narrow'
            ? 'mx-auto w-full max-w-3xl px-5 sm:px-6'
            : 'container-wide';
    @endphp

    @if($definition && $view)
        <section class="builder-rendered-block relative {{ $background }} {{ $padding }} {{ ! empty($style['divider']) ? 'border-y border-[var(--line)]' : '' }}"
                 data-block-id="{{ $block['id'] }}"
                 data-block-label="{{ $definition['label'] }}">
            <div class="{{ $container }}">
                @include($view, ['settings' => $block['settings'], 'data' => $block['data'] ?? [], 'block' => $block])
            </div>
        </section>
    @endif
@endforeach

@if($blocks === [])
    <section class="container-wide py-24 text-center">
        <div class="mx-auto max-w-md rounded-2xl border border-dashed border-[var(--line)] bg-[var(--card)] p-8">
            <div class="font-display text-2xl">This page is empty.</div>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Add a section from the library to begin building the storefront.</p>
        </div>
    </section>
@endif
@endsection
