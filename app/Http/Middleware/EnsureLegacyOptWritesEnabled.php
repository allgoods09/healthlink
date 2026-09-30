<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLegacyOptWritesEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('opt.legacy_writes_enabled'), 410, 'Legacy OPT writes are retired. Use OPT+ Cycles.');

        return $next($request);
    }
}
