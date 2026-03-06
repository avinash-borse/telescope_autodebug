<?php

namespace TelescopeAI\AutoDebug\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AutoDebugEntry extends Model
{
    protected $fillable = [
        'telescope_entry_uuid',
        'exception_hash',
        'exception_class',
        'exception_message',
        'file',
        'line',
        'stacktrace',
        'request_context',
        'ai_analysis',
        'ai_suggested_fix',
        'ai_file_patches',
        'confidence_score',
        'ai_provider',
        'ai_model',
        'status',
        'github_branch',
        'github_pr_url',
        'github_pr_number',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'error_message',
    ];

    protected $casts = [
        'request_context' => 'array',
        'ai_file_patches' => 'array',
        'confidence_score' => 'integer',
        'occurrence_count' => 'integer',
        'first_seen_at'    => 'datetime',
        'last_seen_at'     => 'datetime',
    ];

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                            */
    /* ------------------------------------------------------------------ */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAnalyzed($query)
    {
        return $query->where('status', 'analyzed');
    }

    public function scopeWithFix($query)
    {
        return $query->whereIn('status', ['fix_generated', 'pr_created', 'pr_merged']);
    }

    public function scopeHighConfidence($query, int $threshold = null)
    {
        $threshold = $threshold ?? config('autodebug.analysis.min_confidence_for_pr', 75);
        return $query->where('confidence_score', '>=', $threshold);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /* ------------------------------------------------------------------ */
    /*  Accessors                                                         */
    /* ------------------------------------------------------------------ */

    protected function shortClass(): Attribute
    {
        return Attribute::get(fn () => class_basename($this->exception_class));
    }

    protected function confidenceLabel(): Attribute
    {
        return Attribute::get(function () {
            return match (true) {
                $this->confidence_score >= 85 => 'High',
                $this->confidence_score >= 60 => 'Medium',
                $this->confidence_score >= 30 => 'Low',
                default => 'Very Low',
            };
        });
    }

    protected function statusColor(): Attribute
    {
        return Attribute::get(fn () => match ($this->status) {
            'pending'       => 'yellow',
            'analyzing'     => 'blue',
            'analyzed'      => 'indigo',
            'fix_generated' => 'purple',
            'pr_created'    => 'green',
            'pr_merged'     => 'emerald',
            'ignored'       => 'gray',
            'failed'        => 'red',
            default         => 'gray',
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Methods                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Generate a deterministic hash for deduplication.
     */
    public static function generateHash(string $class, string $file, int $line): string
    {
        return hash('sha256', "{$class}|{$file}|{$line}");
    }

    /**
     * Check if this exception has already been analyzed.
     */
    public static function alreadyTracked(string $hash): bool
    {
        return static::where('exception_hash', $hash)
            ->whereNotIn('status', ['failed'])
            ->exists();
    }

    /**
     * Increment the occurrence count for a recurring exception.
     */
    public static function incrementOccurrence(string $hash): void
    {
        static::where('exception_hash', $hash)->update([
            'occurrence_count' => \DB::raw('occurrence_count + 1'),
            'last_seen_at'     => now(),
        ]);
    }

    /**
     * Mark this entry as currently being analyzed.
     */
    public function markAnalyzing(): void
    {
        $this->update(['status' => 'analyzing']);
    }

    /**
     * Store AI analysis results.
     */
    public function storeAnalysis(
        string $analysis,
        string $suggestedFix,
        ?array $filePatches,
        int $confidence,
        string $provider,
        string $model
    ): void {
        $this->update([
            'ai_analysis'      => $analysis,
            'ai_suggested_fix' => $suggestedFix,
            'ai_file_patches'  => $filePatches,
            'confidence_score' => $confidence,
            'ai_provider'      => $provider,
            'ai_model'         => $model,
            'status'           => $filePatches ? 'fix_generated' : 'analyzed',
        ]);
    }

    /**
     * Store GitHub PR information.
     */
    public function storePR(string $branch, string $prUrl, int $prNumber): void
    {
        $this->update([
            'github_branch'    => $branch,
            'github_pr_url'    => $prUrl,
            'github_pr_number' => $prNumber,
            'status'           => 'pr_created',
        ]);
    }

    /**
     * Mark as failed with an error message.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $error,
        ]);
    }
}
