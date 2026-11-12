@php($dark = ($block['style']['background'] ?? '') === 'ink')

<div class="grid items-center gap-8 rounded-[28px] border {{ $dark ? 'border-white/10 bg-white/[.04]' : 'border-[var(--line)] bg-[var(--card)]' }} px-6 py-9 sm:px-10 lg:grid-cols-[.95fr_1.05fr] lg:px-12">
    <div>
        @if(filled($settings['eyebrow'] ?? ''))
            <div class="text-[10px] font-bold uppercase tracking-[.18em] {{ $dark ? 'text-[#7fd8c9]' : 'text-[var(--link)]' }}">
                <span data-builder-text="eyebrow">{{ $settings['eyebrow'] }}</span>
            </div>
        @endif
        <h2 class="mt-3 font-display text-3xl tracking-tight {{ $dark ? 'text-white' : 'text-[var(--ink)]' }} sm:text-4xl">
            <span data-builder-text="heading">{{ $settings['heading'] }}</span>
        </h2>
        @if(filled($settings['copy'] ?? ''))
            <p class="mt-3 max-w-lg text-sm leading-7 {{ $dark ? 'text-white/60' : 'text-[var(--muted)]' }}">
                <span data-builder-text="copy">{{ $settings['copy'] }}</span>
            </p>
        @endif
    </div>

    <div>
        <form method="post" action="{{ route('storefront.newsletter') }}" class="flex flex-col gap-3 sm:flex-row">
            @csrf
            <label for="newsletter-email-{{ $block['id'] }}" class="sr-only">Email address</label>
            <input id="newsletter-email-{{ $block['id'] }}" type="email" name="email" required maxlength="190"
                   placeholder="{{ $settings['placeholder'] ?? 'you@example.com' }}"
                   class="h-12 min-w-0 flex-1 rounded-full border px-5 text-sm outline-none transition {{ $dark ? 'border-white/15 bg-white/10 text-white placeholder:text-white/40 focus:border-white/40' : 'border-[var(--line)] bg-[var(--surface)] text-[var(--ink)] focus:border-[var(--brand)]' }}">
            <button type="submit" class="btn-primary h-12 shrink-0 px-6">{{ $settings['button_label'] ?? 'Join the list' }}</button>
        </form>
        @if(filled($settings['note'] ?? ''))
            <p class="mt-3 text-[10px] leading-4 {{ $dark ? 'text-white/40' : 'text-[var(--muted)]' }}">
                <span data-builder-text="note">{{ $settings['note'] }}</span>
            </p>
        @endif
        @if(session('newsletter_status'))
            <p class="mt-3 text-xs font-semibold {{ $dark ? 'text-[#7fd8c9]' : 'text-[var(--success)]' }}">{{ session('newsletter_status') }}</p>
        @endif
    </div>
</div>
