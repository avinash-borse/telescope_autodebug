<?php

namespace TelescopeAI\AutoDebug\Services;

use TelescopeAI\AutoDebug\Models\AutoDebugEntry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Send notifications about a processed exception.
     */
    public function notify(AutoDebugEntry $entry, string $event = 'analyzed'): void
    {
        $channels = config('autodebug.notifications.channels', ['database']);

        foreach ($channels as $channel) {
            $channel = trim($channel);

            try {
                match ($channel) {
                    'slack'    => $this->sendSlack($entry, $event),
                    'mail'     => $this->sendMail($entry, $event),
                    'database' => $this->logToDatabase($entry, $event),
                    default    => Log::warning("[AutoDebug] Unknown notification channel: {$channel}"),
                };
            } catch (\Exception $e) {
                Log::error("[AutoDebug] Failed to send {$channel} notification: {$e->getMessage()}");
            }
        }
    }

    /**
     * Send a Slack notification via webhook.
     */
    protected function sendSlack(AutoDebugEntry $entry, string $event): void
    {
        $webhookUrl = config('autodebug.notifications.slack.webhook_url');

        if (!$webhookUrl) {
            Log::warning('[AutoDebug] Slack webhook URL not configured');
            return;
        }

        $emoji = match ($event) {
            'analyzed'    => '🔍',
            'fix_created' => '🛠️',
            'pr_created'  => '🚀',
            'failed'      => '❌',
            default       => '🔭',
        };

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "{$emoji} AutoDebug: {$entry->short_class}",
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Status:*\n`{$entry->status}`",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Confidence:*\n{$entry->confidence_score}%",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*File:*\n`{$entry->file}:{$entry->line}`",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Occurrences:*\n{$entry->occurrence_count}",
                    ],
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Message:*\n```{$entry->exception_message}```",
                ],
            ],
        ];

        // Add PR link if available
        if ($entry->github_pr_url) {
            $blocks[] = [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => '📝 View Pull Request',
                        ],
                        'url' => $entry->github_pr_url,
                        'style' => 'primary',
                    ],
                ],
            ];
        }

        // Add AI analysis summary if available
        if ($entry->ai_analysis) {
            $summary = Str::limit($entry->ai_analysis, 500);
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*AI Analysis:*\n{$summary}",
                ],
            ];
        }

        Http::post($webhookUrl, [
            'channel' => config('autodebug.notifications.slack.channel', '#exceptions'),
            'blocks'  => $blocks,
        ]);
    }

    /**
     * Send an email notification.
     */
    protected function sendMail(AutoDebugEntry $entry, string $event): void
    {
        $recipients = config('autodebug.notifications.mail.to', []);

        if (empty($recipients) || (count($recipients) === 1 && empty($recipients[0]))) {
            return;
        }

        $subject = match ($event) {
            'pr_created' => "🚀 AutoDebug PR: {$entry->short_class}",
            'failed'     => "❌ AutoDebug Failed: {$entry->short_class}",
            default      => "🔭 AutoDebug: {$entry->short_class}",
        };

        $body = $this->buildEmailBody($entry);

        Mail::raw($body, function ($message) use ($recipients, $subject) {
            $message->to($recipients)
                ->subject($subject);
        });
    }

    /**
     * Log to database (for the dashboard).
     */
    protected function logToDatabase(AutoDebugEntry $entry, string $event): void
    {
        Log::channel('daily')->info("[AutoDebug] [{$event}] {$entry->exception_class}", [
            'file'       => $entry->file,
            'line'       => $entry->line,
            'confidence' => $entry->confidence_score,
            'status'     => $entry->status,
            'pr_url'     => $entry->github_pr_url,
        ]);
    }

    /**
     * Build a rich email body.
     */
    protected function buildEmailBody(AutoDebugEntry $entry): string
    {
        $body = "AutoDebug Exception Report\n";
        $body .= str_repeat('=', 40) . "\n\n";
        $body .= "Exception: {$entry->exception_class}\n";
        $body .= "Message: {$entry->exception_message}\n";
        $body .= "File: {$entry->file}:{$entry->line}\n";
        $body .= "Occurrences: {$entry->occurrence_count}\n";
        $body .= "Confidence: {$entry->confidence_score}%\n";
        $body .= "Status: {$entry->status}\n\n";

        if ($entry->ai_analysis) {
            $body .= "AI Analysis\n";
            $body .= str_repeat('-', 40) . "\n";
            $body .= $entry->ai_analysis . "\n\n";
        }

        if ($entry->ai_suggested_fix) {
            $body .= "Suggested Fix\n";
            $body .= str_repeat('-', 40) . "\n";
            $body .= $entry->ai_suggested_fix . "\n\n";
        }

        if ($entry->github_pr_url) {
            $body .= "Pull Request: {$entry->github_pr_url}\n\n";
        }

        return $body;
    }
}
