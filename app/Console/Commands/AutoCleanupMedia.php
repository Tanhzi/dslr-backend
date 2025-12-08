<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoCleanupMedia extends Command
{
    protected $signature = 'media:cleanup';
    protected $description = 'Xóa media cũ hơn 24 giờ trên Supabase Storage và database';

    public function handle()
    {
        $cutoff = now()->subHours(24);
        $oldMedia = Media::where('created_at', '<', $cutoff)->get();

        if ($oldMedia->isEmpty()) {
            $this->info("Không có media nào cần xóa.");
            return;
        }

        $deletedCount = 0;
        $bucket = 'event-assets';
        $supabaseUrl = config('services.supabase.url') ?: env('SUPABASE_URL');
        $supabaseKey = config('services.supabase.key') ?: env('SUPABASE_KEY');

// ...

foreach ($oldMedia as $media) {
    // 1. XÓA FILE TRÊN SUPABASE (nếu có đường dẫn)
    if (!empty($media->file_path)) {
        try {
            $deleteUrl = $supabaseUrl . '/storage/v1/object/' . $bucket . '/' . $media->file_path;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $supabaseKey,
                'apikey' => $supabaseKey,
            ])->delete($deleteUrl);

            if ($response->failed()) {
                Log::warning("Supabase delete failed for media ID {$media->id}", [
                    'path' => $media->file_path,
                    'error' => $response->json()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Exception when deleting media ID {$media->id} from Supabase", [
                'path' => $media->file_path,
                'message' => $e->getMessage()
            ]);
        }
    }

    // 2. NẾU LÀ QR, XÓA id_qr TRONG BẢNG pays
    if ($media->file_type === 'qr' && !empty($media->session_id)) {
        try {
            \App\Models\Pay::where('id_qr', $media->session_id)->update(['id_qr' => null]);
        } catch (\Exception $e) {
            Log::error("Failed to clear id_qr in pays for session_id {$media->session_id}", [
                'media_id' => $media->id,
                'message' => $e->getMessage()
            ]);
        }
    }

    // 3. XÓA BẢN GHI TRONG DB
    $media->delete();
    $deletedCount++;
}

        $this->info("Đã xóa {$deletedCount} media cũ (cũ hơn 24 giờ).");
    }
}