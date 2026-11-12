@extends('theme.layouts.app')

@section('title', ($page->meta_title ?: $page->title).' — '.nexus_setting('store.name'))
@section('description', $page->meta_description ?: Str::limit(strip_tags($page->content), 150))

@section('content')
<div class="border-b border-[var(--line)] bg-[var(--surface-2)]">
    <div class="container-wide py-12 sm:py-16">
        <nav class="text-[11px] font-semibold uppercase tracking-[.12em] text-[var(--muted)]">
            <a href="{{ route('storefront.home') }}" class="hover:text-[var(--link)]">Home</a>
            <span class="px-2">/</span>
            <span class="text-[var(--muted)]">{{ $page->title }}</span>
        </nav>
        <h1 class="mt-4 max-w-3xl font-display text-4xl tracking-tight sm:text-5xl">{{ $page->title }}</h1>
    </div>
</div>
<div class="container-narrow py-12">
    <div class="prose-nexus">
        {!! nexus_page_content($page->content) !!}
    </div>
</div>
@endsection
