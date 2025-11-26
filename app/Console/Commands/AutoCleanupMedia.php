<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class AutoCleanupMedia extends Command
{
    protected $signature = 'media:cleanup';
    protected $description = 'Xóa media cũ hơn 24 giờ';

    public function handle()
    {
        $cutoff = now()->subHours(24);
        $oldMedia = Media::where('created_at', '<', $cutoff)->get();

        foreach ($oldMedia as $media) {
            if ($media->file_path && Storage::disk('public')->exists($media->file_path)) {
                Storage::disk('public')->delete($media->file_path);
            }
            $media->delete();
        }

        $this->info("Đã xóa {$oldMedia->count()} media cũ.");
    }
}