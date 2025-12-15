<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EventController extends Controller
{
    // Cấu hình Supabase
    protected string $bucket = 'event-assets';
    protected string $supabaseUrl;
    protected string $supabaseKey;

    public function __construct()
    {
        $this->supabaseUrl = config('services.supabase.url') ?: env('SUPABASE_URL');
        $this->supabaseKey = config('services.supabase.key') ?: env('SUPABASE_KEY');
    }

    protected function getPublicUrl(string $filePath): string
    {
        return $this->supabaseUrl . '/storage/v1/object/public/' . $this->bucket . '/' . $filePath;
    }

    protected function uploadToSupabase(string $filePath, string $contents, string $mimeType)
    {
        $url = $this->supabaseUrl . '/storage/v1/object/' . $this->bucket . '/' . $filePath;
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->supabaseKey,
            'apikey' => $this->supabaseKey,
            'Content-Type' => $mimeType,
        ])->withBody($contents, $mimeType)->post($url);
    }

    protected function deleteFromSupabase(string $filePath)
    {
        $url = $this->supabaseUrl . '/storage/v1/object/' . $this->bucket . '/' . $filePath;
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->supabaseKey,
            'apikey' => $this->supabaseKey,
        ])->delete($url);
    }



    public function getByAdminId(Request $request, $id_admin)
    {
        try {
            $events = Event::where('id_admin', $id_admin)
                ->select('id', 'name')
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting events by admin ID: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $events = Event::where('id_admin', $id_admin)
            ->select('id', 'name', 'date', 'apply', 'ev_back', 'ev_logo', 'ev_note', 'background', 'logo')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => (int) $event->id,
                    'name' => (string) $event->name,
                    'date' => (string) $event->date,
                    'apply' => is_array($event->apply) ? $event->apply : [],
                    'ev_back' => (int) $event->ev_back,
                    'ev_logo' => (int) $event->ev_logo,
                    'ev_note' => (int) $event->ev_note,
                    'background' => $event->background ? $this->getPublicUrl($event->background) : null,
                    'logo' => $event->logo ? $this->getPublicUrl($event->logo) : null,
                ];
            });

        return response()->json($events);
    }

    public function notes(Request $request)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $notes = Event::where('id_admin', $id_admin)
            ->select('id', 'note1', 'note2', 'note3')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'note1' => (string) $item->note1,
                    'note2' => (string) $item->note2,
                    'note3' => (string) $item->note3,
                ];
            });

        return response()->json($notes);
    }

public function store(Request $request)
{
    $id_admin = $request->query('id_admin');
    if (!$id_admin || !is_numeric($id_admin)) {
        return response()->json(['status' => 'error', 'message' => 'Thiếu hoặc không hợp lệ: id_admin'], 400);
    }

    // 1. Sửa validate: đổi 'required' thành 'nullable'
    $request->validate([
        'name' => 'required|string|max:255',
        'date' => 'required|date_format:Y-m-d',
        'apply' => 'nullable|array', // Cho phép null hoặc mảng rỗng
        'apply.*' => 'integer'
    ]);

    // 2. Đảm bảo biến $apply luôn là mảng (nếu null thì thành [])
    $applyData = $request->input('apply', []);

    DB::beginTransaction();
    try {
        $event = Event::create([
            'name' => $request->name,
            'date' => $request->date,
            'apply' => $applyData, // Sử dụng biến đã xử lý
            'id_admin' => $id_admin,
        ]);

        // 3. Sử dụng $applyData thay vì $request->apply trong logic xử lý trùng lặp
        if (!empty($applyData)) {
            Event::where('id', '!=', $event->id)
                ->where('id_admin', $id_admin)
                ->chunkById(100, function ($events) use ($applyData) { // Truyền $applyData vào use
                    foreach ($events as $e) {
                        // Tính toán lại mảng apply của các event khác
                        $e->apply = array_values(array_diff($e->apply ?? [], $applyData));
                        $e->save();
                    }
                });
        }

        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Tạo event thành công',
            'id' => $event->id
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Lỗi tạo event: " . $e->getMessage());
        return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống'], 500);
    }
}

// Trong file EventController.php

