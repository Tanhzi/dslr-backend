<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // ← DÒNG NÀY LÀ CHÌA KHÓA
use Illuminate\Validation\ValidationException;

class CameraController extends Controller
{
    // API 1: Lấy đầy đủ thông tin camera
    public function show(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer|min:1',
        ]);

        $camera = Camera::where('id_admin', $request->id_admin)->first();

        if (!$camera) {
            return response()->json([
                'status' => 'error',
                'message' => "Không tìm thấy dữ liệu cho id_admin: {$request->id_admin}"
            ], 404);
        }

        return response()->json([
            'time1' => $camera->time1,
            'time2' => $camera->time2,
            'video' => $camera->video,
            'mirror' => $camera->mirror,
            'time_run' => $camera->time_run,
        ]);
    }

    // API 2: Lấy thông tin rút gọn (chỉ time1, time2, mirror)
    public function basic(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer|min:1',
        ]);

        $camera = Camera::where('id_admin', $request->id_admin)->first();

        if (!$camera) {
            return response()->json([
                'status' => 'error',
                'message' => "Không tìm thấy dữ liệu cho id_admin: {$request->id_admin}"
            ], 404);
        }

        return response()->json([
            'time1' => $camera->time1,
            'time2' => $camera->time2,
            'mirror' => $camera->mirror,
        ]);
    }

    public function update(Request $request): JsonResponse // ✅ giờ hợp lệ
    {
        $validated = $request->validate([
            'id_admin' => 'required|integer|exists:camera,id_admin',
            'time1' => 'required|integer',
            'time2' => 'required|integer',
            'video' => 'required|integer',
            'mirror' => 'required|integer',
            'time_run' => 'required|string',
        ]);

        $camera = Camera::where('id_admin', $validated['id_admin'])->first();

        if (! $camera) {
            return response()->json([
                'status' => 'error',
                'message' => "Không tìm thấy dữ liệu cho id_admin: {$validated['id_admin']}"
            ], 404);
        }

        $camera->update($validated);

        return response()->json([
            'status' => 'success',
            'data' => [
                'time1' => (int) $camera->time1,
                'time2' => (int) $camera->time2,
                'video' => (int) $camera->video,
                'mirror' => (int) $camera->mirror,
                'time_run' => $camera->time_run,
            ]
        ]);
    }
}