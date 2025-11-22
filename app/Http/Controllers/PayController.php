<?php

namespace App\Http\Controllers;

use App\Models\Pay;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PayController extends Controller
{
public function getOrders(Request $request): JsonResponse
{
    $idAdmin = $request->query('id_admin');

    if (!$idAdmin) {
        return response()->json([
            'error' => 'Thiếu tham số id_admin'
        ], 400);
    }

    $orders = Pay::where('id_admin', $idAdmin)
        ->whereNotNull('id_qr')
        ->select('id', 'date as time', 'discount_code', 'id_frame as frame_id', 'id_qr as qr_id')
        ->orderBy('date', 'desc')
        ->get();

    return response()->json($orders);
}
}