<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceSiteLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceLinkController extends Controller
{
    public function show(Request $request): View
    {
        $code = strtoupper(trim((string) $request->query('code', '')));
        $link = $this->findPending($code);

        return view('marketplace.link', [
            'code' => $code,
            'link' => $link,
            'error' => $code !== '' && ! $link ? 'That connection code is invalid or has expired. Return to your store admin and start again.' : null,
        ]);
    }

    public function approve(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        $code = strtoupper(trim($data['code']));
        $link = $this->findPending($code);

        if (! $link) {
            return back()->withErrors(['code' => 'That connection code is invalid or has expired. Start again from your store admin.']);
        }

        if ($link->user_id !== null && $link->user_id !== $request->user()->id) {
            abort(403, 'This connection request belongs to another account.');
        }

        $link->forceFill([
            'user_id' => $request->user()->id,
            'approved_at' => $link->approved_at ?: now(),
        ])->save();

        return view('marketplace.link-approved', [
            'link' => $link->fresh('user'),
        ]);
    }

    private function findPending(string $code): ?MarketplaceSiteLink
    {
        if ($code === '') {
            return null;
        }

        return MarketplaceSiteLink::query()
            ->with('user')
            ->where('user_code', $code)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }
}
