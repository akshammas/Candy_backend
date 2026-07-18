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

        $ad = Advertisement::create([
            'product_id' => $data['product_id'],
            'image_path' => $path,
            'caption' => $data['caption'] ?? null,
            'button_text' => $data['button_text'] ?? null,
        ]);

        return response()->json($ad, 201);
    }

    public function destroy(int $id)
    {
        $ad = Advertisement::findOrFail($id);
        Storage::disk('public')->delete($ad->image_path);
        $ad->delete();

        return response()->json(['ok' => true]);
    }
}
