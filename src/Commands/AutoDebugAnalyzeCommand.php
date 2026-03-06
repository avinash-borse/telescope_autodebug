<?php

namespace TelescopeAI\AutoDebug\Commands;

use TelescopeAI\AutoDebug\Models\AutoDebugEntry;
use TelescopeAI\AutoDebug\Services\AIService;
use TelescopeAI\AutoDebug\Services\ExceptionAnalyzer;
use TelescopeAI\AutoDebug\Services\FixGenerator;
use TelescopeAI\AutoDebug\Services\GitHubPRService;
use TelescopeAI\AutoDebug\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AutoDebugAnalyzeCommand extends Command
{
    protected $signature = 'autodebug:analyze
                            {--dry-run : Analyze without creating PRs}
                            {--batch= : Number of exceptions to process}
                            {--force : Bypass rate limiting}
                            {--uuid= : Analyze a specific Telescope entry UUID}';

    protected $description = 'Analyze Telescope exceptions with AI and generate auto-fix PRs';

    protected ExceptionAnalyzer $analyzer;
    protected AIService $aiService;
    protected FixGenerator $fixGenerator;
    protected GitHubPRService $githubService;
    protected NotificationService $notifier;

    public function __construct(
        ExceptionAnalyzer $analyzer,
        AIService $aiService,
        FixGenerator $fixGenerator,
        GitHubPRService $githubService,
        NotificationService $notifier,
    ) {
        parent::__construct();

        $this->analyzer      = $analyzer;
        $this->aiService     = $aiService;
        $this->fixGenerator  = $fixGenerator;
        $this->githubService = $githubService;
        $this->notifier      = $notifier;
    }

    public function handle(): int
    {
        if (!config('autodebug.enabled')) {
            $this->warn('AutoDebug is disabled. Set AUTODEBUG_ENABLED=true in .env');
            return self::SUCCESS;
        }

        $this->info('🔭 AutoDebug — Starting exception analysis...');
        $this->newLine();

        $batchSize = $this->option('batch') ?? config('autodebug.analysis.batch_size', 5);
        $dryRun = $this->option('dry-run') || config('autodebug.analysis.dry_run', false);

        if ($dryRun) {
            $this->warn('⚠️  Running in DRY RUN mode — no PRs will be created');
            $this->newLine();
        }

        // Fetch new exceptions from Telescope
        $exceptions = $this->analyzer->fetchNewExceptions($batchSize);

        if ($exceptions->isEmpty()) {
            $this->info('✅ No new exceptions found. All clear!');
            return self::SUCCESS;
        }

        $this->info("Found {$exceptions->count()} new exception(s) to analyze.");
        $this->newLine();

        $processed = 0;
        $prCreated = 0;
        $failed = 0;

        foreach ($exceptions as $exception) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📋 {$exception->class}");
            $this->comment("   {$exception->message}");
            $this->comment("   📁 {$exception->file}:{$exception->line}");
            $this->newLine();

            try {
                // Step 1: Process (dedup + create tracking entry)
                $entry = $this->analyzer->processException($exception);

                if (!$entry) {
                    $this->comment('   ⏭️  Already tracked — occurrence count incremented');
                    continue;
                }

                // Step 2: Build AI context
                $this->info('   🧠 Preparing context for AI analysis...');
                $context = $this->analyzer->buildAIContext($entry);

                // Step 3: Send to AI for analysis
                $entry->markAnalyzing();
                $this->info('   🤖 Sending to AI for analysis...');

                $providerInfo = $this->aiService->getProviderInfo();
                $this->comment("      Provider: {$providerInfo['provider']} ({$providerInfo['model']})");

                $result = $this->aiService->analyze($context);

                // Step 4: Store analysis
                $entry->storeAnalysis(
                    $result['analysis'],
                    $result['suggested_fix'],
                    $result['file_patches'],
                    $result['confidence'],
                    $providerInfo['provider'],
                    $providerInfo['model']
                );

                $this->renderAnalysis($result);

                // Step 5: Validate and apply patches
                if (!empty($result['file_patches'])) {
                    $this->info('   🔧 Validating patches...');
                    $validation = $this->fixGenerator->validatePatches($result['file_patches']);

                    $this->renderValidation($validation);

                    // Step 6: Create PR if conditions are met
                    if (
                        !$dryRun
                        && !empty($validation['applied'])
                        && $result['confidence'] >= config('autodebug.analysis.min_confidence_for_pr', 75)
                        && config('autodebug.github.enabled', true)
                    ) {
                        $this->info('   🚀 Creating GitHub PR...');
                        $changes = $this->fixGenerator->applyPatches($validation['applied']);

                        $prBody = GitHubPRService::buildPRBody(
                            $result['analysis'],
                            $result['suggested_fix'],
                            $result['confidence'],
                            $entry->exception_class,
                            $entry->exception_message,
                            $entry->file,
                            $entry->line,
                            $changes
                        );

                        $prTitle = "Fix {$entry->short_class}: " . Str::limit($entry->exception_message, 60);
                        $pr = $this->githubService->createPR($entry->exception_class, $prTitle, $prBody, $changes);

                        $entry->storePR($pr['branch'], $pr['pr_url'], $pr['pr_number']);

                        $this->newLine();
                        $this->info("   ✅ PR Created: {$pr['pr_url']}");

                        $this->notifier->notify($entry, 'pr_created');
                        $prCreated++;
                    } elseif ($dryRun && !empty($validation['applied'])) {
                        $this->warn('   🏜️  DRY RUN — PR would have been created');
                        $this->notifier->notify($entry, 'analyzed');
                    } else {
                        $reason = $result['confidence'] < config('autodebug.analysis.min_confidence_for_pr', 75)
                            ? "Confidence too low ({$result['confidence']}%)"
                            : 'No valid patches to apply';
                        $this->comment("   ℹ️  No PR created: {$reason}");
                        $this->notifier->notify($entry, 'analyzed');
                    }
                } else {
                    $this->comment('   ℹ️  No file patches suggested by AI');
                    $this->notifier->notify($entry, 'analyzed');
                }

                $processed++;

            } catch (\Exception $e) {
                $this->error("   ❌ Failed: {$e->getMessage()}");
                $failed++;

                if (isset($entry)) {
                    $entry->markFailed($e->getMessage());
                    $this->notifier->notify($entry, 'failed');
                }
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🔭 AutoDebug Summary");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Exceptions Found', $exceptions->count()],
                ['Processed', $processed],
                ['PRs Created', $prCreated],
                ['Failed', $failed],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Render AI analysis results to console.
     */
    protected function renderAnalysis(array $result): void
    {
        $this->newLine();

        // Confidence bar
        $score = $result['confidence'];
        $color = match (true) {
            $score >= 85 => 'green',
            $score >= 60 => 'yellow',
            default      => 'red',
        };
        $bar = str_repeat('█', intdiv($score, 5)) . str_repeat('░', 20 - intdiv($score, 5));
        $this->line("   <fg={$color}>   Confidence: [{$bar}] {$score}%</>");

        $this->newLine();
        $this->comment('   📊 Analysis:');
        foreach (explode("\n", wordwrap($result['analysis'], 70)) as $line) {
            $this->comment("      {$line}");
        }

        $this->newLine();
        $this->comment('   💡 Suggested Fix:');
        foreach (explode("\n", wordwrap($result['suggested_fix'], 70)) as $line) {
            $this->comment("      {$line}");
        }
    }

    /**
     * Render patch validation results to console.
     */
    protected function renderValidation(array $validation): void
    {
        if (!empty($validation['applied'])) {
            $this->info('      ✅ Valid patches:');
            foreach ($validation['applied'] as $patch) {
                $this->comment("         • {$patch['file']}: {$patch['description']}");
            }
        }

        if (!empty($validation['skipped'])) {
            $this->warn('      ⏭️  Skipped (protected):');
            foreach ($validation['skipped'] as $skip) {
                $this->comment("         • {$skip['file']}: {$skip['reason']}");
            }
        }

        if (!empty($validation['errors'])) {
            $this->error('      ❌ Invalid patches:');
            foreach ($validation['errors'] as $err) {
                $this->comment("         • {$err['file']}: {$err['reason']}");
            }
        }
    }
}
