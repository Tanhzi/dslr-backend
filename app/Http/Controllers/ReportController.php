<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // Đếm số người dùng theo id_admin
    public function countUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_admin' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu tham số id_admin'
            ], 400);
        }

        $id_admin = $request->id_admin;

        $total_customers = User::where('id_admin', $id_admin)->count();

        return response()->json([
            'total_customers' => $total_customers
        ]);
    }

    // Tổng số giao dịch và tổng doanh thu theo id_admin
    public function getSumPrice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_admin' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu tham số id_admin'
            ], 400);
        }

        $id_admin = $request->id_admin;

        $result = Pay::where('id_admin', $id_admin)
            ->selectRaw('COUNT(*) as total_customers, COALESCE(SUM(price), 0) as total_income')
            ->first();

        return response()->json([
            'total_customers' => (int) $result->total_customers,
            'total_income' => (int) $result->total_income,
        ]);
    }

    // Tổng doanh thu theo tháng/năm
    public function getPrice()
    {
        $summary = Pay::selectRaw(
            "EXTRACT(MONTH FROM date) as month, EXTRACT(YEAR FROM date) as year, SUM(price) as total_revenue"
        )
            ->groupByRaw('EXTRACT(YEAR FROM date), EXTRACT(MONTH FROM date)')
            ->orderByRaw('EXTRACT(YEAR FROM date)')
            ->orderByRaw('EXTRACT(MONTH FROM date)')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => (int) $item->month,
                    'year' => (int) $item->year,
                    'total_revenue' => (int) $item->total_revenue,
                ];
            });

        return response()->json($summary);
    }
}