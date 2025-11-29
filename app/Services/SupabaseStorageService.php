<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

class SupabaseStorageService
{
    protected $client;
    protected $url;
    protected $serviceRoleKey;

    public function __construct()
    {
        $this->url = config('services.supabase.url');
        $this->serviceRoleKey = config('services.supabase.key');

        $this->client = new Client([
            'base_uri' => rtrim($this->url, '/') . '/storage/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->serviceRoleKey,
                'apikey' => $this->serviceRoleKey,
                'Content-Type' => 'image/*',
            ],
        ]);
    }

    public function upload(string $bucket, string $path, string $fileContent): string
    {
        $this->client->post("object/{$bucket}/{$path}", [
            'body' => $fileContent,
        ]);

        // Trả về URL public
        return "{$this->url}/storage/v1/object/public/{$bucket}/{$path}";
    }

    public function delete(string $bucket, string $path): void
    {
        $this->client->delete("object/{$bucket}/{$path}");
    }
}