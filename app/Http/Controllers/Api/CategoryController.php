<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Admin-only listing that includes IDs — the public /api/categories
     * endpoint only returns names (that's all the storefront filter needs),
     * but the admin product form needs an id to submit as category_id.
     */
    public function index()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Get-or-create by name, mirroring the old FastAPI get_or_create_category
     * behaviour — lets the "add a new category" option on the product form
     * work without a separate category-management screen.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $category = Category::firstOrCreate(['name' => trim($data['name'])]);

        return response()->json($category, $category->wasRecentlyCreated ? 201 : 200);
    }
}