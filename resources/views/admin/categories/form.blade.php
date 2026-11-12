@extends('admin.layouts.app')

@section('title', $category->exists ? 'Edit '.$category->name : 'New category')
@section('eyebrow', 'Categories')
@section('heading', $category->exists ? 'Edit category' : 'New category')

@section('content')
@php($isEdit = $category->exists)
<form method="post" action="{{ $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store') }}" enctype="multipart/form-data" class="grid gap-5 xl:grid-cols-[1.4fr_1fr]">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="card card-pad">
        <h2 class="font-display text-xl">Category details</h2>
        <div class="mt-4 space-y-4">
            <div>
                <label class="label" for="name">Name</label>
                <input id="name" name="name" value="{{ old('name', $category->name) }}" class="input" required>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="slug">URL slug</label>
                    <input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" class="input" placeholder="auto-generated">
                </div>
                <div>
                    <label class="label" for="parent_id">Parent category</label>
                    <select id="parent_id" name="parent_id" class="select">
                        <option value="">None (top level)</option>
                        @foreach($categories as $option)
                            <option value="{{ $option->id }}" @selected((int) old('parent_id', $category->parent_id) === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="label" for="description">Description</label>
                <textarea id="description" name="description" rows="4" class="textarea">{{ old('description', $category->description) }}</textarea>
            </div>
            <div>
                <label class="label" for="image">Image</label>
                <input id="image" type="file" name="image" accept="image/*" class="block w-full text-xs file:mr-3 file:rounded-full file:border-0 file:bg-[#0f766e] file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                @if($category->imageUrl())
                    <img src="{{ $category->imageUrl() }}" alt="" class="mt-3 h-24 w-24 rounded-xl object-cover">
                @endif
            </div>
            <div>
                <label class="label" for="sort_order">Sort order</label>
                <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" class="input !w-32">
            </div>
        </div>
        <div class="mt-5 flex gap-2">
            <button class="btn-primary">{{ $isEdit ? 'Save category' : 'Create category' }}</button>
            <a href="{{ route('admin.categories.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Visibility</h2>
            <label class="mt-4 flex items-center gap-2.5 text-sm"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" @checked(old('is_active', $category->is_active ?? true))> Show this category in the shop</label>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-xl">SEO</h2>
            <div class="mt-4 space-y-3">
                <div><label class="label" for="meta_title">Meta title</label><input id="meta_title" name="meta_title" value="{{ old('meta_title', $category->meta_title) }}" class="input"></div>
                <div><label class="label" for="meta_description">Meta description</label><textarea id="meta_description" name="meta_description" rows="3" class="textarea">{{ old('meta_description', $category->meta_description) }}</textarea></div>
            </div>
        </div>
        @if($isEdit)
            <div class="card card-pad">
                <button type="submit" form="delete-category" class="text-xs font-bold text-rose-600" onclick="return confirm('Delete this category? Products will be uncategorised.')">Delete category</button>
            </div>
        @endif
    </div>
</form>
@if($isEdit)
    <form id="delete-category" method="post" action="{{ route('admin.categories.destroy', $category) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection
