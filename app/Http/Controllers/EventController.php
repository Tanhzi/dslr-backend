<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->select('id', 'name', 'date', 'apply', 'ev_back', 'ev_logo', 'ev_note')
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
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
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
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    // DELETE /api/events/{id}
    public function destroy(Request $request, $id)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $deleted = Event::where('id', $id)
            ->where('id_admin', $id_admin)
            ->delete();

        if ($deleted) {
            return response()->json(['status' => 'success', 'message' => 'Xóa event thành công']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event để xóa'], 404);
        }
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

        if (!$request->hasFile('logo')) {
            return response()->json(['status' => 'error', 'message' => 'Chưa tải lên file logo'], 400);
        }

        $file = $request->file('logo');
        if (!$file->isValid()) {
            return response()->json(['status' => 'error', 'message' => 'File logo không hợp lệ'], 400);
        }

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file->getClientOriginalExtension(), $allowedTypes)) {
            return response()->json(['status' => 'error', 'message' => 'Chỉ cho phép file JPG, JPEG, PNG, GIF'], 400);
        }

        $apply = $request->input('apply');
        $ev_logo = $apply === 'home' ? 1 : 0;
        $fileData = file_get_contents($file->getRealPath());

        $updated = Event::where('id', $id)
            ->where('id_admin', $id_admin)
            ->update([
                'logo' => $fileData,
                'ev_logo' => $ev_logo,
            ]);

        if ($updated) {
            return response()->json(['status' => 'success', 'message' => 'Cập nhật logo thành công!']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event'], 404);
        }
    }

    // POST /api/events/{id}/background
    public function updateBackground(Request $request, $id)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        if (!$request->hasFile('background')) {
            return response()->json(['status' => 'error', 'message' => 'Chưa tải lên file ảnh'], 400);
        }

        $file = $request->file('background');
        if (!$file->isValid()) {
            return response()->json(['status' => 'error', 'message' => 'File không hợp lệ'], 400);
        }

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file->getClientOriginalExtension(), $allowedTypes)) {
            return response()->json(['status' => 'error', 'message' => 'Chỉ cho phép file JPG, JPEG, PNG, GIF'], 400);
        }

        $apply = $request->input('apply');
        $ev_back = match ($apply) {
            'home' => 1,
            'all-pages' => 2,
            default => 0,
        };

        $fileData = file_get_contents($file->getRealPath());

        $updated = Event::where('id', $id)
            ->where('id_admin', $id_admin)
            ->update([
                'background' => $fileData,
                'ev_back' => $ev_back,
            ]);

        if ($updated) {
            return response()->json(['status' => 'success', 'message' => 'Cập nhật ảnh nền thành công!']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy event'], 404);
        }
    }

    // (Optional) GET /api/events/{id} → chi tiết event (đã có trong code bạn)
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
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->date,
            'ev_back' => (int) $event->ev_back,
            'ev_logo' => (int) $event->ev_logo,
            'ev_note' => (int) $event->ev_note,
            'background' => $event->background ? base64_encode($event->background) : null,
            'logo' => $event->logo ? base64_encode($event->logo) : null,
            'notes' => $event->note1 || $event->note2 || $event->note3
                ? ['note1' => $event->note1, 'note2' => $event->note2, 'note3' => $event->note3]
                : null,
        ]);
    }
}