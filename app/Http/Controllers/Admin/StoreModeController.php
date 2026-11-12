<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StoreMode;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Services\StoreModeService;
use App\Services\StoreModeSwitchException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StoreModeController extends Controller
{
    public function preflight(Request $request, StoreModeService $modes, SettingsService $settings): View|RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::enum(StoreMode::class)],
            'live' => ['nullable', 'boolean'],
        ]);

        $target = StoreMode::from($validated['mode']);
        $current = $settings->mode();

        if (! $current) {
            return redirect()
                ->route('admin.settings.index', ['section' => 'mode'])
                ->with('status', 'Choose the first store model from Store settings.');
        }

        return view('admin.store-mode.preflight', [
            'report' => $modes->report($target),
            'target' => $target,
            'current' => $current,
            'modes' => StoreMode::cases(),
            'live' => $request->boolean('live'),
        ]);
    }

    public function switch(Request $request, StoreModeService $modes): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::enum(StoreMode::class)],
            'confirm' => ['accepted'],
            'live' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = StoreMode::from($validated['mode']);

        try {
            $switch = $modes->switchTo(
                $target,
                $request->user(),
                confirmed: true,
                live: $request->boolean('live'),
                note: $validated['note'] ?? null,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (StoreModeSwitchException|ValidationException $exception) {
            $message = $exception instanceof StoreModeSwitchException
                ? $exception->getMessage()
                : collect($exception->errors())->flatten()->first();

            return redirect()
                ->route('admin.store-mode.preflight', ['mode' => $target->value, 'live' => $request->boolean('live') ? 1 : 0])
                ->withErrors(['mode' => $message]);
        }

        return redirect()
            ->route('admin.settings.index', ['section' => 'mode'])
            ->with('status', 'Store model switched to '.$target->label().'. Revision '.$switch->mode_revision.' is active.');
    }

    public function history(): View
    {
        return view('admin.store-mode.history', [
            'switches' => \App\Models\StoreModeSwitch::query()->with('user')->latest('id')->paginate(20),
        ]);
    }

    public function integrity(StoreModeService $modes): View
    {
        return view('admin.store-mode.integrity', [
            'integrity' => $modes->integrityReport(),
        ]);
    }
}
