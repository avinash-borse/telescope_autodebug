<?php

namespace TelescopeAI\AutoDebug\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $provider;
    protected array $config;

    public function __construct()
    {
        $this->provider = config('autodebug.ai.provider', 'openai');
        $this->config = config("autodebug.ai.{$this->provider}", []);
    }

    /**
     * Send exception context to AI and get analysis + fix suggestions.
     *
     * @return array{analysis: string, suggested_fix: string, file_patches: array|null, confidence: int}
     */
    public function analyze(array $context): array
    {
        // Rate limiting check
        if (!$this->checkRateLimit()) {
            throw new \RuntimeException('AutoDebug AI rate limit exceeded. Max calls per hour: ' . config('autodebug.analysis.max_calls_per_hour'));
        }

        $prompt = $this->buildPrompt($context);

        $response = match ($this->provider) {
            'openai'    => $this->callOpenAI($prompt),
            'anthropic' => $this->callAnthropic($prompt),
            'google'    => $this->callGoogle($prompt),
            'ollama'    => $this->callOllama($prompt),
            default     => throw new \InvalidArgumentException("Unsupported AI provider: {$this->provider}"),
        };

        $this->incrementRateCounter();

        return $this->parseResponse($response);
    }

    /**
     * Get the current provider name and model.
     */
    public function getProviderInfo(): array
    {
        return [
            'provider' => $this->provider,
            'model'    => $this->config['model'] ?? 'unknown',
        ];
    }

    /**
     * Build a structured prompt for the AI.
     */
    protected function buildPrompt(array $context): string
    {
        $prompt = <<<PROMPT
You are an expert Laravel developer and debugger. Analyze the following exception and provide:

1. **Root Cause Analysis**: Explain why this error occurred.
2. **Suggested Fix**: Provide the exact code changes needed to fix this error.
3. **Confidence Score**: Rate your confidence in the fix (0-100).
4. **File Patches**: Provide specific file patches in the JSON format described below.

## Exception Details

- **Class**: {$context['exception_class']}
- **Message**: {$context['exception_message']}
- **File**: {$context['file']}
- **Line**: {$context['line']}
- **Occurrences**: {$context['occurrence_count']}

## Stack Trace
```
{$context['stacktrace']}
```

PROMPT;

        // Add source code context
        if (!empty($context['source_code'])) {
            $prompt .= "\n## Source Code (around error line)\n```php\n{$context['source_code']['content']}\n```\n";
        }

        // Add related files
        if (!empty($context['related_files'])) {
            $prompt .= "\n## Related Files\n";
            foreach ($context['related_files'] as $related) {
                $prompt .= "\n### {$related['file']}\n```php\n{$related['content']}\n```\n";
            }
        }

        // Add request context
        if (!empty($context['request_context'])) {
            $prompt .= "\n## Request Context\n```json\n" . json_encode($context['request_context'], JSON_PRETTY_PRINT) . "\n```\n";
        }

        $prompt .= <<<PROMPT

## Required Response Format

Respond ONLY with valid JSON in this exact structure:
```json
{
    "analysis": "Detailed root cause analysis explaining why the error occurred",
    "suggested_fix": "Human-readable description of the fix",
    "confidence": 85,
    "file_patches": [
        {
            "file": "relative/path/to/file.php",
            "description": "What this change does",
            "search": "exact code to find and replace (multi-line ok)",
            "replace": "the replacement code (multi-line ok)"
        }
    ]
}
```

Important rules for file_patches:
- The "search" field must contain the EXACT existing code that needs to be replaced
- The "replace" field must contain the complete replacement code
- Only include patches for files that actually need changes
- If no code fix is possible, set file_patches to null and confidence to a low score
- Do NOT modify migration files, .env files, or config files
PROMPT;

        return $prompt;
    }

    /**
     * Call the OpenAI API.
     */
    protected function callOpenAI(string $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type'  => 'application/json',
        ])->timeout(60)->withOptions([
            'verify' => false,
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model'       => $this->config['model'],
            'max_tokens'  => $this->config['max_tokens'] ?? 4096,
            'temperature' => 0.2,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are an expert Laravel PHP debugging assistant. Always respond with valid JSON only, no markdown formatting around it.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown OpenAI error');
            throw new \RuntimeException("OpenAI API error: {$error}");
        }

        return $response->json('choices.0.message.content', '');
    }

    /**
     * Call the Anthropic (Claude) API.
     */
    protected function callAnthropic(string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->config['api_key'],
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ])->timeout(60)->withOptions([
            'verify' => false,
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->config['model'],
            'max_tokens' => $this->config['max_tokens'] ?? 4096,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'system' => 'You are an expert Laravel PHP debugging assistant. Always respond with valid JSON only, no markdown formatting around it.',
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown Anthropic error');
            throw new \RuntimeException("Anthropic API error: {$error}");
        }

        return $response->json('content.0.text', '');
    }

    /**
     * Call the Google Gemini API.
     */
    protected function callGoogle(string $prompt): string
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(60)->withOptions([
            'verify' => false,
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->config['model']}:generateContent?key={$this->config['api_key']}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => "System instruction: You are an expert Laravel PHP debugging assistant. Always respond with valid JSON only, no markdown formatting around it.\n\n" . $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => $this->config['max_tokens'] ?? 4096,
            ],
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown Google API error');
            throw new \RuntimeException("Google API error: {$error}");
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    /**
     * Call the local Ollama API.
     */
    protected function callOllama(string $prompt): string
    {
        $response = Http::timeout(120)->withOptions([
            'verify' => false,
        ])->post("{$this->config['base_url']}/api/generate", [
            'model'  => $this->config['model'],
            'prompt' => "System: You are an expert Laravel PHP debugging assistant. Always respond with valid JSON only, no markdown formatting around it.\n\n" . $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.2,
                'num_predict' => $this->config['max_tokens'] ?? 4096,
            ],
        ]);

        if ($response->failed()) {
            $error = $response->json('error', $response->reason() ?: 'Unknown Ollama error');
            throw new \RuntimeException("Ollama API error: {$error}");
        }

        return $response->json('response', '');
    }

    /**
     * Parse the AI response into a structured array.
     */
    protected function parseResponse(string $response): array
    {
        // Strip any markdown code fences the AI might still include
        $cleaned = preg_replace('/^```(?:json)?\s*\n?/m', '', $response);
        $cleaned = preg_replace('/\n?```\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('[AutoDebug] Failed to parse AI response as JSON', [
                'error'    => json_last_error_msg(),
                'response' => substr($response, 0, 500),
            ]);

            return [
                'analysis'      => $response,
                'suggested_fix' => 'Could not parse AI response into structured format.',
                'file_patches'  => null,
                'confidence'    => 10,
            ];
        }

        return [
            'analysis'      => $decoded['analysis'] ?? 'No analysis provided.',
            'suggested_fix' => $decoded['suggested_fix'] ?? 'No fix suggested.',
            'file_patches'  => $decoded['file_patches'] ?? null,
            'confidence'    => (int) ($decoded['confidence'] ?? 0),
        ];
    }

    /**
     * Check if we're within the rate limit.
     */
    protected function checkRateLimit(): bool
    {
        $maxCalls = config('autodebug.analysis.max_calls_per_hour', 10);
        $currentCount = Cache::get('autodebug:rate_count', 0);

        return $currentCount < $maxCalls;
    }

    /**
     * Increment the rate limit counter.
     */
    protected function incrementRateCounter(): void
    {
        $key = 'autodebug:rate_count';
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }
}
