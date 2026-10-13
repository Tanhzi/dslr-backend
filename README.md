# SweetLens — Backend

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A58.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Tests](https://img.shields.io/badge/tests-26%20passing-2E7D32.svg)](#kiểm-thử)
[![License](https://img.shields.io/badge/license-proprietary-C0392B.svg)](#giấy-phép)

REST API cho hệ thống Photo Booth **SweetLens** — xác thực JWT, quản lý dữ liệu, thanh toán, báo cáo doanh thu, in/email và chatbot AI.

> Repo này chỉ chứa **backend**: [github.com/Tanhzi/dslr-backend](https://github.com/Tanhzi/dslr-backend).
> Frontend (React) nằm trong repo riêng: [github.com/Tanhzi/Photobooth](https://github.com/Tanhzi/Photobooth).

---

## Mục lục

- [Tổng quan](#tổng-quan)
- [Tính năng](#tính-năng)
- [Công nghệ](#công-nghệ)
- [Yêu cầu hệ thống](#yêu-cầu-hệ-thống)
- [Cài đặt nhanh](#cài-đặt-nhanh)
- [Các lệnh phổ biến](#các-lệnh-phổ-biến)
- [Cấu hình môi trường](#cấu-hình-môi-trường)
- [Tài liệu API](#tài-liệu-api)
- [Cấu trúc thư mục](#cấu-trúc-thư-mục)
- [Xác thực & phân quyền](#xác-thực--phân-quyền)
- [Caching](#caching)
- [Giấy phép](#giấy-phép)

---

## Tổng quan

- **90 routes** (23 API công khai + 63 route JWT + 4 route web/health), **20 controllers**, **16 migrations**.
- Xác thực **JWT HS256, TTL 60 phút** (`JWT_TTL`) — middleware tự lấy user từ DB và inject `id_admin`/`id_topic` vào request.
- Lưu file (khung/sticker/media) trên **Supabase Storage**; email qua **Brevo API**; chatbot qua **OpenRouter** (chain model free).
- Thống kê dashboard/doanh thu **cache file** có version-key để invalidate chủ động khi có đơn mới.

## Tính năng

| Nhóm | Tính năng |
|---|---|
| Xác thực | Đăng nhập, đăng ký, đổi mật khẩu, quên mật khẩu (OTP) |
| Sự kiện | CRUD, ghi chú, upload logo & ảnh nền |
| Khung ảnh | CRUD (upload hàng loạt), lọc/tìm kiếm/phân trang |
| Sticker | CRUD (upload hàng loạt), lọc theo loại |
| AI | CRUD AI topic, tạo ảnh qua bridge |
| Khuyến mãi | CRUD mã giảm giá, tự sinh mã 8 ký tự, kiểm tra & sử dụng |
| Thanh toán | Tạo giao dịch (`/api/pays`), đơn hàng, cập nhật trạng thái |
| Báo cáo | Doanh thu tổng quan / khoảng ngày / tháng / quý / năm, theo máy |
| Cấu hình | Máy ảnh, giá theo khổ in |
| Đánh giá | Lấy danh sách (công khai), xóa (admin) |
| Chatbot | CRUD nội dung `content_chat`, trả lời qua OpenRouter (`POST /api/chat`) |
| Email | Gửi QR & ảnh gốc qua Brevo |
| Dọn dẹp | Xóa media rác theo lịch (03:00) hoặc cron ngoài qua `CLEANUP_TOKEN` |

## Công nghệ

| Công nghệ | Phiên bản | Vai trò |
|---|---|---|
| Laravel Framework | ^12.0 | REST API framework |
| PHP | ^8.2 | Runtime |
| PostgreSQL | 15 (Supabase) | Database chính |
| Supabase Storage | — | Lưu file (bucket `event-assets`) |
| firebase/php-jwt | ^7.0 | JWT HS256 |
| guzzlehttp/guzzle | ^7.10 | Gọi Supabase/Brevo/OpenRouter |

## Yêu cầu hệ thống

- PHP >= 8.2, extension: `pdo_pgsql`, `openssl`, `mbstring`, `curl`
- Composer
- PostgreSQL (Supabase)

## Cài đặt nhanh

```bash
git clone https://github.com/Tanhzi/dslr-backend.git
cd dslr-backend

composer setup      # install + copy .env + key:generate + migrate
composer dev        # serve + queue + pail logs + vite (đồng thời)
```

Cách thủ công:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

## Các lệnh phổ biến

| Lệnh | Mô tả |
|---|---|
| `composer setup` | Cài đặt lần đầu (install → `.env` → key → migrate) |
| `composer dev` | Chạy song song serve + queue + pail + vite |
| `composer test` | Xóa cache config + chạy toàn bộ PHPUnit |
| `composer lint` | Kiểm tra syntax `php -l` toàn bộ file PHP (bỏ qua `vendor/`) |
| `php artisan route:list` | Liệt kê 90 routes |
| `php artisan schedule:list` | Xem các lệnh theo lịch |

## Cấu hình môi trường

| Biến | Mô tả |
|---|---|
| `APP_URL` | URL của backend (mặc định `http://127.0.0.1:8000`) |
| `APP_KEY` | Khóa mã hóa — sinh bằng `php artisan key:generate` |
| `APP_DEBUG` | **`false` trên production** — `true` lộ stack trace |
| `DB_*` | Kết nối PostgreSQL (Supabase), kèm `DB_SSLMODE` (mặc định `require`) |
| `SUPABASE_URL` / `SUPABASE_KEY` | Supabase Storage |
| `JWT_SECRET` | Khóa bí mật ký JWT (HS256) |
| `JWT_TTL` | TTL access token (phút, mặc định **60**) |
| `JWT_REFRESH_GRACE` | Cửa sổ gia hạn token đã hết hạn (phút, mặc định **10080** = 7 ngày) |
| `JWT_COOKIE_SAMESITE` | `lax` (mặc định) hoặc `none` khi FE gọi thẳng cross-site (bắt buộc HTTPS) |
| `CORS_ORIGINS` | Whitelist origin FE, phân cách dấu phẩy (mặc định `http://localhost:5173`) |
| `BREVO_API_KEY` | Gửi email (QR, ảnh gốc) |
| `OPENROUTER_API_KEY` | Chatbot AI (OpenRouter) |
| `OPENROUTER_MODEL` | Model chatbot, phân cách dấu phẩy = thứ tự fallback (mặc định chain model **free**) |
| `CACHE_STORE` | Driver cache (`file`) |
| `CLEANUP_TOKEN` | Token cho `GET /api/cleanup-trigger?token=...` |

## Tài liệu API

### 1. Công khai (không cần token)

| Phương thức | Endpoint | Mô tả |
|---|---|---|
| POST | `/api/register` | Đăng ký khách hàng |
| POST | `/api/login` | Đăng nhập → JWT (cookie httpOnly + token trong body) |
| POST | `/api/refresh` | Gia hạn JWT (sliding, throttle 30 req/phút) |
| POST | `/api/logout` | Đăng xuất — xóa cookie JWT |
| POST | `/api/forgot-password` | Gửi OTP qua email |
| POST | `/api/verify-otp` | Xác minh OTP |
| POST | `/api/reset-password` | Đặt lại mật khẩu |
| GET | `/api/event-client` | Sự kiện cho kiosk |
| GET | `/api/frames-client` | Danh sách khung cho kiosk |
| GET | `/api/frame-image-client/{filename}` | Ảnh khung cho kiosk |
| GET | `/api/prices` | Cấu hình giá |
| GET | `/api/discounts` | Danh sách mã giảm giá |
| POST | `/api/discounts/check` | Kiểm tra mã giảm giá |
| GET | `/api/ratings` | Đánh giá công khai |
| POST | `/api/ratings` | Gửi đánh giá |
| GET | `/api/camera`, `/api/camera/basic` | Cấu hình máy ảnh (đọc) |
| GET | `/api/background` | Ảnh nền |
| GET | `/api/media/session` | Ảnh theo phiên chụp |
| GET | `/api/get-composite-by-session` | Ảnh ghép khung theo phiên |
| GET | `/api/qr-image`, `/api/download` | Ảnh QR / tải ảnh |
| GET | `/download` | Trang HTML tải ảnh (route web, ngoài `/api`) |
| GET | `/api/cleanup-trigger` | Dọn media (yêu cầu `?token=` khớp `CLEANUP_TOKEN`) |

### 2. Yêu cầu JWT (tự phục vụ — mọi role)

| Phương thức | Endpoint | Ghi chú |
|---|---|---|
| GET | `/api/me` | Thông tin user hiện tại |
| POST | `/api/change-password` | Đổi mật khẩu |
| GET | `/api/get-new-id` | Lấy phiên/ID mới cho kiosk |
| PUT | `/api/update-pay` | Cập nhật trạng thái thanh toán |
| GET | `/api/stickers`, `/api/ai-topics` | Đọc (kiosk dùng) |
| POST | `/api/chat` | Chatbot OpenRouter — throttle **30 req/phút** |

### 3. Yêu cầu JWT — staff & admin (`role:1,2`)

| Phương thức | Endpoint | Mô tả |
|---|---|---|
| GET | `/api/events` | Danh sách sự kiện |
| GET/POST/PUT/DELETE | `/api/frames` | CRUD khung ảnh |
| GET | `/api/frame-image` | Ảnh khung (admin) |
| GET/POST/PUT/DELETE | `/api/stickers` | CRUD sticker |
| POST/PUT/DELETE | `/api/ai-topics` | CRUD AI topic |
| GET | `/api/get-orders`, `/api/top-frames` | Đơn hàng, khung phổ biến |

### 4. Yêu cầu JWT — admin (`role:2`)

| Phương thức | Endpoint | Mô tả |
|---|---|---|
| GET | `/api/count`, `/api/sum-price` | Dashboard (cache 3 phút) |
| GET | `/api/summary`, `/api/price`, `/api/month` | Doanh thu theo máy/tháng |
| GET | `/api/range`, `/api/quarter`, `/api/year` | Báo cáo theo khoảng/quý/năm |
| GET/POST/PUT/DELETE | `/api/users`, `/api/users-admin` | CRUD người dùng |
| GET/POST/PUT/DELETE | `/api/events-admin` | CRUD sự kiện (+ logo, nền, ghi chú) |
| GET | `/api/event`, `/api/event-notes`, `/api/admin/events/{id_admin}` | Sự kiện phụ trợ |
| GET/POST/PUT/DELETE | `/api/content-chat` | Nội dung chatbot |
| GET/POST | `/api/camera`, `/api/size` | Cấu hình máy ảnh / giá |
| GET/POST/PUT | `/api/promotion`, `/api/add-promotion`, `/api/de-promotion/{id}` | Khuyến mãi |
| POST | `/api/pays`, `/api/media`, `/api/discounts/use` | Thanh toán & upload — throttle **30 req/phút** |
| POST | `/api/send-qr-email`, `/api/send-original-images-email` | Email — throttle **10 req/phút** |
| DELETE | `/api/ratings/{id}` | Xóa đánh giá |


## Cấu trúc thư mục

```
backend/
├── app/
│   ├── Console/Commands/         # AutoCleanupMedia (lịch 03:00)
│   ├── Http/
│   │   ├── Controllers/          # 21 controllers
│   │   └── Middleware/
│   │       ├── JwtMiddleware.php # Verify JWT, load user, inject id_admin/id_topic
│   │       └── EnsureRole.php    # role:1,2 / role:2
│   ├── Models/
│   └── Services/                 # JwtService, BrevoMailService...
├── config/
│   ├── jwt.php                   # TTL/grace/cookie + fail-fast JWT_SECRET
│   ├── cors.php                  # whitelist CORS_ORIGINS
│   └── services.php              # openrouter (chain model free)
├── database/migrations/          # 16 migrations
├── routes/
│   ├── api.php                   # 86 API routes (63 JWT + 23 công khai)
│   └── web.php                   # Health JSON (`/`), trang download (`/download`)
├── scripts/lint.php              # php -l toàn bộ (composer lint)
└── tests/
    ├── Feature/ApiSecurityTest.php   # 401/403/404/422
    ├── Feature/AuthCookieTest.php    # Cookie httpOnly, refresh, CSRF Origin
    └── Unit/EnsureRoleTest.php       # role 0/1/2
```

## Xác thực & phân quyền

1. Đăng nhập → nhận JWT (HS256, TTL **60 phút**) dưới 2 dạng:
   - **Cookie httpOnly** (`sweetlens_token`) — FE gửi kèm mọi request (`credentials: 'include'`), không đọc được bằng JS (chống XSS).
   - **Token trong body JSON** — dành cho client không phải trình duyệt.
2. `JwtMiddleware` nhận token từ **header `Authorization` hoặc cookie** (ưu tiên header).
3. Middleware load user từ DB, tự gán `id_admin`/`id_topic` vào request.
4. **Gia hạn sliding:** hết hạn → FE gọi `POST /api/refresh` (cookie/header) — token còn hạn hoặc hết hạn trong **7 ngày grace** sẽ được cấp mới; ngoài cửa sổ → 401 → đăng xuất.
5. **Chống CSRF:** request xác thực bằng cookie + phương thức ghi (POST/PUT/PATCH/DELETE) bắt buộc `Origin` nằm trong `CORS_ORIGINS` (request không có Origin — curl/server-to-server — được cho qua). Request có header `Authorization` không bị chặn Origin.
6. **Phân quyền** `EnsureRole`: **0 = khách**, **1 = staff**, **2 = admin** — vi phạm trả **403**; thiếu/sai token trả **401**.


## Caching

- **Driver:** file (`storage/framework/cache/data/`).
- **Dashboard:** TTL 3 phút (`count_users_*`, `sum_price_*`).
- **Doanh thu theo máy:** TTL 5 phút, key `_v{N}` (version-key).
- **Khung ảnh:** TTL 5 phút (kèm phân trang).
- **Chatbot:** TTL 10 phút theo `id_admin`.
- **Invalidation chủ động:** `POST /api/pays` tăng `revenue_ver_{id_admin}` / `report_ver_{id_admin}` / `report_ver_all` → báo cáo cập nhật ngay, không chờ TTL.


## Giấy phép

Dự án phát triển cho mục đích thương mại — **SweetLens**. Mọi quyền được bảo lưu.
