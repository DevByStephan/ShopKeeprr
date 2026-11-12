@extends('admin.layouts.app')

@section('title', 'Edit '.$file)
@section('eyebrow', 'Theme')
@section('heading', 'Edit template')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.theme.index') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[#4d5d56] hover:text-[#0f766e]">← Theme</a>
        <span class="text-[#d5ddd8]">/</span>
        <span class="font-mono text-xs">{{ $file }}.blade.php</span>
        <span class="badge {{ $isOverride ? 'badge-violet' : 'badge-slate' }}">{{ $isOverride ? 'Custom override' : 'Default template' }}</span>
    </div>
    @if($isOverride)
        <form method="post" action="{{ route('admin.theme.reset', $file) }}" onsubmit="return confirm('Reset this template to the shipped default?')">
            @csrf
            @method('DELETE')
            <button class="text-xs font-semibold text-rose-600">Reset to default</button>
        </form>
    @endif
</div>

<form method="post" action="{{ route('admin.theme.save', $file) }}">
    @csrf
    @method('PUT')
    <div class="overflow-hidden rounded-2xl border border-[#1f3a34] bg-[#102522]">
        <div class="flex items-center justify-between border-b border-white/10 px-4 py-2.5">
            <div class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-rose-400/70"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-300/70"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/70"></span>
            </div>
            <span class="font-mono text-[10px] text-white/35">resources/views/theme/{{ $file }}.blade.php</span>
        </div>
        <textarea name="contents" spellcheck="false" rows="30" class="w-full resize-y border-0 bg-transparent px-5 py-4 font-mono text-[12px] leading-6 text-[#d4ece5] outline-none">{{ $contents }}</textarea>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-[#687771]">The template is compiled before saving; syntax errors are rejected.</p>
        <div class="flex gap-2">
            <a href="{{ route('admin.theme.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Save template</button>
        </div>
    </div>
</form>
@endsection