public function update(Request $request, $id)
{
    $id_admin = $request->query('id_admin');
    if (!$id_admin) {
        return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
    }

    // Cho phép apply là mảng rỗng (nullable)
    $request->validate([
        'name' => 'required|string|max:255',
        'date' => 'required|date_format:Y-m-d',
        'apply' => 'nullable|array',
        'apply.*' => 'integer'
    ]);

    // Tìm sự kiện hiện tại
    $event = Event::where('id', $id)
        ->where('id_admin', $id_admin)
        ->first();

    if (!$event) {
        return response()->json(['status' => 'error', 'message' => 'Event không tồn tại'], 404);
    }

    // Xử lý mảng apply mới (nếu null thì là mảng rỗng)
    $newApplyData = $request->input('apply', []);
    
    // Lấy mảng apply cũ (đảm bảo nó là array)
    $oldApplyData = is_array($event->apply) ? $event->apply : (json_decode($event->apply, true) ?? []);

    DB::beginTransaction();
    try {
        // --- LOGIC MỚI: XỬ LÝ USER BỊ BỎ CHỌN ---
        // Tìm các ID có trong danh sách cũ nhưng KHÔNG có trong danh sách mới
        $removedUserIds = array_diff($oldApplyData, $newApplyData);

        if (!empty($removedUserIds)) {
            // Cập nhật bảng users: set id_topic = null cho những user bị xóa khỏi sự kiện
            // Sử dụng DB::table('users') để chắc chắn cập nhật đúng bảng
            DB::table('users')
                ->whereIn('id', $removedUserIds)
                ->where('id_admin', $id_admin) // Đảm bảo chỉ sửa user của admin này
                ->update(['id_topic' => null]); 
        }
        // ----------------------------------------

        // Cập nhật thông tin sự kiện
        $event->update([
            'name' => $request->name,
            'date' => $request->date,
            'apply' => $newApplyData,
        ]);

        // Logic cũ: Đảm bảo một user không thuộc nhiều sự kiện khác nhau
        if (!empty($newApplyData)) {
            Event::where('id', '!=', $id)
                ->where('id_admin', $id_admin)
                ->chunkById(100, function ($events) use ($newApplyData) {
                    foreach ($events as $e) {
                        $currentApply = is_array($e->apply) ? $e->apply : (json_decode($e->apply, true) ?? []);
                        // Loại bỏ các user vừa được chọn vào sự kiện này khỏi các sự kiện khác
                        $updatedApply = array_values(array_diff($currentApply, $newApplyData));
                        
                        // Chỉ update nếu có thay đổi
                        if (count($currentApply) !== count($updatedApply)) {
                            $e->apply = $updatedApply;
                            $e->save();
                        }
                    }
                });
            
            // (Tùy chọn) Cập nhật luôn id_topic cho những user vừa được thêm vào
            DB::table('users')
                ->whereIn('id', $newApplyData)
                ->where('id_admin', $id_admin)
                ->update(['id_topic' => $id]);
        }

        DB::commit();
        return response()->json(['status' => 'success', 'message' => 'Cập nhật dữ liệu thành công']);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Lỗi cập nhật event $id: " . $e->getMessage());
        return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống'], 500);
    }
}

    public function destroy(Request $request, $id)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $event = Event::where('id', $id)
            ->where('id_admin', $id_admin)
            ->first();

        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event để xóa'], 404);
        }

        // Xóa file trên Supabase
        if (!empty($event->background)) {
            $this->deleteFromSupabase($event->background);
        }
        if (!empty($event->logo)) {
            $this->deleteFromSupabase($event->logo);
        }

        $event->delete();

        return response()->json(['status' => 'success', 'message' => 'Xóa event thành công']);
    }

    public function updateNote(Request $request, $id)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $request->validate([
            'note1' => 'required|string',
            'note2' => 'required|string',
            'note3' => 'required|string',
            'ev_note' => 'required|in:home,cancel',
        ]);

        $ev_note = $request->ev_note === 'home' ? 1 : 0;

        $updated = Event::where('id', $id)
            ->where('id_admin', $id_admin)
            ->update([
                'note1' => $request->note1,
                'note2' => $request->note2,
                'note3' => $request->note3,
                'ev_note' => $ev_note,
            ]);

        if ($updated) {
            return response()->json(['status' => 'success', 'message' => 'Cập nhật ghi chú thành công']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event'], 404);
        }
    }

    // ✅ CẬP NHẬT LOGO
