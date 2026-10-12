<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Whitelist origin FE — KHÔNG dùng '*' (vì supports_credentials = true).
    // Trên production thêm domain Vercel, ví dụ:
    // CORS_ORIGINS=https://your-app.vercel.app,http://localhost:5173
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ORIGINS', 'http://localhost:5173'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    // BẮT BUỘC true để trình duyệt chấp nhận cookie httpOnly (JWT)
    'supports_credentials' => true,

];
