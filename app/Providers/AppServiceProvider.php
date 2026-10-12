<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail-fast khi config được cache (file config/jwt.php không còn chạy):
        // production thiếu JWT_SECRET thì dừng ngay thay vì ký token bằng secret rỗng/mặc định.
        if ($this->app->environment('production') && !config('jwt.secret')) {
            throw new \RuntimeException('Thiếu biến môi trường JWT_SECRET trên production.');
        }
    }
}
