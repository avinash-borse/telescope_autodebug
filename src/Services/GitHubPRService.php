<?php

namespace TelescopeAI\AutoDebug\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitHubPRService
{
    protected string $token;
    protected string $owner;
    protected string $repo;
    protected string $baseBranch;
    protected string $apiBase = 'https://api.github.com';

    public function __construct()
    {
        $this->token      = config('autodebug.github.token', '');
        $this->owner      = config('autodebug.github.owner', '');
        $this->repo       = config('autodebug.github.repo', '');
        $this->baseBranch = config('autodebug.github.base_branch', 'main');
    }

    /**
     * Create a full PR with branch, commit, and pull request.
     *
     * @param  string  $exceptionClass  Short class name for the branch name
     * @param  string  $title           PR title
     * @param  string  $body            PR description (markdown)
     * @param  array   $changes         Array of file changes from FixGenerator
     * @return array{branch: string, pr_url: string, pr_number: int}
     */
    public function createPR(string $exceptionClass, string $title, string $body, array $changes): array
    {
        if (empty($this->token) || empty($this->owner) || empty($this->repo)) {
            throw new \RuntimeException('GitHub configuration is incomplete. Check AUTODEBUG_GITHUB_TOKEN, OWNER, and REPO in .env');
        }

        // 1. Get the latest commit SHA of the base branch
        $baseSha = $this->getLatestCommitSha();

        // 2. Create a new branch
        $branchName = $this->generateBranchName($exceptionClass);
        $this->createBranch($branchName, $baseSha);

        // 3. Commit changes to the new branch
        $this->commitChanges($branchName, $changes, $title);

        // 4. Create the pull request
        $pr = $this->openPullRequest($branchName, $title, $body);

        // 5. Add labels
        $this->addLabels($pr['number'], ['auto-debug', 'ai-fix']);

        Log::info("[AutoDebug] PR created: {$pr['html_url']}");

        return [
            'branch'    => $branchName,
            'pr_url'    => $pr['html_url'],
            'pr_number' => $pr['number'],
        ];
    }

    /**
     * Get the latest commit SHA of the base branch.
     */
    protected function getLatestCommitSha(): string
    {
        $response = $this->apiGet("/repos/{$this->owner}/{$this->repo}/git/ref/heads/{$this->baseBranch}");

        return $response['object']['sha']
            ?? throw new \RuntimeException("Could not get SHA for branch: {$this->baseBranch}");
    }

    /**
     * Create a new branch from a commit SHA.
     */
    protected function createBranch(string $branchName, string $sha): void
    {
        $this->apiPost("/repos/{$this->owner}/{$this->repo}/git/refs", [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $sha,
        ]);
    }

    /**
     * Commit file changes to a branch using the GitHub Contents API.
     */
    protected function commitChanges(string $branch, array $changes, string $commitMessage): void
    {
        foreach ($changes as $change) {
            $filePath = $change['file'];
            $newContent = $change['new_content'];

            // Get current file SHA (needed for update)
            $currentFile = $this->apiGet(
                "/repos/{$this->owner}/{$this->repo}/contents/{$filePath}?ref={$branch}"
            );

            $this->apiPut("/repos/{$this->owner}/{$this->repo}/contents/{$filePath}", [
                'message' => "[AutoDebug] {$commitMessage}",
                'content' => base64_encode($newContent),
                'sha'     => $currentFile['sha'],
                'branch'  => $branch,
            ]);
        }
    }

    /**
     * Open a pull request.
     */
    protected function openPullRequest(string $branch, string $title, string $body): array
    {
        return $this->apiPost("/repos/{$this->owner}/{$this->repo}/pulls", [
            'title' => "[AutoDebug] {$title}",
            'body'  => $body,
            'head'  => $branch,
            'base'  => $this->baseBranch,
        ]);
    }

    /**
     * Add labels to a PR/issue.
     */
    protected function addLabels(int $prNumber, array $labels): void
    {
        try {
            $this->apiPost("/repos/{$this->owner}/{$this->repo}/issues/{$prNumber}/labels", [
                'labels' => $labels,
            ]);
        } catch (\Exception $e) {
            // Labels are non-critical, don't fail the whole flow
            Log::warning("[AutoDebug] Could not add labels to PR #{$prNumber}: {$e->getMessage()}");
        }
    }

    /**
     * Generate a clean branch name from the exception class.
     */
    protected function generateBranchName(string $exceptionClass): string
    {
        $sanitized = Str::slug(class_basename($exceptionClass));
        $hash = substr(md5($exceptionClass . now()->timestamp), 0, 6);

        return "autodebug/fix-{$sanitized}-{$hash}";
    }

    /**
     * Build the PR body with AI analysis details.
     */
    public static function buildPRBody(
        string $analysis,
        string $suggestedFix,
        int $confidence,
        string $exceptionClass,
        string $exceptionMessage,
        string $file,
        ?int $line,
        array $changes
    ): string {
        $filesChanged = collect($changes)->pluck('file')->implode("\n- ");

        return <<<MD
## 🔭 AutoDebug — AI-Generated Fix

> **⚠️ This PR was automatically generated by the AutoDebug system. Please review carefully before merging.**

### Exception Details

| Field | Value |
|-------|-------|
| **Class** | `{$exceptionClass}` |
| **Message** | {$exceptionMessage} |
| **File** | `{$file}` |
| **Line** | {$line} |
| **AI Confidence** | {$confidence}% |

### 🔍 Root Cause Analysis

{$analysis}

### 🛠️ Suggested Fix

{$suggestedFix}

### 📁 Files Changed

- {$filesChanged}

### ⚡ Review Checklist

- [ ] The root cause analysis is accurate
- [ ] The fix correctly addresses the issue
- [ ] No unintended side effects
- [ ] Tests pass after applying the fix
- [ ] Edge cases are handled

---

*Generated by [TelescopeAI AutoDebug](https://github.com/telescopeai/autodebug) • Confidence: {$confidence}%*
MD;
    }

    /* ------------------------------------------------------------------ */
    /*  HTTP Helpers                                                      */
    /* ------------------------------------------------------------------ */

    protected function apiGet(string $endpoint): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->get("{$this->apiBase}{$endpoint}");

        if ($response->failed()) {
            throw new \RuntimeException("GitHub API GET {$endpoint} failed: " . $response->body());
        }

        return $response->json();
    }

    protected function apiPost(string $endpoint, array $data): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->post("{$this->apiBase}{$endpoint}", $data);

        if ($response->failed()) {
            throw new \RuntimeException("GitHub API POST {$endpoint} failed: " . $response->body());
        }

        return $response->json();
    }

    protected function apiPut(string $endpoint, array $data): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->put("{$this->apiBase}{$endpoint}", $data);

        if ($response->failed()) {
            throw new \RuntimeException("GitHub API PUT {$endpoint} failed: " . $response->body());
        }

        return $response->json();
    }

    protected function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Accept'        => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }
}
