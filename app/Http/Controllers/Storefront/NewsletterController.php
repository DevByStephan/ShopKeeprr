<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class NewsletterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_if(nexus_demo(), 403, 'Newsletter signup is disabled in this demo.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        $email = strtolower(trim($data['email']));

        $subscriber = NewsletterSubscriber::query()->firstOrCreate(
            ['email' => $email],
            [
                'source' => 'storefront',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'subscribed_at' => now(),
            ]
        );

        if ($subscriber->wasRecentlyCreated) {
            Event::dispatch('marketing.opt-in', [[
                'consent' => true,
                'email' => $email,
                'name' => auth()->user()?->name,
                'user_id' => auth()->id(),
                'source' => 'newsletter',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'tags' => ['newsletter'],
            ]]);
        }

        return back()->with('newsletter_status', 'You are on the list. Look out for the next note.');
    }
}
