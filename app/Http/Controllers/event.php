<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    // GET /api/events → danh sách sự kiện
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
                    'background' => $event->background ? Storage::url($event->background) : null,
                    'logo' => $event->logo ? Storage::url($event->logo) : null,
                ];
            });

        return response()->json($events);
    }

    // GET /api/event-notes → ghi chú
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

    // POST /api/events → tạo mới
    public function store(Request $request)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin || !is_numeric($id_admin)) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu hoặc không hợp lệ: id_admin'], 400);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date_format:Y-m-d',
            'apply' => 'required|array',
            'apply.*' => 'integer'
        ]);

        DB::beginTransaction();
        try {
            $event = Event::create([
                'name' => $request->name,
                'date' => $request->date,
                'apply' => $request->apply,
                'id_admin' => $id_admin,
                // background, logo để null khi tạo mới
            ]);

            if (!empty($request->apply)) {
                Event::where('id', '!=', $event->id)
                    ->where('id_admin', $id_admin)
                    ->chunkById(100, function ($events) use ($request) {
                        foreach ($events as $e) {
                            $e->apply = array_values(array_diff($e->apply ?? [], $request->apply));
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

    // PUT /api/events/{id} → cập nhật
    public function update(Request $request, $id)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date_format:Y-m-d',
            'apply' => 'required|array',
            'apply.*' => 'integer'
        ]);

        $event = Event::where('id', $id)
            ->where('id_admin', $id_admin)
            ->first();

        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Event không tồn tại'], 404);
        }

        DB::beginTransaction();
        try {
            $event->update([
                'name' => $request->name,
                'date' => $request->date,
                'apply' => $request->apply,
            ]);

            if (!empty($request->apply)) {
                Event::where('id', '!=', $id)
                    ->where('id_admin', $id_admin)
                    ->chunkById(100, function ($events) use ($request) {
                        foreach ($events as $e) {
                            $e->apply = array_values(array_diff($e->apply ?? [], $request->apply));
                            $e->save();
                        }
                    });
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Cập nhật dữ liệu thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi cập nhật event $id: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống'], 500);
        }
    }

    // DELETE /api/events/{id}
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

        // Xóa file ảnh (nếu có)
        if (!empty($event->background)) {
            Storage::disk('public')->delete($event->background);
        }
        if (!empty($event->logo)) {
            Storage::disk('public')->delete($event->logo);
        }

        $event->delete();

        return response()->json(['status' => 'success', 'message' => 'Xóa event thành công']);
    }

    // POST /api/events/{id}/note
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

    // POST /api/events/{id}/logo
    public function updateLogo(Request $request, $id)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,gif|max:8192',
            'apply' => 'required|in:home,cancel',
        ]);

        $event = Event::where('id', $id)
            ->where('id_admin', $id_admin)
            ->first();

        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event'], 404);
        }

        $file = $request->file('logo');
        try {
            if (!empty($event->logo)) {
                Storage::disk('public')->delete($event->logo);
            }

            $filename = uniqid('logo_', true) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('logos', $filename, 'public');

            $ev_logo = $request->apply === 'home' ? 1 : 0;

            $event->update([
                'logo' => $path,
                'ev_logo' => $ev_logo,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Cập nhật logo thành công!']);
        } catch (\Exception $e) {
            Log::error("Lỗi lưu logo cho event $id: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống khi lưu logo'], 500);
        }
    }

    // POST /api/events/{id}/background
    public function updateBackground(Request $request, $id)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $request->validate([
            'background' => 'required|image|mimes:png,jpg,jpeg,gif|max:8192',
            'apply' => 'required|in:home,all-pages',
        ]);

        $event = Event::where('id', $id)
            ->where('id_admin', $id_admin)
            ->first();

        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event'], 404);
        }

        $file = $request->file('background');
        try {
            if (!empty($event->background)) {
                Storage::disk('public')->delete($event->background);
            }

            $filename = uniqid('bg_', true) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('backgrounds', $filename, 'public');

            $ev_back = match ($request->apply) {
                'home' => 1,
                'all-pages' => 2,
                default => 0,
            };

            $event->update([
                'background' => $path,
                'ev_back' => $ev_back,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Cập nhật ảnh nền thành công!']);
        } catch (\Exception $e) {
            Log::error("Lỗi lưu background cho event $id: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống khi lưu ảnh nền'], 500);
        }
    }

    // GET /api/events/{id} → chi tiết event
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
            'background' => $event->background ? "https://dslr-api.onrender.com    " . Storage::url($event->background) : null,
            'logo' => $event->logo ? "https://dslr-api.onrender.com    " . Storage::url($event->logo) : null,
            'notes' => $event->note1 || $event->note2 || $event->note3
                ? ['note1' => $event->note1, 'note2' => $event->note2, 'note3' => $event->note3]
                : null,
        ]);
    }
}