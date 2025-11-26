<?php

namespace App\Http\Controllers;

use App\Models\Pay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
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
            EXTRACT(MONTH FROM date) as month,
            EXTRACT(YEAR FROM date) as year,
            cuts,
            SUM(price) as total_revenue
        ')
            ->where('id_admin', $request->id_admin)
            ->groupBy('id_client', DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(MONTH FROM date)'), 'cuts')
            ->orderBy(DB::raw('EXTRACT(YEAR FROM date)'))
            ->orderBy(DB::raw('EXTRACT(MONTH FROM date)'))
            ->orderBy('id_client')
            ->get();

        return response()->json($data);
    }

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
            EXTRACT(MONTH FROM date) as month,
            EXTRACT(YEAR FROM date) as year,
            cuts,
            SUM(discount) as discount,
            SUM(discount_price) as discount_price,
            SUM(price) as total_revenue
        ')
            ->whereBetween('date', [$request->from_date, $request->to_date])
            ->where('id_admin', $request->id_admin)
            ->groupBy('id_client', DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(MONTH FROM date)'), 'cuts')
            ->orderBy(DB::raw('EXTRACT(YEAR FROM date)'))
            ->orderBy(DB::raw('EXTRACT(MONTH FROM date)'))
            ->orderBy('id_client')
            ->get();

        return response()->json($data);
    }

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
            EXTRACT(MONTH FROM date) as month,
            EXTRACT(YEAR FROM date) as year,
            cuts,
            SUM(discount) as discount,
            SUM(discount_price) as discount_price,
            SUM(price) as total_revenue
        ')
            ->where('id_admin', $request->id_admin)
            ->whereRaw('EXTRACT(MONTH FROM date) = ?', [$request->month])
            ->whereRaw('EXTRACT(YEAR FROM date) = ?', [$request->year])
            ->groupBy('id_client', 'cuts', DB::raw('EXTRACT(MONTH FROM date)'), DB::raw('EXTRACT(YEAR FROM date)'))
            ->orderBy('id_client')
            ->get();

        return response()->json($data);
    }

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
            EXTRACT(MONTH FROM date) as month,
            EXTRACT(YEAR FROM date) as year,
            cuts,
            SUM(discount) as discount,
            SUM(discount_price) as discount_price,
            SUM(price) as total_revenue
        ')
            ->where('id_admin', $request->id_admin)
            ->whereRaw('EXTRACT(YEAR FROM date) = ?', [$request->year])
            ->whereRaw('EXTRACT(MONTH FROM date) BETWEEN ? AND ?', [$startMonth, $endMonth])
            ->groupBy('id_client', DB::raw('EXTRACT(YEAR FROM date)'), DB::raw('EXTRACT(MONTH FROM date)'), 'cuts')
            ->orderBy(DB::raw('EXTRACT(MONTH FROM date)'))
            ->orderBy('id_client')
            ->get()
            ->map(function ($item) use ($quarter) {
                $item->quarter = $quarter;
                return $item;
            });

        return response()->json($data);
    }

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
            EXTRACT(YEAR FROM date) as year,
            cuts,
            SUM(discount) as discount,
            SUM(discount_price) as discount_price,
            SUM(price) as total_revenue
        ')
            ->where('id_admin', $request->id_admin)
            ->whereRaw('EXTRACT(YEAR FROM date) = ?', [$request->year])
            ->groupBy('id_client', 'cuts', DB::raw('EXTRACT(YEAR FROM date)'))
            ->orderBy('id_client')
            ->get();

        return response()->json($data);
    }
}