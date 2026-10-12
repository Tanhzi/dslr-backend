<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pays - composite index cho revenue/report queries
        Schema::table('pays', function (Blueprint $table) {
            $table->index(['id_admin', 'date'], 'pays_admin_date_idx');
        });

        // template - composite index cho frames-client
        Schema::table('template', function (Blueprint $table) {
            $table->index(['id_admin', 'id_topic', 'cuts'], 'template_admin_topic_cuts_idx');
        });

        // event
        Schema::table('event', function (Blueprint $table) {
            $table->index('id_admin', 'event_admin_idx');
        });

        // users
        Schema::table('users', function (Blueprint $table) {
            $table->index(['id_admin', 'role'], 'users_admin_role_idx');
        });

        // stickers
        Schema::table('stickers', function (Blueprint $table) {
            $table->index('id_admin', 'stickers_admin_idx');
        });

        // content_chat
        Schema::table('content_chat', function (Blueprint $table) {
            $table->index('id_admin', 'content_chat_admin_idx');
        });

        // ai_topics
        Schema::table('ai_topics', function (Blueprint $table) {
            $table->index('id_admin', 'ai_topics_admin_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pays', function (Blueprint $table) {
            $table->dropIndex('pays_admin_date_idx');
        });
        Schema::table('template', function (Blueprint $table) {
            $table->dropIndex('template_admin_topic_cuts_idx');
        });
        Schema::table('event', function (Blueprint $table) {
            $table->dropIndex('event_admin_idx');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_admin_role_idx');
        });
        Schema::table('stickers', function (Blueprint $table) {
            $table->dropIndex('stickers_admin_idx');
        });
        Schema::table('content_chat', function (Blueprint $table) {
            $table->dropIndex('content_chat_admin_idx');
        });
        Schema::table('ai_topics', function (Blueprint $table) {
            $table->dropIndex('ai_topics_admin_idx');
        });
    }
};
