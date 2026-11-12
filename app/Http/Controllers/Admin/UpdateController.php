<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpdateRun;
use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateManager;
use App\Services\Updates\UpdatePlan;
use App\Support\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UpdateController extends Controller
{
    public function index(UpdateManager $manager): View
    {
        return view('admin.updates.index', [
            'currentVersion' => Version::current(),
            'moduleApi' => Version::moduleApi(),
            'plan' => $manager->cachedCheck(),
            'lastCheckedAt' => $manager->lastCheckedAt(),
            'channel' => $manager->channel(),
            'manifestUrl' => $manager->manifestUrl(),
            'runs' => $manager->recentRuns(12),
            'updateError' => session('update_error'),
        ]);
    }

    public function check(UpdateManager $manager): RedirectResponse
    {
        try {
            $plan = $manager->check(true);
        } catch (UpdateException $exception) {
            return back()->with('update_error', $exception->getMessage());
        }

        if ($plan->error !== null) {
            return back()->with('update_error', $plan->error);
        }

        return back()->with(
            'status',
            $plan->upToDate()
                ? 'You are running the latest version of ShopKeeprr.'
                : 'Update available: '.$plan->targetVersion().'. Review the release notes before installing.'
        );
    }

    public function confirm(Request $request, UpdateManager $manager): View|RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:30'],
        ]);

        $plan = $this->planFor($manager, $data['version']);

        if ($plan instanceof RedirectResponse) {
            return $plan;
        }

        $release = $plan->releaseFor($data['version']);

        if ($release === null) {
            return redirect()->route('admin.updates.index')
                ->with('update_error', 'That version is no longer in the available upgrade chain. Check again.');
        }

        return view('admin.updates.confirm', [
            'plan' => $plan,
            'release' => $release,
            'currentVersion' => Version::current(),
        ]);
    }

    public function apply(Request $request, UpdateManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:30'],
            'accepted' => ['accepted'],
            'acknowledge_modules' => ['nullable', 'boolean'],
        ], [
            'accepted.accepted' => 'Confirm that you have read the release notes and understand the backup behaviour.',
        ]);

        $plan = $this->planFor($manager, $data['version']);

        if ($plan instanceof RedirectResponse) {
            return $plan;
        }

        if ($plan->hasBlockingWarnings() && ! $request->boolean('acknowledge_modules')) {
            return redirect()->route('admin.updates.confirm', ['version' => $data['version']])
                ->with('update_error', 'One or more installed modules are declared incompatible with this release. Confirm the compatibility warning to continue.');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            $runs = $manager->apply($plan, $request->user());
        } catch (UpdateException $exception) {
            return redirect()->route('admin.updates.index')->with('update_error', $exception->getMessage());
        }

        $last = $runs[array_key_last($runs)];

        $message = 'ShopKeeprr was updated to '.$last->to_version.'. A database and file backup was kept before the update.';

        if ($last->error) {
            $message .= ' '.$last->error;
        }

        return redirect()->route('admin.updates.index')->with('status', $message);
    }

    public function rollback(Request $request, UpdateRun $run, UpdateManager $manager): RedirectResponse
    {
        $request->validate([
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Confirm that you understand the database will be restored to before this update.',
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            $run = $manager->rollback($run, $request->user());
        } catch (UpdateException $exception) {
            return redirect()->route('admin.updates.index')->with('update_error', $exception->getMessage());
        }

        return redirect()->route('admin.updates.index')->with(
            'status',
            'Rolled back to '.$run->from_version.'. The database and files were restored from the backup.'
        );
    }

    private function planFor(UpdateManager $manager, string $version): UpdatePlan|RedirectResponse
    {
        try {
            $plan = $manager->cachedCheck($version) ?? $manager->check(true, $version);
        } catch (UpdateException $exception) {
            return redirect()->route('admin.updates.index')->with('update_error', $exception->getMessage());
        }

        if ($plan->error !== null) {
            return redirect()->route('admin.updates.index')->with('update_error', $plan->error);
        }

        return $plan;
    }
}
