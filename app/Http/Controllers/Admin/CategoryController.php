<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->with('parent')->withCount('products')->orderBy('sort_order')->paginate(20);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true]),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $data['name']);
        $data['image'] = $this->uploadImage($request, $data['name']);

        Category::query()->create($data);

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'categories' => Category::query()->where('id', '!=', $category->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $data['name'], $category->id);
        $image = $this->uploadImage($request, $data['name']);
        if ($image) {
            $this->deleteImage($category->image);
            $data['image'] = $image;
        }

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->deleteImage($category->image);
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($data['parent_id']) && $category && (int) $data['parent_id'] === $category->id) {
            $data['parent_id'] = null;
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'category';
        $slug = $base;
        $i = 2;

        while (Category::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function uploadImage(Request $request, string $name): ?string
    {
        $file = $request->file('image');
        if (! $file || ! $file->isValid()) {
            return null;
        }

        $directory = public_path('uploads/categories');
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $filename = Str::slug($name).'-'.Str::lower(Str::random(8)).'.'.$file->extension();
        $file->move($directory, $filename);

        return 'uploads/categories/'.$filename;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/') && is_file(public_path($path))) {
            @unlink(public_path($path));
        }
    }
}
