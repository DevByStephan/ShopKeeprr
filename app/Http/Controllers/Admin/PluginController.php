<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PluginController extends Controller
{
    public function index(ModuleManager $manager): View
    {
        $manager->sync();
        $modules = Module::query()->orderByDesc('is_enabled')->orderBy('name')->get();

        return view('admin.plugins.index', compact('modules'));
    }

    public function upload(Request $request, ModuleManager $manager): RedirectResponse
    {
        $request->validate([
            'module' => ['required', 'file', 'max:20480'],
            'trust' => ['accepted'],
        ], [
            'trust.accepted' => 'Confirm that you trust the module author before installing.',
        ]);

        try {
            $module = $manager->install($request->file('module'));
        } catch (Throwable $exception) {
            return back()->withErrors(['module' => $exception->getMessage()]);
        }

        return back()->with('status', $module->name.' installed. Enable it when you are ready.');
    }

    public function toggle(Module $module, ModuleManager $manager): RedirectResponse
    {
        $manager->toggle($module);

        return back()->with('status', $module->name.' '.($module->is_enabled ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Module $module, ModuleManager $manager): RedirectResponse
    {
        $name = $module->name;
        $manager->delete($module);

        return back()->with('status', $name.' removed.');
    }
}
