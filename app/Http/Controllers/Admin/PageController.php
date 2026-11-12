<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Store;
use App\Support\LegalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        $pages = Page::query()->latest()->paginate(20);
        $legalPages = Page::query()->whereIn('slug', ['terms', 'privacy'])->get()->keyBy('slug');

        return view('admin.pages.index', compact('pages', 'legalPages'));
    }

    public function restoreLegal(): RedirectResponse
    {
        $store = Store::default();
        $storeName = (string) (nexus_setting('store.name') ?: $store?->name ?: 'this store');
        $storeEmail = (string) (nexus_setting('store.email') ?: $store?->email ?: 'hello@example.com');
        $storeAddress = implode(', ', array_filter([
            (string) nexus_setting('store.address', ''),
            (string) $store?->country,
        ]));

        $policies = [
            'terms' => ['Terms of Service', LegalPages::terms($storeName, $storeEmail, $storeAddress)],
            'privacy' => ['Privacy Policy', LegalPages::privacy($storeName, $storeEmail, $storeAddress)],
        ];

        foreach ($policies as $slug => [$title, $content]) {
            Page::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'store_id' => $store?->id,
                    'title' => $title,
                    'content' => $content,
                    'is_published' => true,
                ]
            );
        }

        return redirect()->route('admin.pages.index')->with('status', 'Starter Terms of Service and Privacy Policy pages are ready to review and edit.');
    }

    public function create(): View
    {
        return view('admin.pages.form', ['page' => new Page(['is_published' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $data['title']);
        Page::query()->create($data);

        return redirect()->route('admin.pages.index')->with('status', 'Page created.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validated($request, $page);
        $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $data['title'], $page->id);
        $page->update($data);

        return redirect()->route('admin.pages.index')->with('status', 'Page updated.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', 'Page deleted.');
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'content' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'page';
        $slug = $base;
        $i = 2;

        while (Page::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
