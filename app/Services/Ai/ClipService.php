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

    /**
     * @param  array<int, string>  $candidateTags
     * @return array<int, array{tag: string, score: float}>
     */
    public function classifyTags(string $imagePath, array $candidateTags, float $threshold = 0.26, int $topK = 5): array
    {
        if ($candidateTags === []) {
            return [];
        }

        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/classify-tags", [
                'paths' => [$imagePath],
                'image_path' => $imagePath,
                'candidate_tags' => array_values($candidateTags),
                'threshold' => $threshold,
                'top_k' => $topK,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    $results = $json['results'] ?? null;
                    if (is_array($results)) {
                        // Find the entry for this image path (or first)
                        $entries = $results[$imagePath] ?? null;
                        if (! is_array($entries)) {
                            foreach ($results as $value) {
                                if (is_array($value)) {
                                    $entries = $value;
                                    break;
                                }
                            }
                        }
                        if (is_array($entries)) {
                            $out = [];
                            foreach ($entries as $entry) {
                                if (is_array($entry) && isset($entry['tag'])) {
                                    $out[] = [
                                        'tag' => (string) $entry['tag'],
                                        'score' => (float) ($entry['score'] ?? 0.0),
                                    ];
                                } elseif (is_string($entry)) {
                                    $out[] = ['tag' => $entry, 'score' => 0.0];
                                }
                            }

                            return $out;
                        }

                        // Fallback: direct tags list
                        foreach (['tags', 'matched_tags', 'matched', 'labels'] as $key) {
                            if (isset($json[$key]) && is_array($json[$key])) {
                                $out = [];
                                foreach ($json[$key] as $entry) {
                                    if (is_array($entry) && isset($entry['tag'])) {
                                        $out[] = ['tag' => (string) $entry['tag'], 'score' => (float) ($entry['score'] ?? 0.0)];
                                    } elseif (is_string($entry)) {
                                        $out[] = ['tag' => $entry, 'score' => 0.0];
                                    }
                                }

                                return $out;
                            }
                        }
                    }
                }
            }
        } catch (Throwable $throwable) {
            Log::warning('AI Worker classify-tags failed: '.$throwable->getMessage());
        }

        return [];
    }
}
