<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;

class AdController extends Controller
{
    public function index()
    {
        return Advertisement::orderBy('sort_order')->get()->map(fn ($ad) => [
            'id' => $ad->id,
            'product_id' => $ad->product_id,
            'image_url' => $ad->image_url,
            'caption' => $ad->caption,
            'button_text' => $ad->button_text,
        ]);
    }
}