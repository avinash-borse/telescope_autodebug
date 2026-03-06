<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auto_debug_entries', function (Blueprint $table) {
            $table->id();

            // Link to Telescope entry
            $table->string('telescope_entry_uuid')->index();
            $table->string('exception_hash', 64)->index();

            // Exception details
            $table->string('exception_class');
            $table->text('exception_message');
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->longText('stacktrace')->nullable();
            $table->json('request_context')->nullable();

            // AI analysis results
            $table->text('ai_analysis')->nullable();
            $table->text('ai_suggested_fix')->nullable();
            $table->json('ai_file_patches')->nullable();
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();

            // Fix status
            $table->enum('status', [
                'pending',       // Awaiting analysis
                'analyzing',     // Currently being analyzed by AI
                'analyzed',      // Analysis complete, awaiting action
                'fix_generated', // Fix code has been generated
                'pr_created',    // Pull request has been created
                'pr_merged',     // Pull request has been merged
                'ignored',       // Manually ignored by team
                'failed',        // Analysis or fix generation failed
            ])->default('pending');

            // GitHub PR tracking
            $table->string('github_branch')->nullable();
            $table->string('github_pr_url')->nullable();
            $table->unsignedInteger('github_pr_number')->nullable();

            // Occurrence tracking
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // Error tracking
            $table->text('error_message')->nullable();

            $table->timestamps();

            // Prevent duplicate analysis
            $table->unique('telescope_entry_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_debug_entries');
    }
};
