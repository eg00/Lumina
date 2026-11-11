<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClipService
{
    private readonly string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.ai_worker.url', 'http://ai-worker:8000');
    }

    /**
     * @return array<int, float>
     */
    public function embedText(string $text): array
    {
        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/embed-text", [
                'text' => $text,
            ]);

            if ($response->successful()) {
                /** @var array<int, float> */
                return $response->json('embedding', []);
            }
        } catch (Throwable $throwable) {
            Log::warning('AI Worker embed-text failed: '.$throwable->getMessage());
        }

        return [];
    }

    /**
     * @param  array<int, string>  $imagePaths
     * @return array<string, array<int, float>>
     */
    public function embedBatch(array $imagePaths): array
    {
        if ($imagePaths === []) {
            return [];
        }

        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/embed-images", [
                'paths' => array_values($imagePaths),
            ]);

            if ($response->successful()) {
                /** @var array<string, array<int, float>> */
                return $response->json('results', []);
            }
        } catch (Throwable $throwable) {
            Log::warning('AI Worker embed-images failed: '.$throwable->getMessage());
        }

        return [];
    }
}
