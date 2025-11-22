<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;

class FrameController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer|min:1',
            'id_topic' => 'required|integer|min:1',
            'cuts' => 'required|integer|min:1',
        ]);

        $frames = Template::where('id_admin', $request->id_admin)
            ->where('id_topic', $request->id_topic)
            ->where('cuts', $request->cuts)
            ->select('id','frame', 'type')
            ->get()
            ->map(function ($frame) {
                $frame->frame = base64_encode($frame->frame);
                return $frame;
            });

        if ($frames->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy khung ảnh phù hợp'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $frames
        ]);
    }
}