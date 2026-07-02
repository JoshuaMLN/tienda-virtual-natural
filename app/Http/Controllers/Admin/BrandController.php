<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::query()
            ->withCount('products')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('estado'), function ($query) use ($request) {
                $query->where('is_active', $request->string('estado')->toString() === 'activo');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.brands.index', compact('brands'));
    }

    public function create(): View
    {
        return view('admin.brands.create', [
            'brand' => new Brand([
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $brand = Brand::create(Arr::except($validated, ['logo', 'cropped_logo', 'remove_logo']));
        $this->storeLogo($brand, $request);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', "Marca {$brand->name} creada correctamente.");
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validated();
        $brand->update(Arr::except($validated, ['logo', 'cropped_logo', 'remove_logo']));
        $this->storeLogo($brand, $request);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', "Marca {$brand->name} actualizada correctamente.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->with('warning', "No se puede eliminar {$brand->name} porque tiene productos asociados. Puedes desactivarla.");
        }

        $brandName = $brand->name;

        if ($brand->logo_path) {
            Storage::disk('public')->delete($brand->logo_path);
        }

        $brand->delete();

        return redirect()
            ->route('admin.brands.index')
            ->with('success', "Marca {$brandName} eliminada correctamente.");
    }

    public function toggleStatus(Brand $brand): RedirectResponse
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return back()->with('success', "Estado de {$brand->name} actualizado.");
    }

    public function suggestSlug(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ignore' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        return response()->json([
            'slug' => Brand::uniqueSlug(
                $request->string('name')->toString(),
                $request->integer('ignore') ?: null
            ),
        ]);
    }

    private function storeLogo(Brand $brand, Request $request): void
    {
        $imageContents = null;
        $extension = 'jpg';

        if ($request->filled('cropped_logo')) {
            $payload = $request->string('cropped_logo')->toString();
            $extension = str_contains($payload, 'image/png') ? 'png' : 'jpg';
            $imageContents = base64_decode((string) Str::of($payload)->after(',')->toString(), true);
        } elseif ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $extension = $file->extension() ?: 'jpg';
            $imageContents = file_get_contents($file->getRealPath());
        }

        if (! $imageContents) {
            if ($request->boolean('remove_logo')) {
                $this->clearLogo($brand);
            }

            return;
        }

        $this->deleteLogoFile($brand);

        $path = 'brands/'.$brand->id.'-'.Str::slug($brand->name).'-'.Str::random(8).'.'.$extension;
        Storage::disk('public')->put($path, $imageContents);
        $brand->update([
            'logo_path' => $path,
            'logo_url' => null,
        ]);
    }

    private function clearLogo(Brand $brand): void
    {
        $this->deleteLogoFile($brand);

        $brand->update([
            'logo_path' => null,
            'logo_url' => null,
        ]);
    }

    private function deleteLogoFile(Brand $brand): void
    {
        if ($brand->logo_path) {
            Storage::disk('public')->delete($brand->logo_path);
        }
    }
}
