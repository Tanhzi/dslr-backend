<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'quality' => 'required|integer|min:0|max:5',
            'smoothness' => 'required|integer|min:0|max:5',
            'photo' => 'required|integer|min:0|max:5',
            'service' => 'required|integer|min:0|max:5',
            'comment' => 'nullable|string|max:2000',
            'id_admin' => 'nullable|integer|exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            $rating = Rating::create([
                'name' => $validated['name'],
                'quality' => $validated['quality'],
                'smoothness' => $validated['smoothness'],
                'photo' => $validated['photo'],
                'service' => $validated['service'],
                'comment' => $validated['comment'] ?? null,
                'id_admin' => $validated['id_admin'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Cảm ơn {$validated['name']} đã đánh giá!",
                'data' => $rating
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gửi thất bại. Vui lòng thử lại.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // app/Http/Controllers/RatingController.php
    public function index(Request $request)
    {
        $request->validate([
            'page' => 'integer|min:1',
            'search' => 'nullable|string|max:255',
            'limit' => 'integer|min:1|max:100'
        ]);

        $query = Rating::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        $limit = $request->limit ?? 10;
        $page = $request->page ?? 1;
        $total = $query->count();

        $ratings = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $ratings,
            'total' => $total,
            'page' => (int) $page,
            'limit' => (int) $limit,
            'total_pages' => ceil($total / $limit)
        ]);
    }

    public function destroy($id)
    {
        $rating = Rating::find($id);
        if (!$rating) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đánh giá'], 404);
        }

        $rating->delete();
        return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
    }
}