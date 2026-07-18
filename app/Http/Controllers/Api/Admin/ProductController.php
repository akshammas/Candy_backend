<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'category_name' => ['nullable', 'string', 'max:100'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'], // 5MB each
            'primary_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create([
                'name' => $data['name'],
                'summary' => $data['summary'] ?? null,
                'description' => $data['description'] ?? null,
                'category_id' => $this->resolveCategoryId($data['category_name'] ?? null),
            ]);

            $this->storeImages($product, $request);

            return $product;
        });

        return response()->json(
            $product->load(['category', 'images']),
            201
        );
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'category_name' => ['nullable', 'string', 'max:100'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
            'primary_index' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($product, $data, $request) {
            $product->update([
                'name' => $data['name'],
                'summary' => $data['summary'] ?? null,
                'description' => $data['description'] ?? null,
                'category_id' => $this->resolveCategoryId($data['category_name'] ?? null),
            ]);

            if ($request->hasFile('images')) {
                $this->storeImages($product, $request);
            }
        });

        return response()->json($product->load(['category', 'images']));
    }

    public function destroy(int $id)
    {
        $product = Product::findOrFail($id);

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }
        $product->delete(); // product_images/advertisements cascade via FK

        return response()->json(['ok' => true]);
    }

    public function destroyImage(int $productId, int $imageId)
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);
        Storage::disk('public')->delete($image->image_path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        // If we just deleted the primary image, promote whatever's left
        // (first by sort_order) so the product never ends up with zero
        // primary images while it still has photos.
        if ($wasPrimary) {
            $product = Product::find($productId);
            $product?->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Marks an existing image as primary without touching anything else —
     * used by the edit page's per-thumbnail "Set primary" button, so
     * changing the primary photo doesn't require re-uploading anything.
     */
    public function setPrimaryImage(int $productId, int $imageId)
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);

        DB::transaction(function () use ($productId, $image) {
            ProductImage::where('product_id', $productId)->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Resolves a category name straight to an id, creating the row if it
     * doesn't exist yet. Hardcoded to C&Y's real product lines (matches
     * "Our Product Categories" on about.html) — no separate
     * /api/admin/categories round trip needed from the frontend, which
     * removes the URL-prefix mismatch that was causing "category missing"
     * on submit.
     */
    private function resolveCategoryId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Category::firstOrCreate(['name' => $name])->id;
    }

    private function storeImages(Product $product, Request $request): void
    {
        if (!$request->hasFile('images')) {
            return;
        }

        $existingCount = $product->images()->count();
        $hadNoImages = $existingCount === 0;
        $primaryIndex = $request->integer('primary_index', 0);

        foreach ($request->file('images') as $i => $file) {
            $path = $file->store('uploads', 'public'); // storage/app/public/uploads/...

            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $path,
                'alt_text' => $product->name,
                'sort_order' => $existingCount + $i,
                'is_primary' => false,
            ]);
        }

        // Only auto-assign a primary when the product had none before this
        // upload (covers "add product" and "first photos on an existing
        // product"). If it already had a primary, leave it alone — use
        // setPrimaryImage() to change it instead of silently overriding it
        // every time someone uploads one more photo during an edit.
        if ($hadNoImages) {
            $chosen = $product->images()->orderBy('sort_order')->skip($primaryIndex)->first()
                ?? $product->images()->orderBy('sort_order')->first();
            $chosen?->update(['is_primary' => true]);
        }
    }
}