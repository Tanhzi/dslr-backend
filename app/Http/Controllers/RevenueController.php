<?php

namespace App\Http\Controllers;

use App\Models\Pay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RevenueController extends Controller
{
    /**
     * PhiÃªn báº£n cache theo mÃ¡y â€” tÄƒng lÃªn má»—i khi cÃ³ Ä‘Æ¡n má»›i (storePay)
     * Ä‘á»ƒ cache bÃ¡o cÃ¡o háº¿t háº¡n ngay láº­p tá»©c thay vÃ¬ chá» TTL.
     */
    private function version(int $idAdmin): int
    {
        return (int) Cache::get('revenue_ver_' . $idAdmin, 0);
    }

    /**
     * L6: Ä‘Ã­nh kÃ¨m tÃªn mÃ¡y (username cá»§a users.id = id_client) cho tá»«ng dÃ²ng
     * Ä‘á»ƒ FE hiá»ƒn thá»‹ "may1" thay vÃ¬ sá»‘ id_client thÃ´.
     */
    private function withClientNames($rows)
    {
        $ids = collect($rows)->pluck('id_client')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return $rows;
        }

        $names = DB::table('users')->whereIn('id', $ids)->pluck('username', 'id');

        foreach ($rows as $row) {
            $name = $names[(int) $row->id_client] ?? null;
            $row->client_name = ($name !== null && $name !== '') ? $name : ('MÃ¡y ' . $row->id_client);
        }

        return $rows;
    }

    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_admin' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Thiáº¿u id_admin'], 400);
        }

        $cacheKey = 'revenue_summary_' . $request->id_admin . '_v' . $this->version((int) $request->id_admin);
        $data = Cache::remember($cacheKey, 300, function () use ($request) {
            return Pay::selectRaw('
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
        });

        return response()->json($this->withClientNames($data));
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

        $cacheKey = 'revenue_range_' . $request->id_admin . '_' . $request->from_date . '_' . $request->to_date . '_v' . $this->version((int) $request->id_admin);
        $data = Cache::remember($cacheKey, 300, function () use ($request) {
            return Pay::selectRaw('
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
        });

        return response()->json($this->withClientNames($data));
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

        $cacheKey = 'revenue_month_' . $request->id_admin . '_' . $request->year . '_' . $request->month . '_v' . $this->version((int) $request->id_admin);
        $data = Cache::remember($cacheKey, 300, function () use ($request) {
            return Pay::selectRaw('
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
        });

        return response()->json($this->withClientNames($data));
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

        $cacheKey = 'revenue_quarter_' . $request->id_admin . '_' . $request->year . '_' . $quarter . '_v' . $this->version((int) $request->id_admin);
        $data = Cache::remember($cacheKey, 300, function () use ($request, $quarter, $startMonth, $endMonth) {
            return Pay::selectRaw('
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
        });

        return response()->json($this->withClientNames($data));
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

        $cacheKey = 'revenue_year_' . $request->id_admin . '_' . $request->year . '_v' . $this->version((int) $request->id_admin);
        $data = Cache::remember($cacheKey, 300, function () use ($request) {
            return Pay::selectRaw('
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
        });

        return response()->json($this->withClientNames($data));
    }
}