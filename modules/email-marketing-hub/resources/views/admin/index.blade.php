@extends('admin.layouts.app')

@section('title', 'Email marketing')
@section('eyebrow', 'Marketing')
@section('heading', 'Email Marketing Hub')

@section('content')
@php
    $providerLabels = collect($providers)->map(fn ($provider) => $provider->label());
    $active = $providers[$providerKey] ?? null;
    $activeConfigured = $configured[$providerKey] ?? false;
@endphp

<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="font-display text-3xl tracking-tight">Your audience, in sync.</h1>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-[#6b7a73]">Consented customers and newsletter subscribers are queued to your mailing provider. Checkout stays fast: nothing is sent to a third party during the request.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <span class="badge {{ $enabled && $activeConfigured ? 'badge-emerald' : 'badge-amber' }}">{{ $enabled && $activeConfigured ? 'Syncing enabled' : 'Not syncing yet' }}</span>
        <span class="badge badge-slate">{{ $active?->label() ?? 'No provider' }}</span>
    </div>
</div>

<div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card"><div class="stat-label">Subscribed</div><div class="stat-value">{{ number_format($stats['subscribed']) }}</div></div>
    <div class="stat-card"><div class="stat-label">Unsubscribed</div><div class="stat-value">{{ number_format($stats['unsubscribed']) }}</div></div>
    <div class="stat-card"><div class="stat-label">Synced</div><div class="stat-value">{{ number_format($stats['synced']) }}</div></div>
    <div class="stat-card"><div class="stat-label">Needs attention</div><div class="stat-value">{{ number_format($stats['failed']) }}</div></div>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-[1fr_330px]">
    <div class="grid content-start gap-5">
        <form method="post" action="{{ route('emailhub.admin.settings') }}" class="card card-pad">
            @csrf
            @method('put')

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Connection</div>
                    <h2 class="mt-1 font-display text-2xl">Choose a provider.</h2>
                </div>
                <span class="text-[11px] text-[#8a9892]">API keys are encrypted at rest</span>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach($providers as $key => $provider)
                    <label class="cursor-pointer rounded-xl border p-4 transition {{ $providerKey === $key ? 'border-[#0f766e] bg-[#f3fbf8] ring-2 ring-[#0f766e]/10' : 'border-[#e3e9e5] bg-white hover:border-[#9fc8bd]' }}">
                        <span class="flex items-start justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <input type="radio" name="provider" value="{{ $key }}" class="accent-[#0f766e]" @checked(old('provider', $providerKey) === $key)>
                                <span class="text-sm font-bold">{{ $provider->label() }}</span>
                            </span>
                            <span class="badge {{ $configured[$key] ? 'badge-emerald' : 'badge-slate' }}">{{ $configured[$key] ? 'Configured' : 'Not linked' }}</span>
                        </span>
                        <span class="mt-2 block text-[11px] leading-5 text-[#687771]">
                            @switch($key)
                                @case('mailchimp') Audiences, tags and contact management. @break
                                @case('klaviyo') Ecommerce profiles, lists and event tracking. @break
                                @case('brevo') Contacts, lists and automation events. @break
                                @case('mailerlite') Subscribers and groups for smaller lists. @break
                                @default Contacts and list memberships.
                            @endswitch
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="mt-5 grid gap-3 rounded-xl border border-[#e3e9e5] bg-[#fbfdfc] p-4 text-xs leading-5 text-[#52625c] sm:grid-cols-2">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="enabled" value="1" class="mt-0.5" @checked(old('enabled', $enabled))>
                    <span><strong class="block text-[#17231f]">Enable synchronization</strong>When off, consent is still recorded locally but nothing is sent to the provider.</span>
                </label>
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="checkout_opt_in" value="1" class="mt-0.5" @checked(old('checkout_opt_in', $checkoutOptIn))>
                    <span><strong class="block text-[#17231f]">Offer an opt-in at checkout</strong>The box is unchecked by default and kept separate from transactional order email.</span>
                </label>
            </div>

            <div class="mt-5 space-y-3">
                <details class="rounded-xl border border-[#e3e9e5] bg-white" @if($providerKey === 'mailchimp') open @endif>
                    <summary class="cursor-pointer px-4 py-3 text-sm font-bold">Mailchimp credentials</summary>
                    <div class="grid gap-4 border-t border-[#edf2ef] p-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="label" for="mailchimp_api_key">API key</label><input id="mailchimp_api_key" name="mailchimp_api_key" type="password" class="input font-mono text-xs" placeholder="{{ $configured['mailchimp'] ? 'Saved — leave blank to keep' : 'xxxxxxxx-us1' }}" autocomplete="new-password"></div>
                        <div><label class="label" for="mailchimp_list_id">Audience ID</label><input id="mailchimp_list_id" name="mailchimp_list_id" value="{{ old('mailchimp_list_id', $settings->get('marketing.mailchimp.list_id')) }}" class="input font-mono text-xs" placeholder="a1b2c3d4e5"></div>
                        <div><label class="label" for="mailchimp_server">Server prefix</label><input id="mailchimp_server" name="mailchimp_server" value="{{ old('mailchimp_server', $settings->get('marketing.mailchimp.server')) }}" class="input font-mono text-xs" placeholder="us1"></div>
                    </div>
                </details>

                <details class="rounded-xl border border-[#e3e9e5] bg-white" @if($providerKey === 'klaviyo') open @endif>
                    <summary class="cursor-pointer px-4 py-3 text-sm font-bold">Klaviyo credentials</summary>
                    <div class="grid gap-4 border-t border-[#edf2ef] p-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="label" for="klaviyo_api_key">Private API key</label><input id="klaviyo_api_key" name="klaviyo_api_key" type="password" class="input font-mono text-xs" placeholder="{{ $configured['klaviyo'] ? 'Saved — leave blank to keep' : 'pk_…' }}" autocomplete="new-password"></div>
                        <div><label class="label" for="klaviyo_list_id">List ID</label><input id="klaviyo_list_id" name="klaviyo_list_id" value="{{ old('klaviyo_list_id', $settings->get('marketing.klaviyo.list_id')) }}" class="input font-mono text-xs"></div>
                        <div><label class="label" for="klaviyo_revision">API revision</label><input id="klaviyo_revision" name="klaviyo_revision" value="{{ old('klaviyo_revision', $settings->get('marketing.klaviyo.revision', '2025-01-15')) }}" class="input font-mono text-xs"></div>
                    </div>
                </details>

                <details class="rounded-xl border border-[#e3e9e5] bg-white" @if($providerKey === 'brevo') open @endif>
                    <summary class="cursor-pointer px-4 py-3 text-sm font-bold">Brevo credentials</summary>
                    <div class="grid gap-4 border-t border-[#edf2ef] p-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="label" for="brevo_api_key">API key</label><input id="brevo_api_key" name="brevo_api_key" type="password" class="input font-mono text-xs" placeholder="{{ $configured['brevo'] ? 'Saved — leave blank to keep' : 'xkeysib-…' }}" autocomplete="new-password"></div>
                        <div><label class="label" for="brevo_list_id">List ID</label><input id="brevo_list_id" name="brevo_list_id" value="{{ old('brevo_list_id', $settings->get('marketing.brevo.list_id')) }}" class="input font-mono text-xs"></div>
                    </div>
                </details>

                <details class="rounded-xl border border-[#e3e9e5] bg-white" @if($providerKey === 'mailerlite') open @endif>
                    <summary class="cursor-pointer px-4 py-3 text-sm font-bold">MailerLite credentials</summary>
                    <div class="grid gap-4 border-t border-[#edf2ef] p-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="label" for="mailerlite_api_key">API token</label><input id="mailerlite_api_key" name="mailerlite_api_key" type="password" class="input font-mono text-xs" placeholder="{{ $configured['mailerlite'] ? 'Saved — leave blank to keep' : 'eyJ0…' }}" autocomplete="new-password"></div>
                        <div><label class="label" for="mailerlite_group_id">Group ID</label><input id="mailerlite_group_id" name="mailerlite_group_id" value="{{ old('mailerlite_group_id', $settings->get('marketing.mailerlite.group_id')) }}" class="input font-mono text-xs"></div>
                    </div>
                </details>

                <details class="rounded-xl border border-[#e3e9e5] bg-white" @if($providerKey === 'constant_contact') open @endif>
                    <summary class="cursor-pointer px-4 py-3 text-sm font-bold">Constant Contact credentials</summary>
                    <div class="grid gap-4 border-t border-[#edf2ef] p-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="label" for="constant_contact_access_token">OAuth access token</label><input id="constant_contact_access_token" name="constant_contact_access_token" type="password" class="input font-mono text-xs" placeholder="{{ $configured['constant_contact'] ? 'Saved — leave blank to keep' : 'Access token' }}" autocomplete="new-password"></div>
                        <div><label class="label" for="constant_contact_list_id">List ID</label><input id="constant_contact_list_id" name="constant_contact_list_id" value="{{ old('constant_contact_list_id', $settings->get('marketing.constant_contact.list_id')) }}" class="input font-mono text-xs"></div>
                    </div>
                </details>
            </div>

            <label class="mt-4 flex items-center gap-2 text-[11px] text-rose-700">
                <input type="checkbox" name="clear_provider_credentials" value="1">
                Clear the saved API key for the selected provider on save
            </label>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <button class="btn-primary btn-sm" type="submit">Save connection</button>
                <span class="text-[11px] text-[#8a9892]">Use the test button after saving.</span>
            </div>
        </form>

        <section class="table-wrap">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#edf2ef] px-4 py-3">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#74837c]">Audience</div>
                    <h2 class="mt-1 font-display text-xl">Consented contacts</h2>
                </div>
                <form method="post" action="{{ route('emailhub.admin.sync') }}">
                    @csrf
                    <button class="btn-secondary btn-sm">Queue all subscribed</button>
                </form>
            </div>
            @if($contacts->isEmpty())
                <div class="p-8 text-center text-sm text-[#687771]">No contacts yet. A checkout opt-in or newsletter signup will create the first record.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Contact</th><th>Consent</th><th>Provider</th><th>Last sync</th><th></th></tr></thead>
                        <tbody>
                            @foreach($contacts as $contact)
                                <tr>
                                    <td>
                                        <div class="font-semibold">{{ $contact->email }}</div>
                                        <div class="text-[10px] text-[#8a9892]">{{ $contact->name ?: 'No name' }} · {{ $contact->source ?: 'unknown source' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $contact->status === 'subscribed' ? 'badge-emerald' : 'badge-slate' }}">{{ ucfirst($contact->status) }}</span>
                                        <div class="mt-1 text-[10px] text-[#8a9892]">{{ $contact->consent_at?->format('M j, Y g:i A') }}</div>
                                    </td>
                                    <td class="text-xs">{{ $providerLabels[$contact->provider] ?? ($contact->provider ?: '—') }}</td>
                                    <td class="text-[11px] text-[#687771]">
                                        {{ $contact->last_synced_at?->diffForHumans() ?? 'Never' }}
                                        @if($contact->last_error)<div class="mt-1 max-w-[18rem] text-[10px] leading-4 text-rose-700">{{ $contact->last_error }}</div>@endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <form method="post" action="{{ route('emailhub.admin.contacts.retry', $contact) }}">@csrf<button class="text-[10px] font-bold text-[#0f766e]">Retry</button></form>
                                            @if($contact->status === 'subscribed')
                                                <form method="post" action="{{ route('emailhub.admin.contacts.unsubscribe', $contact) }}" onsubmit="return confirm('Unsubscribe this contact everywhere?')">@csrf<button class="text-[10px] font-bold text-rose-600">Unsubscribe</button></form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-[#edf2ef] px-4 py-3">{{ $contacts->links() }}</div>
            @endif
        </section>

        <section class="table-wrap">
            <div class="border-b border-[#edf2ef] px-4 py-3">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#74837c]">Reliability</div>
                <h2 class="mt-1 font-display text-xl">Recent sync attempts</h2>
            </div>
            @if($logs->isEmpty())
                <div class="p-6 text-sm text-[#7a8881]">No sync attempts recorded yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>When</th><th>Email</th><th>Action</th><th>Status</th><th>Message</th></tr></thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td class="text-[11px] text-[#687771]">{{ $log->created_at?->format('M j H:i') }}</td>
                                    <td class="text-xs">{{ $log->email }}</td>
                                    <td class="text-[11px]">{{ ucfirst($log->action) }}</td>
                                    <td><span class="badge {{ $log->status === 'ok' ? 'badge-emerald' : 'badge-rose' }}">{{ $log->status }}</span></td>
                                    <td class="max-w-xs text-[11px] leading-5 text-[#687771]">{{ $log->message }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <aside class="grid content-start gap-5">
        <section class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#74837c]">Connection check</div>
            <div class="mt-2 flex items-center gap-2">
                <span class="h-2 w-2 rounded-full {{ $activeConfigured ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                <span class="text-sm font-bold">{{ $active?->label() ?? 'No provider' }}</span>
            </div>
            <p class="mt-2 text-xs leading-5 text-[#687771]">{{ $activeConfigured ? 'Credentials are stored. Run a live check before enabling sync.' : 'Add the API key and list ID for the selected provider.' }}</p>
            <form method="post" action="{{ route('emailhub.admin.test') }}" class="mt-4">@csrf<button class="btn-secondary w-full text-xs">Test connection</button></form>
            <form method="post" action="{{ route('emailhub.admin.sync') }}" class="mt-2">@csrf<button class="btn-primary w-full text-xs">Queue all subscribed</button></form>
        </section>

        <section class="card card-pad border-amber-200 bg-amber-50">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-amber-800">Consent first</div>
            <p class="mt-2 text-[11px] leading-5 text-amber-900">Purchase and marketing consent are separate. The checkout box starts unchecked, the source and timestamp are stored, and an unsubscribe is pushed back to the provider. Transactional order email is never affected.</p>
        </section>

        <section class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#74837c]">How sync works</div>
            <ol class="mt-3 space-y-2 text-[11px] leading-5 text-[#687771]">
                <li><strong class="text-[#17231f]">1.</strong> A shopper opts in at checkout or joins the newsletter.</li>
                <li><strong class="text-[#17231f]">2.</strong> Consent is saved with source, IP and timestamp.</li>
                <li><strong class="text-[#17231f]">3.</strong> A queued job syncs the contact with retry and backoff.</li>
                <li><strong class="text-[#17231f]">4.</strong> Failures appear here with a retry button.</li>
            </ol>
        </section>

        <section class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#74837c]">Provider notes</div>
            <p class="mt-2 text-[11px] leading-5 text-[#687771]">Mailchimp and Klaviyo are best for ecommerce segmentation. Brevo and MailerLite suit newsletters on a tighter budget. Constant Contact needs an OAuth access token from its developer console. Keep queue workers running so syncs are not delayed.</p>
        </section>
    </aside>
</div>
@endsection
