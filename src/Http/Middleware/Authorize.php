<?php

namespace TelescopeAI\AutoDebug\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Authorize
{
    /**
     * Handle the incoming request.
     *
     * This middleware controls access to the AutoDebug dashboard.
     * By default, it only allows access in local environments.
     *
     * To customize, publish the config and set the 'middleware' key,
     * or override this middleware via the service provider.
     */
    public function handle(Request $request, Closure $next)
    {
        return $this->allowedToAccess($request)
            ? $next($request)
            : abort(403);
    }

    /**
     * Determine if the request is allowed to access AutoDebug.
     */
    protected function allowedToAccess(Request $request): bool
    {
        // In local/development, always allow
        if (app()->environment('local', 'development', 'testing')) {
            return true;
        }

        // In production, check if user is authenticated and is an admin
        // Override this logic in your app by extending this middleware
        if ($request->user()) {
            // Check for a custom gate if defined
            if (method_exists($request->user(), 'canAccessAutoDebug')) {
                return $request->user()->canAccessAutoDebug();
            }

            // Fall back to checking for admin role if using Spatie Permission
            if (method_exists($request->user(), 'hasRole')) {
                return $request->user()->hasRole('admin');
            }

            // Default: allow authenticated users
            return true;
        }

        return false;
    }
}
