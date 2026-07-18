<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
            'category_id' => ['nullable', 'exists:categories,id'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'], // 5MB each
            'primary_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create([
                'name' => $data['name'],
                'summary' => $data['summary'] ?? null,
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
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
            'category_id' => ['nullable', 'exists:categories,id'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
            'primary_index' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($product, $data, $request) {
            $product->update([
                'name' => $data['name'],
                'summary' => $data['summary'] ?? null,
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
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
        $image->delete();

        return response()->json(['ok' => true]);
    }

    private function storeImages(Product $product, Request $request): void
    {
        if (!$request->hasFile('images')) {
            return;
        }

        $existingCount = $product->images()->count();
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

        // Only one primary image per product — clear existing, then set the chosen one.
        $product->images()->update(['is_primary' => false]);
        $chosen = $product->images()->orderBy('sort_order')->skip($primaryIndex)->first()
            ?? $product->images()->orderBy('sort_order')->first();
        $chosen?->update(['is_primary' => true]);
    }
}
