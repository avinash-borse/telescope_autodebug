@extends('autodebug::layout')

@section('content')
    {{-- ── Page Header ──────────────────────────────────────── --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.5rem; font-weight:700; letter-spacing:-0.02em;">Exception Dashboard</h1>
            <p class="text-muted text-sm" style="margin-top:0.25rem;">AI-powered exception analysis & automatic fix generation</p>
        </div>
        <div class="action-group">
            <button class="btn btn-secondary btn-sm" onclick="refreshStats()" id="refreshBtn">↻ Refresh</button>
        </div>
    </div>

    {{-- ── Stats Grid ───────────────────────────────────────── --}}
    <div class="stats-grid">
        <div class="stat-card blue">
            <span class="stat-label">Total Exceptions</span>
            <span class="stat-value" id="stat-total">{{ $stats['total'] }}</span>
        </div>
        <div class="stat-card amber">
            <span class="stat-label">Pending Analysis</span>
            <span class="stat-value" id="stat-pending">{{ $stats['pending'] }}</span>
        </div>
        <div class="stat-card purple">
            <span class="stat-label">Analyzed</span>
            <span class="stat-value" id="stat-analyzed">{{ $stats['analyzed'] }}</span>
        </div>
        <div class="stat-card green">
            <span class="stat-label">PRs Created</span>
            <span class="stat-value" id="stat-prs">{{ $stats['prs_created'] }}</span>
        </div>
        <div class="stat-card cyan">
            <span class="stat-label">Avg Confidence</span>
            <span class="stat-value" id="stat-confidence">{{ $stats['avg_confidence'] }}%</span>
        </div>
        <div class="stat-card red">
            <span class="stat-label">Failed</span>
            <span class="stat-value" id="stat-failed">{{ $stats['failed'] }}</span>
        </div>
    </div>

    {{-- ── Filters ──────────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-body" style="padding:1rem 1.5rem;">
            <form method="GET" action="{{ route('autodebug.dashboard') }}" class="filter-bar">
                <input type="text" name="search" class="form-input" placeholder="Search exceptions, files..." value="{{ request('search') }}" style="max-width:320px;" />
                <select name="status" class="form-input" style="max-width:180px;">
                    <option value="">All Statuses</option>
                    @foreach(['pending','analyzing','analyzed','fix_generated','pr_created','pr_merged','ignored','failed'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $s)) }}
                        </option>
                    @endforeach
                </select>
                <select name="min_confidence" class="form-input" style="max-width:180px;">
                    <option value="">Any Confidence</option>
                    <option value="85" {{ request('min_confidence') == '85' ? 'selected' : '' }}>High (85%+)</option>
                    <option value="60" {{ request('min_confidence') == '60' ? 'selected' : '' }}>Medium (60%+)</option>
                    <option value="30" {{ request('min_confidence') == '30' ? 'selected' : '' }}>Low (30%+)</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                @if(request()->hasAny(['search', 'status', 'min_confidence']))
                    <a href="{{ route('autodebug.dashboard') }}" class="btn btn-secondary btn-sm">Clear</a>
                @endif
            </form>
        </div>
    </div>

    {{-- ── Entries Table ────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <h2>Exceptions</h2>
            <span class="text-muted text-sm">{{ $entries->total() }} total</span>
        </div>

        @if($entries->isEmpty())
            <div class="empty-state">
                <div class="icon">🔭</div>
                <p style="font-size:1.05rem; font-weight:500; margin-bottom:0.5rem;">No exceptions found</p>
                <p class="text-sm">When Telescope captures exceptions, they'll appear here for AI analysis.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Exception</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Confidence</th>
                            <th>Occurrences</th>
                            <th>Last Seen</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                            <tr>
                                <td style="max-width:280px;">
                                    <a href="{{ route('autodebug.show', $entry) }}" style="font-weight:600;">
                                        {{ $entry->short_class }}
                                    </a>
                                    <div class="text-muted text-sm truncate" style="max-width:280px;" title="{{ $entry->exception_message }}">
                                        {{ Str::limit($entry->exception_message, 60) }}
                                    </div>
                                </td>
                                <td>
                                    <span class="mono text-muted">
                                        {{ $entry->file ? basename($entry->file) : '—' }}:{{ $entry->line }}
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge {{ $entry->status }}">
                                        <span class="status-dot"></span>
                                        {{ str_replace('_', ' ', $entry->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($entry->confidence_score > 0)
                                        <div class="confidence-bar">
                                            <div class="confidence-track">
                                                <div class="confidence-fill {{ $entry->confidence_score >= 85 ? 'high' : ($entry->confidence_score >= 60 ? 'medium' : 'low') }}" style="width: {{ $entry->confidence_score }}%"></div>
                                            </div>
                                            <span class="confidence-text">{{ $entry->confidence_score }}%</span>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="mono">{{ $entry->occurrence_count }}</span></td>
                                <td><span class="text-muted text-sm">{{ $entry->last_seen_at?->diffForHumans() ?? $entry->created_at->diffForHumans() }}</span></td>
                                <td>
                                    <div class="action-group">
                                        <a href="{{ route('autodebug.show', $entry) }}" class="btn btn-secondary btn-sm">View</a>
                                        @if($entry->github_pr_url)
                                            <a href="{{ $entry->github_pr_url }}" target="_blank" class="btn btn-success btn-sm">PR ↗</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($entries->hasPages())
                <div class="pagination-wrap">
                    @foreach($entries->links()->elements as $element)
                        @if(is_string($element))
                            <span class="page-link" style="opacity:0.5;">{{ $element }}</span>
                        @endif
                        @if(is_array($element))
                            @foreach($element as $page => $url)
                                <a href="{{ $url }}" class="page-link {{ $page == $entries->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                            @endforeach
                        @endif
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
<script>
    async function refreshStats() {
        const btn = document.getElementById('refreshBtn');
        btn.textContent = '⏳ Loading...';
        btn.disabled = true;
        try {
            const resp = await fetch('{{ route("autodebug.stats") }}');
            const data = await resp.json();
            document.getElementById('stat-total').textContent = data.total;
            document.getElementById('stat-pending').textContent = data.pending;
            document.getElementById('stat-prs').textContent = data.prs_created;
            document.getElementById('stat-confidence').textContent = data.avg_confidence + '%';
        } catch (e) { console.error('Failed to refresh stats:', e); }
        finally { btn.textContent = '↻ Refresh'; btn.disabled = false; }
    }
    setInterval(refreshStats, 30000);
</script>
@endpush
