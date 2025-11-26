<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AutoCleanupMedia extends Command
{
    protected $signature = 'media:cleanup';
    protected $description = 'Tự động xóa media cũ hơn 24 giờ';

    public function handle()
    {
        // Lấy thời điểm cách đây 24 giờ
        $cutoff = now()->subHours(24);

        // Tìm tất cả media tạo trước thời điểm đó
        $oldMedia = Media::where('created_at', '<', $cutoff)->get();

        $deletedCount = 0;

        foreach ($oldMedia as $media) {
            // 1. Xóa file trên disk (nếu tồn tại)
            if (!empty($media->file_path) && Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
                $this->info("Đã xóa file: {$media->file_path}");
            }

            // 2. Xóa bản ghi trong DB
            $media->delete();
            $deletedCount++;
        }

        $this->info("✅ Đã dọn dẹp {$deletedCount} media cũ (trước {$cutoff->format('Y-m-d H:i')}).");
        Log::info("Media cleanup: {$deletedCount} records deleted.");
    }
}