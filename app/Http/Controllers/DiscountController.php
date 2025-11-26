<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Models\Pay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DiscountController extends Controller
{
    // 1. GET /api/discounts?id_admin=...
    public function index(Request $request)
    {
        $request->validate(['id_admin' => 'required|integer']);

        $discounts = Discount::where('id_admin', $request->id_admin)
            ->select('id', 'code', 'value', 'quantity', 'count_quantity', 'startdate', 'enddate')
            ->get();

        return response()->json($discounts);
    }

    // 2. POST /api/discounts/check
    public function check(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'id_admin' => 'required|integer',
        ]);

        $discount = Discount::where('code', $request->code)
            ->where('id_admin', $request->id_admin)
            ->first();

        if (!$discount) {
            throw ValidationException::withMessages([
                'code' => ['Mã giảm giá không tồn tại']
            ]);
        }

        if ($discount->count_quantity >= $discount->quantity) {
            throw ValidationException::withMessages([
                'code' => ['Mã này đã hết lần sử dụng']
            ]);
        }

        $now = now()->toDateString();
        if ($now < $discount->startdate || $now > $discount->enddate) {
            throw ValidationException::withMessages([
                'code' => ['Mã giảm giá không còn hiệu lực']
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mã giảm giá hợp lệ',
            'value' => $discount->value,
            'usedCount' => $discount->count_quantity,
            'remainingUses' => $discount->quantity - $discount->count_quantity
        ]);
    }

    // 3. POST /api/discounts/use
    public function use(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'id_admin' => 'required|integer',
        ]);

        $discount = Discount::where('code', $request->code)
            ->where('id_admin', $request->id_admin)
            ->first();

        if (!$discount) {
            return response()->json(['status' => 'error', 'message' => 'Mã không tồn tại'], 404);
        }

        if ($discount->count_quantity >= $discount->quantity) {
            return response()->json(['status' => 'error', 'message' => 'Mã đã hết lượt sử dụng'], 400);
        }

        $now = now()->toDateString();
        if ($now < $discount->startdate || $now > $discount->enddate) {
            return response()->json(['status' => 'error', 'message' => 'Mã đã hết hạn'], 400);
        }

        // Cập nhật count_quantity
        $discount->increment('count_quantity');

        return response()->json([
            'status' => 'success',
            'message' => 'Áp dụng mã thành công',
            'value' => $discount->value,
            'usedCount' => $discount->count_quantity,
            'remainingUses' => $discount->quantity - $discount->count_quantity
        ]);
    }

    // 4. POST /api/pays
    public function storePay(Request $request)
    {
        $request->validate([
            'price' => 'required|numeric',
            'id_admin' => 'required|integer',
            'id_client' => 'required|integer',
            'cuts' => 'required|string',
            'date' => 'required|date',
            'discount' => 'required|in:0,1',
            'discount_price' => 'required|numeric',
            'discount_code' => 'required|string'
        ]);

        DB::beginTransaction();
        try {
            $pay = Pay::create([
                'id_admin' => $request->id_admin,
                'id_client' => $request->id_client,
                'price' => $request->price,
                'cuts' => $request->cuts,
                'date' => $request->date,
                'discount' => $request->discount,
                'discount_price' => $request->discount_price,
                'discount_code' => $request->discount_code
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Tạo pays thành công',
                'id' => $pay->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi lưu thanh toán: ' . $e->getMessage()
            ], 500);
        }
    }

    //Lấy id mới nhất (lớn nhất) theo id_admin
    public function getNewId(Request $request)
    {
        $adminId = $request->query('id_admin'); // ← Sửa thành 'id_admin'

        if (!$adminId) {
            return response()->json(['error' => 'Thiếu id_admin'], 400);
        }

        $newId = Pay::where('id_admin', $adminId)->max('id');

        return response()->json(['id' => $newId]); 
    }

    //Cập nhật lưu thêm thông id_frame và id_qr theo id_pay
    public function updatePay(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'id_frame' => 'required|integer',
            'id_qr' => 'nullable|string',
            'email' => 'nullable|email', // ← CHO PHÉP NULL
        ]);

        $pay = Pay::find($request->id);
        if (!$pay) {
            return response()->json(['error' => 'Không tìm thấy thanh toán'], 404);
        }

        $pay->update([
            'id_frame' => $request->id_frame,
            'id_qr' => $request->id_qr,
            'email' => $request->email
        ]);

        return response()->json(['status' => 'success', 'message' => 'Cập nhật thành công']);
    }


    public function index1(Request $request)
    {
        $adminId = $request->query('admin_id');

        if (!$adminId) {
            return response()->json(['error' => 'Thiếu admin_id'], 400);
        }

        $discounts = Discount::where('id_admin', $adminId)
            ->select('id', 'code', 'value', 'quantity', 'count_quantity', 'startdate', 'enddate')
            ->get();

        return response()->json($discounts);
    }

    // POST /discounts
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'startdate' => 'required|date|after_or_equal:today',
            'enddate'   => 'required|date|after:startdate',
            'value'     => 'required|numeric|min:0.01',
            'quantity'  => 'required|integer|min:1',
            'id_admin'  => 'required|integer',
        ], [
            'startdate.after_or_equal' => 'Ngày phải bắt đầu từ ngày hôm nay trở đi',
            'enddate.after'            => 'Ngày kết thúc phải sau ngày bắt đầu',
            'value.min'                => 'Giá trị giảm giá phải lớn hơn 0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= rand(0, 9);
        }

        $discount = Discount::create([
            'code'           => $code,
            'startdate'      => $request->startdate,
            'enddate'        => $request->enddate,
            'value'          => $request->value,
            'quantity'       => $request->quantity,
            'count_quantity' => 0,
            'id_admin'       => $request->id_admin,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Discount added successfully',
            'code' => $code,
        ]);
    }

    // PUT /discounts/{id}
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'startdate' => 'required|date',
            'enddate'   => 'required|date|after:startdate',
            'value'     => 'required|numeric|min:0.01',
            'quantity'  => 'required|integer|min:1',
        ], [
            'enddate.after' => 'Ngày kết thúc phải sau ngày bắt đầu',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        $discount = Discount::find($id);
        if (!$discount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá'
            ], 404);
        }

        $discount->update([
            'startdate' => $request->startdate,
            'enddate'   => $request->enddate,
            'value'     => $request->value,
            'quantity'  => $request->quantity,
        ]);

        return response()->json([
            'status' => 'success',
            'id' => $discount->id
        ]);
    }

    // DELETE /discounts/{id}
    public function destroy($id)
    {
        $discount = Discount::find($id);

        if (!$discount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá'
            ], 404);
        }

        $discount->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Discount deleted successfully'
        ]);
    }

}