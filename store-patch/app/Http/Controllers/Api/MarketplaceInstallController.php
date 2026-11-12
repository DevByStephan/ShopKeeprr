<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceSiteLink;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Services\DownloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MarketplaceInstallController extends Controller
{
    public function account(Request $request): JsonResponse
    {
        /** @var MarketplaceSiteLink $link */
        $link = $request->attributes->get('marketplace_link');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'name' => $link->user?->name,
                    'email' => $link->user?->email,
                ],
                'site' => [
                    'site_id' => $link->site_id,
                    'site_url' => $link->site_url,
                    'site_name' => $link->site_name,
                ],
                'last_used_at' => $link->last_used_at?->toIso8601String(),
            ],
        ]);
    }

    public function library(Request $request, DownloadService $downloads): JsonResponse
    {
        /** @var MarketplaceSiteLink $link */
        $link = $request->attributes->get('marketplace_link');
        $products = Product::query()
            ->published()
            ->whereIn('type', [Product::TYPE_PLUGIN, Product::TYPE_COMPONENT, Product::TYPE_BUSINESS_MODEL])
            ->with('currentRelease')
            ->orderBy('name')
            ->get()
            ->filter(function (Product $product) use ($link, $downloads): bool {
                $release = $product->currentRelease;

                return $release?->isApprovedForDistribution()
                    && ((int) $product->price_cents === 0 || $downloads->userCanDownload($link->user, $product));
            })
            ->map(fn (Product $product): array => [
                'slug' => $product->slug,
                'name' => $product->name,
                'type' => $product->type,
                'version' => $product->currentRelease?->version,
                'price_cents' => (int) $product->price_cents,
                'owned' => true,
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function install(Request $request, DownloadService $downloads): JsonResponse
    {
        /** @var MarketplaceSiteLink $link */
        $link = $request->attributes->get('marketplace_link');

        $data = $request->validate([
            'product' => ['required', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:40'],
        ]);

        $product = Product::query()
            ->where('slug', $data['product'])
            ->published()
            ->whereIn('type', [Product::TYPE_PLUGIN, Product::TYPE_COMPONENT, Product::TYPE_BUSINESS_MODEL])
            ->first();

        if (! $product) {
            return $this->error('That product is not available for one-click installation.', 404, 'product_not_found');
        }

        $release = $product->releases()
            ->when($data['version'] ?? null, fn ($query, $version) => $query->where('version', $version))
            ->where('is_current', true)
            ->latest('published_at')
            ->first();

        if (! $release || ! $release->isApprovedForDistribution()) {
            return $this->error('That release is not available for distribution yet.', 409, 'release_unavailable');
        }

        if (! Storage::disk('releases')->exists($release->file_path)) {
            return $this->error('The release file is missing from the Store.', 409, 'release_file_missing');
        }

        if ((int) $product->price_cents > 0 && ! $downloads->userCanDownload($link->user, $product)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'purchase_required',
                    'message' => 'Buy this plugin with the connected ShopKeeprr account before installing it.',
                    'purchase_url' => route('products.show', $product),
                ],
            ], 403);
        }

        $url = URL::temporarySignedRoute('api.v1.marketplace.package', now()->addMinutes(10), [
            'release' => $release->id,
            'link' => $link->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product->slug,
                'version' => $release->version,
                'file_name' => $release->file_name,
                'file_size' => (int) $release->file_size,
                'sha256' => $release->sha256,
                'manifest' => $release->manifest,
                'download_url' => $url,
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
                'purchase_url' => route('products.show', $product),
            ],
        ]);
    }

    public function download(
        Request $request,
        ProductRelease $release,
        MarketplaceSiteLink $link,
        DownloadService $downloads,
    ): BinaryFileResponse {
        abort_unless($link->isActive(), 403, 'This site connection is no longer active.');
        abort_unless($release->product && $release->product->is_published, 404);
        abort_unless(in_array($release->product->type, [Product::TYPE_PLUGIN, Product::TYPE_COMPONENT, Product::TYPE_BUSINESS_MODEL], true), 404);
        abort_unless($release->isApprovedForDistribution(), 403, 'This release is not available for distribution.');

        if ((int) $release->product->price_cents > 0) {
            abort_unless($downloads->userCanDownload($link->user, $release->product), 403, 'A paid purchase is required.');
        }

        $disk = Storage::disk('releases');
        abort_unless($disk->exists($release->file_path), 404, 'The release file is missing.');

        $license = $downloads->licenseFor($link->user, $release->product);
        $order = $downloads->paidOrderFor($link->user, $release->product);
        $downloads->record($release, $link->user, $license, $order?->id);

        return response()
            ->download($disk->path($release->file_path), $release->file_name, [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    private function error(string $message, int $status, string $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
