<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Support\Catalog\CategoryIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('estado'), function ($query) use ($request) {
                $query->where('is_active', $request->string('estado')->toString() === 'activo');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new Category([
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 0,
                'icon_class' => CategoryIcon::default(),
            ]),
            'iconOptions' => CategoryIcon::options(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $category = Category::create(Arr::except($validated, ['image', 'cropped_image', 'remove_image']));
        $this->storeImage($category, $request);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Categoria {$category->name} creada correctamente.");
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
            'iconOptions' => CategoryIcon::options(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();
        $category->update(Arr::except($validated, ['image', 'cropped_image', 'remove_image']));
        $this->storeImage($category, $request);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Categoria {$category->name} actualizada correctamente.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('warning', "No se puede eliminar {$category->name} porque tiene productos asociados. Puedes desactivarla.");
        }

        $categoryName = $category->name;
        $this->deleteImageFile($category);
        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Categoria {$categoryName} eliminada correctamente.");
    }

    public function toggleStatus(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', "Estado de {$category->name} actualizado.");
    }

    public function suggestSlug(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ignore' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        return response()->json([
            'slug' => Category::uniqueSlug(
                $request->string('name')->toString(),
                $request->integer('ignore') ?: null
            ),
        ]);
    }

    private function storeImage(Category $category, Request $request): void
    {
        $imageContents = null;
        $extension = 'jpg';

        if ($request->filled('cropped_image')) {
            $payload = $request->string('cropped_image')->toString();
            $extension = str_contains($payload, 'image/png') ? 'png' : 'jpg';
            $imageContents = base64_decode((string) Str::of($payload)->after(',')->toString(), true);
        } elseif ($request->hasFile('image')) {
            $file = $request->file('image');
            $extension = $file->extension() ?: 'jpg';
            $imageContents = file_get_contents($file->getRealPath());
        }

        if (! $imageContents) {
            if ($request->boolean('remove_image')) {
                $this->clearImage($category);
            }

            return;
        }

        $this->deleteImageFile($category);

        $path = 'categories/'.$category->id.'-'.Str::slug($category->name).'-'.Str::random(8).'.'.$extension;
        Storage::disk('public')->put($path, $imageContents);
        $category->update([
            'image_path' => $path,
            'image_url' => null,
        ]);
    }

    private function clearImage(Category $category): void
    {
        $this->deleteImageFile($category);

        $category->update([
            'image_path' => null,
            'image_url' => null,
        ]);
    }

    private function deleteImageFile(Category $category): void
    {
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }
    }
}
