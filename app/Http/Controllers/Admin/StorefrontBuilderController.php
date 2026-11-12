<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Storefront\BlockRegistry;
use App\Services\Storefront\StorefrontBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class StorefrontBuilderController extends Controller
{
    public function __construct(
        private readonly StorefrontBuilder $builder,
        private readonly BlockRegistry $registry,
    ) {}

    public function index(): View
    {
        $page = $this->builder->page();

        return view('admin.builder.index', [
            'page' => $page,
            'definitions' => $this->registry->definitionsForBuilder(),
            'grouped' => $this->registry->grouped(),
            'hasPublished' => $page->isPublished(),
            'isEnabled' => $page->is_enabled,
            'dirty' => $page->hasUnpublishedChanges(),
        ]);
    }

    public function preview(): mixed
    {
        $page = $this->builder->page();
        $blocks = $this->builder->hydrate(is_array($page->blocks) ? $page->blocks : []);

        return response()
            ->view('theme.builder.home', [
                'blocks' => $blocks,
                'builderPreview' => true,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function saveDraft(Request $request): JsonResponse
    {
        $page = $this->builder->page();
        $blocks = $this->normalize($request);

        $page->forceFill([
            'blocks' => $blocks,
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json([
            'ok' => true,
            'saved_at' => now()->toIso8601String(),
            'blocks' => $blocks,
            'dirty' => $page->hasUnpublishedChanges(),
        ]);
    }

    public function publish(Request $request): JsonResponse
    {
        $page = $this->builder->page();
        $blocks = $this->normalize($request);

        if ($blocks === []) {
            throw ValidationException::withMessages(['blocks' => 'Add at least one section before publishing.']);
        }

        $page->forceFill([
            'blocks' => $blocks,
            'published_blocks' => $blocks,
            'is_enabled' => true,
            'published_at' => now(),
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json([
            'ok' => true,
            'published_at' => $page->published_at?->toIso8601String(),
            'blocks' => $blocks,
            'dirty' => false,
            'is_enabled' => true,
        ]);
    }

    public function discard(Request $request): JsonResponse
    {
        $page = $this->builder->page();
        $blocks = is_array($page->published_blocks) && $page->published_blocks !== []
            ? $page->published_blocks
            : $this->registry->defaultBlocks();

        $page->forceFill([
            'blocks' => $blocks,
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json([
            'ok' => true,
            'blocks' => $blocks,
            'dirty' => $page->hasUnpublishedChanges(),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $page = $this->builder->page();
        $blocks = $this->registry->defaultBlocks();

        $page->forceFill([
            'blocks' => $blocks,
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json([
            'ok' => true,
            'blocks' => $blocks,
            'dirty' => $page->hasUnpublishedChanges(),
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $page = $this->builder->page();

        $page->forceFill([
            'is_enabled' => false,
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json(['ok' => true, 'is_enabled' => false]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
        ]);

        $file = $request->file('image');
        $name = 'block-'.now()->format('Ymd').'-'.bin2hex(random_bytes(8)).'.'.strtolower($file->extension());
        $directory = public_path('uploads/builder');

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $file->move($directory, $name);

        return response()->json([
            'ok' => true,
            'path' => 'uploads/builder/'.$name,
            'url' => asset('uploads/builder/'.$name),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalize(Request $request): array
    {
        $request->validate([
            'blocks' => ['present', 'array'],
        ]);

        try {
            return $this->registry->sanitizeBlocks($request->input('blocks', []));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['blocks' => $exception->getMessage()]);
        }
    }
}
