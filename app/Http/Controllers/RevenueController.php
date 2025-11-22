<?php

namespace App\Http\Controllers;

use App\Models\Pay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RevenueController extends Controller
{
    // GET /api/revenue/summary?id_admin=1
    // Tổng doanh thu theo tháng/năm, cuts, và **theo từng id_client**
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_admin' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Thiếu id_admin'], 400);
        }

        $data = Pay::selectRaw('
            id_client,
            MONTH(date) as month,
            YEAR(date) as year,
            cuts,
            SUM(price) as total_revenue
        ')
            ->where('id_admin', $request->id_admin)
            ->groupBy('id_client', 'year', 'month', 'cuts')
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('id_client')
            ->get();

        return response()->json($data);
    }

    // GET /api/revenue/range?from_date=...&to_date=...&id_admin=1
    public function byDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'id_admin'  => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $data = Pay::selectRaw('
            id_client,
            MIN(DATE(date)) as date,
            MONTH(date) as month,
            YEAR(date) as year,
            cuts,
            SUM(discount) as discount,
            SUM(discount_price) as discount_price,
            SUM(price) as total_revenue
        ')
            ->whereBetween('date', [$request->from_date, $request->to_date])
            ->where('id_admin', $request->id_admin)
            ->groupBy('id_client', 'year', 'month', 'cuts')
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('id_client')
            ->get();

        return response()->json($data);
    }

    // GET /api/revenue/month?month=4&year=2025&id_admin=1
    public function byMonth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month'    => 'required|integer|min:1|max:12',
            'year'     => 'required|integer',
            'id_admin' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $data = Pay::selectRaw('
            id_client,
            MONTH(date) as month,
            YEAR(date) as year,
            cuts,
            SUM(discount) as discount,
            SUM(discount_price) as discount_price,
            SUM(price) as total_revenue
        ')
            ->whereRaw('MONTH(date) = ?', [$request->month])
            ->whereRaw('YEAR(date) = ?', [$request->year])
            ->where('id_admin', $request->id_admin)
            ->groupBy('id_client', 'cuts', 'month', 'year')
            ->orderBy('id_client')
            ->get();

        return response()->json($data);
    }

    // GET /api/revenue/quarter?quarter=2&year=2025&id_admin=1
    public function byQuarter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quarter'  => 'required|integer|min:1|max:4',
            'year'     => 'required|integer',
            'id_admin' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $quarter = $request->quarter;
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;

        $data = Pay::selectRaw('
            id_client,
            MONTH(date) as month,
            YEAR(date) as year,
            cuts,
            SUM(discount) as discount,
            SUM(discount_price) as discount_price,
            SUM(price) as total_revenue
        ')
            ->where('id_admin', $request->id_admin)
            ->whereRaw('YEAR(date) = ?', [$request->year])
            ->whereRaw('MONTH(date) BETWEEN ? AND ?', [$startMonth, $endMonth])
            ->groupBy('id_client', 'year', 'month', 'cuts')
            ->orderBy('month')
            ->orderBy('id_client')
            ->get()
            ->map(function ($item) use ($quarter) {
                $item->quarter = $quarter;
                return $item;
            });

        return response()->json($data);
    }

    // GET /api/revenue/year?year=2025&id_admin=1
    public function byYear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year'     => 'required|integer',
            'id_admin' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $data = Pay::selectRaw('
            id_client,
            YEAR(date) as year,
            cuts,
            SUM(discount) as discount,
            SUM(discount_price) as discount_price,
            SUM(price) as total_revenue
        ')
            ->whereRaw('YEAR(date) = ?', [$request->year])
            ->where('id_admin', $request->id_admin)
            ->groupBy('id_client', 'cuts', 'year')
            ->orderBy('id_client')
            ->get();

        return response()->json($data);
    }
}