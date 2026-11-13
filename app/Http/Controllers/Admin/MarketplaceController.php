<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class MarketplaceController extends Controller
{
    public function index(MarketplaceManager $marketplace): View
    {
        $connection = $marketplace->connection();
        $catalog = [];
        $library = [];
        $error = null;

        try {
            $catalog = $marketplace->catalog();

            if ($connection) {
                $library = array_column($marketplace->library(), 'slug');
            }
        } catch (Throwable $exception) {
            $error = $exception instanceof MarketplaceException
                ? $exception->getMessage()
                : 'The marketplace could not be loaded. Try again shortly.';
        }

        return view('admin.marketplace.index', [
            'connection' => $connection,
            'catalog' => $catalog,
            'library' => $library,
            'installed' => Module::query()->pluck('version', 'slug')->all(),
            'ideas' => $this->freePluginIdeas(),
            'error' => $error,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freePluginIdeas(): array
    {
        $path = resource_path('marketplace/free-plugin-ideas.json');

        if (! is_readable($path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            return [];
        }

        $ideas = is_array($payload['ideas'] ?? null) ? $payload['ideas'] : $payload;

        return array_values(array_filter($ideas, static fn (mixed $idea): bool => is_array($idea) && filled($idea['name'] ?? null)));
    }

    public function connect(MarketplaceManager $marketplace): RedirectResponse
    {
        try {
            $marketplace->startLink();
        } catch (Throwable $exception) {
            return redirect()->route('admin.marketplace.index')->withErrors([
                'marketplace' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.marketplace.link');
    }

    public function link(MarketplaceManager $marketplace): View|RedirectResponse
    {
        $pending = $marketplace->pendingLink();

        if (empty($pending['device_code'])) {
            return redirect()->route('admin.marketplace.index')->withErrors([
                'marketplace' => 'Start a Store connection first.',
            ]);
        }

        return view('admin.marketplace.link', ['pending' => $pending]);
    }

    public function status(MarketplaceManager $marketplace): JsonResponse
    {
        try {
            $result = $marketplace->pollLink();
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 410);
        }

        if (($result['status'] ?? null) === 'connected') {
            return response()->json([
                'status' => 'connected',
                'redirect' => route('admin.marketplace.index'),
            ]);
        }

        return response()->json(['status' => 'pending']);
    }

    public function disconnect(MarketplaceManager $marketplace): RedirectResponse
    {
        $marketplace->disconnect();

        return redirect()->route('admin.marketplace.index')->with('status', 'ShopKeeprr Store account disconnected.');
    }

    public function refresh(MarketplaceManager $marketplace): RedirectResponse
    {
        $marketplace->clearCatalogCache();

        return redirect()->route('admin.marketplace.index')->with('status', 'Marketplace catalog refreshed.');
    }

    public function ownership(MarketplaceManager $marketplace): JsonResponse
    {
        try {
            $owned = collect($marketplace->library())
                ->pluck('slug')
                ->filter(static fn (mixed $slug): bool => is_string($slug) && $slug !== '')
                ->unique()
                ->values()
                ->all();
        } catch (MarketplaceException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The Store could not check this purchase right now.',
            ], 500);
        }

        return response()->json(['owned' => $owned]);
    }

    public function install(Request $request, MarketplaceManager $marketplace): RedirectResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $module = $marketplace->install($data['product'], $data['version'] ?? null);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'marketplace' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', $module->name.' installed. Enable it from Plugins when you are ready.');
    }
}
