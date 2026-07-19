<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'image' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:50'],
        ]);

        $path = $request->file('image')->store('uploads', 'public');

        $nextSortOrder = Advertisement::max('sort_order') + 1;

        $ad = Advertisement::create([
            'product_id' => $data['product_id'],
            'image_path' => $path,
            'caption' => $data['caption'] ?? null,
            'button_text' => $data['button_text'] ?? null,
            'sort_order' => $nextSortOrder,
        ]);

        return response()->json($ad, 201);
    }

    public function update(Request $request, int $id)
    {
        $ad = Advertisement::findOrFail($id);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'image' => ['nullable', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:50'],
        ]);

        $update = [
            'product_id' => $data['product_id'],
            'caption' => $data['caption'] ?? null,
            'button_text' => $data['button_text'] ?? null,
        ];

        if ($request->hasFile('image')) {
            $oldPath = $ad->image_path;
            $update['image_path'] = $request->file('image')->store('uploads', 'public');
            Storage::disk('public')->delete($oldPath);
        }

        $ad->update($update);

        return response()->json($ad->fresh());
    }

    public function destroy(int $id)
    {
        $ad = Advertisement::findOrFail($id);
        Storage::disk('public')->delete($ad->image_path);
        $ad->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Accepts { order: [id, id, id, ...] } in that display order and
     * rewrites sort_order to match. Not wired to a UI yet, but the route
     * already existed pointing at this method, so it needs to exist.
     */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:advertisements,id'],
        ]);

        foreach ($data['order'] as $index => $id) {
            Advertisement::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }
}