public function updateLogo(Request $request, $id)
{
    $id_admin = $request->query('id_admin');
    if (!$id_admin) {
        return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
    }

    // Validate apply luôn cần
    $request->validate([
        'apply' => 'required|in:home,cancel',
    ]);

    $event = Event::where('id', $id)
        ->where('id_admin', $id_admin)
        ->first();

    if (!$event) {
        return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event'], 404);
    }

    try {
        // Nếu có file → upload mới
        if ($request->hasFile('logo')) {
            $request->validate([
                'logo' => 'required|image|mimes:png,jpg,jpeg,gif|max:8192',
            ]);

            $file = $request->file('logo');

            // Xóa file cũ
            if (!empty($event->logo)) {
                $this->deleteFromSupabase($event->logo);
            }

            $filename = 'logos/' . uniqid('logo_', true) . '.' . $file->getClientOriginalExtension();
            $contents = file_get_contents($file->getPathname());

            $response = $this->uploadToSupabase($filename, $contents, $file->getMimeType());

            if ($response->failed()) {
                Log::error('Supabase upload logo failed', $response->json());
                throw new \Exception('Upload logo failed');
            }

            $event->logo = $filename;
        }

        // Cập nhật apply
        $event->ev_logo = $request->apply === 'home' ? 1 : 0;
        $event->save();

        return response()->json(['status' => 'success', 'message' => 'Cập nhật logo thành công!']);
    } catch (\Exception $e) {
        Log::error("Lỗi lưu logo cho event $id: " . $e->getMessage());
        return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống khi lưu logo'], 500);
    }
}

    // ✅ CẬP NHẬT BACKGROUND
public function updateBackground(Request $request, $id)
{
    $id_admin = $request->query('id_admin');
    if (!$id_admin) {
        return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
    }

$request->validate([
    'apply' => 'required|in:home,all-pages,cancel', 
]);

    $event = Event::where('id', $id)
        ->where('id_admin', $id_admin)
        ->first();

    if (!$event) {
        return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event'], 404);
    }

    try {
        if ($request->hasFile('background')) {
            $request->validate([
                'background' => 'required|image|mimes:png,jpg,jpeg,gif|max:8192',
            ]);

            $file = $request->file('background');

            if (!empty($event->background)) {
                $this->deleteFromSupabase($event->background);
            }

            $filename = 'backgrounds/' . uniqid('bg_', true) . '.' . $file->getClientOriginalExtension();
            $contents = file_get_contents($file->getPathname());

            $response = $this->uploadToSupabase($filename, $contents, $file->getMimeType());

            if ($response->failed()) {
                Log::error('Supabase upload background failed', $response->json());
                throw new \Exception('Upload background failed');
            }

            $event->background = $filename;
        }

        $event->ev_back = match ($request->apply) {
            'home' => 1,
            'all-pages' => 2,
            'cancel' => 0,
        };

        $event->save();

        return response()->json(['status' => 'success', 'message' => 'Cập nhật ảnh nền thành công!']);
    } catch (\Exception $e) {
        Log::error("Lỗi lưu background cho event $id: " . $e->getMessage());
        return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống khi lưu ảnh nền'], 500);
    }
}

    public function show(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer',
            'id_topic' => 'required|integer',
        ]);

        $event = Event::where('id_admin', $request->integer('id_admin'))
                      ->where('id', $request->integer('id_topic'))
                      ->first();

        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => "Không tìm thấy dữ liệu"
            ], 404);
        }

        return response()->json([
            'id' => (int) $event->id,
            'name' => (string) $event->name,
            'date' => (string) $event->date,
            'ev_back' => (int) $event->ev_back,
            'ev_logo' => (int) $event->ev_logo,
            'ev_note' => (int) $event->ev_note,
            'background' => $event->background ? $this->getPublicUrl($event->background) : null,
            'logo' => $event->logo ? $this->getPublicUrl($event->logo) : null,
            'notes' => $event->note1 || $event->note2 || $event->note3
                ? ['note1' => $event->note1, 'note2' => $event->note2, 'note3' => $event->note3]
                : null,
        ]);
    }
}