<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');

        $query = Product::with(['category', 'images']);
        if ($category) {
            $query->whereHas('category', fn ($q) => $q->where('name', $category));
        }
        $products = $query->get();

        return response()->json([
            'products' => $products->map(fn ($p) => $this->cardShape($p)),
            'categories' => Category::orderBy('name')->pluck('name'),
            'active_category' => $category,
        ]);
    }

    public function show(int $id)
    {
        $product = Product::with(['category', 'images'])->find($id);

        if (!$product) {
            return response()->json(['detail' => 'Product not found'], 404);
        }

        $related = Product::with(['category', 'images'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->whereNotNull('category_id')
            ->limit(3)
            ->get();

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'summary' => $product->summary,
            'description' => $product->description,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'images' => $product->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'alt_text' => $img->alt_text,
                'is_primary' => $img->is_primary,
                'sort_order' => $img->sort_order,
            ]),
            'related_products' => $related->map(fn ($p) => $this->cardShape($p)),
        ]);
    }

    /** Lightweight shape used in listing/related grids — mirrors the old FastAPI _serialize_product_card. */
    private function cardShape(Product $product): array
    {
        $primary = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'summary' => $product->summary,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'primary_image' => $primary ? [
                'id' => $primary->id,
                'url' => $primary->url,
                'alt_text' => $primary->alt_text,
                'is_primary' => $primary->is_primary,
                'sort_order' => $primary->sort_order,
            ] : null,
        ];
    }
}
