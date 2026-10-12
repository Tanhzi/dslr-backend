<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đồng bộ schema với DB Supabase đang chạy thật:
 * - users: thêm cột username/role/id_admin/id_topic/reset_token/reset_expires_at nếu chưa có
 *   (DB cũ chạy migration chỉ có name/email/password)
 * - event: chuyển background/logo từ BYTEA sang text nếu còn (code lưu path/URL Supabase)
 * Idempotent: chạy lại nhiều lần không sao; DB mới migrate từ đầu thì không làm gì.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'username')) {
                    $table->string('username')->nullable()->unique();
                }
                if (!Schema::hasColumn('users', 'role')) {
                    $table->smallInteger('role')->default(0);
                }
                if (!Schema::hasColumn('users', 'id_admin')) {
                    $table->unsignedBigInteger('id_admin')->nullable();
                }
                if (!Schema::hasColumn('users', 'id_topic')) {
                    $table->unsignedBigInteger('id_topic')->nullable();
                }
                if (!Schema::hasColumn('users', 'reset_token')) {
                    $table->string('reset_token')->nullable();
                }
                if (!Schema::hasColumn('users', 'reset_expires_at')) {
                    $table->timestamp('reset_expires_at')->nullable();
                }
            });

            // Bỏ cột 'name' kiểu skeleton Laravel nếu DB đã có cả name lẫn username
            if (Schema::hasColumn('users', 'name') && Schema::hasColumn('users', 'username')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn('name');
                });
            }
        }

        // event.background / event.logo: BYTEA -> text (chỉ Postgres;
        // sqlite/mysql không có information_schema → bỏ qua)
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['background', 'logo'] as $col) {
            $row = DB::selectOne(
                "select data_type from information_schema.columns
                 where table_schema = 'public' and table_name = 'event' and column_name = ?",
                [$col]
            );
            if ($row && $row->data_type === 'bytea') {
                DB::statement("alter table event alter column {$col} type text using {$col}::text");
            }
        }
    }

    public function down(): void
    {
        // Không hoàn tác: DB hiện tại do Supabase quản lý thủ công.
    }
};
