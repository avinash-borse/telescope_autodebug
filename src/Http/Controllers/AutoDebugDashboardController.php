<?php

namespace TelescopeAI\AutoDebug\Http\Controllers;

use TelescopeAI\AutoDebug\Models\AutoDebugEntry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class AutoDebugDashboardController extends Controller
{
    /**
     * Display the main auto-debug dashboard.
     */
    public function index(Request $request)
    {
        $query = AutoDebugEntry::query()->latest();

        // Filters
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('exception_class', 'like', "%{$search}%")
                  ->orWhere('exception_message', 'like', "%{$search}%")
                  ->orWhere('file', 'like', "%{$search}%");
            });
        }

        if ($minConfidence = $request->get('min_confidence')) {
            $query->where('confidence_score', '>=', (int) $minConfidence);
        }

        $entries = $query->paginate(20);

        // Stats
        $stats = [
            'total'         => AutoDebugEntry::count(),
            'pending'       => AutoDebugEntry::pending()->count(),
            'analyzed'      => AutoDebugEntry::analyzed()->count(),
            'prs_created'   => AutoDebugEntry::where('status', 'pr_created')->count(),
            'prs_merged'    => AutoDebugEntry::where('status', 'pr_merged')->count(),
            'failed'        => AutoDebugEntry::where('status', 'failed')->count(),
            'high_conf'     => AutoDebugEntry::highConfidence()->count(),
            'last_24h'      => AutoDebugEntry::recent(24)->count(),
            'avg_confidence' => (int) AutoDebugEntry::whereNotNull('confidence_score')
                ->where('confidence_score', '>', 0)
                ->avg('confidence_score'),
        ];

        return view('autodebug::dashboard', compact('entries', 'stats'));
    }

    /**
     * Show detailed view of a single auto-debug entry.
     */
    public function show(AutoDebugEntry $entry)
    {
        return view('autodebug::show', compact('entry'));
    }

    /**
     * Re-analyze an entry.
     */
    public function reanalyze(AutoDebugEntry $entry)
    {
        $entry->update([
            'status'           => 'pending',
            'ai_analysis'      => null,
            'ai_suggested_fix' => null,
            'ai_file_patches'  => null,
            'confidence_score' => 0,
            'error_message'    => null,
        ]);

        return redirect()->back()->with('success', 'Entry queued for re-analysis.');
    }

    /**
     * Ignore an entry.
     */
    public function ignore(AutoDebugEntry $entry)
    {
        $entry->update(['status' => 'ignored']);

        return redirect()->back()->with('success', 'Entry marked as ignored.');
    }

    /**
     * Get dashboard stats as JSON (for AJAX refresh).
     */
    public function stats()
    {
        return response()->json([
            'total'          => AutoDebugEntry::count(),
            'pending'        => AutoDebugEntry::pending()->count(),
            'prs_created'    => AutoDebugEntry::where('status', 'pr_created')->count(),
            'last_24h'       => AutoDebugEntry::recent(24)->count(),
            'avg_confidence' => (int) AutoDebugEntry::whereNotNull('confidence_score')
                ->where('confidence_score', '>', 0)
                ->avg('confidence_score'),
        ]);
    }
}
