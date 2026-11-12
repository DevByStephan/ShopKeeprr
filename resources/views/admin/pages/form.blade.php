@extends('admin.layouts.app')

@section('title', $page->exists ? 'Edit '.$page->title : 'New page')
@section('eyebrow', 'Pages')
@section('heading', $page->exists ? 'Edit page' : 'New page')

@section('content')
@php($isEdit = $page->exists)
<form method="post" action="{{ $isEdit ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="grid gap-5 xl:grid-cols-[1.4fr_1fr]">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="card card-pad">
        <div class="space-y-4">
            <div>
                <label class="label" for="title">Title</label>
                <input id="title" name="title" value="{{ old('title', $page->title) }}" class="input" required>
            </div>
            <div>
                <label class="label" for="slug">URL slug</label>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-[#8a9892]">/pages/</span>
                    <input id="slug" name="slug" value="{{ old('slug', $page->slug) }}" class="input" placeholder="shipping-returns">
                </div>
            </div>
            <div>
                <label class="label" for="content">Content</label>
                <textarea id="content" name="content" rows="16" class="textarea font-mono text-xs" required>{{ old('content', $page->content) }}</textarea>
                <p class="mt-1 text-[11px] leading-5 text-[#8a9892]">Plain text with light formatting: <code>## Heading</code>, <code>### Subheading</code>, <code>- list item</code>, <code>**bold**</code>, and a blank line between paragraphs.</p>
            </div>
        </div>
        <div class="mt-5 flex gap-2">
            <button class="btn-primary">{{ $isEdit ? 'Save page' : 'Create page' }}</button>
            <a href="{{ route('admin.pages.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </div>
    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Publish</h2>
            <label class="mt-4 flex items-center gap-2.5 text-sm"><input type="checkbox" name="is_published" value="1" class="accent-[#0f766e]" @checked(old('is_published', $page->is_published ?? true))> Visible on the storefront</label>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-xl">SEO</h2>
            <div class="mt-4 space-y-3">
                <div><label class="label" for="meta_title">Meta title</label><input id="meta_title" name="meta_title" value="{{ old('meta_title', $page->meta_title) }}" class="input"></div>
                <div><label class="label" for="meta_description">Meta description</label><textarea id="meta_description" name="meta_description" rows="3" class="textarea">{{ old('meta_description', $page->meta_description) }}</textarea></div>
            </div>
        </div>
    </div>
</form>
@if($isEdit)
    <div class="mt-5 card card-pad">
        <form method="post" action="{{ route('admin.pages.destroy', $page) }}" onsubmit="return confirm('Delete this page?')">
            @csrf
            @method('DELETE')
            <button class="text-xs font-bold text-rose-600">Delete page</button>
        </form>
    </div>
@endif
@endsection
