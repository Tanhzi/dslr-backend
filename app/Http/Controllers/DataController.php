<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DataController extends Controller
{
    // 1. Lấy background event
public function getEventBackground(Request $request)
{
    $request->validate([
        'id_admin' => 'required|integer',
        'id_topic' => 'required|integer',
    ]);

    $event = Event::where('id_admin', $request->id_admin)
                  ->where('id', $request->id_topic)
                  ->first();

    if (!$event) {
        return response()->json([
            'status' => 'error',
            'message' => "Không tìm thấy dữ liệu cho id_topic = {$request->id_topic} và id_admin = {$request->id_admin}"
        ], 404);
    }

    // ✅ Trả URL công khai (giống Appclien)
    $backgroundUrl = $event->background 
        ? Storage::url($event->background) 
        : null;

    return response()->json([
        'status' => 'success',
        'ev_back' => (int) $event->ev_back,
        'background' => "http://localhost:8000" . $backgroundUrl, // ← KHÔNG DÙNG base64
        'applyBackground' => ((int) $event->ev_back === 2),
    ]);
}

    // 2. Lấy size1, size2 từ bảng prices
    public function getPrices(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer',
        ]);

        $price = Price::where('id_admin', $request->id_admin)->first();

        if (! $price) {
            return response()->json([
                'status' => 'error',
                'message' => "Không tìm thấy dữ liệu cho id_admin: {$request->id_admin}"
            ], 404);
        }

        $size1 = $price->size1; 
        $size2 = $price->size2;

        return response()->json([
            'status' => 'success',
            'data' => [
                'size1' => $size1,
                'size2' => $size2,
            ]
        ]);
    }
}