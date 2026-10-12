<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dọn file media định kỳ (cần cron chạy `php artisan schedule:run` mỗi phút trên server)
Schedule::command('media:cleanup')->dailyAt('03:00');
