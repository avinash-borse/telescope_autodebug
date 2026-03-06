@extends('autodebug::layout')

@section('content')
    {{-- ── Breadcrumb & Header ──────────────────────────────── --}}
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('autodebug.dashboard') }}" class="text-muted text-sm" style="display:inline-flex; align-items:center; gap:0.3rem; margin-bottom:0.75rem;">
            ← Back to Dashboard
        </a>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
            <div>
                <h1 style="font-size:1.4rem; font-weight:700; letter-spacing:-0.02em; display:flex; align-items:center; gap:0.75rem;">
                    {{ $entry->short_class }}
                    <span class="status-badge {{ $entry->status }}">
                        <span class="status-dot"></span>
                        {{ str_replace('_', ' ', $entry->status) }}
                    </span>
                </h1>
                <p class="text-muted" style="margin-top:0.35rem; max-width:700px;">{{ $entry->exception_message }}</p>
            </div>
            <div class="action-group">
                @if(!in_array($entry->status, ['ignored']))
                    <form method="POST" action="{{ route('autodebug.reanalyze', $entry) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">🔄 Re-analyze</button>
                    </form>
                    <form method="POST" action="{{ route('autodebug.ignore', $entry) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">🚫 Ignore</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('autodebug.reanalyze', $entry) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">🔄 Un-ignore & Re-analyze</button>
                    </form>
                @endif
                @if($entry->github_pr_url)
                    <a href="{{ $entry->github_pr_url }}" target="_blank" class="btn btn-success btn-sm">📝 View PR ↗</a>
                @endif
            </div>
        </div>
    </div>

    <div class="detail-grid">
        {{-- ── Exception Info ───────────────────────────────── --}}
        <div class="card">
            <div class="card-header"><h2>📋 Exception Details</h2></div>
            <div class="card-body">
                <div class="detail-row"><span class="detail-label">Full Class</span><span class="detail-value mono" style="font-size:0.78rem;">{{ $entry->exception_class }}</span></div>
                <div class="detail-row"><span class="detail-label">File</span><span class="detail-value mono">{{ $entry->file ?? '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Line</span><span class="detail-value mono">{{ $entry->line ?? '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Occurrences</span><span class="detail-value">{{ $entry->occurrence_count }}</span></div>
                <div class="detail-row"><span class="detail-label">First Seen</span><span class="detail-value text-sm">{{ $entry->first_seen_at?->format('M j, Y g:i A') ?? '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Last Seen</span><span class="detail-value text-sm">{{ $entry->last_seen_at?->format('M j, Y g:i A') ?? '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Telescope UUID</span><span class="detail-value mono" style="font-size:0.72rem; color:var(--text-muted);">{{ $entry->telescope_entry_uuid }}</span></div>
            </div>
        </div>

        {{-- ── AI Analysis Summary ──────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h2>🤖 AI Analysis</h2>
                @if($entry->ai_provider)
                    <span class="text-muted text-sm">{{ ucfirst($entry->ai_provider) }} · {{ $entry->ai_model }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($entry->ai_analysis)
                    <div style="margin-bottom:1.25rem;">
                        <div class="section-title">Confidence Score</div>
                        <div style="display:flex; align-items:center; gap:1rem;">
                            <div style="flex:1; height:10px; background:var(--bg-primary); border-radius:5px; overflow:hidden;">
                                <div class="confidence-fill {{ $entry->confidence_score >= 85 ? 'high' : ($entry->confidence_score >= 60 ? 'medium' : 'low') }}" style="width:{{ $entry->confidence_score }}%; height:100%;"></div>
                            </div>
                            <span style="font-size:1.3rem; font-weight:700; font-family:'JetBrains Mono',monospace; min-width:55px;">{{ $entry->confidence_score }}%</span>
                        </div>
                        <span class="text-muted text-sm" style="margin-top:0.35rem; display:block;">
                            {{ $entry->confidence_label }} confidence
                            @if($entry->confidence_score >= config('autodebug.analysis.min_confidence_for_pr', 75))
                                — meets threshold for automatic PR
                            @else
                                — below threshold for automatic PR ({{ config('autodebug.analysis.min_confidence_for_pr', 75) }}% required)
                            @endif
                        </span>
                    </div>

                    @if($entry->github_pr_url)
                        <div style="margin-bottom:1.25rem; padding:0.85rem; background:var(--accent-green-glow); border:1px solid rgba(16,185,129,0.3); border-radius:var(--radius-sm);">
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem;">
                                <span style="font-size:1.1rem;">🚀</span>
                                <span style="font-weight:600; color:var(--accent-green);">Pull Request Created</span>
                            </div>
                            <a href="{{ $entry->github_pr_url }}" target="_blank" class="mono text-sm">{{ $entry->github_pr_url }}</a>
                            <div class="text-muted text-sm" style="margin-top:0.25rem;">Branch: <span class="mono">{{ $entry->github_branch }}</span></div>
                        </div>
                    @endif
                @else
                    <div class="empty-state" style="padding:2rem;">
                        <div class="icon">🧠</div>
                        <p>No AI analysis yet.</p>
                        <p class="text-sm text-muted" style="margin-top:0.25rem;">This exception is awaiting analysis.</p>
                    </div>
                @endif

                @if($entry->error_message && $entry->status === 'failed')
                    <div style="margin-top:1rem; padding:0.85rem; background:var(--accent-red-glow); border:1px solid rgba(239,68,68,0.3); border-radius:var(--radius-sm);">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem;">
                            <span style="font-size:1rem;">❌</span>
                            <span style="font-weight:600; color:var(--accent-red);">Analysis Failed</span>
                        </div>
                        <span class="text-sm" style="color:var(--accent-red);">{{ $entry->error_message }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Root Cause Analysis ──────────────────────────── --}}
        @if($entry->ai_analysis)
            <div class="card full-width">
                <div class="card-header"><h2>🔍 Root Cause Analysis</h2></div>
                <div class="card-body">
                    <div style="white-space:pre-wrap; line-height:1.7; color:var(--text-primary);">{{ $entry->ai_analysis }}</div>
                </div>
            </div>
        @endif

        {{-- ── Suggested Fix ────────────────────────────────── --}}
        @if($entry->ai_suggested_fix)
            <div class="card full-width">
                <div class="card-header"><h2>💡 Suggested Fix</h2></div>
                <div class="card-body">
                    <div style="white-space:pre-wrap; line-height:1.7; color:var(--text-primary);">{{ $entry->ai_suggested_fix }}</div>
                </div>
            </div>
        @endif

        {{-- ── File Patches ─────────────────────────────────── --}}
        @if($entry->ai_file_patches)
            <div class="card full-width">
                <div class="card-header">
                    <h2>📝 File Patches</h2>
                    <span class="text-muted text-sm">{{ count($entry->ai_file_patches) }} file(s)</span>
                </div>
                <div class="card-body" style="display:flex; flex-direction:column; gap:1.25rem;">
                    @foreach($entry->ai_file_patches as $patch)
                        <div style="border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden;">
                            <div style="padding:0.65rem 1rem; background:var(--bg-primary); border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                                <span class="mono" style="font-weight:500;">📁 {{ $patch['file'] ?? 'unknown' }}</span>
                                <span class="text-muted text-sm">{{ $patch['description'] ?? '' }}</span>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; min-height:0;">
                                <div style="padding:0.85rem; border-right:1px solid var(--border);">
                                    <div class="text-sm text-muted" style="margin-bottom:0.5rem; font-weight:600;"><span style="color:var(--accent-red);">— Remove</span></div>
                                    <pre class="code-block" style="margin:0; background:rgba(239,68,68,0.05); border-color:rgba(239,68,68,0.2);">{{ $patch['search'] ?? '' }}</pre>
                                </div>
                                <div style="padding:0.85rem;">
                                    <div class="text-sm text-muted" style="margin-bottom:0.5rem; font-weight:600;"><span style="color:var(--accent-green);">+ Add</span></div>
                                    <pre class="code-block" style="margin:0; background:rgba(16,185,129,0.05); border-color:rgba(16,185,129,0.2);">{{ $patch['replace'] ?? '' }}</pre>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── Stack Trace ──────────────────────────────────── --}}
        @if($entry->stacktrace)
            <div class="card full-width">
                <div class="card-header"><h2>📚 Stack Trace</h2></div>
                <div class="card-body"><pre class="code-block">{{ $entry->stacktrace }}</pre></div>
            </div>
        @endif

        {{-- ── Request Context ──────────────────────────────── --}}
        @if($entry->request_context)
            <div class="card full-width">
                <div class="card-header"><h2>🌐 Request Context</h2></div>
                <div class="card-body"><pre class="code-block">{{ json_encode($entry->request_context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
            </div>
        @endif
    </div>
@endsection
