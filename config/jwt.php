<?php

// JWT_SECRET bắt buộc trên production — fail-fast thay vì âm thầm dùng secret mặc định.
// Dùng env() (không phải app()->environment()) vì config được load trước khi binding 'env' sẵn sàng.
if (env('APP_ENV', 'production') === 'production' && !env('JWT_SECRET')) {
    throw new RuntimeException('Thiếu biến môi trường JWT_SECRET trên production.');
}

return [
    'secret' => env('JWT_SECRET', 'sweetlens_default_secret'),

    // TTL access token (PHÚT). Mặc định 60 phút — token hết hạn sẽ
    // được gia hạn qua POST /api/refresh trong khoảng grace bên dưới.
    'ttl' => (int) env('JWT_TTL', 60),

    // Cửa sổ grace (PHÚT) cho phép POST /api/refresh gia hạn token
    // ĐÃ hết hạn nhưng còn trong cửa sổ này (mặc định 7 ngày).
    'refresh_grace' => (int) env('JWT_REFRESH_GRACE', 60 * 24 * 7),

    'algorithm' => 'HS256',

    // Cookie httpOnly mang JWT (FE gửi kèm credentials: 'include')
    'cookie' => [
        'name' => env('JWT_COOKIE', 'sweetlens_token'),
        'path' => '/',
        'domain' => env('JWT_COOKIE_DOMAIN'), // null = domain hiện tại

        // 'lax'   → an toàn mặc định (cùng site / qua reverse proxy)
        // 'none'  → GỌI TRỰC TIẾP cross-site (Vercel → Render) —
        //           BẮT BUỘC HTTPS; CSRF được chặn bằng Origin check
        'same_site' => env('JWT_COOKIE_SAMESITE', 'lax'),
    ],
];
