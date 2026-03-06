<?php

namespace TelescopeAI\AutoDebug\Services;

use TelescopeAI\AutoDebug\Models\AutoDebugEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExceptionAnalyzer
{
    /**
     * Fetch unprocessed exception entries from Telescope.
     *
     * @return \Illuminate\Support\Collection
     */
    public function fetchNewExceptions(int $batchSize = 5): \Illuminate\Support\Collection
    {
        $ignoredExceptions = config('autodebug.safety.ignored_exceptions', []);

        return DB::table('telescope_entries')
            ->where('type', 'exception')
            ->whereNotIn('uuid', function ($query) {
                $query->select('telescope_entry_uuid')
                    ->from('auto_debug_entries');
            })
            ->orderByDesc('created_at')
            ->limit($batchSize)
            ->get()
            ->map(function ($entry) {
                $content = json_decode($entry->content, true);
                return (object) [
                    'uuid'      => $entry->uuid,
                    'content'   => $content,
                    'class'     => $content['class'] ?? 'Unknown',
                    'message'   => $content['message'] ?? '',
                    'file'      => $content['file'] ?? null,
                    'line'      => $content['line'] ?? null,
                    'trace'     => $content['trace'] ?? [],
                    'created_at' => $entry->created_at,
                ];
            })
            ->filter(function ($entry) use ($ignoredExceptions) {
                return !in_array($entry->class, $ignoredExceptions);
            });
    }

    /**
     * Process a single Telescope exception entry.
     * Returns null if already tracked (deduplication).
     */
    public function processException(object $telescopeEntry): ?AutoDebugEntry
    {
        $hash = AutoDebugEntry::generateHash(
            $telescopeEntry->class,
            $telescopeEntry->file ?? '',
            $telescopeEntry->line ?? 0
        );

        // Deduplication: if we've already analyzed this exact exception location, just bump count
        if (AutoDebugEntry::alreadyTracked($hash)) {
            AutoDebugEntry::incrementOccurrence($hash);
            Log::info("[AutoDebug] Duplicate exception skipped: {$telescopeEntry->class} at {$telescopeEntry->file}:{$telescopeEntry->line}");
            return null;
        }

        // Create a new tracking entry
        return AutoDebugEntry::create([
            'telescope_entry_uuid' => $telescopeEntry->uuid,
            'exception_hash'       => $hash,
            'exception_class'      => $telescopeEntry->class,
            'exception_message'    => $telescopeEntry->message,
            'file'                 => $telescopeEntry->file,
            'line'                 => $telescopeEntry->line,
            'stacktrace'           => $this->formatStacktrace($telescopeEntry->trace),
            'request_context'      => $this->extractRequestContext($telescopeEntry->content),
            'first_seen_at'        => $telescopeEntry->created_at,
            'last_seen_at'         => $telescopeEntry->created_at,
            'status'               => 'pending',
        ]);
    }

    /**
     * Build a structured context payload for the AI service.
     */
    public function buildAIContext(AutoDebugEntry $entry): array
    {
        $context = [
            'exception_class'   => $entry->exception_class,
            'exception_message' => $entry->exception_message,
            'file'              => $entry->file,
            'line'              => $entry->line,
            'stacktrace'        => $entry->stacktrace,
            'occurrence_count'  => $entry->occurrence_count,
            'request_context'   => $entry->request_context,
        ];

        // Include surrounding source code if the file exists
        if ($entry->file && file_exists(base_path($entry->file))) {
            $context['source_code'] = $this->extractSourceContext(
                base_path($entry->file),
                $entry->line,
                config('autodebug.analysis.context_lines', 20)
            );
        }

        // Include related file contents from the stack trace
        $context['related_files'] = $this->extractRelatedFiles($entry->stacktrace);

        return $context;
    }

    /**
     * Format the stack trace into a readable string.
     */
    protected function formatStacktrace(array $trace): string
    {
        $formatted = [];

        foreach (array_slice($trace, 0, 15) as $i => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? '?';
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';
            $call = $class ? "{$class}::{$function}" : $function;

            $formatted[] = "#{$i} {$file}:{$line} → {$call}()";
        }

        return implode("\n", $formatted);
    }

    /**
     * Extract relevant request context from Telescope entry content.
     */
    protected function extractRequestContext(array $content): array
    {
        return array_filter([
            'url'     => $content['url'] ?? null,
            'method'  => $content['method'] ?? null,
            'headers' => $content['headers'] ?? null,
            'payload' => $content['payload'] ?? null,
            'user_id' => $content['user_id'] ?? null,
        ]);
    }

    /**
     * Extract source code lines around the error line.
     */
    protected function extractSourceContext(string $filePath, int $errorLine, int $contextLines): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        $start = max(0, $errorLine - $contextLines - 1);
        $end = min(count($lines), $errorLine + $contextLines);

        $result = [];
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $marker = ($lineNum === $errorLine) ? ' >>> ' : '     ';
            $result[] = sprintf('%s%4d | %s', $marker, $lineNum, $lines[$i]);
        }

        return [
            'file'    => $filePath,
            'content' => implode("\n", $result),
        ];
    }

    /**
     * Extract source code from related files in the stack trace.
     */
    protected function extractRelatedFiles(string $stacktrace): array
    {
        $relatedFiles = [];
        $lines = explode("\n", $stacktrace);
        $seen = [];

        foreach (array_slice($lines, 0, 5) as $line) {
            if (preg_match('/^#\d+\s+(.+?):(\d+)/', $line, $matches)) {
                $file = $matches[1];
                $lineNum = (int) $matches[2];

                // Only include app files, not vendor
                if (str_contains($file, 'vendor/') || isset($seen[$file])) {
                    continue;
                }

                $seen[$file] = true;
                $fullPath = base_path($file);

                if (file_exists($fullPath)) {
                    $relatedFiles[] = $this->extractSourceContext($fullPath, $lineNum, 10);
                }
            }
        }

        return $relatedFiles;
    }
}
