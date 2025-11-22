<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SizeController extends Controller
{
    /**
     * Lấy dữ liệu size theo id_admin
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'id_admin' => 'required|integer|min:1|exists:prices,id_admin',
        ]);

        $price = Price::where('id_admin', $request->id_admin)->first();

        if (! $price) {
            return response()->json([
                'status' => 'error',
                'message' => "Không tìm thấy dữ liệu cho id_admin: {$request->id_admin}"
            ], 404);
        }

        return response()->json([
            'size1' => $price->size1, // đã tự động decode từ JSON → array
            'size2' => $price->size2,
        ]);
    }

    /**
     * Cập nhật size
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_admin' => 'required|integer|min:1|exists:prices,id_admin',
            'size1' => 'required|array',
            'size2' => 'required|array',
        ]);

        $price = Price::where('id_admin', $validated['id_admin'])->first();

        if (! $price) {
            return response()->json([
                'status' => 'error',
                'message' => "Không tìm thấy dữ liệu để cập nhật cho id_admin: {$validated['id_admin']}"
            ], 404);
        }

        $price->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Dữ liệu đã được cập nhật thành công.'
        ]);
    }
}