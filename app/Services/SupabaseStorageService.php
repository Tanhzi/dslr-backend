<?php

namespace App\Services;

use Supabase\SupabaseClient;

class SupabaseStorageService
{
    protected $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseClient(
            config('services.supabase.url'),
            config('services.supabase.key')
        );
    }

    public function upload(string $bucket, string $filePath, string $contents, string $mimeType = 'application/octet-stream')
    {
        return $this->supabase->storage->from($bucket)->upload($filePath, $contents, [
            'contentType' => $mimeType,
            'upsert' => false,
        ]);
    }

    public function getPublicUrl(string $bucket, string $filePath): string
    {
        return config('services.supabase.url') . '/storage/v1/object/public/' . $bucket . '/' . $filePath;
    }
}