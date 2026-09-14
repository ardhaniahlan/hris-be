<?php

namespace App\Http\Services\Concerns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

trait AnalyzesWithGemini
{
    protected function callGeminiVision(string $imagePath, string $mimeType, string $prompt, string $context): ?array
    {
        if (!file_exists($imagePath)) {
            Log::error("Gemini [{$context}]: file gak ditemukan di {$imagePath}");
            return null;
        }

        $base64Image = base64_encode(file_get_contents($imagePath));

        try {
            $response = Http::timeout(30)
                ->post($this->apiUrl . '?key=' . $this->apiKey, [
                    'contents' => [[
                        'parts' => [
                            ['text' => $prompt],
                            ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]],
                        ],
                    ]],
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error("Gemini API Error [{$context}]: " . $response->body());
                if ($response->status() === 429) {
                    throw new \Exception('AI_QUOTA_EXCEEDED');
                }

                return null;
            }

            $result = $response->json();
            $finishReason = $result['candidates'][0]['finishReason'] ?? null;

            if (in_array($finishReason, ['SAFETY', 'PROHIBITED_CONTENT'], true)) {
                Log::warning("Gemini blocked [{$context}]: " . json_encode($result));
                return null;
            }

            $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if ($textResponse === null) {
                Log::error("Gemini no text in response [{$context}]: " . json_encode($result));
                return null;
            }

            $textResponse = trim(str_replace(['```json', '```'], '', $textResponse));
            $decoded = json_decode($textResponse, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                Log::error("Gemini JSON Parse Error [{$context}]: " . $textResponse);
                return null;
            }

            return $decoded;

        } catch (Exception $e) {
            Log::error("Gemini Exception [{$context}]: " . $e->getMessage());
            return null;
        }
    }
}