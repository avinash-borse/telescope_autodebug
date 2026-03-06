<?php

namespace TelescopeAI\AutoDebug\Services;

use Illuminate\Support\Facades\Log;

class FixGenerator
{
    protected array $protectedPaths;

    public function __construct()
    {
        $this->protectedPaths = config('autodebug.safety.protected_paths', []);
    }

    /**
     * Validate and apply file patches from AI analysis.
     *
     * @param  array  $patches  Array of patch objects from AI
     * @return array{applied: array, skipped: array, errors: array}
     */
    public function validatePatches(array $patches): array
    {
        $results = [
            'applied' => [],
            'skipped' => [],
            'errors'  => [],
        ];

        foreach ($patches as $patch) {
            $file = $patch['file'] ?? null;
            $search = $patch['search'] ?? null;
            $replace = $patch['replace'] ?? null;
            $description = $patch['description'] ?? 'No description';

            if (!$file || !$search || $replace === null) {
                $results['errors'][] = [
                    'file'   => $file ?? 'unknown',
                    'reason' => 'Missing required fields (file, search, replace)',
                ];
                continue;
            }

            // Safety check: is the file protected?
            if ($this->isProtected($file)) {
                $results['skipped'][] = [
                    'file'   => $file,
                    'reason' => 'File is in the protected paths list',
                ];
                Log::info("[AutoDebug] Skipped protected file: {$file}");
                continue;
            }

            // Check file exists
            $fullPath = base_path($file);
            if (!file_exists($fullPath)) {
                $results['errors'][] = [
                    'file'   => $file,
                    'reason' => 'File does not exist',
                ];
                continue;
            }

            // Validate the search string exists in the file
            $content = file_get_contents($fullPath);
            if (strpos($content, $search) === false) {
                // Try normalizing line endings
                $normalizedContent = str_replace("\r\n", "\n", $content);
                $normalizedSearch = str_replace("\r\n", "\n", $search);

                if (strpos($normalizedContent, $normalizedSearch) === false) {
                    $results['errors'][] = [
                        'file'   => $file,
                        'reason' => 'Search string not found in file — the AI-generated patch may be inaccurate',
                    ];
                    continue;
                }
            }

            $results['applied'][] = [
                'file'        => $file,
                'description' => $description,
                'search'      => $search,
                'replace'     => $replace,
            ];
        }

        return $results;
    }

    /**
     * Apply validated patches to files.
     * Returns an array of changes for commit.
     *
     * @param  array  $validatedPatches  The 'applied' array from validatePatches()
     * @return array  Applied changes with before/after content
     */
    public function applyPatches(array $validatedPatches): array
    {
        $changes = [];

        foreach ($validatedPatches as $patch) {
            $fullPath = base_path($patch['file']);
            $originalContent = file_get_contents($fullPath);

            // Perform the replacement
            $newContent = str_replace($patch['search'], $patch['replace'], $originalContent);

            if ($newContent === $originalContent) {
                Log::warning("[AutoDebug] Patch had no effect on: {$patch['file']}");
                continue;
            }

            // Do NOT write to disk — we'll push via GitHub API
            $changes[] = [
                'file'             => $patch['file'],
                'description'      => $patch['description'],
                'original_content' => $originalContent,
                'new_content'      => $newContent,
            ];
        }

        return $changes;
    }

    /**
     * Check if a file path is in the protected list.
     */
    protected function isProtected(string $file): bool
    {
        foreach ($this->protectedPaths as $protectedPath) {
            if (str_starts_with($file, $protectedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a unified diff for display purposes.
     */
    public function generateDiff(string $file, string $search, string $replace): string
    {
        $searchLines = explode("\n", $search);
        $replaceLines = explode("\n", $replace);

        $diff = "--- a/{$file}\n+++ b/{$file}\n";

        foreach ($searchLines as $line) {
            $diff .= "- {$line}\n";
        }

        foreach ($replaceLines as $line) {
            $diff .= "+ {$line}\n";
        }

        return $diff;
    }
}
